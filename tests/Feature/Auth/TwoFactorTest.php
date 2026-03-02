<?php

namespace Tests\Feature\Auth;

use App\Models\Tenant\User;
use App\Services\TwoFactorService;
use Tests\TestCase;

class TwoFactorTest extends TestCase
{
    public function test_activation_2fa_avec_code_valide(): void
    {
        $user = User::factory()->create();
        $service = app(TwoFactorService::class);
        $setup = $service->generateSetup($user);

        // Simuler un code TOTP valide
        $google2fa = app(\PragmaRX\Google2FA\Google2FA::class);
        $code = $google2fa->getCurrentOtp($setup['secret']);

        $result = $service->enable($user, $setup['secret'], $code);

        $this->assertTrue($result);
        $user->refresh();
        $this->assertTrue($user->totp_enabled);
        $this->assertNotNull($user->totp_secret_enc);
    }

    public function test_verification_2fa_code_invalide(): void
    {
        $user = User::factory()->withTotp()->create();
        $service = app(TwoFactorService::class);

        $result = $service->verify($user, '000000');

        $this->assertFalse($result);
    }

    public function test_challenge_2fa_redirige_si_pas_de_session(): void
    {
        $this->get(route('2fa.challenge'))
            ->assertRedirect(route('login'));
    }
}
