<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        // Supprimer tous les doublons (y compris soft-deleted) avant d'ajouter la contrainte
        \Illuminate\Support\Facades\DB::connection('tenant')->statement('
            DELETE m1 FROM media_items m1
            INNER JOIN media_items m2
            ON m1.file_path = m2.file_path
            AND m1.album_id = m2.album_id
            AND m1.id > m2.id
        ');

        Schema::connection('tenant')->table('media_items', function (Blueprint $table) {
            // Empêche l'ingestion du même fichier deux fois dans le même album
            // file_path peut être long — on utilise un index partiel sur 191 chars (limite MySQL utf8mb4)
            \Illuminate\Support\Facades\DB::connection('tenant')->statement(
                'ALTER TABLE media_items ADD UNIQUE INDEX media_items_album_file_unique (album_id, file_path(191))'
            );
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('media_items', function (Blueprint $table) {
            \Illuminate\Support\Facades\DB::connection('tenant')->statement(
                'ALTER TABLE media_items DROP INDEX media_items_album_file_unique'
            );
        });
    }
};
