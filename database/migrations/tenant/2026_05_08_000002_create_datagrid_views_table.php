<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vues métier DataGrid (ADR-040 §4).
 *
 * Une vue métier regroupe plusieurs tables sous un nom unique affiché
 * à l'utilisateur. Il ne voit jamais les tables techniques sous-jacentes.
 *
 * Exemple : "Élus et mandats" regroupe les tables elus, mandats, commissions.
 *
 * primary_table_id : FK datagrid_tables — table principale de la vue
 * folder_id        : FK datagrid_folders — rangement dans la sidebar
 * config           : JSON — configuration des onglets popup, colonnes
 *                    calculées à afficher, tables liées incluses
 * is_active        : false = visible Super Admin uniquement
 */
return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::connection('tenant')->create('datagrid_views', function (Blueprint $table) {
            $table->id();
            $table->string('label', 255);
            $table->text('description')->nullable();
            $table->unsignedBigInteger('primary_table_id');
            $table->unsignedBigInteger('folder_id')->nullable();
            $table->json('config')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('primary_table_id')
                ->references('id')
                ->on('datagrid_tables')
                ->cascadeOnDelete();

            $table->index('primary_table_id');
            $table->index('folder_id');
            $table->index('is_active');
            $table->index('sort_order');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('datagrid_views');
    }
};
