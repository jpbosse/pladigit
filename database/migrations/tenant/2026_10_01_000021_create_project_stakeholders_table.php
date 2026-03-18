<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Parties prenantes d'un projet.
 *
 * Une partie prenante peut être :
 *   - Un utilisateur Pladigit (user_id renseigné)
 *   - Une personne externe (name + role renseignés, user_id null)
 *
 * Matrice adhésion × influence utilisée pour la carte des parties prenantes.
 */
return new class extends Migration
{
    public function connection(): string
    {
        return 'tenant';
    }

    public function up(): void
    {
        Schema::connection('tenant')->create('project_stakeholders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->onDelete('cascade');

            // Lien optionnel vers un user Pladigit
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');

            // Nom libre (obligatoire si user_id null, sinon déduit du user)
            $table->string('name', 255)->nullable();
            $table->string('role', 255)->comment('Ex: Commanditaire, DSI, Élu délégué, Agents de terrain');

            $table->enum('adhesion', ['champion', 'supporter', 'neutre', 'vigilant', 'resistant'])
                ->default('neutre');
            $table->enum('influence', ['high', 'medium', 'low'])->default('medium');

            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('project_id');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('project_stakeholders');
    }
};
