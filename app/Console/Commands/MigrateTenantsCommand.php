<?php

namespace App\Console\Commands;

use App\Models\Platform\Organization;
use App\Services\TenantManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Applique les migrations Laravel sur toutes les bases tenant actives.
 *
 * Usage :
 *   php artisan migrate:tenants               — toutes les organisations actives
 *   php artisan migrate:tenants --slug=demo   — une seule organisation
 *   php artisan migrate:tenants --pretend     — aperçu sans exécuter
 *   php artisan migrate:tenants --force       — pas de confirmation en production
 *
 * Fonctionnement :
 *   Pour chaque organisation, la connexion Eloquent 'tenant' est reconfigurée
 *   via TenantManager::connectTo(), puis php artisan migrate est appelé
 *   avec --database=tenant et --path=database/migrations/tenant.
 *
 *   La table migrations est gérée par tenant — chaque base a son propre
 *   historique de migrations, exactement comme en mode standard Laravel.
 */
class MigrateTenantsCommand extends Command
{
    protected $signature = 'migrate:tenants
                            {--slug= : Slug d\'une organisation spécifique}
                            {--pretend : Afficher les requêtes SQL sans les exécuter}
                            {--force : Ne pas demander de confirmation en production}';

    protected $description = 'Applique les migrations sur toutes les bases tenant (ou une seule avec --slug)';

    public function __construct(private TenantManager $tenantManager)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        // Confirmation en production (sauf --force)
        if (app()->isProduction() && ! $this->option('force')) {
            if (! $this->confirm('Vous êtes en PRODUCTION. Lancer les migrations sur tous les tenants ?')) {
                $this->info('Annulé.');

                return self::SUCCESS;
            }
        }

        $query = Organization::query();

        if ($slug = $this->option('slug')) {
            $query->where('slug', $slug);
        } else {
            $query->where('status', 'active');
        }

        $organizations = $query->get();

        if ($organizations->isEmpty()) {
            $this->warn('Aucune organisation trouvée.');

            return self::SUCCESS;
        }

        $this->info("Migrations tenant — {$organizations->count()} organisation(s) à traiter.");
        $this->newLine();

        $success = 0;
        $errors = 0;

        foreach ($organizations as $org) {
            $this->line("  → <fg=cyan>{$org->slug}</> (<fg=gray>{$org->db_name}</>)");

            try {
                // Connecter la base du tenant
                $this->tenantManager->connectTo($org);

                // Construire les options pour migrate
                $options = [
                    '--database' => 'tenant',
                    '--path' => 'database/migrations/tenant',
                    '--force' => true, // Toujours forcé ici (confirmation déjà faite)
                ];

                if ($this->option('pretend')) {
                    $options['--pretend'] = true;
                }

                $exitCode = $this->call('migrate', $options);

                if ($exitCode === 0) {
                    $this->line('     <fg=green>✓ OK</>');
                    $success++;
                } else {
                    $this->line("     <fg=red>✗ Échec (code {$exitCode})</>");
                    $errors++;
                }
            } catch (\Throwable $e) {
                $this->line("     <fg=red>✗ Erreur : {$e->getMessage()}</>");
                $this->line("     <fg=gray>{$e->getFile()}:{$e->getLine()}</>");
                $errors++;
            } finally {
                // Purger la connexion tenant entre chaque organisation
                DB::purge('tenant');
            }

            $this->newLine();
        }

        // Résumé
        $this->line('─────────────────────────────────────────');
        $this->line("  <fg=green>✓ {$success} succès</>  <fg=red>✗ {$errors} erreur(s)</>");

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }
}
