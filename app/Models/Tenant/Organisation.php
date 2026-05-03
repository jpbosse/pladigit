<?php

namespace App\Models\Tenant;

use App\Enums\OrganisationType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Organisation référencée dans l'annuaire des personnalités.
 *
 * @property int $id
 * @property string $nom
 * @property OrganisationType $type
 * @property string|null $siret
 * @property string|null $adresse
 */
class Organisation extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'tenant';

    protected $fillable = [
        'nom',
        'type',
        'siret',
        'adresse',
    ];

    protected $casts = [
        'type' => OrganisationType::class,
        'deleted_at' => 'datetime',
    ];

    // ── Relations ────────────────────────────────────────────

    /** @return HasMany<RoleTitre, $this> */
    public function rolesTitres(): HasMany
    {
        return $this->hasMany(RoleTitre::class, 'organisation_id');
    }

    /** Mandats actifs au sein de cette organisation. */
    public function rolesTitresActifs(): HasMany
    {
        return $this->rolesTitres()->where('statut', 'actif');
    }

    // ── Scopes ───────────────────────────────────────────────

    public function scopeOfType($query, OrganisationType $type)
    {
        return $query->where('type', $type->value);
    }
}
