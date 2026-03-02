<?php

namespace Tests;

use App\Models\Platform\Organization;
use App\Services\TenantManager;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenantDatabase();
    }

    protected function setUpTenantDatabase(): void
    {
        $dbHost     = env('DB_HOST', '127.0.0.1');
        $dbPort     = env('DB_PORT', '3306');
        $dbUsername = env('DB_USERNAME', 'pladigit');
        $dbPassword = env('DB_PASSWORD', '');
        $dbTenant   = 'pladigit_testing_tenant';

        // Créer la base tenant via la connexion platform explicite
        DB::connection('mysql')->statement(
            "CREATE DATABASE IF NOT EXISTS `{$dbTenant}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
        );

        // Configurer la connexion 'tenant'
        config(['database.connections.tenant' => [
            'driver'    => 'mysql',
            'host'      => $dbHost,
            'port'      => $dbPort,
            'database'  => $dbTenant,
            'username'  => $dbUsername,
            'password'  => $dbPassword,
            'charset'   => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ]]);

        DB::purge('tenant');
        DB::reconnect('tenant');

        // migrate:fresh sur tenant uniquement
        $this->artisan('migrate:fresh', [
            '--database' => 'tenant',
            '--path'     => 'database/migrations/tenant',
            '--force'    => true,
        ]);

        // Simuler un tenant actif
        $org = new Organization([
            'id'            => 1,
            'name'          => 'Test Org',
            'slug'          => 'test',
            'db_name'       => $dbTenant,
            'status'        => 'active',
            'plan'          => 'starter',
            'max_users'     => 50,
            'primary_color' => '#1E3A5F',
        ]);

        app(TenantManager::class)->connectTo($org);
    }

    /**
     * Désactive RefreshDatabase sur la connexion par défaut (platform).
     * Chaque test reçoit une base tenant fraîche via setUpTenantDatabase().
     */
    protected function beginDatabaseTransaction(): void
    {
        // On ne démarre pas de transaction sur platform — uniquement sur tenant
        DB::connection('tenant')->beginTransaction();
        $this->beforeApplicationDestroyed(function () {
            DB::connection('tenant')->rollBack();
        });
    }
}
