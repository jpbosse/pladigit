<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class SslController extends Controller
{
    /**
     * Active Let's Encrypt pour le domaine principal (APP_URL).
     * Lancé depuis le bandeau super-admin.
     */
    public function activate(): JsonResponse
    {
        $domain = parse_url(config('app.url'), PHP_URL_HOST);
        $email = config('superadmin.email', 'contact@'.$domain);

        if (empty($domain)) {
            return response()->json(['ok' => false, 'message' => 'APP_URL non configuré.']);
        }

        // Certificat déjà valide ?
        if (file_exists("/etc/letsencrypt/live/{$domain}/fullchain.pem")) {
            // Vérifier qu'il n'est pas expiré
            $expiry = shell_exec("openssl x509 -enddate -noout -in /etc/letsencrypt/live/{$domain}/fullchain.pem 2>/dev/null");
            if ($expiry && ! str_contains((string) $expiry, 'error')) {
                return response()->json([
                    'ok' => true,
                    'message' => 'Certificat déjà actif pour '.$domain.'. Rechargement...',
                ]);
            }
        }

        $certbot = $this->findCertbot();

        // Vérification DNS
        $serverIp = trim((string) shell_exec('curl -4 -sf --max-time 5 https://ifconfig.me 2>/dev/null'));
        $dnsIp = gethostbyname($domain);
        if (! empty($serverIp) && $dnsIp !== $serverIp) {
            return response()->json([
                'ok' => false,
                'message' => "Le domaine {$domain} ne pointe pas sur ce serveur ({$dnsIp} ≠ {$serverIp}). Vérifiez votre DNS.",
            ]);
        }

        $cmd = "sudo {$certbot} --nginx -d {$domain} --non-interactive --agree-tos --email {$email} --redirect 2>&1";
        $output = shell_exec($cmd);

        Log::info('ssl:activate super-admin', ['cmd' => $cmd, 'output' => substr((string) $output, 0, 1000)]);

        $success = str_contains((string) $output, 'Successfully received certificate')
                || str_contains((string) $output, 'Certificate not yet due for renewal')
                || file_exists("/etc/letsencrypt/live/{$domain}/fullchain.pem");

        if ($success) {
            // S'assurer que le cron de renouvellement est en place
            if (! shell_exec('crontab -l 2>/dev/null | grep "certbot renew"')) {
                shell_exec('(crontab -l 2>/dev/null; echo "0 3 * * * certbot renew --quiet --post-hook \'systemctl reload nginx\'") | crontab -');
            }

            return response()->json([
                'ok' => true,
                'message' => "HTTPS activé pour {$domain} ! La page va se recharger.",
            ]);
        }

        // Extraire le message d'erreur utile
        $errorMsg = $this->extractCertbotError((string) $output);

        return response()->json([
            'ok' => false,
            'message' => $errorMsg,
        ]);
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

    private function extractCertbotError(string $output): string
    {
        if (str_contains($output, 'too many certificates')) {
            // Extraire la date de retry si disponible
            preg_match('/retry after ([^:]+UTC)/', $output, $m);
            $retryInfo = isset($m[1]) ? ' Réessayez après : '.$m[1] : '';

            return 'Quota Let\'s Encrypt atteint (5 certificats/semaine).'.$retryInfo;
        }
        if (str_contains($output, 'DNS')) {
            return 'Problème DNS — le domaine ne pointe pas encore sur ce serveur.';
        }
        if (str_contains($output, 'Connection refused') || str_contains($output, 'Timeout')) {
            return 'Certbot n\'a pas pu joindre Let\'s Encrypt. Vérifiez la connexion réseau.';
        }

        return 'Échec Let\'s Encrypt. Vérifiez le journal : /var/log/letsencrypt/letsencrypt.log';
    }
}
