<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajout des champs nécessaires au module Gestion de Projet sur la table tasks.
 *
 * - start_date   : date de début — nécessaire pour le Gantt (ADR-009)
 * - milestone_id : rattachement optionnel à un jalon du projet
 */
return new class extends Migration
{
    public function connection(): string
    {
        return 'tenant';
    }

    public function up(): void
    {
        Schema::connection('tenant')->table('tasks', function (Blueprint $table) {
            // Date de début pour le Gantt — nullable, les tâches sans start_date
            // n'apparaissent pas dans la vue Gantt (affichage "date manquante")
            $table->date('start_date')->nullable()->after('due_date');

            // Rattachement optionnel à un jalon — FK ajoutée après création
            // de project_milestones (migration 000002)
            $table->unsignedBigInteger('milestone_id')->nullable()->after('start_date');

            // Index pour les requêtes Gantt (tri/filtre par start_date)
            $table->index('start_date');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('tasks', function (Blueprint $table) {
            $table->dropIndex(['start_date']);
            $table->dropColumn(['start_date', 'milestone_id']);
        });
    }
};
