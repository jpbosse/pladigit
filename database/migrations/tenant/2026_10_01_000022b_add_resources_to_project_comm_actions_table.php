<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajout du champ resources_needed sur project_comm_actions.
 * Champ texte libre listant les ressources/matériels nécessaires
 * pour réaliser l'action (salle, VP, impression, etc.).
 */
return new class extends Migration
{
    public function connection(): string
    {
        return 'tenant';
    }

    public function up(): void
    {
        Schema::connection('tenant')->table('project_comm_actions', function (Blueprint $table) {
            $table->text('resources_needed')
                ->nullable()
                ->after('message')
                ->comment('Ressources nécessaires : salle, matériel, accès, prestataire...');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('project_comm_actions', function (Blueprint $table) {
            $table->dropColumn('resources_needed');
        });
    }
};
