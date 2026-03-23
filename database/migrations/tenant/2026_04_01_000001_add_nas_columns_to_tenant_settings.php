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
            $table->string('nas_photo_driver', 10)->default('local');
            $table->string('nas_photo_local_path', 500)->nullable();
            $table->string('nas_photo_host', 255)->nullable();
            $table->unsignedSmallInteger('nas_photo_port')->nullable();
            $table->string('nas_photo_username', 255)->nullable();
            $table->text('nas_photo_password_enc')->nullable();
            $table->string('nas_photo_share', 255)->nullable();
            $table->string('nas_photo_root_path', 500)->nullable();
            $table->unsignedSmallInteger('nas_photo_sync_interval_minutes')->default(60);
            $table->timestamp('nas_photo_last_sync_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('tenant_settings', function (Blueprint $table) {
            $table->dropColumn([
                'nas_photo_driver',
                'nas_photo_local_path',
                'nas_photo_host',
                'nas_photo_port',
                'nas_photo_username',
                'nas_photo_password_enc',
                'nas_photo_share',
                'nas_photo_root_path',
                'nas_photo_sync_interval_minutes',
                'nas_photo_last_sync_at',
            ]);
        });
    }
};
