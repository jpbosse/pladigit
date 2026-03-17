<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function connection()
    {
        return 'tenant';
    }

    /**
     * Ajoute le statut 'draft' (brouillon) aux projets.
     * Les brouillons ne sont visibles que par leur créateur.
     */
    public function up(): void
    {
        // Modifier l'enum status pour ajouter 'draft'
        Schema::connection('tenant')->table('projects', function (Blueprint $table) {
            $table->enum('status', ['draft', 'active', 'on_hold', 'completed', 'archived'])
                ->default('active')
                ->change();
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('projects', function (Blueprint $table) {
            $table->enum('status', ['active', 'on_hold', 'completed', 'archived'])
                ->default('active')
                ->change();
        });
    }
};
