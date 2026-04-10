<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function connection(): string
    {
        return 'tenant';
    }

    public function up(): void
    {
        $existing = Schema::connection('tenant')->getColumnListing('tenant_settings');

        Schema::connection('tenant')->table('tenant_settings', function (Blueprint $table) use ($existing) {
            if (! in_array('nas_ged_driver', $existing)) {
                $table->string('nas_ged_driver', 10)->default('local');
            }
            if (! in_array('nas_ged_local_path', $existing)) {
                $table->string('nas_ged_local_path', 500)->nullable();
            }
            if (! in_array('nas_ged_host', $existing)) {
                $table->string('nas_ged_host', 255)->nullable();
            }
            if (! in_array('nas_ged_port', $existing)) {
                $table->unsignedSmallInteger('nas_ged_port')->nullable();
            }
            if (! in_array('nas_ged_username', $existing)) {
                $table->string('nas_ged_username', 255)->nullable();
            }
            if (! in_array('nas_ged_password_enc', $existing)) {
                $table->text('nas_ged_password_enc')->nullable();
            }
            if (! in_array('nas_ged_share', $existing)) {
                $table->string('nas_ged_share', 255)->nullable();
            }
            if (! in_array('nas_ged_root_path', $existing)) {
                $table->string('nas_ged_root_path', 500)->nullable();
            }
        });

        // Colonnes de sync : normalement ajoutées par 2026_04_01_000002, mais cette
        // migration s'exécute après lors d'un migrate:fresh. On les ajoute ici si absentes.
        if (! Schema::connection('tenant')->hasColumn('tenant_settings', 'nas_ged_last_sync_at')) {
            Schema::connection('tenant')->table('tenant_settings', function (Blueprint $table) {
                $table->timestamp('nas_ged_last_sync_at')->nullable();
                $table->unsignedSmallInteger('nas_ged_sync_interval_minutes')->default(60);
                $table->json('nas_ged_last_sync_errors')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('tenant_settings', function (Blueprint $table) {
            $table->dropColumn(['nas_ged_driver', 'nas_ged_local_path', 'nas_ged_host',
                'nas_ged_port', 'nas_ged_username', 'nas_ged_password_enc',
                'nas_ged_share', 'nas_ged_root_path']);
        });

        if (Schema::connection('tenant')->hasColumn('tenant_settings', 'nas_ged_last_sync_at')) {
            Schema::connection('tenant')->table('tenant_settings', function (Blueprint $table) {
                $table->dropColumn(['nas_ged_last_sync_at', 'nas_ged_sync_interval_minutes', 'nas_ged_last_sync_errors']);
            });
        }
    }
};
