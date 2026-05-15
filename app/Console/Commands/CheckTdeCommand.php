<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Vérifie que MySQL InnoDB TDE est correctement configuré.
 *
 * Usage :
 *   php8.4 artisan pladigit:check-tde
 *   php8.4 artisan pladigit:check-tde --fix-tables
 */
class CheckTdeCommand extends Command
{
    protected $signature = 'pladigit:check-tde
                            {--fix-tables : Chiffrer automatiquement les tables non chiffrées}';

    protected $description = 'Vérifie l\'état du chiffrement InnoDB TDE MySQL';

    private const KEYRING_PATH = '/etc/mysql/keyring/keyring';

    private const EXCLUDED_SCHEMAS = ['information_schema', 'performance_schema', 'mysql', 'sys'];

    public function handle(): int
    {
        $errors = [];

        // 1. Plugin keyring
        $plugin = DB::selectOne(
            "SELECT PLUGIN_STATUS FROM information_schema.PLUGINS WHERE PLUGIN_NAME = 'keyring_file'"
        );
        $pluginOk = $plugin && $plugin->PLUGIN_STATUS === 'ACTIVE';
        $this->line($pluginOk ? '<fg=green>✓</> keyring_file actif' : '<fg=red>✗</> keyring_file inactif');
        if (! $pluginOk) {
            $errors[] = 'Ajouter early-plugin-load=keyring_file.so dans mysqld.cnf';
        }

        // 2. Variables innodb_encrypt
        $rows = collect(DB::select("SHOW VARIABLES LIKE 'innodb_encrypt%'"))->pluck('Value', 'Variable_name');
        $encTablesOk = strtoupper((string) $rows->get('innodb_encrypt_tables', '')) === 'ON';
        $this->line($encTablesOk ? '<fg=green>✓</> innodb_encrypt_tables ON' : '<fg=red>✗</> innodb_encrypt_tables OFF');
        if (! $encTablesOk) {
            $errors[] = 'Ajouter innodb_encrypt_tables=ON dans mysqld.cnf';
        }

        // 3. Fichier keyring (non bloquant — droits mysql:mysql)
        $keyringOk = file_exists(self::KEYRING_PATH);
        $this->line($keyringOk
            ? '<fg=green>✓</> Keyring présent : '.self::KEYRING_PATH
            : '<fg=yellow>!</> Keyring non lisible par PHP (normal si droits mysql:mysql 700)'
        );

        // 4. Tables chiffrées
        $excluded = implode(',', array_map(fn ($s) => "'{$s}'", self::EXCLUDED_SCHEMAS));
        $tables = DB::select("SELECT TABLE_SCHEMA, TABLE_NAME, CREATE_OPTIONS
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA NOT IN ({$excluded}) AND ENGINE = 'InnoDB'");

        $total = count($tables);
        $encryptedCount = count(array_filter(
            $tables,
            fn ($t) => str_contains(strtoupper((string) $t->CREATE_OPTIONS), "ENCRYPTION='Y'")
        ));

        $tablesOk = $total > 0 && $encryptedCount === $total;
        $this->line($tablesOk
            ? "<fg=green>✓</> Tables chiffrées : {$encryptedCount}/{$total}"
            : "<fg=red>✗</> Tables chiffrées : {$encryptedCount}/{$total}"
        );

        if (! $tablesOk && $this->option('fix-tables')) {
            $this->fixTables($tables);
        } elseif (! $tablesOk) {
            $errors[] = 'Relancer avec --fix-tables pour chiffrer automatiquement';
        }

        // Résumé
        if (empty($errors)) {
            $this->info('TDE OK');
            Log::info('pladigit:check-tde OK');

            return self::SUCCESS;
        }

        foreach ($errors as $e) {
            $this->error($e);
        }
        Log::warning('pladigit:check-tde — anomalies détectées', $errors);

        return self::FAILURE;
    }

    private function fixTables(array $tables): void
    {
        $fixed = 0;

        foreach ($tables as $t) {
            if (str_contains(strtoupper((string) $t->CREATE_OPTIONS), "ENCRYPTION='Y'")) {
                continue;
            }

            $schema = str_replace('`', '', $t->TABLE_SCHEMA);
            $table = str_replace('`', '', $t->TABLE_NAME);

            try {
                DB::statement("ALTER TABLE `{$schema}`.`{$table}` ENCRYPTION='Y'");
                $fixed++;
            } catch (\Throwable $e) {
                $this->error("Échec {$schema}.{$table} : ".$e->getMessage());
            }
        }

        $this->info("{$fixed} table(s) chiffrée(s).");
        Log::info("pladigit:check-tde --fix-tables : {$fixed} tables chiffrées");
    }
}
