<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Vérifie que MySQL InnoDB TDE (Transparent Data Encryption) est correctement
 * configuré et actif sur le serveur.
 *
 * Usage :
 *   php8.4 artisan pladigit:check-tde
 *   php8.4 artisan pladigit:check-tde --fix-tables   # chiffre les tables non chiffrées
 *
 * Vérifications effectuées :
 *   1. Plugin keyring_file actif
 *   2. Variables innodb_encrypt_* activées
 *   3. Fichier keyring présent sur le disque
 *   4. Tables InnoDB chiffrées vs non chiffrées
 */
class CheckTdeCommand extends Command
{
    protected $signature = 'pladigit:check-tde
                            {--fix-tables : Chiffrer automatiquement les tables non chiffrées}';

    protected $description = 'Vérifie et rapporte l\'état du chiffrement InnoDB TDE MySQL';

    private const KEYRING_PATH = '/etc/mysql/keyring/keyring';

    private const EXCLUDED_SCHEMAS = ['information_schema', 'performance_schema', 'mysql', 'sys'];

    public function handle(): int
    {
        $this->info('');
        $this->info('═══════════════════════════════════════════');
        $this->info('  Pladigit — Vérification TDE MySQL InnoDB');
        $this->info('═══════════════════════════════════════════');
        $this->info('');

        $allOk = true;

        // ── 1. Plugin keyring ────────────────────────────────────────────────
        $allOk = $this->checkKeyringPlugin() && $allOk;

        // ── 2. Variables innodb_encrypt_* ────────────────────────────────────
        $allOk = $this->checkInnodbVariables() && $allOk;

        // ── 3. Fichier keyring sur le disque ─────────────────────────────────
        $allOk = $this->checkKeyringFile() && $allOk;

        // ── 4. Tables chiffrées ──────────────────────────────────────────────
        $allOk = $this->checkEncryptedTables() && $allOk;

        // ── Résumé ───────────────────────────────────────────────────────────
        $this->info('');

        if ($allOk) {
            $this->info('✓ TDE MySQL opérationnel — toutes les vérifications sont OK.');
            Log::info('pladigit:check-tde — OK');
        } else {
            $this->error('✗ Des problèmes TDE ont été détectés — voir ci-dessus.');
            Log::warning('pladigit:check-tde — des problèmes ont été détectés');
        }

        $this->info('');

        return $allOk ? self::SUCCESS : self::FAILURE;
    }

    // =========================================================================

    private function checkKeyringPlugin(): bool
    {
        $this->line('1. Plugin keyring_file');

        try {
            $row = DB::selectOne(
                "SELECT PLUGIN_STATUS FROM information_schema.PLUGINS WHERE PLUGIN_NAME = 'keyring_file'"
            );

            if ($row && $row->PLUGIN_STATUS === 'ACTIVE') {
                $this->line('   <fg=green>✓</> Plugin keyring_file : ACTIF');

                return true;
            }

            $this->line('   <fg=red>✗</> Plugin keyring_file : INACTIF ou absent');
            $this->line('   → Ajouter early-plugin-load=keyring_file.so dans mysqld.cnf');

            return false;

        } catch (\Throwable $e) {
            $this->line('   <fg=red>✗</> Impossible de vérifier le plugin : '.$e->getMessage());

            return false;
        }
    }

    private function checkInnodbVariables(): bool
    {
        $this->line('');
        $this->line('2. Variables innodb_encrypt_*');

        $vars = [
            'innodb_encrypt_tables'            => 'ON',
            'innodb_encrypt_online_alter_logs'  => 'ON',
            'innodb_encrypt_temporary_tables'   => 'ON',
        ];

        $ok = true;

        try {
            $rows = DB::select("SHOW VARIABLES LIKE 'innodb_encrypt%'");
            $found = collect($rows)->pluck('Value', 'Variable_name');

            foreach ($vars as $var => $expected) {
                $value = $found->get($var, 'N/A');

                if (strtoupper((string) $value) === $expected) {
                    $this->line("   <fg=green>✓</> {$var} : {$value}");
                } else {
                    $this->line("   <fg=red>✗</> {$var} : {$value} (attendu : {$expected})");
                    $this->line("   → Ajouter {$var} = {$expected} dans mysqld.cnf");
                    $ok = false;
                }
            }

        } catch (\Throwable $e) {
            $this->line('   <fg=red>✗</> Impossible de lire les variables : '.$e->getMessage());

            return false;
        }

        return $ok;
    }

