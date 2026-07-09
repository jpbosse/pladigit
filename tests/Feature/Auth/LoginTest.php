<?php

namespace Tests\Feature\Auth;

use App\Models\Tenant\User;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Tests de l'authentification locale.
 * Couverture cible : 90 %
 */
class LoginTest extends TestCase
{
    public function test_login_avec_credentials_valides(): void
    {
        $user = User::factory()->create([
            'password_hash' => Hash::make('MotDePasse!123'),
            'status' => 'active',
        ]);

        $response = $this->post(route('login'), [
            'email' => $user->email,
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
            'email' => $user->email,
            'password' => 'MauvaisMotDePasse',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_compte_bloque_apres_tentatives_excessives(): void
    {
        $user = User::factory()->create([
            'password_hash' => Hash::make('CorrectPassword!1'),
            'login_attempts' => 9,
        ]);

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'Mauvais',
        ]);

        $user->refresh();
        $this->assertEquals('locked', $user->status);
        $this->assertNotNull($user->locked_until);
    }

    public function test_isolation_tenant(): void
    {
        // Un utilisateur d'un tenant ne doit pas rester authentifié si la
        // session porte l'org_id d'un AUTRE tenant que celui résolu pour
        // la requête courante (fuite via cookie de session partagé entre
        // sous-domaines, cf. GuardTenantSession).
        $user = User::factory()->create([
            'password_hash' => Hash::make('MotDePasse!123'),
            'status' => 'active',
        ]);

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'MotDePasse!123',
        ]);

        $this->assertAuthenticatedAs($user);
        $this->assertSame(1, session('tenant_org_id'));

        // Simule une session portant l'org_id d'un autre tenant (collision
        // d'ID entre bases indépendantes, scénario reproduit manuellement).
        session(['tenant_org_id' => 999]);

        $response = $this->get(route('dashboard'));

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_session_valide_conserve_acces(): void
    {
        // Contrôle négatif : une session dont l'org_id correspond au tenant
        // résolu ne doit jamais être invalidée par GuardTenantSession.
        $user = User::factory()->create([
            'password_hash' => Hash::make('MotDePasse!123'),
            'status' => 'active',
        ]);

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'MotDePasse!123',
        ]);

        $response = $this->get(route('dashboard'));

        $response->assertOk();
        $this->assertAuthenticatedAs($user);
    }
}
