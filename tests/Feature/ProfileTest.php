<?php

namespace Tests\Feature;

use App\Models\Tenant\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Tests Feature — Profil utilisateur.
 */
class ProfileTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'role' => 'user',
            'status' => 'active',
            'password_hash' => Hash::make('MotDePasse!123'),
        ]);
    }

    // ── Accès ──────────────────────────────────────────────────────────

    public function test_invité_ne_peut_pas_voir_le_profil(): void
    {
        $this->get(route('profile.show'))
            ->assertRedirect(route('login'));
    }

    public function test_utilisateur_peut_voir_son_profil(): void
    {
        $this->actingAs($this->user)
            ->get(route('profile.show'))
            ->assertOk()
            ->assertViewIs('profile.show')
            ->assertViewHas('user');
    }

    // ── Mise à jour informations ───────────────────────────────────────

    public function test_utilisateur_peut_modifier_son_nom(): void
    {
        $this->actingAs($this->user)
            ->patch(route('profile.update-info'), [
                'name' => 'Nouveau Nom',
            ])
            ->assertRedirect()
            ->assertSessionHas('success_info');

        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'name' => 'Nouveau Nom',
        ], 'tenant');
    }

    public function test_nom_vide_refusé(): void
    {
        $this->actingAs($this->user)
            ->patch(route('profile.update-info'), ['name' => ''])
            ->assertSessionHasErrors('name');
    }

    public function test_nom_trop_long_refusé(): void
    {
        $this->actingAs($this->user)
            ->patch(route('profile.update-info'), ['name' => str_repeat('A', 256)])
            ->assertSessionHasErrors('name');
    }

    // ── Changement mot de passe ────────────────────────────────────────

    public function test_utilisateur_peut_changer_son_mot_de_passe(): void
    {
        $this->actingAs($this->user)
            ->patch(route('profile.update-password'), [
                'current_password' => 'MotDePasse!123',
                'password' => 'NouveauMdP!456',
                'password_confirmation' => 'NouveauMdP!456',
            ])
            ->assertRedirect()
            ->assertSessionHas('success_password');
    }

    public function test_mauvais_mot_de_passe_actuel_refusé(): void
    {
        $this->actingAs($this->user)
            ->patch(route('profile.update-password'), [
                'current_password' => 'MauvaisMotDePasse!',
                'password' => 'NouveauMdP!456',
                'password_confirmation' => 'NouveauMdP!456',
            ])
            ->assertSessionHasErrors('current_password');
    }

    public function test_confirmation_mot_de_passe_incorrecte_refusée(): void
    {
        $this->actingAs($this->user)
            ->patch(route('profile.update-password'), [
                'current_password' => 'MotDePasse!123',
                'password' => 'NouveauMdP!456',
                'password_confirmation' => 'AutreChose!789',
            ])
            ->assertSessionHasErrors('password');
    }

    public function test_mot_de_passe_actuel_requis(): void
    {
        $this->actingAs($this->user)
            ->patch(route('profile.update-password'), [
                'current_password' => '',
                'password' => 'NouveauMdP!456',
                'password_confirmation' => 'NouveauMdP!456',
            ])
            ->assertSessionHasErrors('current_password');
    }

    // ── Codes de secours 2FA ───────────────────────────────────────────

    public function test_régénération_codes_secours_sans_2fa_refusée(): void
    {
        $this->actingAs($this->user)
            ->post(route('profile.regenerate-backup-codes'), [
                'password' => 'MotDePasse!123',
            ])
            ->assertSessionHasErrors('password');
    }

    public function test_régénération_codes_secours_mauvais_mdp_refusée(): void
    {
        $user = User::factory()->create([
            'totp_enabled' => true,
            'password_hash' => Hash::make('MotDePasse!123'),
        ]);

        $this->actingAs($user)
            ->post(route('profile.regenerate-backup-codes'), [
                'password' => 'MauvaisMotDePasse!',
            ])
            ->assertSessionHasErrors('password');
    }

    public function test_régénération_codes_secours_avec_bon_mdp_retourne_codes(): void
    {
        $user = User::factory()->create([
            'totp_enabled' => true,
            'password_hash' => Hash::make('MotDePasse!123'),
        ]);

        $this->actingAs($user)
            ->post(route('profile.regenerate-backup-codes'), [
                'password' => 'MotDePasse!123',
            ])
            ->assertRedirect()
            ->assertSessionHas('new_backup_codes');
    }
}
