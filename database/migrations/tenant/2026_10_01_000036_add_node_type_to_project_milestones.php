<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajout du champ node_type sur project_milestones.
 *
 * Remplace la distinction implicite phase/jalon (basée sur parent_id null) par
 * un label libre choisi par l'utilisateur (Phase, Étape, Jalon, Livrable, Sprint…).
 * La profondeur maximale est limitée à 4 niveaux (depth 0-3) dans le contrôleur.
 */
return new class extends Migration
{
    public function connection(): string
    {
        return 'tenant';
    }

    public function up(): void
    {
        Schema::connection('tenant')->table('project_milestones', function (Blueprint $table) {
            $table->string('node_type', 50)->nullable()->after('parent_id');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('project_milestones', function (Blueprint $table) {
            $table->dropColumn('node_type');
        });
    }
};
