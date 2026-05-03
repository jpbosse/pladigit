<?php

namespace App\Models\Tenant;

use App\Enums\PersonneBaseLegale;
use App\Enums\PersonneVisibilite;
use App\Enums\RoleTitreStatut;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Personne physique de l'annuaire — entité partagée inter-DataGrids.
 *
 * Une même personne n'est jamais dupliquée, même si elle apparaît
 * dans plusieurs grilles (élus, commissions, associations…).
 *
 * coordonnees_priv : accès restreint, RGPD — ne jamais exposer sans vérification de droits.
 * opposition       : droit d'opposition exercé (art. 21 RGPD) — bloque tout traitement.
 *
 * @property int $id
 * @property string $nom
 * @property string $prenom
 * @property string|null $photo
 * @property array|null $coordonnees_pro
 * @property array|null $coordonnees_priv
 * @property PersonneBaseLegale $base_legale
 * @property bool $opposition
 * @property \Carbon\Carbon|null $date_opposition
 * @property PersonneVisibilite $visibilite
 * @property \Carbon\Carbon|null $date_revision
 */
class Personne extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'tenant';

    protected $fillable = [
        'nom',
        'prenom',
        'photo',
        'coordonnees_pro',
        'coordonnees_priv',
        'base_legale',
        'opposition',
        'date_opposition',
        'visibilite',
        'date_revision',
    ];

    protected $casts = [
        'coordonnees_pro' => 'array',
        'coordonnees_priv' => 'array',
        'base_legale' => PersonneBaseLegale::class,
        'opposition' => 'bool',
        'date_opposition' => 'date',
        'visibilite' => PersonneVisibilite::class,
        'date_revision' => 'date',
        'deleted_at' => 'datetime',
    ];

    // ── Relations ────────────────────────────────────────────

    /** @return HasMany<RoleTitre, $this> */
    public function rolesTitres(): HasMany
    {
        return $this->hasMany(RoleTitre::class, 'personne_id');
    }

    /** Mandats actifs uniquement. */
    public function rolesTitresActifs(): HasMany
    {
        return $this->rolesTitres()->where('statut', RoleTitreStatut::ACTIF->value);
    }

    /** @return HasManyThrough<Organisation, RoleTitre, $this> */
    public function organisations(): HasManyThrough
    {
        return $this->hasManyThrough(
            Organisation::class,
            RoleTitre::class,
            'personne_id',
            'id',
            'id',
            'organisation_id'
        );
    }

    // ── Scopes ───────────────────────────────────────────────

    public function scopeVisible($query, PersonneVisibilite $niveau = PersonneVisibilite::INTERNE)
    {
        return $query->where('visibilite', $niveau->value);
    }

    public function scopeSansOpposition($query)
    {
        return $query->where('opposition', false);
    }

    // ── Helpers ──────────────────────────────────────────────

    public function nomComplet(): string
    {
        return trim("{$this->prenom} {$this->nom}");
    }

    /** Indique si le traitement des données de cette personne est bloqué (opposition RGPD). */
    public function traitementBloque(): bool
    {
        return $this->opposition;
    }
}
