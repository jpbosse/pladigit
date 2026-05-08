<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajout des colonnes pour les relations entre tables (ADR-040) et les
 * colonnes calculées/agrégées sur datagrid_columns.
 *
 * Migration strictement additive — aucune colonne existante n'est modifiée.
 *
 * relation_table_id    : FK vers datagrid_tables (table cible de la relation)
 * relation_display_col : colonne à afficher dans la table cible
 * relation_type        : 'n1' | '1n' | 'nn'
 * relation_pivot_table : nom MySQL de la table de liaison (N-N uniquement)
 * relation_fk_source   : colonne FK côté table source
 * relation_fk_target   : colonne FK côté table cible
 * computed_sql         : sous-requête SQL calculée (lecture seule, SELECT uniquement)
 * computed_readonly    : true si la colonne est calculée et non éditable
 * aggregated_separator : séparateur pour les colonnes agrégées (ex: ', ')
 * fuzzy_search         : active la recherche floue (Levenshtein) sur cette colonne
 */
return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::connection('tenant')->table('datagrid_columns', function (Blueprint $table) {
            // Relations
            $table->unsignedBigInteger('relation_table_id')->nullable()->after('options');
            $table->string('relation_display_col', 64)->nullable()->after('relation_table_id');
            $table->string('relation_type', 10)->nullable()->after('relation_display_col'); // 'n1' | '1n' | 'nn'
            $table->string('relation_pivot_table', 128)->nullable()->after('relation_type');
            $table->string('relation_fk_source', 64)->nullable()->after('relation_pivot_table');
            $table->string('relation_fk_target', 64)->nullable()->after('relation_fk_source');

            // Colonnes calculées et agrégées
            $table->text('computed_sql')->nullable()->after('relation_fk_target');
            $table->boolean('computed_readonly')->default(false)->after('computed_sql');
            $table->string('aggregated_separator', 10)->nullable()->after('computed_readonly');

            // Recherche floue
            $table->boolean('fuzzy_search')->default(false)->after('aggregated_separator');

            // Index sur la relation pour les jointures
            $table->index('relation_table_id');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('datagrid_columns', function (Blueprint $table) {
            $table->dropIndex(['relation_table_id']);
            $table->dropColumn([
                'relation_table_id',
                'relation_display_col',
                'relation_type',
                'relation_pivot_table',
                'relation_fk_source',
                'relation_fk_target',
                'computed_sql',
                'computed_readonly',
                'aggregated_separator',
                'fuzzy_search',
            ]);
        });
    }
};
