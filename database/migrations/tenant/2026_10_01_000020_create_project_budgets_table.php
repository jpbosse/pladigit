<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Budget d'un projet — Invest. / Fonctionnement, multi-années.
 *
 * Une ligne = une enveloppe budgétaire.
 * Synthèse élus = agrégat par type + année.
 * Détail services = toutes les lignes.
 */
return new class extends Migration
{
    public function connection(): string
    {
        return 'tenant';
    }

    public function up(): void
    {
        Schema::connection('tenant')->create('project_budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->onDelete('cascade');
            $table->enum('type', ['invest', 'fonct'])->comment('invest = investissement, fonct = fonctionnement');
            $table->string('label', 255)->comment('Ex: Prestations externes, Formation agents');
            $table->year('year')->default(now()->year);
            $table->decimal('amount_planned', 10, 2)->default(0);
            $table->decimal('amount_committed', 10, 2)->default(0)->comment('Engagé / commandé');
            $table->decimal('amount_paid', 10, 2)->default(0)->comment('Mandaté / payé');
            $table->string('cofinancer', 255)->nullable()->comment('DETR, Région, FEDER...');
            $table->decimal('cofinancing_rate', 5, 2)->nullable()->comment('Taux de cofinancement en %');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->onDelete('restrict');
            $table->timestamps();
            $table->softDeletes();
            $table->index('project_id');
            $table->index(['project_id', 'type', 'year']);
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('project_budgets');
    }
};
