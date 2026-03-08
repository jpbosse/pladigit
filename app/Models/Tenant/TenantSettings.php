<?php

namespace App\Models\Tenant;

use Database\Factories\Tenant\TenantSettingsFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Paramètres de configuration du tenant.
 * Table singleton (une seule ligne par base tenant).
 *
 * @property string|null $nas_photo_driver
 * @property string|null $nas_photo_local_path
 * @property string|null $nas_photo_host
 * @property int|null $nas_photo_port
 * @property string|null $nas_photo_username
 * @property string|null $nas_photo_share
 * @property string|null $nas_photo_root_path
 * @property string|null $nas_ged_driver
 * @property string|null $nas_ged_local_path
 * @property string|null $nas_ged_host
 * @property int|null $nas_ged_port
 * @property string|null $nas_ged_username
 * @property string|null $nas_ged_share
 * @property string|null $nas_ged_root_path
 */
class TenantSettings extends Model
{
    /** @use HasFactory<TenantSettingsFactory> */
    use HasFactory;

    protected $connection = 'tenant';

    protected $table = 'tenant_settings';

    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = [
        // Sécurité / mots de passe
        'pwd_min_length', 'pwd_require_uppercase', 'pwd_require_number',
        'pwd_require_special', 'pwd_validity_days', 'pwd_history_count',
        'login_max_attempts', 'login_lockout_minutes', 'session_lifetime_minutes',
        'force_2fa',
        // LDAP
        'ldap_host', 'ldap_port', 'ldap_base_dn', 'ldap_bind_dn',
        'ldap_bind_password_enc', 'ldap_use_tls', 'ldap_use_ssl', 'ldap_sync_interval_hours',
        // Maintenance
        'maintenance_window_day', 'maintenance_window_start', 'maintenance_window_end',
        // Photothèque affichage
        'media_default_cols',
        // NAS Photothèque
        'nas_photo_driver',
        'nas_photo_local_path',
        'nas_photo_host',
        'nas_photo_port',
        'nas_photo_username',
        'nas_photo_password_enc',
        'nas_photo_share',
        'nas_photo_root_path',
        'nas_photo_sync_interval_minutes',
        'nas_photo_last_sync_at',
        // NAS GED (Phase 5)
        'nas_ged_driver',
        'nas_ged_local_path',
        'nas_ged_host',
        'nas_ged_port',
        'nas_ged_username',
        'nas_ged_password_enc',
        'nas_ged_share',
        'nas_ged_root_path',
        'nas_ged_sync_interval_minutes',
        'nas_ged_last_sync_at',
        // Timestamp
        'updated_at',
    ];

    protected $casts = [
        'pwd_require_uppercase' => 'boolean',
        'pwd_require_number' => 'boolean',
        'pwd_require_special' => 'boolean',
        'force_2fa' => 'boolean',
        'ldap_use_tls' => 'boolean',
        'ldap_use_ssl' => 'boolean',
        // NAS Photothèque
        'nas_photo_port' => 'integer',
        'nas_photo_sync_interval_minutes' => 'integer',
        'nas_photo_last_sync_at' => 'datetime',
        // NAS GED
        'nas_ged_port' => 'integer',
        'nas_ged_sync_interval_minutes' => 'integer',
        'nas_ged_last_sync_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // ─────────────────────────────────────────────────────────────
    //  Helpers NAS Photothèque
    // ─────────────────────────────────────────────────────────────

    public function nasPhotoDriverLabel(): string
    {
        return match ($this->nas_photo_driver ?? 'local') {
            'local' => 'Local (développement)',
            'sftp' => 'SFTP (Linux / NAS)',
            'smb' => 'SMB/CIFS (Windows / NAS)',
            default => $this->nas_photo_driver ?? 'local',
        };
    }

    public function nasPhotoIsConfigured(): bool
    {
        $driver = $this->nas_photo_driver ?? 'local';
        if ($driver === 'local') {
            return true;
        }

        return ! empty($this->nas_photo_host) && ! empty($this->nas_photo_username);
    }

    // ─────────────────────────────────────────────────────────────
    //  Helpers NAS GED (Phase 5)
    // ─────────────────────────────────────────────────────────────

    public function nasGedDriverLabel(): string
    {
        return match ($this->nas_ged_driver ?? 'local') {
            'local' => 'Local (développement)',
            'sftp' => 'SFTP (Linux / NAS)',
            'smb' => 'SMB/CIFS (Windows / NAS)',
            default => $this->nas_ged_driver ?? 'local',
        };
    }

    public function nasGedIsConfigured(): bool
    {
        $driver = $this->nas_ged_driver ?? 'local';
        if ($driver === 'local') {
            return true;
        }

        return ! empty($this->nas_ged_host) && ! empty($this->nas_ged_username);
    }

    // ─────────────────────────────────────────────────────────────
    //  Rétrocompatibilité — anciens appels nas_* (deprecated)
    // ─────────────────────────────────────────────────────────────

    /** @deprecated Utiliser nas_photo_driver */
    public function nasDriverLabel(): string
    {
        return $this->nasPhotoDriverLabel();
    }

    /** @deprecated Utiliser nasPhotoIsConfigured() */
    public function nasIsConfigured(): bool
    {
        return $this->nasPhotoIsConfigured();
    }
}
