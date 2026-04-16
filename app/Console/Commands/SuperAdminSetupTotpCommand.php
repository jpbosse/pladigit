<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PragmaRX\Google2FA\Google2FA;

class SuperAdminSetupTotpCommand extends Command
{
    protected $signature = 'superadmin:setup-totp';

    protected $description = 'Génère un secret TOTP pour le super-admin et affiche le QR code provisioning URI';

    public function handle(): int
    {
        $g2fa = new Google2FA;

        $secret = $g2fa->generateSecretKey(32);

        $email = config('superadmin.email') ?? 'super-admin@pladigit';
        $issuer = 'Pladigit SA';
        $uri = $g2fa->getQRCodeUrl($issuer, $email, $secret);

        $this->newLine();
        $this->line('<fg=yellow>══════════════════════════════════════════════════</>');
        $this->line('<fg=yellow>  TOTP Super-Admin — Configuration initiale       </>');
        $this->line('<fg=yellow>══════════════════════════════════════════════════</>');
        $this->newLine();
        $this->line('1. Ajoutez cette ligne dans votre <fg=cyan>.env</> :');
        $this->newLine();
        $this->line("   <fg=green>SUPER_ADMIN_TOTP_SECRET={$secret}</>");
        $this->newLine();
        $this->line('2. Scannez ce lien dans <fg=cyan>Google Authenticator</> / <fg=cyan>Aegis</> :');
        $this->newLine();
        $this->line("   {$uri}");
        $this->newLine();
        $this->line('<fg=red>⚠  Conservez le secret en lieu sûr. Il ne sera plus affiché.</>');
        $this->line('<fg=yellow>══════════════════════════════════════════════════</>');
        $this->newLine();

        return self::SUCCESS;
    }
}
