<?php

namespace App\Models\Platform;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * @property bool $backup_enabled
 * @property string $backup_schedule
 * @property string $backup_driver
 * @property string|null $backup_local_path
 * @property string|null $backup_sftp_host
 * @property int $backup_sftp_port
 * @property string|null $backup_sftp_user
 * @property string|null $backup_sftp_password_enc
 * @property string|null $backup_sftp_path
 * @property int $backup_retention_count
 * @property bool $backup_gpg_enabled
 * @property string|null $backup_gpg_passphrase_enc
 * @property Carbon|null $backup_last_run_at
 * @property string|null $backup_last_status
 * @property string|null $backup_last_message
 * @property int|null $backup_last_size_bytes
 * @property Carbon|null $update_last_run_at
 * @property string|null $update_last_status
 * @property string|null $update_last_message
 * @property string|null $update_current_version
 * @property string|null $update_available_version
 * @property string|null $update_log_path
 */
class PlatformSettings extends Model
{
    protected $table = 'platform_settings';

    protected $fillable = [
        'backup_enabled', 'backup_schedule',
        'backup_driver',
        'backup_local_path',
        'backup_sftp_host', 'backup_sftp_port', 'backup_sftp_user',
        'backup_sftp_password_enc', 'backup_sftp_path',
        'backup_retention_count',
        'backup_last_run_at', 'backup_last_status',
        'backup_last_message', 'backup_last_size_bytes',
        'backup_gpg_enabled', 'backup_gpg_passphrase_enc',
        'update_last_run_at', 'update_last_status',
        'update_last_message', 'update_current_version',
        'update_available_version', 'update_log_path',
    ];

    protected $casts = [
        'backup_enabled' => 'boolean',
        'backup_gpg_enabled' => 'boolean',
        'backup_sftp_port' => 'integer',
        'backup_retention_count' => 'integer',
        'backup_last_run_at' => 'datetime',
        'backup_last_size_bytes' => 'integer',
        'update_last_run_at' => 'datetime',
    ];

    public function backupHumanSize(): ?string
    {
        $bytes = $this->backup_last_size_bytes;
        if ($bytes === null || $bytes === 0) {
            return null;
        }
        $units = ['o', 'Ko', 'Mo', 'Go', 'To'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 1).' '.$units[$i];
    }

    public function backupIsConfigured(): bool
    {
        $driver = $this->backup_driver ?? 'local';

        if ($driver === 'local') {
            return ! empty($this->backup_local_path);
        }

        return ! empty($this->backup_sftp_host) && ! empty($this->backup_sftp_user);
    }
}
