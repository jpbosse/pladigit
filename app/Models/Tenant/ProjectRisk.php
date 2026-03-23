<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Risque / frein identifié sur un projet.
 *
 * Score = probabilité × impact (matrice 3×4).
 * Score ≥ 6 → critique, ≥ 4 → élevé, ≥ 2 → modéré, < 2 → faible.
 */
class ProjectRisk extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'tenant';

    protected $fillable = [
        'project_id',
        'title',
        'description',
        'category',
        'probability',
        'impact',
        'status',
        'mitigation_plan',
        'owner_id',
    ];

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Score probabilité × impact (1–12).
     */
    public function score(): int
    {
        $prob = ['low' => 1, 'medium' => 2, 'high' => 3];
        $impact = ['low' => 1, 'medium' => 2, 'high' => 3, 'critical' => 4];

        return ($prob[$this->probability] ?? 1) * ($impact[$this->impact] ?? 1);
    }

    /**
     * Niveau de criticité dérivé du score.
     */
    public function criticality(): string
    {
        return match (true) {
            $this->score() >= 9 => 'critique',
            $this->score() >= 6 => 'élevé',
            $this->score() >= 3 => 'modéré',
            default => 'faible',
        };
    }

    /**
     * @return array<string, array{bg: string, text: string}>
     */
    public static function criticalityColors(): array
    {
        return [
            'critique' => ['bg' => '#FEE2E2', 'text' => '#991B1B'],
            'élevé' => ['bg' => '#FEF3C7', 'text' => '#92400E'],
            'modéré' => ['bg' => '#DBEAFE', 'text' => '#1E40AF'],
            'faible' => ['bg' => '#F1F5F9', 'text' => '#475569'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function categoryLabels(): array
    {
        return [
            'humain' => 'Humain',
            'technique' => 'Technique',
            'budget' => 'Budget',
            'planning' => 'Planning',
            'juridique' => 'Juridique',
            'autre' => 'Autre',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function statusLabels(): array
    {
        return [
            'identified' => 'Identifié',
            'monitored' => 'Surveillé',
            'mitigated' => 'Atténué',
            'closed' => 'Clôturé',
        ];
    }
}
