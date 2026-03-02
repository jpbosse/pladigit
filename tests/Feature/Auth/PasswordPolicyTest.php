<?php

namespace Tests\Feature\Auth;

use App\Models\Tenant\TenantSettings;
use App\Models\Tenant\User;
use App\Services\PasswordPolicyService;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Tests unitaires du PasswordPolicyService.
 *
 * Note : TenantSettings est une table singleton — la migration insère
 * déjà une ligne par défaut. On tronque avant chaque factory()->create()
 * pour éviter le MultipleRecordsFoundException dans sole().
 */
class PasswordPolicyTest extends TestCase
{

    private PasswordPolicyService $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = app(PasswordPolicyService::class);

        // Vider la ligne insérée par la migration avant chaque test
        // (TenantSettings::sole() échoue s'il trouve plus d'une ligne)
        TenantSettings::truncate();
    }

    public function test_mot_de_passe_trop_court_est_refuse(): void
    {
        TenantSettings::factory()->create(['pwd_min_length' => 10]);

        $errors = $this->policy->validate('Court1!');

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('10', $errors[0]);
    }

    public function test_mot_de_passe_sans_majuscule_est_refuse(): void
    {
        TenantSettings::factory()->create([
            'pwd_min_length'        => 8,
            'pwd_require_uppercase' => true,
        ]);

        $errors = $this->policy->validate('minuscule1!');

        $this->assertNotEmpty($errors);
    }

    public function test_mot_de_passe_sans_chiffre_est_refuse(): void
    {
        TenantSettings::factory()->create([
            'pwd_min_length'     => 8,
            'pwd_require_number' => true,
        ]);

        $errors = $this->policy->validate('SansChiffre!');

        $this->assertNotEmpty($errors);
    }

    public function test_mot_de_passe_sans_special_est_refuse(): void
    {
        TenantSettings::factory()->create([
            'pwd_min_length'      => 8,
            'pwd_require_special' => true,
        ]);

        $errors = $this->policy->validate('SansSpecial1');

        $this->assertNotEmpty($errors);
    }

    public function test_mot_de_passe_valide_passe(): void
    {
        TenantSettings::factory()->create([
            'pwd_min_length'        => 8,
            'pwd_require_uppercase' => true,
            'pwd_require_number'    => true,
            'pwd_require_special'   => true,
        ]);

        $errors = $this->policy->validate('Valid1!ok');

        $this->assertEmpty($errors);
    }

    public function test_mot_de_passe_deja_utilise_est_refuse(): void
    {
        TenantSettings::factory()->create([
            'pwd_min_length'    => 8,
            'pwd_history_count' => 5,
        ]);

        $oldHash = Hash::make('AncienMdp1!');
        $errors  = $this->policy->validate('AncienMdp1!', [$oldHash]);

        $this->assertNotEmpty($errors);
    }

    public function test_update_password_met_a_jour_historique(): void
    {
        TenantSettings::factory()->create([
            'pwd_min_length'    => 8,
            'pwd_history_count' => 5,
        ]);

        $user = User::factory()->create([
            'password_hash'    => Hash::make('AncienMdp1!'),
            'password_history' => [],
        ]);

        $this->policy->updatePassword($user, 'NouveauMdp1!');

        $user->refresh();
        $this->assertFalse((bool) $user->force_pwd_change);
        $this->assertNotNull($user->password_changed_at);
    }
}
