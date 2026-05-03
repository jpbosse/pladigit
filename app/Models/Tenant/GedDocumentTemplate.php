<?php

namespace App\Models\Tenant;

use App\Enums\GedDocumentType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Modèle de document par type pour les collectivités.
 *
 * Définit le patron de nommage, le dossier cible et les métadonnées
 * obligatoires pour chaque type documentaire (délibération, arrêté, etc.).
 *
 * @property int $id
 * @property string $document_type
 * @property string $name
 * @property string|null $description
 * @property string|null $name_pattern
 * @property int|null $default_folder_id
 * @property int|null $default_department_id
 * @property string|null $template_file_path
 * @property array|null $required_fields
 * @property bool $is_active
 * @property int $sort_order
 * @property int $created_by
 */
class GedDocumentTemplate extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'tenant';

    protected $fillable = [
        'document_type',
        'name',
        'description',
        'name_pattern',
        'default_folder_id',
        'default_department_id',
        'template_file_path',
        'required_fields',
        'is_active',
        'sort_order',
        'created_by',
    ];

    protected $casts = [
        'required_fields' => 'array',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'deleted_at' => 'datetime',
    ];

    // ── Relations ────────────────────────────────────────────

    /** @return BelongsTo<GedFolder, $this> */
    public function defaultFolder(): BelongsTo
    {
        return $this->belongsTo(GedFolder::class, 'default_folder_id');
    }

    /** @return BelongsTo<Department, $this> */
    public function defaultDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'default_department_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Documents créés depuis ce modèle. */
    /** @return HasMany<GedDocument, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(GedDocument::class, 'template_id');
    }

    // ── Scopes ───────────────────────────────────────────────

    /** Modèles actifs uniquement. */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /** Filtrer par type documentaire. */
    public function scopeOfType($query, GedDocumentType|string $type)
    {
        $value = $type instanceof GedDocumentType ? $type->value : $type;

        return $query->where('document_type', $value);
    }

    // ── Helpers ──────────────────────────────────────────────

    /**
     * Retourne l'enum GedDocumentType correspondant à ce modèle.
     */
    public function documentTypeEnum(): GedDocumentType
    {
        return GedDocumentType::from($this->document_type);
    }

    /**
     * Indique si un champ est obligatoire selon ce modèle.
     */
    public function requiresField(string $field): bool
    {
        return in_array($field, $this->required_fields ?? [], true);
    }

    /**
     * Applique le patron de nommage pour construire un nom de fichier.
     *
     * Variables disponibles :
     *   {PREFIX}  → préfixe du type (DEL, ARR…)
     *   {YEAR}    → année courante sur 4 chiffres
     *   {SEQ}     → séquence sur 3 chiffres (ex: 042)
     *   {DEPT}    → slug du département émetteur (ou vide)
     *   {SLUG}    → slug de l'objet
     *
     * @param  array<string, string>  $vars  Variables à substituer
     */
    public function applyPattern(array $vars): string
    {
        if (empty($this->name_pattern)) {
            // Pas de patron → nom libre construit par DocumentNamingService
            return '';
        }

        $pattern = $this->name_pattern;

        foreach ($vars as $key => $value) {
            $pattern = str_replace('{'.$key.'}', $value, $pattern);
        }

        return $pattern;
    }

    /**
     * Liste des variables de patron utilisées dans name_pattern.
     *
     * @return array<string>
     */
    public function patternVariables(): array
    {
        if (empty($this->name_pattern)) {
            return [];
        }

        preg_match_all('/\{(\w+)\}/', $this->name_pattern, $matches);

        return $matches[1];
    }
}
