<?php

namespace Tests\Feature\Console;

use App\Models\Platform\Organization;
use App\Services\TenantManager;
use Tests\TestCase;

/**
 * Tests de la commande migrate:tenants.
 *
 * On vérifie le comportement de la commande sans exécuter de vraies migrations
 * (les bases de test n'ont qu'un seul tenant configuré).
 */
class MigrateTenantsCommandTest extends TestCase
{
    public function test_commande_existe_et_accepte_option_slug(): void
    {
        // Vérifie que la commande est enregistrée et que --slug est reconnu
        $this->artisan('migrate:tenants --help')
            ->assertSuccessful();
    }

    public function test_commande_affiche_avertissement_si_aucune_organisation(): void
    {
        // Mocker Organization pour retourner une collection vide
        $this->mock(TenantManager::class);

        // Avec un slug inexistant, aucune organisation ne doit être trouvée
        $this->artisan('migrate:tenants', [
            '--slug' => 'organisation-inexistante',
            '--force' => true,
        ])
            ->expectsOutput('Aucune organisation trouvée.')
            ->assertSuccessful();
    }

    public function test_option_force_evite_confirmation(): void
    {
        // Sans --force en production, la commande demanderait confirmation.
        // Avec --force + slug inexistant → sort proprement sans confirmation.
        $this->artisan('migrate:tenants', [
            '--slug' => 'slug-qui-nexiste-pas',
            '--force' => true,
        ])->assertSuccessful();
    }

    public function test_commande_migre_tenant_demo(): void
    {
        // Vérifie que la commande tourne sur le tenant de test sans erreur
        $this->artisan('migrate:tenants', [
            '--slug' => 'testing',
            '--force' => true,
        ])->assertSuccessful();
    }
}
