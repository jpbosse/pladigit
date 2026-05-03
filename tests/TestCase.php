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

    // Ensures stale compiled Blade views are purged exactly once per test session.
    private static bool $viewCacheCleared = false;

    protected function setUp(): void
    {
        parent::setUp();

        // Use a writable tmp directory for compiled views during tests
        // (avoids permission issues when web-server-compiled cache files exist)
        $tmpViews = sys_get_temp_dir().'/pladigit_views_test';
        if (! is_dir($tmpViews)) {
            mkdir($tmpViews, 0775, true);
        }

        // Purge compiled *.php files once per session so that Blade always
        // re-compiles views with all registered directives (including @livewire).
        // A stale compiled file lacking the @livewire directive produces a null
        // snapshot and breaks every Livewire::test() call.
        if (! self::$viewCacheCleared) {
            array_map('unlink', glob($tmpViews.'/*.php') ?: []);
            self::$viewCacheCleared = true;
        }

        config(['view.compiled' => $tmpViews]);
        // Update the compiled path directly on the existing BladeCompiler so that
        // Livewire directives (registered at boot on this instance) are preserved.
        // forgetInstance('blade.compiler') would lose them and break Livewire::test().
        $prop = new \ReflectionProperty(app('blade.compiler'), 'cachePath');
        $prop->setAccessible(true);
        $prop->setValue(app('blade.compiler'), $tmpViews);

        if (! self::$dbConfigured) {
            $this->configureDatabases();
            self::$dbConfigured = true;
        } else {
            // Les commandes artisan exécutées dans les tests peuvent appeler
            // TenantManager::connectTo() et laisser les connexions dans un état
            // incorrect. On purge les deux pour repartir sur une connexion fraîche
            // et éviter l'accumulation de transactions mysql non rollback-ées.
            $dbTenant = env('DB_TENANT_DATABASE', 'pladigit_testing_tenant');
            config(['database.connections.tenant.database' => $dbTenant]);
            DB::purge('tenant');
            DB::purge('mysql');
        }

        $this->runMigrationsIfNeeded();

        // ── Sécurité : ne jamais tourner sur prod ────────────────────────
        $this->assertTestingDatabases();

        // ── Nettoyage par transaction (rollback dans tearDown) ───────────
        // Transaction mysql ouverte avant l'insert org.
        DB::connection('mysql')->beginTransaction();

        // DELETE + INSERT plutôt que updateOrInsert pour éviter la race condition
        // entre SELECT et INSERT lorsque la ligne vient d'être rollbackée.
        DB::connection('mysql')->statement('SET FOREIGN_KEY_CHECKS=0');
        DB::connection('mysql')->table('organizations')->where('id', 1)->delete();
        DB::connection('mysql')->table('organizations')->insert([
            'id' => 1,
            'name' => 'Test Org',
            'slug' => 'test',
            'db_name' => env('DB_TENANT_DATABASE', 'pladigit_testing_tenant'),
            'status' => 'active',
            'plan' => 'communautaire',
            'max_users' => 200,
            'primary_color' => '#1E3A5F',
            'enabled_modules' => json_encode(['media', 'projects', 'ged']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::connection('mysql')->statement('SET FOREIGN_KEY_CHECKS=1');

        $org = Organization::on('mysql')->find(1);

        // connectTo() purge + reconnecte la connexion tenant → on démarre la
        // transaction tenant APRÈS pour être sur la bonne connexion.
        app(TenantManager::class)->connectTo($org);

        DB::connection('tenant')->beginTransaction();
    }

    protected function tearDown(): void
    {
        // Rollback dans l'ordre inverse : tenant d'abord, puis platform.
        foreach (['tenant', 'mysql'] as $conn) {
            try {
                if (DB::connection($conn)->transactionLevel() > 0) {
                    DB::connection($conn)->rollBack();
                }
            } catch (\Throwable) {
                // Connexion perdue (ex: artisan command a appelé connectTo()) — on ignore.
            }
        }

        parent::tearDown();
    }

    // ── Helpers privés ────────────────────────────────────────────────────

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
            // Premier test de la session : wipe propre avant migrate:fresh pour
            // éviter l'erreur 1051 si la DB est en état partiel (run précédent avorté).
            $this->artisan('db:wipe', ['--database' => 'mysql', '--force' => true]);
            $this->artisan('migrate:fresh', [
                '--database' => 'mysql',
                '--path' => 'database/migrations/platform',
                '--force' => true,
            ]);
            self::$platformMigrated = true;
        } else {
            try {
                DB::connection('mysql')->table('organizations')->count();
            } catch (\Throwable) {
                $this->artisan('db:wipe', ['--database' => 'mysql', '--force' => true]);
                $this->artisan('migrate:fresh', [
                    '--database' => 'mysql',
                    '--path' => 'database/migrations/platform',
                    '--force' => true,
                ]);
            }
        }

        if (! self::$tenantMigrated) {
            // Toujours wipe + migrate:fresh en début de session pour garantir
            // un état propre, même si le schéma est à jour (données résiduelles
            // d'une session précédente interrompue).
            $this->artisan('db:wipe', ['--database' => 'tenant', '--force' => true]);
            $this->artisan('migrate:fresh', [
                '--database' => 'tenant',
                '--path' => 'database/migrations/tenant',
                '--force' => true,
            ]);
            self::$tenantMigrated = true;
        }
    }

    private function assertTestingDatabases(): void
    {
        $platformDb = DB::connection('mysql')->getDatabaseName();
        if (! str_contains($platformDb, 'testing')) {
            throw new \RuntimeException("DANGER : opération sur base prod interdite : {$platformDb}");
        }

        $tenantDb = DB::connection('tenant')->getDatabaseName();
        if (! str_contains($tenantDb, 'testing')) {
            throw new \RuntimeException("DANGER : opération sur base prod interdite : {$tenantDb}");
        }
    }
}