    private function checkKeyringFile(): bool
    {
        $this->line('');
        $this->line('3. Fichier keyring');

        if (file_exists(self::KEYRING_PATH)) {
            $size = filesize(self::KEYRING_PATH);
            $mtime = date('Y-m-d H:i', filemtime(self::KEYRING_PATH));
            $this->line('   <fg=green>✓</> Fichier présent : '.self::KEYRING_PATH." ({$size} octets, modifié {$mtime})");

            return true;
        }

        // Pas accessible depuis PHP (permissions mysql:mysql 700) — ce n'est pas forcément une erreur
        $this->line('   <fg=yellow>!</> Fichier non lisible par PHP : '.self::KEYRING_PATH);
        $this->line('   → Normal si les permissions sont mysql:mysql 700');
        $this->line('   → Vérifiez manuellement : sudo ls -la /etc/mysql/keyring/');

        return true; // Pas bloquant — PHP ne doit pas lire le keyring
    }

    private function checkEncryptedTables(): bool
    {
        $this->line('');
        $this->line('4. Tables InnoDB chiffrées');

        try {
            $excludedList = implode(
                ', ',
                array_map(fn ($s) => "'{$s}'", self::EXCLUDED_SCHEMAS)
            );

            $allTables = DB::select("
                SELECT TABLE_SCHEMA, TABLE_NAME, CREATE_OPTIONS
                FROM information_schema.TABLES
                WHERE TABLE_SCHEMA NOT IN ({$excludedList})
                  AND ENGINE = 'InnoDB'
                ORDER BY TABLE_SCHEMA, TABLE_NAME
            ");

            $total = count($allTables);

            if ($total === 0) {
                $this->line('   <fg=yellow>!</> Aucune table InnoDB trouvée.');

                return true;
            }

            $encrypted = array_filter(
                $allTables,
                fn ($t) => str_contains(strtoupper((string) $t->CREATE_OPTIONS), 'ENCRYPTION=\'Y\'')
            );

            $encryptedCount = count($encrypted);
            $unencryptedCount = $total - $encryptedCount;

            if ($encryptedCount === $total) {
                $this->line("   <fg=green>✓</> Toutes les tables chiffrées : {$encryptedCount}/{$total}");

                return true;
            }

            $this->line("   <fg=red>✗</> Tables chiffrées : {$encryptedCount}/{$total} ({$unencryptedCount} non chiffrées)");

            $unencrypted = array_filter(
                $allTables,
                fn ($t) => ! str_contains(strtoupper((string) $t->CREATE_OPTIONS), 'ENCRYPTION=\'Y\'')
            );

            foreach (array_slice($unencrypted, 0, 20) as $t) {
                $this->line("   <fg=yellow>  - {$t->TABLE_SCHEMA}.{$t->TABLE_NAME}</>");
            }

            if ($unencryptedCount > 20) {
                $this->line("   <fg=yellow>  ... et ".($unencryptedCount - 20).' autres.</fg>');
            }

            if ($this->option('fix-tables')) {
                return $this->fixUnencryptedTables($unencrypted);
            }

            $this->line('   → Relancer avec --fix-tables pour chiffrer automatiquement');
            $this->line('   → Ou consulter docs/deploy/tde-mysql.md §5');

            return false;

        } catch (\Throwable $e) {
            $this->line('   <fg=red>✗</> Erreur lors de la vérification des tables : '.$e->getMessage());

            return false;
        }
    }

    private function fixUnencryptedTables(array $tables): bool
    {
        $this->line('');
        $this->line('   <fg=yellow>→ Chiffrement des tables manquantes...</>');

        $ok = true;
        $fixed = 0;
        $failed = 0;

        foreach ($tables as $t) {
            $schema = str_replace('`', '', $t->TABLE_SCHEMA);
            $table = str_replace('`', '', $t->TABLE_NAME);

            try {
                DB::statement("ALTER TABLE `{$schema}`.`{$table}` ENCRYPTION='Y'");
                $this->line("   <fg=green>✓</> Chiffré : {$schema}.{$table}");
                $fixed++;
            } catch (\Throwable $e) {
                $this->line("   <fg=red>✗</> Échec {$schema}.{$table} : ".$e->getMessage());
                $failed++;
                $ok = false;
            }
        }

        $this->line('');
        $this->line("   Tables chiffrées : {$fixed} | Échecs : {$failed}");

        if ($fixed > 0) {
            Log::info("pladigit:check-tde --fix-tables : {$fixed} tables chiffrées, {$failed} échecs");
        }

        return $ok;
    }
}
