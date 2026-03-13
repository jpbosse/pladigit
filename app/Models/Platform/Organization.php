<?php

namespace App\Models\Platform;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Organisation cliente (tenant).
 * Stockée dans la base pladigit_platform.
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
        // SMTP par tenant
        'smtp_host', 'smtp_port', 'smtp_encryption',
        'smtp_user', 'smtp_password_enc',
        'smtp_from_address', 'smtp_from_name',
    ];

    protected $casts = [
        'trial_ends_at' => 'date',
        'contract_signed_at' => 'date',
    ];

    public static function dbNameFromSlug(string $slug): string
    {
        return 'pladigit_'.str_replace('-', '_', $slug);
    }
}
