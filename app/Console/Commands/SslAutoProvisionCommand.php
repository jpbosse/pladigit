<?php

namespace App\Console\Commands;

use App\Mail\SslActivatedMail;
use App\Mail\SslFailedMail;
use App\Models\Platform\Organization;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Tentative automatique d'activation HTTPS via Let's Encrypt.
 *
 * Déclenchement : 7 jours après ssl_requested_at (première connexion admin avec cert auto-signé).
 * Relances : J+14, J+21 (3 tentatives max).
 * Succès : Nginx reconfiguré, email à super-admin + admin tenant.
 * Échec : Email d'alerte, tentative suivante planifiée.
 */
class SslAutoProvisionCommand extends Command
{
    protected $signature = 'ssl:auto-provision {--force : Forcer la tentative même si délai non atteint}';

    protected $description = "Tente d'activer HTTPS Let's Encrypt automatiquement pour les tenants avec certificat auto-signé";

    private const MAX_ATTEMPTS = 3;

    private const DELAY_DAYS = 7;

    public function handle(): int
    {
        $candidates = Organization::where('ssl_type', 'self_signed')
            ->whereNotNull('ssl_requested_at')
            ->where('ssl_attempt_count', '<', self::MAX_ATTEMPTS)
            ->where('status', 'active')
            ->get();

        if ($candidates->isEmpty()) {
            $this->info('Aucun tenant à traiter.');

            return Command::SUCCESS;
        }

        foreach ($candidates as $org) {
            $this->processOrganization($org);
        }

        return Command::SUCCESS;
    }

    private function processOrganization(Organization $org): void
    {
        $daysElapsed = now()->diffInDays($org->ssl_requested_at);
        $requiredDays = self::DELAY_DAYS * ($org->ssl_attempt_count + 1);

        if (! $this->option('force') && $daysElapsed < $requiredDays) {
            $this->line("⏳ {$org->slug} — J+{$daysElapsed} / J+{$requiredDays} requis. Pas encore.");

            return;
        }

        $attemptNumber = $org->ssl_attempt_count + 1;
        $this->info("🔒 Tentative SSL pour {$org->slug} (tentative #{$attemptNumber})...");

        $domain = $this->tenantDomain($org);
        $email = config('superadmin.email', 'contact@'.parse_url(config('app.url'), PHP_URL_HOST));
        $certbot = $this->findCertbot();

        // Vérification DNS avant de contacter Let's Encrypt
        if (! $this->dnsPointsHere($domain)) {
            $this->warn("⚠  DNS {$domain} ne pointe pas sur ce serveur. Tentative ignorée.");
            $this->recordAttempt($org, false, 'DNS ne pointe pas sur ce serveur');

            return;
        }

        // Lancement certbot
        $cmd = "{$certbot} --nginx -d {$domain} --non-interactive --agree-tos --email {$email} --redirect 2>&1";
        $output = shell_exec($cmd);
        $success = str_contains((string) $output, 'Successfully received certificate')
                || str_contains((string) $output, 'Certificate not yet due for renewal')
                || file_exists("/etc/letsencrypt/live/{$domain}/fullchain.pem");

        $this->recordAttempt($org, $success, $output ?? '');

        if ($success) {
            $this->activateHttpsNginx($org, $domain);
            $org->update(['ssl_type' => 'letsencrypt']);
            $this->info("✅ HTTPS activé pour {$domain}");
            $this->sendSuccessMails($org, $domain);
        } else {
            $this->warn("❌ Échec Let's Encrypt pour {$domain}");
            Log::warning("ssl:auto-provision échec [{$org->slug}]", ['output' => $output]);
            $this->sendFailureMails($org, $domain, $output ?? 'Erreur inconnue');
        }
    }

    private function recordAttempt(Organization $org, bool $success, string $log): void
    {
        $org->update([
            'ssl_last_attempt_at' => now(),
            'ssl_attempt_count' => $org->ssl_attempt_count + 1,
        ]);

        Log::info("ssl:auto-provision [{$org->slug}] tentative #{$org->ssl_attempt_count}", [
            'success' => $success,
            'log' => substr($log, 0, 500),
        ]);
    }

    /**
     * Injecte le bloc HTTPS dans la config Nginx du tenant.
     * Certbot --nginx le fait déjà automatiquement.
     * On recharge juste Nginx pour être sûr.
     */
    private function activateHttpsNginx(Organization $org, string $domain): void
    {
        shell_exec('sudo /bin/systemctl reload nginx 2>&1');
    }

    private function sendSuccessMails(Organization $org, string $domain): void
    {
        $superAdminEmail = config('superadmin.email');
        if ($superAdminEmail) {
            try {
                Mail::to($superAdminEmail)->send(new SslActivatedMail($org, $domain));
            } catch (\Throwable $e) {
                Log::warning("ssl:auto-provision — email super-admin échoué: {$e->getMessage()}");
            }
        }
    }

    private function sendFailureMails(Organization $org, string $domain, string $error): void
    {
        $superAdminEmail = config('superadmin.email');
        if ($superAdminEmail) {
            try {
                Mail::to($superAdminEmail)->send(new SslFailedMail($org, $domain, $error));
            } catch (\Throwable $e) {
                Log::warning("ssl:auto-provision — email échec super-admin échoué: {$e->getMessage()}");
            }
        }
    }

    private function tenantDomain(Organization $org): string
    {
        $rootHost = parse_url(config('app.url'), PHP_URL_HOST);

        return $org->slug.'.'.$rootHost;
    }

    private function findCertbot(): string
    {
        foreach (['/usr/bin/certbot', '/usr/local/bin/certbot'] as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        return 'certbot';
    }

    private function dnsPointsHere(string $domain): bool
    {
        $serverIp = trim((string) shell_exec('curl -4 -sf --max-time 5 https://ifconfig.me 2>/dev/null'));
        if (empty($serverIp)) {
            return true; // On ne peut pas vérifier — on tente quand même
        }
        $dnsIp = gethostbyname($domain);

        return $dnsIp === $serverIp;
    }
}
