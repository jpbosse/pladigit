<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registre des risques et freins d'un projet.
 * + Observations des élus sur le tableau de bord.
 */
return new class extends Migration
{
    public function connection(): string
    {
        return 'tenant';
    }

    public function up(): void
    {
        // ── Risques & freins ──────────────────────────────────────────────
        Schema::connection('tenant')->create('project_risks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->onDelete('cascade');
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->enum('category', ['humain', 'technique', 'budget', 'planning', 'juridique', 'autre'])
                ->default('autre');
            $table->enum('probability', ['low', 'medium', 'high'])->default('medium');
            $table->enum('impact', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->enum('status', ['identified', 'monitored', 'mitigated', 'closed'])
                ->default('identified');
            $table->text('mitigation_plan')->nullable();
            $table->foreignId('owner_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();
            $table->index('project_id');
            $table->index(['project_id', 'status']);
        });

        // ── Observations élus (tableau de bord) ──────────────────────────
        Schema::connection('tenant')->create('project_observations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->text('body');
            $table->enum('type', ['observation', 'question', 'validation', 'alerte'])->default('observation');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['project_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('project_observations');
        Schema::connection('tenant')->dropIfExists('project_risks');
    }
};
