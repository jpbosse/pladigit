<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dossiers pour organiser les grilles dans la sidebar (ADR-039 §2.4).
 *
 * Structure hiérarchique autoréférentielle (même principe que departments).
 * parent_id NULL = dossier racine.
 * Sous-dossiers illimités en profondeur.
 * Une grille (datagrid_tables) ou une vue (datagrid_views) peut exister
 * hors dossier (folder_id NULL = niveau racine).
 */
return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        // Table des dossiers
        Schema::connection('tenant')->create('datagrid_folders', function (Blueprint $table) {
            $table->id();
            $table->string('label', 255);
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->foreign('parent_id')
                ->references('id')
                ->on('datagrid_folders')
                ->nullOnDelete();

            $table->index('parent_id');
            $table->index('sort_order');
        });

        // Ajout de folder_id sur datagrid_tables
        Schema::connection('tenant')->table('datagrid_tables', function (Blueprint $table) {
            $table->unsignedBigInteger('folder_id')->nullable()->after('description');
            $table->index('folder_id');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('datagrid_tables', function (Blueprint $table) {
            $table->dropIndex(['folder_id']);
            $table->dropColumn('folder_id');
        });

        Schema::connection('tenant')->dropIfExists('datagrid_folders');
    }
};
