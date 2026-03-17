<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Jalon (milestone) d'un projet.
 *
 * Affiché comme un losange orange sur la vue Gantt.
 * Les tâches peuvent y être rattachées via tasks.milestone_id.
 *
 * Un jalon est considéré atteint quand reached_at est renseigné.
 */
class ProjectMilestone extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'tenant';

    protected $fillable = [
        'project_id',
        'title',
        'description',
        'due_date',
        'reached_at',
        'color',
    ];

    protected $casts = [
        'due_date' => 'date',
        'reached_at' => 'datetime',
    ];

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Tâches rattachées à ce jalon.
     *
     * @return HasMany<Task>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'milestone_id');
    }

    /**
     * Le jalon est-il atteint ?
     */
    public function isReached(): bool
    {
        return $this->reached_at !== null;
    }

    /**
     * Le jalon est-il en retard (due_date dépassée et pas atteint) ?
     */
    public function isLate(): bool
    {
        return ! $this->isReached()
            && $this->due_date !== null
            && $this->due_date->isPast();
    }

    /**
     * Marque le jalon comme atteint maintenant.
     */
    public function markReached(): bool
    {
        return $this->update(['reached_at' => now()]);
    }

    /**
     * Progression des tâches du jalon (% done).
     */
    public function progressionPercent(): int
    {
        $total = $this->tasks()->count();

        if ($total === 0) {
            return 0;
        }

        $done = $this->tasks()->where('status', 'done')->count();

        return (int) round($done / $total * 100);
    }
}
