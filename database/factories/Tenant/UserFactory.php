<?php
 
namespace Database\Factories\Tenant;
 
use App\Models\Tenant\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
 
class UserFactory extends Factory
{
    protected $model = User::class;
 
    public function definition(): array
    {
        return [
            'name'           => fake()->name(),
            'email'          => fake()->unique()->safeEmail(),
            'password_hash'  => Hash::make('Password!123'),
            'role'           => 'user',
            'status'         => 'active',
            'totp_enabled'   => false,
            'force_pwd_change' => false,
            'login_attempts' => 0,
        ];
    }
 
    public function admin(): static
    {
        return $this->state(['role' => 'admin']);
    }
 
    public function locked(): static
    {
        return $this->state([
            'status'       => 'locked',
            'locked_until' => now()->addMinutes(15),
        ]);
    }
 
    public function withTotp(): static
    {
        return $this->state([
            'totp_enabled'    => true,
            'totp_secret_enc' => \Illuminate\Support\Facades\Crypt::encryptString(
                app(\PragmaRX\Google2FA\Google2FA::class)->generateSecretKey(32)
            ),
        ]);
    }
}
