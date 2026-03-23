<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Observation / commentaire d'un élu sur le tableau de bord projet.
 */
class ProjectObservation extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'tenant';

    protected $fillable = [
        'project_id',
        'user_id',
        'body',
        'type',
    ];

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, array{label: string, bg: string, text: string}>
     */
    public static function typeConfig(): array
    {
        return [
            'observation' => ['label' => 'Observation', 'bg' => '#F1F5F9', 'text' => '#475569'],
            'question' => ['label' => 'Question',    'bg' => '#DBEAFE', 'text' => '#1E40AF'],
            'validation' => ['label' => 'Validation',  'bg' => '#D1FAE5', 'text' => '#065F46'],
            'alerte' => ['label' => 'Alerte',      'bg' => '#FEE2E2', 'text' => '#991B1B'],
        ];
    }
}
