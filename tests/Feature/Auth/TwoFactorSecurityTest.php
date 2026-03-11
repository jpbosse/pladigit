<?php

namespace Tests\Feature\Auth;

use App\Models\Tenant\User;
use App\Services\TwoFactorService;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/**
 * Tests de sécurité du module 2FA.
 *
 * Couvre :
 *  - Absence de données sensibles dans les logs (OWASP A09)
 *  - Rate limiting sur /2fa/verify
 *  - Fonctionnement nominal verify() et verifyBackupCode()
 */
class TwoFactorSecurityTest extends TestCase
{
    // ── Helpers ───────────────────────────────────────────

    private function makeUserWith2FA(): array
    {
        $google2fa = new Google2FA;
        $secret = $google2fa->generateSecretKey(32);
        $code = $google2fa->getCurrentOtp($secret);

        $user = User::factory()->create([
            'totp_enabled' => true,
            'totp_secret_enc' => Crypt::encryptString($secret),
        ]);

        return [$user, $secret, $code];
    }

    // ── Tests : pas de données sensibles dans les logs ────

    /**
     * Le code TOTP saisi ne doit jamais apparaître dans les logs.
     * (OWASP A09 — Security Logging Failures)
     */
    public function test_code_totp_absent_des_logs(): void
    {
        [$user, $secret, $code] = $this->makeUserWith2FA();

        Log::shouldReceive('info')
            ->withArgs(function (string $message, array $context = []) use ($code) {
                // Aucun log ne doit contenir le code TOTP
                $this->assertArrayNotHasKey('code', $context, 'Le code TOTP ne doit jamais être loggué.');
                $flat = json_encode($context);
                $this->assertStringNotContainsString($code, $flat, 'Le code TOTP ne doit pas apparaître dans les logs.');

                return true;
            })
            ->zeroOrMoreTimes();

        Log::shouldReceive('warning')->zeroOrMoreTimes();
        Log::shouldReceive('error')->zeroOrMoreTimes();

        $service = app(TwoFactorService::class);
        $service->verify($user, $code);
    }

    /**
     * Le secret TOTP déchiffré ne doit jamais apparaître dans les logs.
     */
    public function test_secret_totp_absent_des_logs(): void
    {
        [$user, $secret, $code] = $this->makeUserWith2FA();

        Log::shouldReceive('info')
            ->withArgs(function (string $message, array $context = []) use ($secret) {
                $flat = json_encode($context);
                $this->assertStringNotContainsString($secret, $flat, 'Le secret TOTP déchiffré ne doit pas apparaître dans les logs.');

                return true;
            })
            ->zeroOrMoreTimes();

        Log::shouldReceive('warning')->zeroOrMoreTimes();

        $service = app(TwoFactorService::class);
        $service->verify($user, $code);
    }

    /**
     * Le secret de setup ne doit pas être loggué dans TwoFactorController::confirm().
     */
    public function test_secret_setup_absent_des_logs_confirm(): void
    {
        $google2fa = new Google2FA;
        $secret = $google2fa->generateSecretKey(32);
        $code = $google2fa->getCurrentOtp($secret);

        $user = User::factory()->create(['totp_enabled' => false]);

        $this->actingAs($user);
        session(['2fa_setup_secret' => $secret]);

        Log::shouldReceive('info')
            ->withArgs(function (string $message, array $context = []) use ($secret, $code) {
                $flat = json_encode($context);
                $this->assertStringNotContainsString($secret, $flat, 'Le secret ne doit pas être loggué lors du confirm.');
                $this->assertStringNotContainsString($code, $flat, 'Le code ne doit pas être loggué lors du confirm.');

                return true;
            })
            ->zeroOrMoreTimes();

        $this->post(route('2fa.confirm'), ['code' => $code]);
    }

    // ── Tests : rate limiting sur /2fa/verify ─────────────

    /**
     * Le rate limiting doit bloquer après 5 tentatives en 10 minutes.
     */
    public function test_rate_limiting_bloque_apres_5_tentatives(): void
    {
        $user = User::factory()->create(['totp_enabled' => true]);
        session(['2fa_user_id' => $user->id]);

        // 5 tentatives — doivent passer (même si code invalide → 422)
        for ($i = 0; $i < 5; $i++) {
            $response = $this->post(route('2fa.verify'), ['code' => '000000']);
            $this->assertNotEquals(429, $response->status(), "La tentative $i ne devrait pas être bloquée.");
        }

        // 6ème tentative — doit être throttlée
        $response = $this->post(route('2fa.verify'), ['code' => '000000']);
        $response->assertStatus(429);
    }

    /**
     * Une tentative valide avant d'atteindre la limite ne doit pas être bloquée.
     */
    public function test_tentative_valide_non_bloquee_sous_la_limite(): void
    {
        [$user, $secret, $code] = $this->makeUserWith2FA();
        session(['2fa_user_id' => $user->id]);

        // 4 mauvaises tentatives
        for ($i = 0; $i < 4; $i++) {
            $this->post(route('2fa.verify'), ['code' => '000000']);
        }

        // La 5ème avec le bon code doit réussir
        $response = $this->post(route('2fa.verify'), ['code' => $code]);
        $response->assertRedirect(route('dashboard'));
    }

    // ── Tests : fonctionnement nominal ────────────────────

    /**
     * verify() retourne true avec un code TOTP valide.
     */
    public function test_verify_retourne_true_avec_code_valide(): void
    {
        [$user, $secret, $code] = $this->makeUserWith2FA();

        $service = app(TwoFactorService::class);
        $this->assertTrue($service->verify($user, $code));
    }

    /**
     * verify() retourne false avec un code invalide.
     */
    public function test_verify_retourne_false_avec_code_invalide(): void
    {
        [$user] = $this->makeUserWith2FA();

        $service = app(TwoFactorService::class);
        $this->assertFalse($service->verify($user, '000000'));
    }

    /**
     * verify() retourne false si 2FA désactivé (pas de secret en base).
     */
    public function test_verify_retourne_false_si_2fa_desactive(): void
    {
        $user = User::factory()->create([
            'totp_enabled' => false,
            'totp_secret_enc' => null,
        ]);

        $service = app(TwoFactorService::class);
        $this->assertFalse($service->verify($user, '123456'));
    }

    /**
     * Un code de secours valide permet la connexion et est consommé (usage unique).
     */
    public function test_code_de_secours_usage_unique(): void
    {
        $google2fa = new Google2FA;
        $secret = $google2fa->generateSecretKey(32);
        $backupCode = strtoupper('ABCD1234');

        $user = User::factory()->create([
            'totp_enabled' => true,
            'totp_secret_enc' => Crypt::encryptString($secret),
            'totp_backup_code_enc' => Crypt::encryptString(
                json_encode([hash('sha256', $backupCode)])
            ),
        ]);

        $service = app(TwoFactorService::class);

        // Premier usage → OK
        $this->assertTrue($service->verify($user, $backupCode));

        // Deuxième usage → rejeté (consommé)
        $this->assertFalse($service->verify($user, $backupCode));
    }
}
