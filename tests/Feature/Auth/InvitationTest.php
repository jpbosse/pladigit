<?php

namespace Tests\Feature\Auth;

use App\Mail\UserInvitationMail;
use App\Models\Tenant\User;
use App\Services\InvitationService;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Tests du système d'invitation utilisateur par email.
 *
 * Couverture :
 *   - Token valide → affiche le formulaire d'activation
 *   - Token expiré → affiche la vue "lien expiré"
 *   - Token déjà utilisé → affiche la vue "lien invalide"
 *   - Token inexistant → affiche la vue "lien invalide"
 *   - Activation réussie → compte actif, MDP défini, token consommé
 *   - Usage unique → deuxième soumission rejetée
 *   - Email envoyé lors de la création d'un utilisateur
 */
class InvitationTest extends TestCase
{
    private InvitationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(InvitationService::class);
        Mail::fake();
    }

    // ─── InvitationService ────────────────────────────────────────────────

    public function test_generate_stocke_hash_et_expiration(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $token = $this->service->generate($user);

        $user->refresh();

        $this->assertNotNull($user->invitation_token);
        $this->assertNotEquals($token, $user->invitation_token); // Hash, pas le token brut
        $this->assertEquals(hash('sha256', $token), $user->invitation_token);
        $this->assertNotNull($user->invitation_expires_at);
        $this->assertTrue($user->invitation_expires_at->isFuture());
        $this->assertEquals('inactive', $user->status);
        $this->assertNull($user->password_hash);
    }

    public function test_find_valid_user_retourne_user_avec_token_valide(): void
    {
        $user = User::factory()->create();
        $token = $this->service->generate($user);

        $found = $this->service->findValidUser($token);

        $this->assertNotNull($found);
        $this->assertEquals($user->id, $found->id);
    }

    public function test_find_valid_user_retourne_null_si_token_expire(): void
    {
        $user = User::factory()->create();
        $token = $this->service->generate($user);

        // Forcer l'expiration
        $user->update(['invitation_expires_at' => now()->subHour()]);

        $found = $this->service->findValidUser($token);

        $this->assertNull($found);
    }

    public function test_find_valid_user_retourne_null_si_deja_utilise(): void
    {
        $user = User::factory()->create();
        $token = $this->service->generate($user);

        $this->service->consume($user);

        $found = $this->service->findValidUser($token);

        $this->assertNull($found);
    }

    public function test_consume_invalide_le_token_et_active_le_compte(): void
    {
        $user = User::factory()->create();
        $this->service->generate($user);

        $this->service->consume($user);

        $user->refresh();

        $this->assertNotNull($user->invitation_used_at);
        $this->assertNull($user->invitation_token);
        $this->assertEquals('active', $user->status);
        $this->assertFalse((bool) $user->force_pwd_change);
    }

    // ─── Routes HTTP ──────────────────────────────────────────────────────

    public function test_formulaire_activation_affiche_avec_token_valide(): void
    {
        $user = User::factory()->create();
        $token = $this->service->generate($user);

        $response = $this->get(route('invitation.show', $token));

        $response->assertStatus(200);
        $response->assertViewIs('auth.invitation-accept');
        $response->assertSee($user->email);
    }

    public function test_formulaire_affiche_vue_expired_si_token_expire(): void
    {
        $user = User::factory()->create();
        $token = $this->service->generate($user);
        $user->update(['invitation_expires_at' => now()->subHour()]);

        $response = $this->get(route('invitation.show', $token));

        $response->assertStatus(200);
        $response->assertViewIs('auth.invitation-invalid');
        $response->assertViewHas('reason', 'expired');
    }

    public function test_formulaire_affiche_vue_invalid_si_token_inconnu(): void
    {
        $response = $this->get(route('invitation.show', 'token-inexistant'));

        $response->assertStatus(200);
        $response->assertViewIs('auth.invitation-invalid');
    }

    public function test_activation_reussie_cree_mot_de_passe_et_connecte(): void
    {
        $user = User::factory()->create();
        $token = $this->service->generate($user);

        $response = $this->post(route('invitation.accept', $token), [
            'password' => 'NouveauMdp!123',
            'password_confirmation' => 'NouveauMdp!123',
        ]);

        $response->assertRedirect(route('dashboard'));

        $user->refresh();
        $this->assertNotNull($user->password_hash);
        $this->assertEquals('active', $user->status);
        $this->assertNull($user->invitation_token);
        $this->assertNotNull($user->invitation_used_at);
        $this->assertAuthenticatedAs($user);
    }

    public function test_usage_unique_deuxieme_soumission_rejetee(): void
    {
        $user = User::factory()->create();
        $token = $this->service->generate($user);

        // Première activation — réussit
        $this->post(route('invitation.accept', $token), [
            'password' => 'NouveauMdp!123',
            'password_confirmation' => 'NouveauMdp!123',
        ]);

        auth()->logout();

        // Deuxième tentative avec le même token — doit être rejetée
        $response = $this->post(route('invitation.accept', $token), [
            'password' => 'AutreMdp!456',
            'password_confirmation' => 'AutreMdp!456',
        ]);

        // Redirige vers le formulaire avec erreur (token invalide)
        $response->assertRedirect(route('invitation.show', $token));
    }

    public function test_activation_echoue_si_mots_de_passe_differents(): void
    {
        $user = User::factory()->create();
        $token = $this->service->generate($user);

        $response = $this->post(route('invitation.accept', $token), [
            'password' => 'NouveauMdp!123',
            'password_confirmation' => 'AutreMdp!999',
        ]);

        $response->assertSessionHasErrors('password');
        // Le token doit être encore valide
        $this->assertNotNull($this->service->findValidUser($token));
    }

    public function test_email_invitation_envoye_a_la_creation(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Nouveau User',
            'email' => 'nouveau@example.com',
            'role' => 'user',
        ]);

        Mail::assertSent(UserInvitationMail::class, function ($mail) {
            return $mail->hasTo('nouveau@example.com');
        });
    }
}
