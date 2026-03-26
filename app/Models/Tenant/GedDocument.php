<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Document GED rattaché à un dossier.
 */
class GedDocument extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'tenant';

    protected $fillable = [
        'folder_id',
        'name',
        'disk_path',
        'mime_type',
        'size_bytes',
        'current_version',
        'created_by',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
        'current_version' => 'integer',
        'deleted_at' => 'datetime',
    ];

    // ── Relations ────────────────────────────────────────────

    /** @return BelongsTo<GedFolder, $this> */
    public function folder(): BelongsTo
    {
        return $this->belongsTo(GedFolder::class, 'folder_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ── Helpers ──────────────────────────────────────────────

    /** Taille lisible. Ex: "1,4 Mo", "320 Ko". */
    public function humanSize(): string
    {
        if ($this->size_bytes < 1024) {
            return $this->size_bytes.' o';
        }
        if ($this->size_bytes < 1024 * 1024) {
            return round($this->size_bytes / 1024, 1).' Ko';
        }

        return number_format($this->size_bytes / 1024 / 1024, 1, ',', ' ').' Mo';
    }

    /** Icône selon le type MIME. */
    public function icon(): string
    {
        return match (true) {
            str_contains($this->mime_type ?? '', 'pdf') => '📄',
            str_contains($this->mime_type ?? '', 'word') ||
            str_contains($this->mime_type ?? '', 'document') => '📝',
            str_contains($this->mime_type ?? '', 'excel') ||
            str_contains($this->mime_type ?? '', 'spreadsheet') => '📊',
            str_contains($this->mime_type ?? '', 'image/') => '🖼',
            str_contains($this->mime_type ?? '', 'video/') => '🎬',
            default => '📁',
        };
    }

    /**
     * True si le document peut être affiché inline (PDF ou image).
     * Utilisé par la route serve pour décider Content-Disposition.
     */
    public function isPreviewable(): bool
    {
        return str_contains($this->mime_type ?? '', 'pdf')
            || str_starts_with($this->mime_type ?? '', 'image/');
    }
}
