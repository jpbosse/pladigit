<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Partie prenante d'un projet.
 *
 * Peut être liée à un utilisateur Pladigit (user_id) ou externe (name libre).
 * La méthode displayName() résout automatiquement le nom à afficher.
 */
class ProjectStakeholder extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'tenant';

    protected $fillable = [
        'project_id',
        'user_id',
        'name',
        'role',
        'adhesion',
        'influence',
        'notes',
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
     * Nom à afficher : user Pladigit ou nom libre.
     */
    public function displayName(): string
    {
        return $this->user?->name ?? $this->name ?? '—'; // @phpstan-ignore-line nullsafe.neverNull
    }

    /**
     * Initiales pour l'avatar.
     */
    public function initials(): string
    {
        $name = $this->displayName();
        $parts = explode(' ', $name);

        return strtoupper(
            count($parts) >= 2
                ? substr($parts[0], 0, 1).substr($parts[1], 0, 1)
                : substr($name, 0, 2)
        );
    }

    /**
     * @return array<string, array{label: string, bg: string, text: string}>
     */
    public static function adhesionConfig(): array
    {
        return [
            'champion' => ['label' => 'Champion',  'bg' => '#D1FAE5', 'text' => '#065F46'],
            'supporter' => ['label' => 'Soutien',   'bg' => '#DBEAFE', 'text' => '#1E40AF'],
            'neutre' => ['label' => 'Neutre',     'bg' => '#F1F5F9', 'text' => '#475569'],
            'vigilant' => ['label' => 'Vigilant',  'bg' => '#FEF3C7', 'text' => '#92400E'],
            'resistant' => ['label' => 'Résistant', 'bg' => '#FEE2E2', 'text' => '#991B1B'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function influenceLabels(): array
    {
        return [
            'high' => 'Forte',
            'medium' => 'Moyenne',
            'low' => 'Faible',
        ];
    }
}
