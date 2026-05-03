<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Journal d'audit RGPD — immuable, jamais effaçable (pas de softDeletes).
 *
 * Toute action sur une grille has_rgpd=true est enregistrée ici :
 * lecture, modification, suppression, export, import.
 *
 * row_id      : identifiant de la ligne concernée dans la table sous-jacente.
 * column_name : colonne concernée (NULL = action sur la ligne entière).
 * old_value / new_value : diff pour les modifications.
 */
return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::connection('tenant')->create('datagrid_audit_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('datagrid_table_id')
                ->constrained('datagrid_tables')
                ->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('action', 20); // DatagridAuditAction enum
            $table->unsignedBigInteger('row_id')->nullable();
            $table->string('column_name', 100)->nullable();
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->string('ip_address', 45);
            $table->timestamps();

            $table->index('datagrid_table_id');
            $table->index(['datagrid_table_id', 'user_id']);
            $table->index(['datagrid_table_id', 'row_id']);
            $table->index('action');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('datagrid_audit_log');
    }
};
