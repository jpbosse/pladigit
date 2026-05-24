<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'mysql';

    public function up(): void
    {
        Schema::connection($this->connection)
            ->table('organizations', function (Blueprint $table) {
                // Type de certificat : 'none' | 'self_signed' | 'letsencrypt'
                $table->string('ssl_type', 20)->default('none')->after('locale')
                    ->comment('none | self_signed | letsencrypt');

                // Date de première demande SSL par l'admin tenant (déclencheur du délai J+7)
                $table->timestamp('ssl_requested_at')->nullable()->after('ssl_type')
                    ->comment('Première connexion admin tenant avec cert auto-signé');

                // Date de dernière tentative automatique Let\'s Encrypt
                $table->timestamp('ssl_last_attempt_at')->nullable()->after('ssl_requested_at');

                // Nombre de tentatives automatiques (pour éviter boucle infinie)
                $table->unsignedTinyInteger('ssl_attempt_count')->default(0)->after('ssl_last_attempt_at');
            });
    }

    public function down(): void
    {
        Schema::connection($this->connection)
            ->table('organizations', function (Blueprint $table) {
                $table->dropColumn(['ssl_type', 'ssl_requested_at', 'ssl_last_attempt_at', 'ssl_attempt_count']);
            });
    }
};
