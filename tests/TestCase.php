<?php

namespace Tests;

use App\Models\Platform\Organization;
use App\Services\TenantManager;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    private static bool $tenantMigrated = false;

    private static bool $platformMigrated = false;

    private static bool $dbConfigured = false;

    protected function setUp(): void
    {
        parent::setUp();

        // Use a writable tmp directory for compiled views during tests
        // (avoids permission issues when web-server-compiled cache files exist)
        $tmpViews = sys_get_temp_dir().'/pladigit_views_test';
        if (! is_dir($tmpViews)) {
            mkdir($tmpViews, 0775, true);
        }
        config(['view.compiled' => $tmpViews]);
        // Force the BladeCompiler singleton to be re-resolved with the new path
        $this->app->forgetInstance('blade.compiler');
        $this->app->forgetInstance('view');
        $this->app->forgetInstance('view.engine.resolver');

        if (! self::$dbConfigured) {
            $this->configureDatabases();
            self::$dbConfigured = true;
        }

        $this->runMigrationsIfNeeded();
        $this->cleanDatabase();

        $org = new Organization([
            'id' => 1,
            'name' => 'Test Org',
            'slug' => 'test',
            'db_name' => env('DB_TENANT_DATABASE', 'pladigit_testing_tenant'),
            'status' => 'active',
            'plan' => 'communautaire',
            'max_users' => 200,
            'primary_color' => '#1E3A5F',
            'enabled_modules' => ['media', 'projects', 'ged'],
        ]);
        // Persister l'org en base pour que la commande puisse la trouver
        $org->save();
        app(TenantManager::class)->connectTo($org);
    }

    private function configureDatabases(): void
    {
        $dbTenant = env('DB_TENANT_DATABASE', 'pladigit_testing_tenant');

        config([
            'database.connections.mysql.host' => env('DB_HOST', '127.0.0.1'),
            'database.connections.mysql.port' => env('DB_PORT', '3306'),
            'database.connections.mysql.database' => env('DB_DATABASE', 'pladigit_testing_platform'),
            'database.connections.mysql.username' => env('DB_USERNAME', 'pladigit'),
            'database.connections.mysql.password' => env('DB_PASSWORD', ''),
        ]);
        DB::purge('mysql');
        DB::reconnect('mysql');

        DB::connection('mysql')->statement(
            "CREATE DATABASE IF NOT EXISTS `{$dbTenant}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
        );

        config(['database.connections.tenant' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => $dbTenant,
            'username' => env('DB_USERNAME', 'pladigit'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ]]);
        DB::purge('tenant');
        DB::reconnect('tenant');
    }

    private function runMigrationsIfNeeded(): void
    {
        if (! self::$platformMigrated) {
            $this->artisan('migrate:fresh', [
                '--database' => 'mysql',
                '--path' => 'database/migrations/platform',
                '--force' => true,
            ]);
            self::$platformMigrated = true;
        } else {
            // Recréer la table si elle a disparu (purge entre tests)
            try {
                DB::connection('mysql')->table('organizations')->count();
            } catch (\Throwable) {
                $this->artisan('migrate:fresh', [
                    '--database' => 'mysql',
                    '--path' => 'database/migrations/platform',
                    '--force' => true,
                ]);
            }
        }

        if (! self::$tenantMigrated) {
            $schema = DB::connection('tenant')->getSchemaBuilder();
            $gedOk = $schema->hasTable('ged_folders')
                && $schema->hasColumn('ged_folders', 'slug')
                && $schema->hasColumn('ged_folders', 'path')
                && $schema->hasColumn('ged_folders', 'is_private')
                && $schema->hasTable('ged_documents');

            if (! $gedOk) {
                $this->artisan('migrate:fresh', [
                    '--database' => 'tenant',
                    '--path' => 'database/migrations/tenant',
                    '--force' => true,
                ]);
            }
            self::$tenantMigrated = true;
        }

    }

    private function cleanDatabase(): void
    {
        $platformDb = DB::connection('mysql')->getDatabaseName();
        if (! str_contains($platformDb, 'testing')) {
            throw new \RuntimeException("DANGER cleanDatabase() sur base prod : {$platformDb}");
        }
        $tenantDb = DB::connection('tenant')->getDatabaseName();
        if (! str_contains($tenantDb, 'testing')) {
            throw new \RuntimeException("DANGER cleanDatabase() sur base prod : {$tenantDb}");
        }

        try {
            DB::connection('mysql')->statement('SET FOREIGN_KEY_CHECKS=0');
            DB::connection('mysql')->table('organizations')->delete();
            DB::connection('mysql')->statement('SET FOREIGN_KEY_CHECKS=1');
        } catch (\Throwable) {
        }

        try {
            $db = DB::connection('tenant');
            $db->statement('SET FOREIGN_KEY_CHECKS=0');
            foreach ([
                'users', 'departments', 'user_department',
                'invitations',
                'media_item_tag', 'media_tags',
                'media_albums', 'media_items', 'album_permissions',
                'album_user_permissions', 'media_share_links', 'shares',
                'tenant_settings',
                'audit_logs', 'sessions', 'notifications', 'email_logs',
                'task_dependencies', 'task_comments', 'tasks',
                'project_milestones', 'project_members', 'projects',
                'event_participants', 'events',
                'ged_documents', 'ged_folders',
            ] as $t) {
                try {
                    $db->statement("TRUNCATE TABLE `{$t}`");
                } catch (\Throwable) {
                    // Table may not exist yet (migration not run) — skip
                }
            }
            $db->statement('SET FOREIGN_KEY_CHECKS=1');
        } catch (\Throwable) {
        }
    }
}
