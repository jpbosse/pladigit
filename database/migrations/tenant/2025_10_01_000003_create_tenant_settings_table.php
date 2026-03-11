<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::connection($this->connection)
            ->create('tenant_settings', function (Blueprint $table) {
                $table->id();

                // Politique mots de passe
                $table->unsignedTinyInteger('pwd_min_length')->default(12);
                $table->boolean('pwd_require_uppercase')->default(true);
                $table->boolean('pwd_require_number')->default(true);
                $table->boolean('pwd_require_special')->default(true);
                $table->unsignedSmallInteger('pwd_validity_days')->nullable()
                    ->default(365)->comment('NULL = pas d\'expiration');
                $table->unsignedTinyInteger('pwd_history_count')->default(5);
                $table->unsignedTinyInteger('login_max_attempts')->default(10);
                $table->unsignedSmallInteger('login_lockout_minutes')->default(15);
                $table->unsignedInteger('session_lifetime_minutes')->default(120);

                // 2FA (Phase 2)
                $table->boolean('force_2fa')->default(false);

                // LDAP (Phase 2)
                $table->string('ldap_host')->nullable();
                $table->unsignedSmallInteger('ldap_port')->nullable()->default(636);
                $table->string('ldap_base_dn', 500)->nullable();
                $table->string('ldap_bind_dn', 500)->nullable();
                $table->text('ldap_bind_password_enc')->nullable()
                    ->comment('Chiffré AES-256');
                $table->boolean('ldap_use_tls')->default(true);
                $table->boolean('ldap_use_ssl')->default(true);
                $table->unsignedTinyInteger('ldap_sync_interval_hours')->default(24);

                // Fenêtre de maintenance
                $table->unsignedTinyInteger('maintenance_window_day')
                    ->nullable()->default(0)->comment('0=dimanche');
                $table->time('maintenance_window_start')->nullable()->default('02:00:00');
                $table->time('maintenance_window_end')->nullable()->default('04:00:00');

                $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            });

        // Insérer la ligne de configuration par défaut
        DB::connection($this->connection)->table('tenant_settings')->insert([
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('tenant_settings');
    }
};
