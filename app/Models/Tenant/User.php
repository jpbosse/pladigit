<?php

namespace App\Models\Tenant;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
        'department',   // Conservé pour rétrocompatibilité (string libre)
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
        'email_verified_at'  => 'datetime',
        'last_login_at'      => 'datetime',
        'locked_until'       => 'datetime',
        'ldap_synced_at'     => 'datetime',
        'password_changed_at' => 'datetime',
        'totp_enabled'       => 'boolean',
        'force_pwd_change'   => 'boolean',
        'password_history'   => 'array',
    ];

    // Laravel attend 'password' par défaut — on remplace
    public function getAuthPassword(): string
    {
        return $this->password_hash ?? '';
    }

    // ── Relations départements ────────────────────────────────

    /**
     * Tous les départements (directions + services) auxquels l'utilisateur appartient.
     */
    public function departments(): BelongsToMany
    {
        return $this->belongsToMany(Department::class, 'user_department')
                    ->withPivot('is_manager')
                    ->withTimestamps();
    }

    /**
     * Départements où l'utilisateur est responsable (is_manager = true).
     */
    public function managedDepartments(): BelongsToMany
    {
        return $this->belongsToMany(Department::class, 'user_department')
                    ->withPivot('is_manager')
                    ->wherePivot('is_manager', true)
                    ->withTimestamps();
    }

    /**
     * Retourne tous les utilisateurs visibles par cet utilisateur selon son rôle.
     *
     * - admin / president / dgs → tous les utilisateurs
     * - resp_direction → membres de ses directions ET de leurs services enfants
     * - resp_service   → membres de ses services uniquement
     * - user           → lui-même uniquement
     */
    public function visibleUsers(): \Illuminate\Database\Eloquent\Collection
    {
        $role = UserRole::tryFrom($this->role ?? '');

        if (! $role) {
            return collect([$this]);
        }

        // Admin, président, DGS voient tout
        if ($role->atLeast(UserRole::DGS)) {
            return User::on('tenant')->where('id', '!=', null)->get();
        }

        // Resp. de direction : ses directions + tous les services enfants
        if ($role === UserRole::RESP_DIRECTION) {
            $directionIds = $this->managedDepartments()
                                 ->where('type', 'direction')
                                 ->pluck('departments.id');

            $serviceIds = Department::on('tenant')
                                    ->where('type', 'service')
                                    ->whereIn('parent_id', $directionIds)
                                    ->pluck('id');

            $allDeptIds = $directionIds->merge($serviceIds);

            return User::on('tenant')
                       ->whereHas('departments', fn ($q) => $q->whereIn('departments.id', $allDeptIds))
                       ->get();
        }

        // Resp. de service : membres de ses services
        if ($role === UserRole::RESP_SERVICE) {
            $serviceIds = $this->managedDepartments()
                               ->where('type', 'service')
                               ->pluck('departments.id');

            return User::on('tenant')
                       ->whereHas('departments', fn ($q) => $q->whereIn('departments.id', $serviceIds))
                       ->get();
        }

        // Utilisateur simple : lui-même uniquement
        return collect([$this]);
    }

    // ── Helpers rôles ─────────────────────────────────────

    public function isAdmin(): bool
    {
        return $this->role === UserRole::ADMIN->value;
    }

    /**
     * Retourne true si l'utilisateur a au moins le rôle $minRole.
     */
    public function hasRoleAtLeast(string $minRole): bool
    {
        $userRole     = UserRole::tryFrom($this->role ?? '');
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
