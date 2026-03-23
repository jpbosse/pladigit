<?php

namespace Database\Factories\Tenant;

use App\Models\Tenant\TenantSettings;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory pour TenantSettings.
 * La table est un singleton (une seule ligne par base tenant).
 * Utilisez firstOrCreate() ou truncate() avant create() dans les tests
 * pour éviter les doublons.
 */
class TenantSettingsFactory extends Factory
{
    protected $model = TenantSettings::class;

    public function definition(): array
    {
        return [
            // Politique mots de passe — valeurs permissives par défaut en test
            'pwd_min_length' => 8,
            'pwd_require_uppercase' => false,
            'pwd_require_number' => false,
            'pwd_require_special' => false,
            'pwd_validity_days' => 90,
            'pwd_history_count' => 5,

            // Anti-brute-force
            'login_max_attempts' => 5,
            'login_lockout_minutes' => 15,

            // Session
            'session_lifetime_minutes' => 120,

            // 2FA
            'force_2fa' => false,

            // LDAP — désactivé par défaut en test
            'ldap_host' => null,
            'ldap_port' => 636,
            'ldap_base_dn' => null,
            'ldap_bind_dn' => null,
            'ldap_bind_password_enc' => null,
            'ldap_use_tls' => true,
            'ldap_use_ssl' => false,
            'ldap_sync_interval_hours' => 24,

            // Watermark — désactivé par défaut en test
            'wm_enabled' => false,
            'wm_type' => 'text',
            'wm_text' => null,
            'wm_position' => 'bottom-right',
            'wm_opacity' => 60,
            'wm_size' => 'medium',
        ];
    }

    /** Politique stricte — utile pour tester les rejets. */
    public function strict(): static
    {
        return $this->state([
            'pwd_min_length' => 12,
            'pwd_require_uppercase' => true,
            'pwd_require_number' => true,
            'pwd_require_special' => true,
            'pwd_history_count' => 5,
        ]);
    }

    /** Politique permissive — utile pour tester les cas nominaux. */
    public function permissive(): static
    {
        return $this->state([
            'pwd_min_length' => 6,
            'pwd_require_uppercase' => false,
            'pwd_require_number' => false,
            'pwd_require_special' => false,
            'pwd_history_count' => 0,
        ]);
    }
}
