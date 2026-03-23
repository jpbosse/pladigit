<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        // Supprimer d'abord les doublons résiduels avant d'ajouter la contrainte
        \Illuminate\Support\Facades\DB::connection('tenant')->statement('
            DELETE m1 FROM media_items m1
            INNER JOIN media_items m2
            WHERE m1.id > m2.id
            AND m1.file_path = m2.file_path
            AND m1.album_id = m2.album_id
            AND m1.deleted_at IS NULL
            AND m2.deleted_at IS NULL
        ');

        Schema::connection('tenant')->table('media_items', function (Blueprint $table) {
            // Empêche l'ingestion du même fichier deux fois dans le même album
            $table->unique(['album_id', 'file_path'], 'media_items_album_file_unique');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('media_items', function (Blueprint $table) {
            $table->dropUnique('media_items_album_file_unique');
        });
    }
};
