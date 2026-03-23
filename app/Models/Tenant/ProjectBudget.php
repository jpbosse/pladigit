<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Enveloppe budgétaire d'un projet.
 *
 * @property string $type invest|fonct
 * @property string $label
 * @property int $year
 * @property float $amount_planned
 * @property float $amount_committed
 * @property float $amount_paid
 * @property string|null $cofinancer
 * @property float|null $cofinancing_rate
 */
class ProjectBudget extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'tenant';

    protected $fillable = [
        'project_id',
        'type',
        'label',
        'year',
        'amount_planned',
        'amount_committed',
        'amount_paid',
        'cofinancer',
        'cofinancing_rate',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'year' => 'integer',
        'amount_planned' => 'float',
        'amount_committed' => 'float',
        'amount_paid' => 'float',
        'cofinancing_rate' => 'float',
    ];

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Taux de consommation du budget engagé (0-100).
     */
    public function committedRate(): float
    {
        if ($this->amount_planned <= 0) {
            return 0.0;
        }

        return round($this->amount_committed / $this->amount_planned * 100, 1);
    }

    /**
     * Écart budget — positif = dépassement prévisible.
     */
    public function variance(): float
    {
        return $this->amount_committed - $this->amount_planned;
    }

    /**
     * Libellés français des types.
     *
     * @return array<string, string>
     */
    public static function typeLabels(): array
    {
        return [
            'invest' => 'Investissement',
            'fonct' => 'Fonctionnement',
        ];
    }
}
