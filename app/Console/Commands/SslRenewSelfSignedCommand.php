<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Renouvelle les certificats auto-signés avant leur expiration (365 jours).
 * Planifié tous les 6 mois — garantit que les tenants ne se retrouvent jamais
 * avec un certificat expiré, même si le super-admin est injoignable.
 */
class SslRenewSelfSignedCommand extends Command
{
    protected $signature = 'ssl:renew-self-signed';

    protected $description = 'Renouvelle les certificats auto-signés (validité 365 jours, renouvelés tous les 6 mois)';

    public function handle(): int
    {
        // Domaine principal
        $domain = parse_url(config('app.url'), PHP_URL_HOST);

        // Si Let's Encrypt est actif, rien à faire
        if (file_exists("/etc/letsencrypt/live/{$domain}/fullchain.pem")) {
            $this->info("Let's Encrypt actif pour {$domain} — aucun renouvellement auto-signé nécessaire.");

            return Command::SUCCESS;
        }

        $keyPath = '/etc/ssl/private/pladigit-selfsigned.key';
        $certPath = '/etc/ssl/certs/pladigit-selfsigned.crt';

        // Regénérer le certificat auto-signé
        $cmd = 'sudo openssl req -x509 -nodes -days 365 -newkey rsa:2048 '
                ."-keyout {$keyPath} "
                ."-out {$certPath} "
                ."-subj \"/CN={$domain}\" 2>&1";

        $output = shell_exec($cmd);

        if (file_exists($certPath)) {
            shell_exec('sudo /bin/systemctl reload nginx 2>&1');
            $this->info("✅ Certificat auto-signé renouvelé pour {$domain}.");
            Log::info("ssl:renew-self-signed — certificat renouvelé pour {$domain}");
        } else {
            $this->error('❌ Échec du renouvellement du certificat auto-signé.');
            Log::error("ssl:renew-self-signed — échec pour {$domain}", ['output' => $output]);

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
