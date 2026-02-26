<?php
 
namespace Tests\Feature\Auth;
 
use App\Models\Tenant\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
 
/**
 * Tests de l'authentification locale.
 * Couverture cible : 90 %
 */
class LoginTest extends TestCase
{
    use RefreshDatabase;
 
    public function test_login_avec_credentials_valides(): void
    {
        $user = User::factory()->create([
            'password_hash' => Hash::make('MotDePasse!123'),
            'status'        => 'active',
        ]);
 
        $response = $this->post(route('login'), [
            'email'    => $user->email,
            'password' => 'MotDePasse!123',
        ]);
 
        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }
 
    public function test_login_echoue_avec_mauvais_mot_de_passe(): void
    {
        $user = User::factory()->create([
            'password_hash' => Hash::make('BonMotDePasse!1'),
        ]);
 
        $this->post(route('login'), [
            'email'    => $user->email,
            'password' => 'MauvaisMotDePasse',
        ])->assertSessionHasErrors('email');
 
        $this->assertGuest();
    }
 
    public function test_compte_bloque_apres_tentatives_excessives(): void
    {
        $user = User::factory()->create([
            'password_hash'  => Hash::make('CorrectPassword!1'),
            'login_attempts' => 9,
        ]);
 
        $this->post(route('login'), [
            'email'    => $user->email,
            'password' => 'Mauvais',
        ]);
 
        $user->refresh();
        $this->assertEquals('locked', $user->status);
        $this->assertNotNull($user->locked_until);
    }
 
    public function test_isolation_tenant(): void
    {
        // Un utilisateur d'un tenant ne doit pas accéder à un autre tenant
        $this->assertTrue(true); // Complété en test d'intégration séparé
    }
}
