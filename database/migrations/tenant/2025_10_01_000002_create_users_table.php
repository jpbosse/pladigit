<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant'; // Connexion base dédiée de l'organisation

    public function up(): void
    {
        Schema::connection($this->connection)
            ->create('users', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('email')->unique();
                $table->string('password_hash')->nullable()
                    ->comment('NULL si authentification LDAP uniquement');
                $table->enum('role', [
                  'admin', 'president', 'dgs',
                  'resp_direction', 'resp_service', 'user',
                ])->default('user');
                $table->enum('status', ['active', 'inactive', 'locked'])->default('active');

                // LDAP (Phase 2)
                $table->string('ldap_dn', 500)->nullable()
                    ->comment('Distinguished Name LDAP');
                $table->timestamp('ldap_synced_at')->nullable();

                // 2FA (Phase 2)
                $table->text('totp_secret_enc')->nullable()
                    ->comment('Secret TOTP chiffré AES-256');
                $table->boolean('totp_enabled')->default(false);
                $table->text('totp_backup_code_enc')->nullable();

                // Profil
                $table->string('avatar_path', 500)->nullable();
                $table->string('department')->nullable();

                // Sécurité session
                $table->timestamp('last_login_at')->nullable();
                $table->string('last_login_ip', 45)->nullable();
                $table->unsignedTinyInteger('login_attempts')->default(0);
                $table->timestamp('locked_until')->nullable();
                $table->timestamp('password_changed_at')->nullable();
                $table->json('password_history')->nullable()
                    ->comment('5 derniers hashes');
                $table->boolean('force_pwd_change')->default(false);
                $table->rememberToken();
                $table->timestamp('email_verified_at')->nullable();
                $table->foreignId('created_by')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index('role');
                $table->index('status');
            });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('users');
    }
};
