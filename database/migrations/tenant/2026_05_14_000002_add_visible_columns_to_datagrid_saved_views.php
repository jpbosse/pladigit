<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajoute la persistance des colonnes visibles dans les vues sauvegardées.
 *
 * visible_columns : JSON — tableau d'IDs de DatagridColumn visibles dans cette vue.
 *                   NULL = utiliser la visibilité par défaut de la grille.
 * sort_column     : colonne de tri active lors de la sauvegarde (nom MySQL).
 * sort_direction  : sens du tri ('asc' ou 'desc').
 */
return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::connection('tenant')->table('datagrid_saved_views', function (Blueprint $table) {
            $table->json('visible_columns')->nullable()->after('filters')
                ->comment('IDs des colonnes visibles. NULL = visibilité par défaut.');
            $table->string('sort_column', 100)->nullable()->after('visible_columns')
                ->comment('Colonne de tri active au moment de la sauvegarde.');
            $table->string('sort_direction', 4)->nullable()->after('sort_column')
                ->comment('Sens du tri : asc ou desc.');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('datagrid_saved_views', function (Blueprint $table) {
            $table->dropColumn(['visible_columns', 'sort_column', 'sort_direction']);
        });
    }
};
