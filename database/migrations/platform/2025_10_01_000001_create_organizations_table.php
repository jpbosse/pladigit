<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'mysql'; // Connexion pladigit_platform

    public function up(): void
    {
        Schema::connection($this->connection)
            ->create('organizations', function (Blueprint $table) {
                $table->id();
                $table->string('slug', 63)->unique()->comment('Identifiant URL unique');
                $table->string('name');
                $table->string('db_name', 63)->unique()->comment('Nom base MySQL dédiée');
                $table->enum('status', ['active', 'suspended', 'pending', 'archived'])
                    ->default('pending');
                $table->unsignedInteger('max_users')->default(50);
                $table->unsignedBigInteger('storage_quota_mb')->default(10240);
                $table->unsignedInteger('file_max_size_mb')->default(50);
                $table->string('logo_path', 500)->nullable();
                $table->string('primary_color', 7)->default('#1E3A5F');
                $table->string('login_bg_path', 500)->nullable();
                $table->string('smtp_host')->nullable();
                $table->unsignedSmallInteger('smtp_port')->nullable()->default(587);
                $table->string('smtp_user')->nullable();
                $table->text('smtp_password_enc')->nullable()->comment('Chiffré AES-256');
                $table->string('smtp_from_address')->nullable();
                $table->string('smtp_from_name')->nullable();
                $table->string('timezone', 63)->default('Europe/Paris');
                $table->string('locale', 10)->default('fr_FR');
                $table->enum('plan', ['communautaire', 'assistance', 'enterprise'])
                    ->default('communautaire');
                $table->date('trial_ends_at')->nullable();
                $table->date('contract_signed_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index('status');
            });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('organizations');
    }
};
