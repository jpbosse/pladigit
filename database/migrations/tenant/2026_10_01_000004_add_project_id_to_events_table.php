<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajout de project_id sur la table events.
 *
 * Permet de lier un événement à un projet spécifique.
 * Un événement sans project_id appartient à l'agenda global du tenant.
 * Un événement avec project_id apparaît dans l'onglet Agenda du projet.
 *
 * L'agenda global (/agenda) agrège tous les événements visibles
 * selon le rôle de l'utilisateur, filtrables par projet (?project_id=X).
 */
return new class extends Migration
{
    public function connection(): string
    {
        return 'tenant';
    }

    public function up(): void
    {
        Schema::connection('tenant')->table('events', function (Blueprint $table) {
            $table->unsignedBigInteger('project_id')
                ->nullable()
                ->after('created_by');

            $table->foreign('project_id')
                ->references('id')
                ->on('projects')
                ->onDelete('set null');

            // Index pour filtrer l'agenda par projet
            $table->index('project_id');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('events', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
            $table->dropIndex(['project_id']);
            $table->dropColumn('project_id');
        });
    }
};
