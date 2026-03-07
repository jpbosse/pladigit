<?php

namespace Database\Factories\Tenant;

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
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password_hash' => Hash::make('password'),
            'role' => 'user',
            'status' => 'active',
            'department' => fake()->optional()->word(),
            'totp_enabled' => false,
        ];
    }

    public function admin(): static
    {
        return $this->state(['role' => 'admin']);
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
            'totp_enabled' => true,
            'totp_secret_enc' => Crypt::encryptString($secret),
        ]);
    }

    public function ldap(): static
    {
        return $this->state([
            'ldap_dn' => 'uid=test,ou=users,dc=pladigit,dc=fr',
            'password_hash' => null,
        ]);
    }
}
