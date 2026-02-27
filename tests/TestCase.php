<?php

namespace Tests;

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
        // Créer la base de test tenant si elle n'existe pas
        DB::statement('CREATE DATABASE IF NOT EXISTS `pladigit_testing_tenant` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

        // Configurer la connexion tenant vers la base de test
        config(['database.connections.tenant' => [
            'driver' => 'mysql',
            'host' => '127.0.0.1',
            'port' => '3306',
            'database' => 'pladigit_testing_tenant',
            'username' => env('DB_USERNAME', 'pladigit'),
            'password' => env('DB_PASSWORD', 'Bsg75&Ncc1701'),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ]]);

        // Migrer la base tenant de test
        $this->artisan('migrate', [
            '--database' => 'tenant',
            '--path' => 'database/migrations/tenant',
            '--force' => true,
        ]);

        // Simuler un tenant actif
        $org = new \App\Models\Platform\Organization([
            'id' => 1,
            'name' => 'Test Org',
            'slug' => 'test',
            'db_name' => 'pladigit_testing_tenant',
            'status' => 'active',
            'plan' => 'starter',
            'max_users' => 50,
            'primary_color' => '#1E3A5F',
        ]);

        app(\App\Services\TenantManager::class)->connectTo($org);
    }
}
