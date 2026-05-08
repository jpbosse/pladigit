<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Préférences utilisateur par grille (ADR-039 §2.4).
 *
 * Persiste les choix de chaque utilisateur entre les sessions :
 * - colonnes visibles (sélecteur de colonnes)
 * - nombre de lignes par page
 * - dernière vue sauvegardée active
 *
 * key    : 'visible_columns' | 'per_page' | 'active_view'
 * value  : JSON
 *
 * table_id NULL : préférences globales (ex: per_page toutes grilles)
 */
return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::connection('tenant')->create('datagrid_user_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('table_id')->nullable();
            $table->string('key', 64);
            $table->json('value');
            $table->timestamps();

            $table->unique(['user_id', 'table_id', 'key']);
            $table->index(['user_id', 'table_id']);
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('datagrid_user_preferences');
    }
};
