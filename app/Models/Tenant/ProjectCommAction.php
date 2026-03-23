<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Action du plan de communication d'un projet.
 */
class ProjectCommAction extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'tenant';

    protected $fillable = [
        'project_id',
        'title',
        'target_audience',
        'channel',
        'message',
        'resources_needed',
        'planned_at',
        'done_at',
        'responsible_id',
        'notes',
    ];

    protected $casts = [
        'planned_at' => 'date',
        'done_at' => 'date',
    ];

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<User, $this> */
    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_id');
    }

    public function isDone(): bool
    {
        return $this->done_at !== null;
    }

    public function isLate(): bool
    {
        return ! $this->isDone() && $this->planned_at->isPast();
    }

    /**
     * @return array<string, array{label: string, icon: string}>
     */
    public static function channelConfig(): array
    {
        return [
            'email' => ['label' => 'Email',        'icon' => '✉'],
            'reunion' => ['label' => 'Réunion',      'icon' => '👥'],
            'affichage' => ['label' => 'Affichage',    'icon' => '📋'],
            'courrier' => ['label' => 'Courrier',     'icon' => '📬'],
            'intranet' => ['label' => 'Intranet',     'icon' => '🌐'],
            'autre' => ['label' => 'Autre',        'icon' => '📌'],
        ];
    }
}
