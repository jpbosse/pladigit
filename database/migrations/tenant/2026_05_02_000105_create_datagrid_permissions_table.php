<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Droits par rôle ou département sur une grille (ou une colonne spécifique).
 *
 * column_name NULL  → droit s'applique à toute la table.
 * column_name SET   → droit s'applique uniquement à cette colonne (granularité RGPD).
 *
 * subject_type 'role'       → subject_role contient la valeur UserRole.
 * subject_type 'department' → subject_id pointe vers departments.
 *
 * denied = true : exception explicite — prime sur tout héritage hiérarchique.
 */
return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::connection('tenant')->create('datagrid_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('datagrid_table_id')
                ->constrained('datagrid_tables')
                ->cascadeOnDelete();
            $table->string('column_name', 100)->nullable();
            $table->string('subject_type', 20); // 'role' | 'department'
            $table->foreignId('subject_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('subject_role', 30)->nullable();
            $table->boolean('can_read')->default(false);
            $table->boolean('can_write')->default(false);
            $table->boolean('can_delete')->default(false);
            $table->boolean('can_export')->default(false);
            $table->boolean('denied')->default(false);
            $table->timestamps();

            $table->index('datagrid_table_id', 'dgp_table_id_idx');
            $table->index(['datagrid_table_id', 'subject_type', 'subject_id'], 'dgp_table_type_id_idx');
            $table->index(['datagrid_table_id', 'subject_type', 'subject_role'], 'dgp_table_type_role_idx');
            $table->index('column_name', 'dgp_column_name_idx');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('datagrid_permissions');
    }
};
