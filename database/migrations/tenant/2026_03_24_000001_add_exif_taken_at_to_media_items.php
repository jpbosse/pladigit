<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajoute la colonne exif_taken_at sur media_items.
 *
 * Pourquoi une colonne dédiée plutôt que d'extraire depuis exif_data (JSON) ?
 *   - ORDER BY sur une colonne datetime indexée = O(log n)
 *   - ORDER BY sur JSON = full scan, non indexable en MySQL 8
 *   - Facilite les filtres par plage de dates (photothèque avancée Phase 5)
 *
 * La colonne est backfillée via :
 *   php artisan media:refresh-exif --force
 * qui met désormais à jour exif_taken_at en même temps que exif_data.
 */
return new class extends Migration
{
    public function connection(): string
    {
        return 'tenant';
    }

    public function up(): void
    {
        Schema::connection('tenant')->table('media_items', function (Blueprint $table) {
            $table->dateTime('exif_taken_at')
                ->nullable()
                ->after('exif_data')
                ->comment('Date de prise de vue extraite des EXIF (DateTimeOriginal). Null si absente ou non image.');

            // Index pour le tri rapide — la majorité des requêtes sur cet album
            // triées par date de prise de vue passeront par cet index.
            $table->index(['album_id', 'exif_taken_at'], 'media_items_album_exif_taken_at_index');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('media_items', function (Blueprint $table) {
            $table->dropIndex('media_items_album_exif_taken_at_index');
            $table->dropColumn('exif_taken_at');
        });
    }
};
