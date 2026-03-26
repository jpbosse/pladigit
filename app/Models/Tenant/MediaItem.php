<?php

namespace App\Models\Tenant;

use App\Models\Concerns\Shareable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Fichier média appartenant à un album.
 *
 * Relations :
 *   $item->album()     → MediaAlbum parent
 *   $item->uploader()  → User qui a uploadé le fichier
 *
 * Helpers :
 *   $item->isImage()   → true si mime_type image/*
 *   $item->isVideo()   → true si mime_type video/*
 *   $item->isPdf()     → true si application/pdf
 *   $item->humanSize() → "2,4 Mo"
 *   $item->takenAt()   → date EXIF de prise de vue (ou null)
 */
class MediaItem extends Model
{
    use HasFactory, Shareable, SoftDeletes;

    protected $connection = 'tenant';

    protected $fillable = [
        'album_id',
        'uploaded_by',
        'file_name',
        'file_path',
        'thumb_path',
        'mime_type',
        'file_size_bytes',
        'width_px',
        'height_px',
        'exif_data',
        'caption',
        'sha256_hash',
        'is_duplicate',
        'processing_status',
        'exif_taken_at',
    ];

    protected $casts = [
        'exif_data' => 'array',
        'file_size_bytes' => 'integer',
        'width_px' => 'integer',
        'height_px' => 'integer',
        'is_duplicate' => 'boolean',
        'exif_taken_at' => 'datetime',
    ];

    // ── Relations ────────────────────────────────────────────

    /**
     * Album parent du média.
     *
     * @return BelongsTo<MediaAlbum, $this>
     */
    public function album(): BelongsTo
    {
        return $this->belongsTo(MediaAlbum::class, 'album_id');
    }

    /**
     * Tags manuels associés à ce média.
     *
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'media_item_tag');
    }

    /**
     * Utilisateur qui a uploadé le fichier.
     *
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    // ── Scopes ───────────────────────────────────────────────

    /**
     * Filtre les items visibles par un utilisateur.
     * Un item est visible si et seulement si son album l'est.
     * Délègue entièrement à MediaAlbum::scopeVisibleFor.
     */
    public function scopeVisibleFor($query, User $user)
    {
        return $query->whereHas('album', fn ($q) => $q->visibleFor($user));
    }

    /**
     * Vérifie si un utilisateur a un droit sur cet item.
     * Les droits sont portés par l'album parent — un item hérite
     * des droits de son album.
     *
     * @param  'can_view'|'can_download'|'can_edit'|'can_manage'  $ability
     */
    public function userCan(User $user, string $ability): bool
    {
        return $this->album->userCan($user, $ability);
    }

    /**
     * Exclut les miniatures générées automatiquement (stockées dans thumbs/).
     * Ces entrées ne doivent jamais apparaître dans la galerie.
     */
    public function scopeNotThumbs($query)
    {
        return $query->where('file_path', 'not like', '%/thumbs/%');
    }

    /**
     * Uniquement les images.
     */
    public function scopeImages($query)
    {
        return $query->where('mime_type', 'like', 'image/%');
    }

    /**
     * Uniquement les vidéos.
     */
    public function scopeVideos($query)
    {
        return $query->where('mime_type', 'like', 'video/%');
    }

    /**
     * Uniquement les PDFs.
     */
    public function scopePdfs($query)
    {
        return $query->where('mime_type', 'application/pdf');
    }

    // ── Helpers de type ──────────────────────────────────────

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    public function isVideo(): bool
    {
        return str_starts_with($this->mime_type, 'video/');
    }

    public function isPdf(): bool
    {
        return $this->mime_type === 'application/pdf';
    }

    /**
     * Taille lisible en français.
     * Ex : "2,4 Mo", "340 Ko", "8 o"
     */
    public function humanSize(): string
    {
        $bytes = $this->file_size_bytes;

        if ($bytes >= 1_048_576) {
            return number_format($bytes / 1_048_576, 1, ',', ' ').' Mo';
        }

        if ($bytes >= 1_024) {
            return number_format($bytes / 1_024, 0, ',', ' ').' Ko';
        }

        return $bytes.' o';
    }

    /**
     * Date de prise de vue extraite des données EXIF.
     * Retourne null si non disponible.
     */
    public function takenAt(): ?\DateTimeImmutable
    {
        $exif = $this->exif_data;

        if (empty($exif)) {
            return null;
        }

        // Clés EXIF standards pour la date
        $raw = $exif['DateTimeOriginal']
            ?? $exif['DateTime']
            ?? $exif['DateTimeDigitized']
            ?? null;

        if (! $raw) {
            return null;
        }

        try {
            return new \DateTimeImmutable($raw);
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * Coordonnées GPS depuis les données EXIF.
     * Retourne ['lat' => float, 'lng' => float] ou null.
     *
     * @return array{lat: float, lng: float}|null
     */
    public function gpsCoordinates(): ?array
    {
        $exif = $this->exif_data;

        if (empty($exif['GPSLatitude']) || empty($exif['GPSLongitude'])) {
            return null;
        }

        return [
            'lat' => $this->convertGpsDms($exif['GPSLatitude'], $exif['GPSLatitudeRef'] ?? 'N'),
            'lng' => $this->convertGpsDms($exif['GPSLongitude'], $exif['GPSLongitudeRef'] ?? 'E'),
        ];
    }

    // ── Helpers privés ───────────────────────────────────────

    /**
     * Convertit DMS (degrés/minutes/secondes) en décimal.
     *
     * @param  array<int, float>  $dms
     */
    private function convertGpsDms(array $dms, string $ref): float
    {
        $decimal = $dms[0] + ($dms[1] / 60) + ($dms[2] / 3600);

        return in_array($ref, ['S', 'W']) ? -$decimal : $decimal;
    }
}
