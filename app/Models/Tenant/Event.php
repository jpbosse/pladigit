<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Event extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'tenant';

    protected $fillable = [
        'created_by',
        'project_id',
        'title',
        'description',
        'location',
        'starts_at',
        'ends_at',
        'all_day',
        'is_recurring',
        'recurrence_rule',
        'color',
        'visibility',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'all_day' => 'boolean',
        'is_recurring' => 'boolean',
        'recurrence_rule' => 'array',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function participants(): HasMany
    {
        return $this->hasMany(EventParticipant::class);
    }
}
