<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::connection('tenant')->table('departments', function (Blueprint $table) {
            // Label libre : Pôle, Direction, Service, Bureau, Cellule...
            $table->string('label', 100)->nullable()->after('type');
            // Couleur optionnelle par nœud (hex)
            $table->string('color', 7)->nullable()->after('label');
            // Lien transversal (pas hiérarchique)
            $table->boolean('is_transversal')->default(false)->after('color');
            // Ordre d'affichage
            $table->unsignedInteger('sort_order')->default(0)->after('is_transversal');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('departments', function (Blueprint $table) {
            $table->dropColumn(['label', 'color', 'is_transversal', 'sort_order']);
        });
    }
};
