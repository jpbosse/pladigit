<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajoute les colonnes de configuration NAS dans tenant_settings.
 * Phase 3 — Photothèque NAS.
 */
return new class extends Migration
{
    public function connection(): string
    {
        return 'tenant';
    }

    public function up(): void
    {
        Schema::connection('tenant')->table('tenant_settings', function (Blueprint $table) {
            $table->string('nas_driver', 10)->default('local');
            $table->string('nas_local_path', 500)->nullable();
            $table->string('nas_host', 255)->nullable();
            $table->unsignedSmallInteger('nas_port')->nullable();
            $table->string('nas_username', 255)->nullable();
            $table->text('nas_password_enc')->nullable();
            $table->string('nas_share', 255)->nullable();
            $table->string('nas_root_path', 500)->nullable();
            $table->unsignedSmallInteger('nas_sync_interval_minutes')->default(60);
            $table->timestamp('nas_last_sync_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('tenant_settings', function (Blueprint $table) {
            $table->dropColumn([
                'nas_driver',
                'nas_local_path',
                'nas_host',
                'nas_port',
                'nas_username',
                'nas_password_enc',
                'nas_share',
                'nas_root_path',
                'nas_sync_interval_minutes',
                'nas_last_sync_at',
            ]);
        });
    }
};
