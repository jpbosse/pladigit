<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::connection('tenant')->table('media_items', function (Blueprint $table) {
            // Marque un item comme doublon d'un autre (même SHA-256 dans le tenant).
            // Mis à jour automatiquement à l'upload et à la suppression.
            $table->boolean('is_duplicate')->default(false)->after('sha256_hash');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('media_items', function (Blueprint $table) {
            $table->dropColumn('is_duplicate');
        });
    }
};
