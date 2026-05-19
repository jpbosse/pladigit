<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rend datagrid_table_id nullable dans datagrid_audit_log.
 *
 * Nécessaire pour conserver les logs de suppression de grille (STRUCTURE_DROP)
 * après le forceDelete() — le cascadeOnDelete effaçait le log sinon.
 */
return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::connection('tenant')->table('datagrid_audit_log', function (Blueprint $table) {
            $table->dropForeign(['datagrid_table_id']);
            $table->foreignId('datagrid_table_id')
                ->nullable()
                ->change();
            $table->foreign('datagrid_table_id')
                ->references('id')
                ->on('datagrid_tables')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('datagrid_audit_log', function (Blueprint $table) {
            $table->dropForeign(['datagrid_table_id']);
            $table->foreignId('datagrid_table_id')
                ->nullable(false)
                ->change();
            $table->foreign('datagrid_table_id')
                ->references('id')
                ->on('datagrid_tables')
                ->cascadeOnDelete();
        });
    }
};
