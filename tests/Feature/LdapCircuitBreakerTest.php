<?php

namespace Tests\Feature;

use App\Models\Tenant\TenantSettings;
use App\Models\Tenant\User;
use App\Services\LdapAuthService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LdapRecord\Connection;
use LdapRecord\LdapRecordException;
use Mockery;
use Tests\TestCase;

/**
 * Sous-classe de test permettant d'injecter une Connection mockée
 * sans avoir à mocker buildConnection() (problème de type strict).
 */
class TestableLdapService extends LdapAuthService
{
    public ?Connection $fakeConnection = null;
    public bool $throwOnConnect = false;
    public bool $throwOnQuery = false;

    protected function buildConnection(TenantSettings $settings): Connection
    {
        if ($this->throwOnConnect) {
            throw new LdapRecordException('Connection refused');
        }
        return $this->fakeConnection;
    }
}

class LdapCircuitBreakerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        TenantSettings::firstOrCreate([])->update([
            'ldap_host'              => 'ldap.test.local',
            'ldap_port'              => 636,
            'ldap_base_dn'           => 'dc=test,dc=local',
            'ldap_bind_dn'           => 'cn=admin,dc=test,dc=local',
            'ldap_bind_password_enc' => \Illuminate\Support\Facades\Crypt::encryptString('secret'),
            'ldap_use_ssl'           => true,
            'ldap_use_tls'           => false,
        ]);
    }

    protected function tearDown(): void
    {
        DB::connection('tenant')->table('users')->delete();
        DB::connection('tenant')->table('tenant_settings')->delete();
        Mockery::close();
        parent::tearDown();
    }

    private function makeService(?array $ldapUsers = null, bool $throwOnConnect = false, bool $throwOnQuery = false): TestableLdapService
    {
        $service = new TestableLdapService();
        $service->throwOnConnect = $throwOnConnect;

        if (!$throwOnConnect) {
            $queryMock = Mockery::mock(\LdapRecord\Query\Builder::class);
            $queryMock->shouldReceive('setDn')->andReturnSelf();
            $queryMock->shouldReceive('whereHas')->andReturnSelf();
            $queryMock->shouldReceive('whereEquals')->andReturnSelf();
            $queryMock->shouldReceive('whereContains')->andReturnSelf();

            if ($throwOnQuery) {
                $queryMock->shouldReceive('get')->andThrow(new LdapRecordException('Query failed'));
            } else {
                $queryMock->shouldReceive('get')->andReturn($ldapUsers ?? []);
            }

            $connMock = Mockery::mock(Connection::class);
            $connMock->shouldReceive('connect')->andReturn(true);
            $connMock->shouldReceive('query')->andReturn($queryMock);
            $connMock->shouldReceive('auth')->andReturn(Mockery::mock(['attempt' => true]));

            $service->fakeConnection = $connMock;
        }

        return $service;
    }

    public function test_connexion_echouee_ne_verrouille_pas_les_comptes(): void
    {
        User::factory()->count(3)->create(['ldap_dn' => 'uid=user,dc=test,dc=local', 'status' => 'active']);

        Log::shouldReceive('error')->once();

        $this->makeService(throwOnConnect: true)->syncAllUsers();

        $this->assertEquals(0, User::where('status', 'locked')->count());
    }

    public function test_query_echouee_ne_verrouille_pas_les_comptes(): void
    {
        User::factory()->count(3)->create(['ldap_dn' => 'uid=user,dc=test,dc=local', 'status' => 'active']);

        Log::shouldReceive('error')->once();

        $this->makeService(throwOnQuery: true)->syncAllUsers();

        $this->assertEquals(0, User::where('status', 'locked')->count());
    }

    public function test_resultat_vide_bloque_la_desactivation_masse(): void
    {
        User::factory()->count(5)->create(['ldap_dn' => 'uid=user,dc=test,dc=local', 'status' => 'active']);

        Log::shouldReceive('warning')->once();

        $this->makeService(ldapUsers: [])->syncAllUsers();

        $this->assertEquals(0, User::where('status', 'locked')->count());
    }

    public function test_desactivation_masse_superieure_50_pourcent_bloquee(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            User::factory()->create(['ldap_dn' => "uid=user{$i},dc=test,dc=local", 'status' => 'active']);
        }

        $ldapUsers = [
            ['dn' => 'uid=user1,dc=test,dc=local', 'mail' => ['user1@test.local'], 'cn' => ['User 1']],
            ['dn' => 'uid=user2,dc=test,dc=local', 'mail' => ['user2@test.local'], 'cn' => ['User 2']],
        ];

        Log::shouldReceive('warning')->once();

        $this->makeService(ldapUsers: $ldapUsers)->syncAllUsers();

        $this->assertEquals(0, User::where('status', 'locked')->count());
    }

    public function test_sync_normale_verrouille_utilisateur_absent(): void
    {
        User::factory()->create(['ldap_dn' => 'uid=user1,dc=test,dc=local', 'status' => 'active', 'email' => 'user1@test.local']);
        User::factory()->create(['ldap_dn' => 'uid=user2,dc=test,dc=local', 'status' => 'active', 'email' => 'user2@test.local']);
        User::factory()->create(['ldap_dn' => 'uid=user3,dc=test,dc=local', 'status' => 'active', 'email' => 'user3@test.local']);

        $ldapUsers = [
            ['dn' => 'uid=user1,dc=test,dc=local', 'mail' => ['user1@test.local'], 'cn' => ['User 1']],
            ['dn' => 'uid=user2,dc=test,dc=local', 'mail' => ['user2@test.local'], 'cn' => ['User 2']],
        ];

        Log::shouldReceive("info")->once();

        $this->makeService(ldapUsers: $ldapUsers)->syncAllUsers();

        $this->assertEquals('locked', User::where('ldap_dn', 'uid=user3,dc=test,dc=local')->value('status'));
        $this->assertEquals('active', User::where('ldap_dn', 'uid=user1,dc=test,dc=local')->value('status'));
    }
}
