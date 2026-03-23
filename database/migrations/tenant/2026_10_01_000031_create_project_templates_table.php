<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modèles de projets réutilisables.
 *
 * Un modèle capture : paramètres du projet + liste de tâches types
 * (sans dates absolues, avec des offsets en jours depuis le démarrage).
 */
return new class extends Migration
{
    public function connection(): string
    {
        return 'tenant';
    }

    public function up(): void
    {
        Schema::connection('tenant')->create('project_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->string('color', 7)->default('#1E3A5F');
            $table->json('task_templates')->nullable()
                ->comment('Array of {title, priority, offset_days, estimated_hours, description}');
            $table->json('milestone_templates')->nullable()
                ->comment('Array of {title, color, offset_days}');
            $table->foreignId('created_by')->constrained('users')->onDelete('restrict');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('project_templates');
    }
};
