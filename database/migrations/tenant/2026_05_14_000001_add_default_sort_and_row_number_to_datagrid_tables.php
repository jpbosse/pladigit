<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajoute les paramètres de présentation au niveau de la grille.
 *
 * default_sort_column    : colonne triée par défaut (nom MySQL). NULL = ordre naturel.
 * default_sort_direction : sens du tri par défaut ('asc' ou 'desc'). Défaut : 'asc'.
 * show_row_number        : affiche une colonne # en tête de grille (utile pour les
 *                          registres officiels). Défaut : false.
 *
 * Ces trois colonnes sont optionnelles et n'affectent pas les données existantes.
 */
return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::connection('tenant')->table('datagrid_tables', function (Blueprint $table) {
            $table->string('default_sort_column', 100)->nullable()->after('has_rgpd')
                ->comment('Colonne MySQL triée par défaut. NULL = ordre naturel.');
            $table->string('default_sort_direction', 4)->default('asc')->after('default_sort_column')
                ->comment('Sens du tri par défaut : asc ou desc.');
            $table->boolean('show_row_number')->default(false)->after('default_sort_direction')
                ->comment('Affiche une colonne # numérotant les lignes de la grille.');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('datagrid_tables', function (Blueprint $table) {
            $table->dropColumn(['default_sort_column', 'default_sort_direction', 'show_row_number']);
        });
    }
};
