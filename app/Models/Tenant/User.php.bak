<?php

namespace App\Models\Tenant;

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

    public function getConnectionName()
    {
        return app(\App\Services\TenantManager::class)->hasTenant()
            ? 'tenant'
            : null;
    }

    //   protected $connection = 'tenant';

    protected $fillable = [
        'name', 'email', 'password_hash', 'role', 'status',
        'ldap_dn', 'avatar_path', 'department',
        'totp_enabled', 'force_pwd_change', 'created_by',
        'last_login_at', 'last_login_ip', 'login_attempts',
        'locked_until', 'ldap_synced_at',
        'totp_enabled', 'force_pwd_change', 'created_by',
        'totp_secret_enc', 'totp_backup_code_enc',
    ];

    protected $hidden = [
        'password_hash', 'totp_secret_enc', 'totp_backup_code_enc',
        'password_history', 'remember_token',
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
        return $this->role === 'admin';
    }

    public function hasRoleAtLeast(string $minRole): bool
    {
        $hierarchy = [
            'admin' => 1,
            'president' => 2,
            'dgs' => 3,
            'resp_direction' => 4,
            'resp_service' => 5,
            'user' => 6,
        ];

        return ($hierarchy[$this->role] ?? 99) <= ($hierarchy[$minRole] ?? 99); // @phpstan-ignore-line
    }

    public function isLocked(): bool
    {
        return $this->status === 'locked'
            || ($this->locked_until && $this->locked_until->isFuture());
    }
}
