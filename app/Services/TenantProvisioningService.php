<?php

namespace App\Services;

use App\Models\Platform\Organization;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Provisionne une nouvelle base de données pour un tenant.
 *
 * Séquence :
 *   1. Créer la base MySQL dédiée
 *   2. Connecter TenantManager sur cette base
 *   3. Exécuter les migrations tenant
 *   4. Insérer tenant_settings par défaut
 *   5. Passer l'organisation en statut 'active'
 *   6. Obtenir un certificat SSL pour le sous-domaine (best-effort)
 *
 * En cas d'échec à n'importe quelle étape (hors SSL) :
 *   - La base MySQL créée est supprimée (DROP DATABASE)
 *   - L'organisation reste en statut 'pending'
 *   - Une ProvisioningException est levée avec le contexte complet
 *
 * Note : CREATE/DROP DATABASE sont des DDL MySQL — non rollbackables
 * via DB::transaction(). La compensation est donc manuelle.
 */
class TenantProvisioningService
{
    /**
     * @throws ProvisioningException Si le provisioning échoue à n'importe quelle étape
     */
    public function provisionTenant(Organization $org): void
    {
        $dbCreated = false;

        try {
            // 1. Créer la base de données
            DB::statement("CREATE DATABASE IF NOT EXISTS `{$org->db_name}`
                CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $dbCreated = true;

            // 2. Activer la connexion tenant vers cette nouvelle base
            app(TenantManager::class)->connectTo($org);

            // 3. Exécuter les migrations tenant
            $exitCode = Artisan::call('migrate', [
                '--database' => 'tenant',
                '--path' => 'database/migrations/tenant',
                '--force' => true,
            ]);

            if ($exitCode !== 0) {
                throw new \RuntimeException(
                    'Les migrations tenant ont échoué (exit code '.$exitCode.'). '.
                    'Sortie Artisan : '.Artisan::output()
                );
            }

            // 4. Insérer la ligne tenant_settings par défaut
            DB::connection('tenant')->table('tenant_settings')->insertOrIgnore([
                'updated_at' => now(),
            ]);

            // 5. Activer l'organisation
            $org->update(['status' => 'active']);

            // 6. Obtenir un certificat SSL pour le sous-domaine (best-effort)
            $this->provisionSsl($org);

        } catch (\Throwable $e) {
            // Compensation : supprimer la base si elle a été créée
            if ($dbCreated) {
                try {
                    DB::statement("DROP DATABASE IF EXISTS `{$org->db_name}`");
                } catch (\Throwable $dropException) {
                    Log::error('TenantProvisioningService : impossible de supprimer la base après échec', [
                        'org' => $org->slug,
                        'db_name' => $org->db_name,
                        'drop_error' => $dropException->getMessage(),
                    ]);
                }
            }

            // L'org reste en 'pending' — pas d'update status
            Log::error('TenantProvisioningService : échec du provisioning', [
                'org' => $org->slug,
                'db_name' => $org->db_name,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new ProvisioningException(
                "Échec du provisioning pour « {$org->name} » : ".$e->getMessage(),
                previous: $e
            );
        }
    }

    /**
     * Tente d'obtenir un certificat SSL Let's Encrypt pour le sous-domaine du tenant.
     *
     * Séquence :
     *   1. Vérifier que certbot est disponible
     *   2. Construire le FQDN du sous-domaine (slug.domaine.fr)
     *   3. Court-circuiter si le certificat existe déjà
     *   4. Créer un bloc Nginx HTTP minimal pour le sous-domaine
     *      (nécessaire pour que certbot --nginx trouve le bon vhost)
     *   5. Lancer certbot --nginx sur ce sous-domaine
     *   6. Recharger Nginx
     *
     * Opération best-effort : un échec ne bloque pas le provisioning.
     * Le tenant sera actif en HTTP si SSL échoue.
     */
    private function provisionSsl(Organization $org): void
    {
        // 1. Vérifier que certbot est disponible
        $certbot = match (true) {
            file_exists('/usr/bin/certbot') => '/usr/bin/certbot',
            file_exists('/usr/local/bin/certbot') => '/usr/local/bin/certbot',
            default => null,
        };

        if ($certbot === null) {
            Log::info('TenantProvisioningService : certbot absent — SSL ignoré', ['org' => $org->slug]);

            return;
        }

        // 2. Construire le FQDN
        $rootHost = parse_url(config('app.url'), PHP_URL_HOST);
        $domain = $org->slug.'.'.$rootHost;

        // 3. Court-circuiter si le certificat existe déjà
        if (file_exists("/etc/letsencrypt/live/{$domain}/fullchain.pem")) {
            Log::info('TenantProvisioningService : certificat SSL déjà présent', ['domain' => $domain]);

            return;
        }

        // 4. Créer un bloc Nginx HTTP minimal dédié à ce sous-domaine
        //    Certbot --nginx en a besoin pour trouver le vhost et injecter le bloc 443.
        //    Sans ça, il modifie le bloc wildcard *.domaine.fr → résultat imprévisible.
        $nginxConf = base_path("../../../etc/nginx/sites-available/pladigit-tenant-{$org->slug}");
        $nginxEnabled = base_path("../../../etc/nginx/sites-enabled/pladigit-tenant-{$org->slug}");
        $pladigitRoot = base_path('../public');

        $vhost = <<<NGINX
        # Bloc temporaire pour l'obtention SSL — sera remplacé par certbot
        server {
            listen 80;
            listen [::]:80;
            server_name {$domain};

            root {$pladigitRoot};
            index index.php;

            location /.well-known/acme-challenge/ {
                root /var/www/html;
            }

            location / {
                return 301 https://\$host\$request_uri;
            }
        }
        NGINX;

        // Écrire via un fichier temporaire passé à sudo tee (www-data n'a pas accès à /etc/nginx)
        $tmpConf = sys_get_temp_dir()."/pladigit-nginx-{$org->slug}.conf";
        file_put_contents($tmpConf, $vhost);

        $cpCmd = sprintf('sudo cp %s %s 2>&1', escapeshellarg($tmpConf), escapeshellarg($nginxConf));
        $lnCmd = sprintf('sudo ln -sf %s %s 2>&1', escapeshellarg($nginxConf), escapeshellarg($nginxEnabled));
        $ngxCmd = 'sudo nginx -t 2>&1 && sudo systemctl reload nginx 2>&1';

        shell_exec($cpCmd);
        shell_exec($lnCmd);
        shell_exec($ngxCmd);

        @unlink($tmpConf);

        // 5. Lancer certbot
        $email = config('superadmin.email', 'contact@'.$rootHost);

        $certCmd = sprintf(
            'sudo %s --nginx -d %s --non-interactive --agree-tos --email %s --redirect 2>&1',
            $certbot,
            escapeshellarg($domain),
            escapeshellarg($email)
        );

        $output = shell_exec($certCmd);
        $success = str_contains($output ?? '', 'Congratulations')
            || str_contains($output ?? '', 'Certificate not yet due');

        if ($success) {
            // 6. Recharger Nginx pour activer la config SSL injectée par certbot
            shell_exec('sudo systemctl reload nginx 2>&1');

            // Permissions lecture cert pour www-data (health checks, etc.)
            shell_exec(sprintf('sudo chmod 755 /etc/letsencrypt/live/%s/ 2>&1', escapeshellarg($domain)));

            Log::info('TenantProvisioningService : certificat SSL obtenu', [
                'domain' => $domain,
                'org' => $org->slug,
            ]);
        } else {
            Log::warning('TenantProvisioningService : échec SSL — tenant actif en HTTP', [
                'domain' => $domain,
                'output' => substr($output ?? '', 0, 500),
            ]);
        }
    }
}
