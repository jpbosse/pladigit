<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajout de la contrainte FK template_id sur ged_documents.
 *
 * Séparé de 000001 car ged_document_templates n'existait pas encore
 * au moment du premier ALTER TABLE.
 * Ordre d'exécution garanti : 000001 → 000002 → 000003.
 */
return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::connection('tenant')->table('ged_documents', function (Blueprint $table) {
            $table->foreign('template_id')
                ->references('id')
                ->on('ged_document_templates')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('ged_documents', function (Blueprint $table) {
            $table->dropForeign(['template_id']);
        });
    }
};
