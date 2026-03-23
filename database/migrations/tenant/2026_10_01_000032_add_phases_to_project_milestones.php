<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajout du support phases sur project_milestones.
 *
 * Un jalon sans parent_id = Phase (niveau supérieur, peut contenir des jalons).
 * Un jalon avec parent_id = Jalon enfant d'une phase.
 *
 * Nouveaux champs :
 *   parent_id  — FK auto-référencée nullable
 *   start_date — date de début (optionnelle, utile pour les phases)
 *   sort_order — ordre d'affichage au sein du projet
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
            $table->foreignId('parent_id')
                ->nullable()
                ->after('project_id')
                ->constrained('project_milestones')
                ->onDelete('cascade');

            $table->date('start_date')->nullable()->after('description');
            $table->unsignedSmallInteger('sort_order')->default(0)->after('color');

            $table->index('parent_id');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('project_milestones', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropIndex(['parent_id']);
            $table->dropColumn(['parent_id', 'start_date', 'sort_order']);
        });
    }
};
