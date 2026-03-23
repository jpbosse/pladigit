<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Création de la table task_dependencies.
 *
 * Modélise les dépendances entre tâches : une tâche ne peut démarrer
 * que lorsque ses dépendances (tâches bloquantes) sont terminées.
 *
 * Règles métier :
 *   - Une tâche ne peut pas dépendre d'elle-même (CHECK task_id != depends_on_task_id)
 *   - Pas de doublon de dépendance (UNIQUE task_id + depends_on_task_id)
 *   - La détection de cycles circulaires est faite au niveau applicatif
 *     dans TaskController avant l'INSERT (pas de contrainte BDD)
 *
 * Affichage Gantt : flèche SVG du bord droit de depends_on_task vers
 * le bord gauche de task (ADR-009).
 */
return new class extends Migration
{
    public function connection(): string
    {
        return 'tenant';
    }

    public function up(): void
    {
        Schema::connection('tenant')->create('task_dependencies', function (Blueprint $table) {
            $table->id();

            // Tâche bloquée (celle qui attend)
            $table->foreignId('task_id')
                ->constrained('tasks')
                ->onDelete('cascade');

            // Tâche bloquante (celle qui doit être terminée en premier)
            $table->foreignId('depends_on_task_id')
                ->constrained('tasks')
                ->onDelete('cascade');

            $table->timestamps();

            // Pas de doublon : une tâche ne peut dépendre qu'une fois d'une autre
            $table->unique(['task_id', 'depends_on_task_id']);

            $table->index('task_id');
            $table->index('depends_on_task_id');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('task_dependencies');
    }
};
