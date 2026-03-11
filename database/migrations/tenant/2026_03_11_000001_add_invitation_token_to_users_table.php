<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajoute les colonnes d'invitation par email sur la table users.
 *
 * Stratégie token :
 *   - invitation_token      : hash SHA-256 du token brut (jamais le token en clair)
 *   - invitation_expires_at : timestamp d'expiration (72h après génération)
 *   - invitation_used_at    : timestamp d'utilisation — NULL = pas encore utilisé
 *                             Rempli immédiatement à la soumission du formulaire
 *                             d'activation → usage unique garanti.
 *
 * Supprime aussi le besoin de stocker un mot de passe en clair lors de la création.
 */
return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::connection($this->connection)
            ->table('users', function (Blueprint $table) {
                // Hash SHA-256 du token d'invitation (64 chars hex)
                // NULL = pas d'invitation en cours / compte déjà activé
                $table->string('invitation_token', 64)->nullable()->unique()
                    ->after('email')
                    ->comment('Hash SHA-256 du token envoyé par email');

                // Expiration 72h après génération
                $table->timestamp('invitation_expires_at')->nullable()
                    ->after('invitation_token');

                // Horodatage d'utilisation — usage unique
                $table->timestamp('invitation_used_at')->nullable()
                    ->after('invitation_expires_at')
                    ->comment('Rempli à la première utilisation → token invalidé');
            });
    }

    public function down(): void
    {
        Schema::connection($this->connection)
            ->table('users', function (Blueprint $table) {
                $table->dropColumn([
                    'invitation_token',
                    'invitation_expires_at',
                    'invitation_used_at',
                ]);
            });
    }
};
