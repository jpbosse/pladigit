<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registre des traitements RGPD liés aux grilles DataGrid.
 *
 * Chaque import ou traitement de masse doit être consigné ici :
 * base légale appliquée, colonnes sensibles concernées, décision opérateur.
 *
 * file_hash         : empreinte SHA-256 du fichier importé (traçabilité source).
 * sensitive_columns : JSON listant les colonnes sensibles impliquées dans l'opération.
 * operator_decision : décision ou commentaire de l'opérateur DPO.
 */
return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::connection('tenant')->create('datagrid_rgpd_registry', function (Blueprint $table) {
            $table->id();
            $table->foreignId('datagrid_table_id')
                ->constrained('datagrid_tables')
                ->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('event_type', 100);
            $table->string('source', 255)->nullable();
            $table->string('legal_basis', 100)->nullable();
            $table->unsignedInteger('record_count')->nullable();
            $table->json('sensitive_columns')->nullable();
            $table->text('operator_decision')->nullable();
            $table->string('file_hash', 64)->nullable(); // SHA-256
            $table->timestamps();

            $table->index('datagrid_table_id');
            $table->index(['datagrid_table_id', 'user_id']);
            $table->index('event_type');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('datagrid_rgpd_registry');
    }
};
