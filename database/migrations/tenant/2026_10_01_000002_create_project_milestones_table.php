<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Création de la table project_milestones (jalons de projet).
 *
 * Un jalon représente un point de contrôle majeur dans le projet.
 * Il est affiché comme un losange sur la vue Gantt.
 * Les tâches peuvent y être rattachées via tasks.milestone_id.
 *
 * Après création de cette table, on ajoute la FK sur tasks.milestone_id.
 */
return new class extends Migration
{
    public function connection(): string
    {
        return 'tenant';
    }

    public function up(): void
    {
        Schema::connection('tenant')->create('project_milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')
                ->constrained('projects')
                ->onDelete('cascade');
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->date('due_date');
            // Renseigné quand le jalon est effectivement atteint
            $table->timestamp('reached_at')->nullable();
            // Couleur d'affichage sur le Gantt — orange par défaut
            $table->string('color', 7)->default('#EA580C');
            $table->timestamps();
            $table->softDeletes();

            $table->index('project_id');
            $table->index('due_date');
        });

        // Ajout de la FK sur tasks.milestone_id maintenant que la table existe
        Schema::connection('tenant')->table('tasks', function (Blueprint $table) {
            $table->foreign('milestone_id')
                ->references('id')
                ->on('project_milestones')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        // Supprimer la FK avant de dropper la table
        Schema::connection('tenant')->table('tasks', function (Blueprint $table) {
            $table->dropForeign(['milestone_id']);
        });

        Schema::connection('tenant')->dropIfExists('project_milestones');
    }
};
