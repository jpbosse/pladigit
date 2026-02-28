<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

/**
 * Paramètres de configuration du tenant.
 * Table singleton (une seule ligne par base tenant).
 */
class TenantSettings extends Model
{
    protected $connection = 'tenant';

    protected $table = 'tenant_settings';

    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = [
        'pwd_min_length', 'pwd_require_uppercase', 'pwd_require_number',
        'pwd_require_special', 'pwd_validity_days', 'pwd_history_count',
        'login_max_attempts', 'login_lockout_minutes', 'session_lifetime_minutes',
        'force_2fa',
        'ldap_host', 'ldap_port', 'ldap_base_dn', 'ldap_bind_dn',
        'ldap_bind_password_enc', 'ldap_use_tls', 'ldap_use_ssl', 'ldap_sync_interval_hours',
        'maintenance_window_day', 'maintenance_window_start', 'maintenance_window_end',
        'updated_at',
    ];

    protected $casts = [
        'pwd_require_uppercase' => 'boolean',
        'pwd_require_number' => 'boolean',
        'pwd_require_special' => 'boolean',
        'force_2fa' => 'boolean',
        'ldap_use_tls' => 'boolean',
        'updated_at' => 'datetime',
        'ldap_use_ssl' => 'boolean',
    ];
}
