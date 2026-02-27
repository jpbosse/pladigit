<?php

namespace App\Models\Platform;

use Illuminate\Database\Eloquent\Model;

/**
 * Organisation cliente (tenant).
 * Stockée dans la base pladigit_platform.
 */
class Organization extends Model
{
    protected $connection = 'mysql'; // Base platform

    protected $fillable = [
        'slug', 'name', 'db_name', 'status',
        'max_users', 'storage_quota_mb', 'plan',
        'primary_color', 'timezone', 'locale',
    ];

    protected $casts = [
        'trial_ends_at' => 'date',
        'contract_signed_at' => 'date',
    ];

    /** Génère le nom de la base de données à partir du slug. */
    public static function dbNameFromSlug(string $slug): string
    {
        return 'pladigit_'.str_replace('-', '_', $slug);
    }
}
