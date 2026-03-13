<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Notification in-app d'un utilisateur tenant.
 *
 * Colonnes : user_id, type, title, body, link, read, read_at
 *
 * Types courants :
 *   document.validation  — document en attente
 *   user.invitation      — invitation acceptée
 *   agenda.reminder      — rappel événement
 *   storage.warning      — quota stockage
 *   ldap.sync            — résultat synchro LDAP
 *   chat.message         — nouveau message
 */
class Notification extends Model
{
    protected $connection = 'tenant';

    protected $fillable = [
        'user_id', 'type', 'title', 'body', 'link', 'read', 'read_at',
    ];

    protected $casts = [
        'read' => 'boolean',
        'read_at' => 'datetime',
    ];

    // ── Relations ────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ── Scopes ───────────────────────────────────────────────

    /** Notifications non lues */
    public function scopeUnread($query)
    {
        return $query->where('read', false);
    }

    /** Notifications d'un utilisateur donné */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    // ── Helpers ──────────────────────────────────────────────

    /**
     * Icône emoji selon le type de notification.
     */
    public function icon(): string
    {
        return match (true) {
            str_starts_with($this->type, 'document') => '📄',
            str_starts_with($this->type, 'user') => '✅',
            str_starts_with($this->type, 'agenda') => '📅',
            str_starts_with($this->type, 'storage') => '💾',
            str_starts_with($this->type, 'ldap') => '🔗',
            str_starts_with($this->type, 'chat') => '💬',
            str_starts_with($this->type, 'security') => '🔐',
            default => '🔔',
        };
    }

    /**
     * Couleur de fond de l'icône selon le type.
     */
    public function iconBg(): string
    {
        return match (true) {
            str_starts_with($this->type, 'document') => 'rgba(59,154,225,0.12)',
            str_starts_with($this->type, 'user') => 'rgba(46,204,113,0.12)',
            str_starts_with($this->type, 'agenda') => 'rgba(155,89,182,0.12)',
            str_starts_with($this->type, 'storage') => 'rgba(232,168,56,0.12)',
            str_starts_with($this->type, 'ldap') => 'rgba(46,204,113,0.12)',
            str_starts_with($this->type, 'chat') => 'rgba(59,154,225,0.12)',
            str_starts_with($this->type, 'security') => 'rgba(231,76,60,0.12)',
            default => 'rgba(107,114,128,0.12)',
        };
    }

    /**
     * Marquer comme lu.
     */
    public function markAsRead(): void
    {
        $this->update(['read' => true, 'read_at' => now()]);
    }
}
