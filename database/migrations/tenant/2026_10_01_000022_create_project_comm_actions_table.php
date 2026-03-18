<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Actions du plan de communication d'un projet.
 */
return new class extends Migration
{
    public function connection(): string
    {
        return 'tenant';
    }

    public function up(): void
    {
        Schema::connection('tenant')->create('project_comm_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->onDelete('cascade');
            $table->string('title', 255);
            $table->string('target_audience', 255)->comment('Public cible : agents, élus, usagers...');
            $table->enum('channel', ['email', 'reunion', 'affichage', 'courrier', 'intranet', 'autre'])
                ->default('reunion');
            $table->text('message')->nullable()->comment('Contenu / points clés du message');
            $table->date('planned_at');
            $table->date('done_at')->nullable();
            $table->foreignId('responsible_id')->nullable()->constrained('users')->onDelete('set null');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['project_id', 'planned_at']);
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('project_comm_actions');
    }
};
