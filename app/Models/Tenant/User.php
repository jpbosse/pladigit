<?php

namespace App\Models\Tenant;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Modèle utilisateur d'un tenant.
 * Utilise la connexion 'tenant' (base dédiée de l'organisation).
 */
class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes;

    protected $connection = 'tenant';

    protected $fillable = [
        // Identité
        'name',
        'email',
        'department',
        'avatar_path',
        'created_by',

        // Authentification
        'password_hash',
        'password_history',
        'password_changed_at',
        'role',
        'status',

        // Connexion
        'last_login_at',
        'last_login_ip',
        'login_attempts',
        'locked_until',

        // Flags
        'force_pwd_change',

        // 2FA
        'totp_enabled',
        'totp_secret_enc',
        'totp_backup_code_enc',

        // LDAP
        'ldap_dn',
        'ldap_synced_at',
    ];

    protected $hidden = [
        'password_hash',
        'totp_secret_enc',
        'totp_backup_code_enc',
        'password_history',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'locked_until' => 'datetime',
        'ldap_synced_at' => 'datetime',
        'password_changed_at' => 'datetime',
        'totp_enabled' => 'boolean',
        'force_pwd_change' => 'boolean',
        'password_history' => 'array',
    ];

    // Laravel attend 'password' par défaut — on remplace
    public function getAuthPassword(): string
    {
        return $this->password_hash ?? '';
    }

    // ── Helpers rôles ─────────────────────────────────────

    public function isAdmin(): bool
    {
        return $this->role === UserRole::ADMIN->value;
    }

    /**
     * Retourne true si l'utilisateur a au moins le rôle $minRole.
     * Ex : $user->hasRoleAtLeast('dgs')
     */
    public function hasRoleAtLeast(string $minRole): bool
    {
        $userRole = UserRole::tryFrom($this->role ?? '');
        $requiredRole = UserRole::tryFrom($minRole);

        if (! $userRole || ! $requiredRole) {
            return false;
        }

        return $userRole->atLeast($requiredRole);
    }

    public function isLocked(): bool
    {
        return $this->status === 'locked'
            || ($this->locked_until && $this->locked_until->isFuture());
    }
}
