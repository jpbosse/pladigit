<?php

namespace App\Models\Platform;

use Illuminate\Database\Eloquent\Model;

/**
 * Paramètres de configuration de la plateforme (niveau super-admin).
 * Table singleton (une seule ligne, connection 'mysql').
 *
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
 * @property \Carbon\Carbon|null $backup_last_run_at
 * @property string|null $backup_last_status
 * @property string|null $backup_last_message
 * @property int|null $backup_last_size_bytes
 */
class PlatformSettings extends Model
{
    protected $connection = 'mysql';

    protected $table = 'platform_settings';

    public $timestamps = false;

    protected $fillable = [
        'backup_enabled', 'backup_schedule',
        'backup_driver',
        'backup_local_path',
        'backup_sftp_host', 'backup_sftp_port', 'backup_sftp_user',
        'backup_sftp_password_enc', 'backup_sftp_path',
        'backup_retention_count',
        'backup_last_run_at', 'backup_last_status',
        'backup_last_message', 'backup_last_size_bytes',
        'update_last_run_at', 'update_last_status',
        'update_last_message', 'update_current_version',
        'update_available_version', 'update_log_path',
    ];

    protected $casts = [
        'backup_enabled' => 'boolean',
        'backup_sftp_port' => 'integer',
        'backup_retention_count' => 'integer',
        'backup_last_run_at' => 'datetime',
        'backup_last_size_bytes' => 'integer',
        'update_last_run_at' => 'datetime',
    ];

    public function backupIsConfigured(): bool
    {
        $driver = $this->backup_driver ?? 'local';

        if ($driver === 'local') {
            return ! empty($this->backup_local_path);
        }

        return ! empty($this->backup_sftp_host) && ! empty($this->backup_sftp_user);
    }

    public function backupHumanSize(): ?string
    {
        $bytes = $this->backup_last_size_bytes;

        if ($bytes === null || $bytes === 0) {
            return null;
        }

        $units = ['o', 'Ko', 'Mo', 'Go'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return number_format($bytes, $i > 0 ? 1 : 0, ',', ' ').' '.$units[$i];
    }
}
