<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::connection('tenant')->table('tenant_settings', function (Blueprint $table) {
            // Seuil (en Mo) au-delà duquel serve() bascule sur stream() pour les images.
            // 0 = toujours readFile() (comportement legacy). Défaut : 10 Mo.
            $table->unsignedSmallInteger('media_stream_threshold_mb')->default(10);
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('tenant_settings', function (Blueprint $table) {
            $table->dropColumn('media_stream_threshold_mb');
        });
    }
};
