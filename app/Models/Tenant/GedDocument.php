<?php

namespace App\Models\Tenant;

use App\Enums\GedDocumentType;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

/**
 * Document GED rattaché à un dossier.
 *
 * Depuis le Niveau 2 (ADR-038), un document peut être typé (délibération,
 * arrêté, compte-rendu…) avec référence normalisée, date officielle et
 * service émetteur.
 *
 * @property string|null $document_type
 * @property string|null $reference Ex: DEL-2026-042
 * @property Carbon|null $document_date
 * @property string|null $object
 * @property int|null $department_id
 * @property int|null $template_id
 * @property array|null $tags
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
        // Niveau 2 — classification documentaire (ADR-038)
        'document_type',
        'reference',
        'document_date',
        'object',
        'department_id',
        'template_id',
        'tags',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
        'current_version' => 'integer',
        'deleted_at' => 'datetime',
        'document_date' => 'date',
        'tags' => 'array',
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

    /** @return HasMany<GedDocumentVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(GedDocumentVersion::class, 'document_id')->orderByDesc('version_number');
    }

    /** @return HasMany<ProjectGedLink, $this> */
    public function projectLinks(): HasMany
    {
        return $this->hasMany(ProjectGedLink::class, 'ged_document_id');
    }

    // ── Relations Niveau 2 ───────────────────────────────────

    /** @return BelongsTo<Department, $this> */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    /** @return BelongsTo<GedDocumentTemplate, $this> */
    public function template(): BelongsTo
    {
        return $this->belongsTo(GedDocumentTemplate::class, 'template_id');
    }

    /**
     * Projets liés à ce document GED.
     *
     * @return Collection<int, Project>
     */
    public function linkedProjects(): Collection
    {
        return $this->projectLinks()
            ->where('documentable_type', Project::class)
            ->with('documentable')
            ->get()
            ->pluck('documentable')
            ->filter()
            ->values();
    }

    /**
     * Tâches liées à ce document GED.
     *
     * @return Collection<int, Task>
     */
    public function linkedTasks(): Collection
    {
        return $this->projectLinks()
            ->where('documentable_type', Task::class)
            ->with('documentable')
            ->get()
            ->pluck('documentable')
            ->filter()
            ->values();
    }

    // ── Helpers ──────────────────────────────────────────────

    /**
     * Retourne l'enum GedDocumentType ou null si non typé.
     */
    public function documentTypeEnum(): ?GedDocumentType
    {
        if (empty($this->document_type)) {
            return null;
        }

        return GedDocumentType::tryFrom($this->document_type);
    }

    /**
     * Indique si ce document est un acte réglementaire officiel
     * (délibération, arrêté, décision).
     */
    public function isOfficialAct(): bool
    {
        $type = $this->documentTypeEnum();

        return $type !== null && $type->isOfficialAct();
    }

    /**
     * Label du type documentaire pour l'affichage.
     * Retourne "Non classifié" si aucun type n'est défini.
     */
    public function documentTypeLabel(): string
    {
        return $this->documentTypeEnum()?->label() ?? 'Non classifié';
    }

    /**
     * Classes CSS Tailwind du badge de type (couleur de fond + texte).
     */
    public function documentTypeBadgeColor(): string
    {
        return $this->documentTypeEnum()?->badgeColor() ?? 'bg-gray-100 text-gray-400';
    }

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

    /**
     * True si le document peut être ouvert dans Collabora Online.
     */
    public function isCollaboraSupported(): bool
    {
        $mimes = (array) config('collabora.supported_mimes', []);

        return ! empty($mimes) && in_array($this->mime_type, $mimes, true);
    }
}
