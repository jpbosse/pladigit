<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Droits individuels par utilisateur — prioritaires sur toutes les règles de groupe.
 *
 * column_name NULL → droit sur toute la table.
 * denied = true    → bloque définitivement, même si une règle de groupe accorde l'accès.
 */
return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::connection('tenant')->create('datagrid_user_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('datagrid_table_id')
                ->constrained('datagrid_tables')
                ->cascadeOnDelete();
            $table->string('column_name', 100)->nullable();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('can_read')->default(false);
            $table->boolean('can_write')->default(false);
            $table->boolean('can_delete')->default(false);
            $table->boolean('can_export')->default(false);
            $table->boolean('denied')->default(false);
            $table->timestamps();

            $table->index('datagrid_table_id');
            $table->index(['datagrid_table_id', 'user_id']);
            $table->index('column_name');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('datagrid_user_permissions');
    }
};
