<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Pièce jointe (fichier ou lien) sur un projet, une tâche ou un jalon.
 *
 * @property int $id
 * @property string $documentable_type
 * @property int $documentable_id
 * @property string $type file|link
 * @property string $driver local|nas
 * @property string $name
 * @property string $path
 * @property string|null $mime_type
 * @property int $size_bytes
 * @property string|null $description
 * @property int $uploaded_by
 */
class ProjectDocument extends Model
{
    use SoftDeletes;

    protected $connection = 'tenant';

    protected $table = 'project_documents';

    protected $fillable = [
        'documentable_type',
        'documentable_id',
        'type',
        'driver',
        'name',
        'path',
        'mime_type',
        'size_bytes',
        'description',
        'uploaded_by',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
    ];

    // ── Relations ─────────────────────────────────────────────────────

    /** @return MorphTo<Model, $this> */
    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    // ── Helpers ───────────────────────────────────────────────────────

    /** Taille lisible (ex: 2.4 Mo). */
    public function humanSize(): string
    {
        if ($this->size_bytes < 1024) {
            return $this->size_bytes.' o';
        }
        if ($this->size_bytes < 1024 * 1024) {
            return round($this->size_bytes / 1024, 1).' Ko';
        }

        return round($this->size_bytes / 1024 / 1024, 1).' Mo';
    }

    /** Icône selon le type MIME. */
    public function icon(): string
    {
        if ($this->type === 'link') {
            return '🔗';
        }

        return match (true) {
            str_contains($this->mime_type ?? '', 'pdf') => '📄',
            str_contains($this->mime_type ?? '', 'word') ||
            str_contains($this->mime_type ?? '', 'document') => '📝',
            str_contains($this->mime_type ?? '', 'sheet') ||
            str_contains($this->mime_type ?? '', 'excel') => '📊',
            str_contains($this->mime_type ?? '', 'presentation') ||
            str_contains($this->mime_type ?? '', 'powerpoint') => '📋',
            str_contains($this->mime_type ?? '', 'image') => '🖼️',
            str_contains($this->mime_type ?? '', 'zip') ||
            str_contains($this->mime_type ?? '', 'archive') => '🗜️',
            default => '📎',
        };
    }

    /** Extensions autorisées pour l'upload. */
    public static function allowedExtensions(): array
    {
        return ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
            'odt', 'ods', 'odp', 'txt', 'csv', 'zip', 'png', 'jpg', 'jpeg'];
    }

    /** Taille max upload en Mo. */
    public static function maxSizeMb(): int
    {
        return 50;
    }
}
