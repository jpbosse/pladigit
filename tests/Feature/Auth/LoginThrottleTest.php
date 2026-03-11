<?php

namespace Tests\Feature\Auth;

use App\Models\Tenant\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Tests du throttle login — protection credential stuffing / DDoS.
 *
 * Stratégie testée :
 *   - Clé IP+email  : 10 tentatives max / 5 minutes par couple (IP, email)
 *   - Clé IP seule  : 20 tentatives max / 5 minutes par IP (tous comptes confondus)
 *
 * Ces tests vérifient que le middleware throttle:login retourne 429
 * et que les compteurs sont bien isolés par couple IP+email.
 */
class LoginThrottleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Réinitialiser tous les compteurs entre chaque test
        RateLimiter::clear('login|email:127.0.0.1|victim@example.com');
        RateLimiter::clear('login|ip:127.0.0.1');
        RateLimiter::clear('login|email:127.0.0.1|other@example.com');
        RateLimiter::clear('login|email:1.2.3.4|victim@example.com');
    }

    // ─── Throttle par couple IP + email ───────────────────────────────────

    /**
     * Après 10 tentatives échouées sur le même couple (IP, email),
     * la 11ème doit être bloquée avec HTTP 429 (ou redirection avec erreur throttle).
     */
    public function test_throttle_bloque_apres_10_tentatives_meme_couple_ip_email(): void
    {
        $user = User::factory()->create([
            'email' => 'victim@example.com',
            'password_hash' => Hash::make('CorrectPass!1'),
            'status' => 'active',
        ]);

        // 10 tentatives avec mauvais mot de passe — toutes doivent passer
        for ($i = 0; $i < 10; $i++) {
            $this->post(route('login'), [
                'email' => 'victim@example.com',
                'password' => 'WrongPass',
            ]);
        }

        // La 11ème doit être throttlée
        $response = $this->post(route('login'), [
            'email' => 'victim@example.com',
            'password' => 'WrongPass',
        ]);

        $response->assertStatus(429);
    }

    /**
     * Le throttle est spécifique au couple (IP, email) :
     * un autre email depuis la même IP dispose de son propre compteur
     * (jusqu'à ce que le limiter IP seul se déclenche).
     */
    public function test_throttle_isole_par_email_meme_ip(): void
    {
        User::factory()->create([
            'email' => 'victim@example.com',
            'password_hash' => Hash::make('CorrectPass!1'),
            'status' => 'active',
        ]);
        User::factory()->create([
            'email' => 'other@example.com',
            'password_hash' => Hash::make('CorrectPass!1'),
            'status' => 'active',
        ]);

        // Épuiser le compteur sur victim@example.com
        for ($i = 0; $i < 10; $i++) {
            $this->post(route('login'), [
                'email' => 'victim@example.com',
                'password' => 'WrongPass',
            ]);
        }

        // other@example.com doit toujours être accessible (compteur propre)
        $response = $this->post(route('login'), [
            'email' => 'other@example.com',
            'password' => 'WrongPass',
        ]);

        // Doit échouer sur les credentials, PAS sur le throttle
        $response->assertSessionHasErrors('email');
        $response->assertStatus(302); // redirect, pas 429
    }

    /**
     * Même email depuis une IP différente dispose de son propre compteur.
     * Simule un attaquant qui change d'IP à chaque tentative.
     */
    public function test_throttle_isole_par_ip_meme_email(): void
    {
        User::factory()->create([
            'email' => 'victim@example.com',
            'password_hash' => Hash::make('CorrectPass!1'),
            'status' => 'active',
        ]);

        // Épuiser le compteur depuis 127.0.0.1
        for ($i = 0; $i < 10; $i++) {
            $this->post(route('login'), [
                'email' => 'victim@example.com',
                'password' => 'WrongPass',
            ]);
        }

        // Même email depuis une IP différente : compteur IP+email distinct → non throttlé
        $response = $this->withServerVariables(['REMOTE_ADDR' => '1.2.3.4'])
            ->post(route('login'), [
                'email' => 'victim@example.com',
                'password' => 'WrongPass',
            ]);

        $response->assertSessionHasErrors('email');
        $response->assertStatus(302);
    }

    // ─── Throttle IP seul (filet de sécurité global) ──────────────────────

    /**
     * Après 20 requêtes depuis la même IP (tous emails confondus),
     * le limiter IP seul doit se déclencher.
     */
    public function test_throttle_ip_seul_bloque_apres_20_tentatives(): void
    {
        // Créer 20 comptes distincts pour varier les emails
        $emails = [];
        for ($i = 0; $i < 20; $i++) {
            $email = "user{$i}@example.com";
            $emails[] = $email;
            User::factory()->create([
                'email' => $email,
                'password_hash' => Hash::make('Pass!1'),
                'status' => 'active',
            ]);
            RateLimiter::clear("login|email:127.0.0.1|{$email}");
        }

        // 20 tentatives avec des emails différents (credential stuffing simulé)
        for ($i = 0; $i < 20; $i++) {
            $this->post(route('login'), [
                'email' => $emails[$i],
                'password' => 'WrongPass',
            ]);
        }

        // La 21ème tentative doit être bloquée par le limiter IP
        $response = $this->post(route('login'), [
            'email' => 'new@example.com',
            'password' => 'WrongPass',
        ]);

        $response->assertStatus(429);
    }

    // ─── Le throttle ne bloque pas une connexion légitime ─────────────────

    /**
     * Un utilisateur légitime qui se trompe moins de 10 fois
     * doit pouvoir se connecter avec ses bons identifiants.
     */
    public function test_connexion_legitime_non_bloquee_sous_le_seuil(): void
    {
        $user = User::factory()->create([
            'email' => 'legit@example.com',
            'password_hash' => Hash::make('CorrectPass!1'),
            'status' => 'active',
        ]);
        RateLimiter::clear('login|email:127.0.0.1|legit@example.com');

        // 3 mauvaises tentatives
        for ($i = 0; $i < 3; $i++) {
            $this->post(route('login'), [
                'email' => 'legit@example.com',
                'password' => 'WrongPass',
            ]);
        }

        // Le bon mot de passe doit fonctionner
        $response = $this->post(route('login'), [
            'email' => 'legit@example.com',
            'password' => 'CorrectPass!1',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }
}
