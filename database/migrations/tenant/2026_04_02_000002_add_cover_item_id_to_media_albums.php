<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::connection('tenant')->table('media_albums', function (Blueprint $table) {
            // ID du MediaItem choisi comme couverture.
            // null = couverture automatique (premier item de l'album).
            $table->unsignedBigInteger('cover_item_id')->nullable()->after('cover_path');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('media_albums', function (Blueprint $table) {
            $table->dropColumn('cover_item_id');
        });
    }
};
