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
        $dbHost = env('DB_HOST', '127.0.0.1');
        $dbPort = env('DB_PORT', '3306');
        $dbUsername = env('DB_USERNAME', 'pladigit');
        $dbPassword = env('DB_PASSWORD', '');
        $dbTenant = 'pladigit_testing_tenant';

        DB::connection('mysql')->statement(
            "CREATE DATABASE IF NOT EXISTS `{$dbTenant}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
        );

        config(['database.connections.tenant' => [
            'driver' => 'mysql',
            'host' => $dbHost,
            'port' => $dbPort,
            'database' => $dbTenant,
            'username' => $dbUsername,
            'password' => $dbPassword,
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ]]);

        DB::purge('tenant');
        DB::reconnect('tenant');

        $this->artisan('migrate:fresh', [
            '--database' => 'tenant',
            '--path' => 'database/migrations/tenant',
            '--force' => true,
        ]);

        $org = new Organization([
            'id' => 1,
            'name' => 'Test Org',
            'slug' => 'test',
            'db_name' => $dbTenant,
            'status' => 'active',
            'plan' => 'starter',
            'max_users' => 50,
            'primary_color' => '#1E3A5F',
        ]);

        app(TenantManager::class)->connectTo($org);
    }
}
