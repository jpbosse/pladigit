<?php

namespace App\Models\Tenant;

use App\Enums\RoleTitreCategorie;
use App\Enums\RoleTitreStatut;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Mandat, fonction ou titre d'une personne au sein d'une organisation.
 *
 * Une personne peut avoir plusieurs rôles simultanés.
 * Les colonnes "rôles" importées depuis Excel sont transposées en lignes ici,
 * évitant la prolifération de colonnes dans la table personnes.
 *
 * @property int $id
 * @property int $personne_id
 * @property RoleTitreCategorie $categorie
 * @property string $fonction
 * @property int|null $organisation_id
 * @property string|null $civilite_contexte
 * @property int|null $rang_protocolaire
 * @property Carbon|null $date_debut
 * @property Carbon|null $date_fin
 * @property RoleTitreStatut $statut
 */
class RoleTitre extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'tenant';

    protected $fillable = [
        'personne_id',
        'categorie',
        'fonction',
        'organisation_id',
        'civilite_contexte',
        'rang_protocolaire',
        'date_debut',
        'date_fin',
        'statut',
    ];

    protected $casts = [
        'categorie' => RoleTitreCategorie::class,
        'statut' => RoleTitreStatut::class,
        'rang_protocolaire' => 'int',
        'date_debut' => 'date',
        'date_fin' => 'date',
        'deleted_at' => 'datetime',
    ];

    // ── Relations ────────────────────────────────────────────

    /** @return BelongsTo<Personne, $this> */
    public function personne(): BelongsTo
    {
        return $this->belongsTo(Personne::class, 'personne_id');
    }

    /** @return BelongsTo<Organisation, $this> */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class, 'organisation_id');
    }

    // ── Scopes ───────────────────────────────────────────────

    public function scopeActifs($query)
    {
        return $query->where('statut', RoleTitreStatut::ACTIF->value);
    }

    public function scopeEnAttente($query)
    {
        return $query->where('statut', RoleTitreStatut::EN_ATTENTE->value);
    }

    public function scopeDeCategorie($query, RoleTitreCategorie $categorie)
    {
        return $query->where('categorie', $categorie->value);
    }

    public function scopeParRangProtocolaire($query)
    {
        return $query->orderByRaw('rang_protocolaire IS NULL, rang_protocolaire ASC');
    }

    // ── Helpers ──────────────────────────────────────────────

    public function isActif(): bool
    {
        return $this->statut === RoleTitreStatut::ACTIF;
    }
}
