<?php

namespace Tests\Feature\SuperAdmin;

use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/**
 * Tests du flux d'authentification TOTP pour le super-admin.
 */
class SuperAdminTotpAuthTest extends TestCase
{
    private string $secret;

    protected function setUp(): void
    {
        parent::setUp();
        $this->secret = (new Google2FA)->generateSecretKey(32);

        // Configurer un hash valide pour les tests (bcrypt rounds=4 en test)
        config([
            'superadmin.email' => 'sa@pladigit.test',
            'superadmin.password_hash' => Hash::make('secret'),
        ]);
    }

    // ── Mode legacy (pas de TOTP configuré) ───────────────────────────

    public function test_login_sans_totp_donne_accès_direct(): void
    {
        config(['superadmin.totp_secret' => null]);

        $this->post(route('super-admin.login.post'), [
            'email' => config('superadmin.email'),
            'password' => 'secret',
        ])->assertRedirect(route('super-admin.dashboard'));

        $this->assertTrue(session('super_admin_verified'));
    }

    public function test_mauvais_identifiants_retourne_erreur(): void
    {
        config(['superadmin.totp_secret' => null]);

        $this->post(route('super-admin.login.post'), [
            'email' => config('superadmin.email'),
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');
    }

    // ── Mode TOTP activé ──────────────────────────────────────────────

    public function test_login_avec_totp_redirige_vers_formulaire_totp(): void
    {
        config(['superadmin.totp_secret' => $this->secret]);

        $this->post(route('super-admin.login.post'), [
            'email' => config('superadmin.email'),
            'password' => 'secret',
        ])->assertRedirect(route('super-admin.login.totp'));

        $this->assertEquals(config('superadmin.email'), session('super_admin_totp_pending'));
        $this->assertNull(session('super_admin_verified'));
    }

    public function test_formulaire_totp_sans_session_pending_redirige_vers_login(): void
    {
        $this->get(route('super-admin.login.totp'))
            ->assertRedirect(route('super-admin.login'));
    }

    public function test_code_totp_valide_donne_accès(): void
    {
        config(['superadmin.totp_secret' => $this->secret]);

        $code = (new Google2FA)->getCurrentOtp($this->secret);

        $this->withSession(['super_admin_totp_pending' => config('superadmin.email')])
            ->post(route('super-admin.login.totp.verify'), ['code' => $code])
            ->assertRedirect(route('super-admin.dashboard'));

        $this->assertTrue(session('super_admin_verified'));
        $this->assertNull(session('super_admin_totp_pending'));
    }

    public function test_code_totp_invalide_retourne_erreur(): void
    {
        config(['superadmin.totp_secret' => $this->secret]);

        $this->withSession(['super_admin_totp_pending' => config('superadmin.email')])
            ->post(route('super-admin.login.totp.verify'), ['code' => '000000'])
            ->assertSessionHasErrors('code');

        $this->assertNull(session('super_admin_verified'));
    }

    public function test_code_totp_non_numerique_echoue_validation(): void
    {
        config(['superadmin.totp_secret' => $this->secret]);

        $this->withSession(['super_admin_totp_pending' => config('superadmin.email')])
            ->post(route('super-admin.login.totp.verify'), ['code' => 'abcdef'])
            ->assertSessionHasErrors('code');
    }

    public function test_logout_efface_la_session_totp(): void
    {
        $this->withSession([
            'super_admin_email' => config('superadmin.email'),
            'super_admin_verified' => true,
            'super_admin_totp_pending' => config('superadmin.email'),
        ])->post(route('super-admin.logout'))
            ->assertRedirect(route('super-admin.login'));

        $this->assertNull(session('super_admin_verified'));
        $this->assertNull(session('super_admin_totp_pending'));
    }
}
