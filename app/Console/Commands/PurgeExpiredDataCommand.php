<?php

namespace App\Console\Commands;

use App\Models\Tenant\MediaShareLink;
use App\Models\Tenant\TenantSettings;
use App\Models\Tenant\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PurgeExpiredDataCommand extends Command
{
    protected $signature = 'pladigit:purge-expired
                            {--dry-run : Afficher ce qui serait supprimé sans agir}';

    protected $description = 'Purge les données expirées : liens de partage, invitations, sessions DB obsolètes';

    public function handle(): int
    {
        $dry = $this->option('dry-run');

        $this->purgeShareLinks($dry);
        $this->purgeInvitations($dry);
        $this->purgeSessions($dry);

        if ($dry) {
            $this->info('[dry-run] Aucune donnée supprimée.');
        }

        return self::SUCCESS;
    }

    private function purgeShareLinks(bool $dry): void
    {
        $query = MediaShareLink::where('expires_at', '<', now());
        $count = $query->count();

        if ($count === 0) {
            return;
        }

        $this->line("Liens de partage expirés : {$count}");

        if (! $dry) {
            $query->delete();
        }
    }

    private function purgeInvitations(bool $dry): void
    {
        $query = User::whereNotNull('invitation_token')
            ->whereNotNull('invitation_expires_at')
            ->whereNull('invitation_used_at')
            ->where('invitation_expires_at', '<', now());

        $count = $query->count();

        if ($count === 0) {
            return;
        }

        $this->line("Invitations expirées non utilisées : {$count}");

        if (! $dry) {
            $query->update([
                'invitation_token' => null,
                'invitation_expires_at' => null,
            ]);
        }
    }

    private function purgeSessions(bool $dry): void
    {
        $settings = TenantSettings::first();
        $lifetimeMinutes = $settings !== null ? $settings->session_lifetime_minutes : 120;
        $cutoff = now()->subMinutes($lifetimeMinutes)->timestamp;

        $count = DB::connection('tenant')
            ->table('sessions')
            ->where('last_activity', '<', $cutoff)
            ->count();

        if ($count === 0) {
            return;
        }

        $this->line("Sessions DB obsolètes (>{$lifetimeMinutes} min) : {$count}");

        if (! $dry) {
            DB::connection('tenant')
                ->table('sessions')
                ->where('last_activity', '<', $cutoff)
                ->delete();
        }
    }
}
