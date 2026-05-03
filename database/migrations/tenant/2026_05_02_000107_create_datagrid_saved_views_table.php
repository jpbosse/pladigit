<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Filtres sauvegardés par utilisateur ou département.
 *
 * user_id NULL + department_id SET → vue partagée par le département.
 * user_id SET + department_id NULL → vue personnelle.
 * is_default = true                → vue activée automatiquement à l'ouverture.
 */
return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::connection('tenant')->create('datagrid_saved_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('datagrid_table_id')
                ->constrained('datagrid_tables')
                ->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('name', 255);
            $table->json('filters');
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index('datagrid_table_id');
            $table->index(['datagrid_table_id', 'user_id']);
            $table->index(['datagrid_table_id', 'department_id']);
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('datagrid_saved_views');
    }
};
