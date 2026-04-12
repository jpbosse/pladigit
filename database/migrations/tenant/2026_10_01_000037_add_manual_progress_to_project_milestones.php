<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajoute un avancement manuel (0-100) sur les nœuds de la hiérarchie projet.
 * Utilisé lorsque le nœud n'a pas de tâches rattachées pour calculer automatiquement
 * la progression. Priorité : si manual_progress est non-null ET aucune tâche n'est
 * attachée, on l'utilise ; sinon on calcule depuis les tâches.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->table('project_milestones', function (Blueprint $table) {
            $table->tinyInteger('manual_progress')->nullable()->after('sort_order')
                ->comment('Avancement manuel 0–100 utilisé quand aucune tâche n\'est rattachée');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('project_milestones', function (Blueprint $table) {
            $table->dropColumn('manual_progress');
        });
    }
};
