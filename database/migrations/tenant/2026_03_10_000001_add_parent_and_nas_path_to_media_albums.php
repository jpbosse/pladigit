<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ajoute la hiérarchie d'albums (sous-albums) et le chemin NAS.
 *
 * parent_id   → auto-référentiel, permet d'imbriquer les albums
 *               comme des dossiers (Album > Sous-album > Sous-sous-album...)
 *               Null = album racine.
 *               nullOnDelete : si le parent est supprimé, les enfants
 *               deviennent des albums racine (pas de suppression en cascade).
 *
 * nas_path    → chemin absolu du dossier correspondant sur le NAS
 *               (ex: /photos/evenements/fete-commune-2025).
 *               Null pour les albums créés manuellement sans lien NAS.
 *               Index partiel (255 chars) pour respecter la limite MySQL utf8mb4.
 */
return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::connection('tenant')->table('media_albums', function (Blueprint $table) {
            $table->foreignId('parent_id')
                ->nullable()
                ->after('id')
                ->constrained('media_albums')
                ->nullOnDelete();

            $table->string('nas_path', 1000)
                ->nullable()
                ->after('cover_path')
                ->comment('Chemin absolu du dossier NAS correspondant à cet album');

            $table->index('parent_id');
        });

        // Index partiel sur nas_path — limite MySQL utf8mb4 = 3072 bytes max
        // varchar(1000) × 4 bytes = 4000 bytes → on indexe les 255 premiers chars
        DB::connection('tenant')->statement(
            'ALTER TABLE media_albums ADD INDEX media_albums_nas_path_index (nas_path(255))'
        );
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('media_albums', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropIndex(['parent_id']);
            $table->dropIndex('media_albums_nas_path_index');
            $table->dropColumn(['parent_id', 'nas_path']);
        });
    }
};
