<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Colonnes d'une grille DataGrid.
 *
 * is_rgpd_sensitive : marque la colonne comme donnée personnelle —
 *                     lecture et export seront tracés dans datagrid_audit_log.
 * is_role_column    : à l'import Excel, cette colonne sera transposée en lignes
 *                     roles_titres plutôt qu'en colonne directe de personnes.
 * options           : JSON des valeurs possibles pour type=select.
 */
return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::connection('tenant')->create('datagrid_columns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('datagrid_table_id')
                ->constrained('datagrid_tables')
                ->cascadeOnDelete();
            $table->string('name', 100); // nom technique (colonne MySQL)
            $table->string('label', 255); // libellé affiché
            $table->string('type', 30); // DatagridColumnType enum
            $table->unsignedSmallInteger('length')->nullable();
            $table->boolean('required')->default(false);
            $table->boolean('visible_by_default')->default(true);
            $table->boolean('is_rgpd_sensitive')->default(false);
            $table->boolean('is_role_column')->default(false);
            $table->string('default_value', 500)->nullable();
            $table->json('options')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['datagrid_table_id', 'name']);
            $table->index('datagrid_table_id');
            $table->index('sort_order');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('datagrid_columns');
    }
};
