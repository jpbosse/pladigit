<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajout du flag is_private sur les projets.
 *
 * Un projet privé est visible uniquement par ses membres explicites.
 * La couche hiérarchique automatique (ADR-011) est ignorée pour ces projets.
 * Admin / Président / DGS voient toujours tout.
 */
return new class extends Migration
{
    public function connection(): string
    {
        return 'tenant';
    }

    public function up(): void
    {
        Schema::connection('tenant')->table('projects', function (Blueprint $table) {
            $table->boolean('is_private')->default(false)->after('color');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('projects', function (Blueprint $table) {
            $table->dropColumn('is_private');
        });
    }
};
