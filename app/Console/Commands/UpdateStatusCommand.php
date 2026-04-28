<?php

namespace App\Console\Commands;

use App\Models\Platform\PlatformSettings;
use Illuminate\Console\Command;

class UpdateStatusCommand extends Command
{
    protected $signature = 'pladigit:update-status {status : running|success|error} {message? : Message optionnel}';

    protected $description = 'Met à jour le statut de mise à jour dans PlatformSettings (appelé par update.sh)';

    public function handle(): int
    {
        $status = $this->argument('status');
        $message = $this->argument('message') ?? '';

        if (! in_array($status, ['running', 'success', 'error'])) {
            $this->error("Statut invalide : $status (attendu : running|success|error)");

            return self::FAILURE;
        }

        $settings = PlatformSettings::firstOrCreate([]);
        $settings->update([
            'update_last_status' => $status,
            'update_last_message' => $message,
        ]);

        $this->line("[pladigit:update-status] $status — $message");

        return self::SUCCESS;
    }
}
