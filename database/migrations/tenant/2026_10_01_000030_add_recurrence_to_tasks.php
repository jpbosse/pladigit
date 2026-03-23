<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Récurrence des tâches.
 *
 * recurrence_type  : null (pas de récurrence) | daily | weekly | monthly
 * recurrence_every : tous les N jours/semaines/mois (défaut : 1)
 * recurrence_ends  : null (pas de fin) | date de fin
 * recurrence_parent_id : ID de la tâche originale (pour les occurrences générées)
 */
return new class extends Migration
{
    public function connection(): string
    {
        return 'tenant';
    }

    public function up(): void
    {
        Schema::connection('tenant')->table('tasks', function (Blueprint $table) {
            $table->enum('recurrence_type', ['daily', 'weekly', 'monthly'])->nullable()->after('sort_order');
            $table->unsignedTinyInteger('recurrence_every')->default(1)->after('recurrence_type');
            $table->date('recurrence_ends')->nullable()->after('recurrence_every');
            $table->foreignId('recurrence_parent_id')->nullable()->constrained('tasks')->onDelete('cascade')->after('recurrence_ends');
            $table->index('recurrence_parent_id');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('tasks', function (Blueprint $table) {
            $table->dropForeign(['recurrence_parent_id']);
            $table->dropColumn(['recurrence_type', 'recurrence_every', 'recurrence_ends', 'recurrence_parent_id']);
        });
    }
};
