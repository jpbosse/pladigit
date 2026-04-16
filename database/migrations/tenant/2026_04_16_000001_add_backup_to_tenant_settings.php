<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::connection('tenant')->table('tenant_settings', function (Blueprint $table) {
            // ── Activation et planification ───────────────────────────
            $table->boolean('backup_enabled')->default(false);
            $table->string('backup_schedule', 20)->default('daily');

            // ── Destination ───────────────────────────────────────────
            $table->string('backup_driver', 10)->default('local');
            // Local
            $table->string('backup_local_path', 500)->nullable();
            // SFTP
            $table->string('backup_sftp_host', 255)->nullable();
            $table->unsignedSmallInteger('backup_sftp_port')->default(22);
            $table->string('backup_sftp_user', 255)->nullable();
            $table->text('backup_sftp_password_enc')->nullable();
            $table->string('backup_sftp_path', 500)->nullable();

            // ── Rétention ─────────────────────────────────────────────
            $table->unsignedTinyInteger('backup_retention_count')->default(7);

            // ── Statut de la dernière sauvegarde ──────────────────────
            $table->timestamp('backup_last_run_at')->nullable();
            $table->string('backup_last_status', 20)->nullable();
            $table->text('backup_last_message')->nullable();
            $table->unsignedBigInteger('backup_last_size_bytes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('tenant_settings', function (Blueprint $table) {
            $table->dropColumn([
                'backup_enabled', 'backup_schedule',
                'backup_driver',
                'backup_local_path',
                'backup_sftp_host', 'backup_sftp_port', 'backup_sftp_user',
                'backup_sftp_password_enc', 'backup_sftp_path',
                'backup_retention_count',
                'backup_last_run_at', 'backup_last_status',
                'backup_last_message', 'backup_last_size_bytes',
            ]);
        });
    }
};
