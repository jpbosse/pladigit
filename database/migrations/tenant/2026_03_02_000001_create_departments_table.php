<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Crée la table departments (directions et services) et la table pivot
 * user_department pour gérer l'appartenance multi-tenant.
 *
 * Hiérarchie :
 *   Direction (type='direction', parent_id=null)
 *     └── Service (type='service', parent_id=direction.id)
 *           └── Agents et responsables via user_department
 *
 * Un utilisateur peut appartenir à plusieurs services/directions.
 * Un responsable est identifié par is_manager=true dans la table pivot.
 */
return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::connection('tenant')->create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('type', ['direction', 'service']);
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('departments')
                ->nullOnDelete();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['type', 'parent_id']);
        });

        Schema::connection('tenant')->create('user_department', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('department_id')->constrained('departments')->cascadeOnDelete();
            $table->boolean('is_manager')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'department_id']);
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('user_department');
        Schema::connection('tenant')->dropIfExists('departments');
    }
};
