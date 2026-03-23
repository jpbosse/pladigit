<?php

namespace App\Models\Platform;

use App\Enums\ModuleKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Organisation cliente (tenant).
 * Stockée dans la base pladigit_platform.
 *
 * @property list<string>|null $enabled_modules Clés des modules activés (JSON).
 */
class Organization extends Model
{
    use HasFactory;

    protected $connection = 'mysql';

    protected $fillable = [
        'slug', 'name', 'db_name', 'status',
        'max_users', 'storage_quota_mb', 'plan',
        'primary_color', 'timezone', 'locale',
        'logo_path', 'login_bg_path',
        // Modules activables
        'enabled_modules',
        // SMTP par tenant
        'smtp_host', 'smtp_port', 'smtp_encryption',
        'smtp_user', 'smtp_password_enc',
        'smtp_from_address', 'smtp_from_name',
    ];

    protected $casts = [
        'trial_ends_at' => 'date',
        'contract_signed_at' => 'date',
        'enabled_modules' => 'array',
    ];

    // ─────────────────────────────────────────────────────────────
    //  Helpers modules
    // ─────────────────────────────────────────────────────────────

    /**
     * Vérifie si un module est activé pour cette organisation.
     *
     * Usage :
     *   $org->hasModule(ModuleKey::MEDIA)   // true / false
     */
    public function hasModule(ModuleKey $module): bool
    {
        $modules = $this->enabled_modules ?? [];

        return in_array($module->value, $modules, strict: true);
    }

    /**
     * Active un module (sans sauvegarder — appeler save() ensuite).
     */
    public function enableModule(ModuleKey $module): void
    {
        $modules = $this->enabled_modules ?? [];
        if (! in_array($module->value, $modules, strict: true)) {
            $modules[] = $module->value;
        }
        $this->enabled_modules = $modules;
    }

    /**
     * Désactive un module (sans sauvegarder — appeler save() ensuite).
     */
    public function disableModule(ModuleKey $module): void
    {
        $this->enabled_modules = array_values(
            array_filter(
                $this->enabled_modules ?? [],
                fn (string $key) => $key !== $module->value
            )
        );
    }

    /**
     * Retourne les instances ModuleKey actives pour cette organisation.
     *
     * @return list<ModuleKey>
     */
    public function activeModules(): array
    {
        return array_values(
            array_filter(
                ModuleKey::cases(),
                fn (ModuleKey $m) => $this->hasModule($m)
            )
        );
    }

    // ─────────────────────────────────────────────────────────────

    public static function dbNameFromSlug(string $slug): string
    {
        return 'pladigit_'.str_replace('-', '_', $slug);
    }
}
