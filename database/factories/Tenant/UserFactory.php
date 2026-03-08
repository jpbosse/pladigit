<?php

namespace Database\Factories\Tenant;

use App\Enums\UserRole;
use App\Models\Tenant\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'name'               => fake()->name(),
            'email'              => fake()->unique()->safeEmail(),
            'password_hash'      => Hash::make('password'),
            'role'               => UserRole::USER->value,
            'status'             => 'active',
            'force_pwd_change'   => false,
            'totp_enabled'       => false,
            'password_changed_at' => now(),
        ];
    }

    public function admin(): static
    {
        return $this->state(['role' => UserRole::ADMIN->value]);
    }

    public function president(): static
    {
        return $this->state(['role' => UserRole::PRESIDENT->value]);
    }

    public function dgs(): static
    {
        return $this->state(['role' => UserRole::DGS->value]);
    }

    public function respDirection(): static
    {
        return $this->state(['role' => UserRole::RESP_DIRECTION->value]);
    }

    public function respService(): static
    {
        return $this->state(['role' => UserRole::RESP_SERVICE->value]);
    }

    public function inactive(): static
    {
        return $this->state(['status' => 'inactive']);
    }

    public function withTotp(): static
    {
        $google2fa = new Google2FA;
        $secret = $google2fa->generateSecretKey();

        return $this->state([
            'totp_enabled'    => true,
            'totp_secret_enc' => Crypt::encryptString($secret),
        ]);
    }

    public function ldap(): static
    {
        return $this->state([
            'ldap_dn'       => 'uid=test,ou=users,dc=pladigit,dc=fr',
            'password_hash' => null,
        ]);
    }

    public function forcePwdChange(): static
    {
        return $this->state(['force_pwd_change' => true]);
    }
}
