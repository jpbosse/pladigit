<?php

namespace Tests\Feature;

use App\Models\Tenant\TenantSettings;
use App\Models\Tenant\User;
use App\Services\LdapAuthService;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

/**
 * LdapAuthTest — Tests d'intégration LDAP (§6.2)
 *
 * Prérequis : OpenLDAP Docker en cours d'exécution
 *   docker compose -f docker-compose.test.yml --env-file docker-compose.test.env up -d
 *   sleep 5
 *   php artisan test --filter LdapAuthTest
 *
 * Données de test (docker/ldap/init.ldif) :
 *   alice@pladigit.test   / password_alice  → groupe admin → rôle admin
 *   bob@pladigit.test     / password_bob    → pas de groupe → rôle user
 *   charlie@pladigit.test / password_charlie → groupe dgs → rôle dgs
 *
 * @group ldap
 */
class LdapAuthTest extends TestCase
{
    use WithFaker;

    private const LDAP_HOST = '127.0.0.1';

    private const LDAP_PORT = 3389;          // port mappé docker — plain LDAP (pas TLS en test)

    private const LDAP_BASE_DN = 'dc=pladigit,dc=test';

    private const LDAP_BIND_DN = 'cn=admin,dc=pladigit,dc=test';

    private const LDAP_BIND_PW = 'test_admin_secret';

    protected function setUp(): void
    {
        parent::setUp();

        // Connexion tenant de test
        Config::set('database.connections.tenant', array_merge(
            config('database.connections.mysql'),
            ['database' => env('DB_TENANT_DATABASE', 'pladigit_testing_tenant')]
        ));

        // Rediriger les logs vers stderr — évite les erreurs de permission storage/logs
        Config::set('logging.default', 'stderr');

        // ── Simuler un tenant résolu ──────────────────────────────────
        // LdapAuthService vérifie hasTenant() en premier.
        // En test sans requête HTTP il n'y a pas de tenant → not_configured → null.
        $fakeOrg = new \App\Models\Platform\Organization([
            'slug' => 'testing',
            'name' => 'Organisation Test',
            'db_name' => env('DB_TENANT_DATABASE', 'pladigit_testing_tenant'),
            'status' => 'active',
        ]);
        $fakeOrg->id = 0;
        app(\App\Services\TenantManager::class)->connectTo($fakeOrg);

        // Settings LDAP pointant vers OpenLDAP Docker (plain LDAP, pas TLS)
        TenantSettings::on('tenant')->updateOrCreate([], [
            'ldap_host' => self::LDAP_HOST,
            'ldap_port' => self::LDAP_PORT,
            'ldap_base_dn' => self::LDAP_BASE_DN,
            'ldap_bind_dn' => self::LDAP_BIND_DN,
            'ldap_bind_password_enc' => Crypt::encryptString(self::LDAP_BIND_PW),
            'ldap_use_tls' => false,
            'ldap_use_ssl' => false,
        ]);
    }

    protected function tearDown(): void
    {
        // Nettoyer les utilisateurs créés pendant les tests
        User::on('tenant')
            ->whereIn('email', [
                'alice@pladigit.test',
                'bob@pladigit.test',
                'charlie@pladigit.test',
            ])
            ->forceDelete();

        // Réinitialiser les settings LDAP
        TenantSettings::on('tenant')->updateOrCreate([], [
            'ldap_host' => null,
            'ldap_bind_password_enc' => null,
            'ldap_use_tls' => false,
            'ldap_use_ssl' => false,
        ]);

        parent::tearDown();
    }

    // ────────────────────────────────────────────────────────────────
    // Cas 1 — Authentification réussie, utilisateur créé en base
    // ────────────────────────────────────────────────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function cas1_authentification_ldap_reussie_cree_utilisateur(): void
    {
        $service = app(LdapAuthService::class);

        $user = $service->authenticate('bob@pladigit.test', 'password_bob');

        // Diagnostic : affiche la raison si null
        $reason = $service->getLastFailureReason();
        $this->assertNotNull(
            $user,
            "authenticate() a retourné null. Raison : {$reason}. "
            .'Vérifiez que OpenLDAP Docker tourne (docker ps) et que init.ldif est chargé.'
        );
        $this->assertEquals('bob@pladigit.test', $user->email);
        $this->assertEquals('active', $user->status);
        $this->assertNotNull($user->ldap_dn, 'Le DN LDAP doit être sauvegardé');
        $this->assertNull($reason);

        $this->assertDatabaseHas('users', [
            'email' => 'bob@pladigit.test',
            'status' => 'active',
        ], 'tenant');
    }

    // ────────────────────────────────────────────────────────────────
    // Cas 2 — Mauvais mot de passe → bind_failed
    // ────────────────────────────────────────────────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function cas2_mauvais_mot_de_passe_retourne_bind_failed(): void
    {
        $service = app(LdapAuthService::class);

        $result = $service->authenticate('alice@pladigit.test', 'mauvais_mdp');

        $this->assertNull($result, 'authenticate() doit retourner null si bind échoue');
        $this->assertEquals('bind_failed', $service->getLastFailureReason());
    }

    // ────────────────────────────────────────────────────────────────
    // Cas 3 — Email inconnu → user_not_found
    // ────────────────────────────────────────────────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function cas3_email_inconnu_retourne_user_not_found(): void
    {
        $service = app(LdapAuthService::class);

        $result = $service->authenticate('inconnu@pladigit.test', 'nimporte_quoi');

        $this->assertNull($result);
        $this->assertEquals('user_not_found', $service->getLastFailureReason());
    }

    // ────────────────────────────────────────────────────────────────
    // Cas 4 — Serveur inaccessible → unavailable
    // ────────────────────────────────────────────────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function cas4_serveur_inaccessible_retourne_unavailable(): void
    {
        // Port fermé = serveur inaccessible
        TenantSettings::on('tenant')->updateOrCreate([], [
            'ldap_host' => '127.0.0.1',
            'ldap_port' => 9999,
            'ldap_bind_password_enc' => Crypt::encryptString('irrelevant'),
            'ldap_use_tls' => false,
            'ldap_use_ssl' => false,
        ]);

        $service = app(LdapAuthService::class);
        $result = $service->authenticate('alice@pladigit.test', 'password_alice');

        $this->assertNull($result);
        $this->assertEquals('unavailable', $service->getLastFailureReason());
        $this->assertTrue($service->isUnavailable());
    }

    // ────────────────────────────────────────────────────────────────
    // Cas 5 — Rôles résolus depuis les groupes LDAP
    // ────────────────────────────────────────────────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function cas5_role_resolu_depuis_groupes_ldap(): void
    {
        $service = app(LdapAuthService::class);

        $alice = $service->authenticate('alice@pladigit.test', 'password_alice');
        $this->assertNotNull($alice);
        $this->assertEquals('admin', $alice->role, 'Alice → groupe admin');

        $charlie = $service->authenticate('charlie@pladigit.test', 'password_charlie');
        $this->assertNotNull($charlie);
        $this->assertEquals('dgs', $charlie->role, 'Charlie → groupe dgs');

        $bob = $service->authenticate('bob@pladigit.test', 'password_bob');
        $this->assertNotNull($bob);
        $this->assertEquals('user', $bob->role, 'Bob → pas de groupe → user');
    }
}
