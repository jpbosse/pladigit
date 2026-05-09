<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'mysql';

    public function up(): void
    {
        Schema::connection('mysql')->create('platform_settings', function (Blueprint $table) {
            $table->id();

            // ── Sauvegarde plateforme ─────────────────────────────────
            $table->boolean('backup_enabled')->default(false);
            $table->string('backup_schedule', 20)->default('daily');

            $table->string('backup_driver', 10)->default('local');
            $table->string('backup_local_path', 500)->nullable();

            $table->string('backup_sftp_host', 255)->nullable();
            $table->unsignedSmallInteger('backup_sftp_port')->default(22);
            $table->string('backup_sftp_user', 255)->nullable();
            $table->text('backup_sftp_password_enc')->nullable();
            $table->string('backup_sftp_path', 500)->nullable();

            $table->unsignedTinyInteger('backup_retention_count')->default(7);

            $table->timestamp('backup_last_run_at')->nullable();
            $table->string('backup_last_status', 20)->nullable();
            $table->text('backup_last_message')->nullable();
            $table->unsignedBigInteger('backup_last_size_bytes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('mysql')->dropIfExists('platform_settings');
    }
};
