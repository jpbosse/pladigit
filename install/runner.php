<?php

/**
 * Pladigit Install Runner — exécuté en arrière-plan par le wizard (api_run).
 *
 * Fichier FIXE et VERSIONNÉ (refonte installeur, point 4) :
 *   - aucune valeur cuite : tout est lu dans install/config.json à l'exécution
 *   - config.json est la seule source de vérité (installation.md §2)
 *   - le .env est écrit par le wizard (write_env) AVANT le lancement du runner
 *
 * Nettoyage sélectif en fin d'installation : seuls les artefacts sensibles
 * ou temporaires sont supprimés (config.json, logs, marqueurs). Les fichiers
 * versionnés (index.php, runner.php, modules/) et le verrou .lock sont
 * CONSERVÉS — le wizard reste verrouillé (403) et l'arbre Git reste propre.
 */
set_time_limit(0);
ini_set('display_errors', '0');

define('INSTALL_DIR', __DIR__);
define('PLADIGIT_ROOT', dirname(__DIR__));
define('CONFIG_FILE', INSTALL_DIR.'/config.json');
define('LOG_FILE', INSTALL_DIR.'/install.log');
define('PID_FILE', INSTALL_DIR.'/install.pid');
define('DONE_FILE', INSTALL_DIR.'/install.done');
define('FAIL_FILE', INSTALL_DIR.'/install.fail');
define('LOCK_FILE', INSTALL_DIR.'/.lock');

function ilog(string $msg): void
{
    $line = '['.date('H:i:s').'] '.trim($msg)."\n";
    file_put_contents(LOG_FILE, $line, FILE_APPEND);
}

function fail(string $msg): void
{
    ilog('✗ ERREUR : '.$msg);
    file_put_contents(FAIL_FILE, $msg);
    exit(1);
}

try {
    // ── Configuration — seule source de vérité ──────────────────────────────
    if (! file_exists(CONFIG_FILE)) {
        fail('config.json introuvable — le wizard doit être complété avant le lancement.');
    }
    $cfg = json_decode((string) file_get_contents(CONFIG_FILE), true);
    if (! is_array($cfg)) {
        fail('config.json illisible ou invalide.');
    }

    $db = $cfg['db'] ?? [];
    $app = $cfg['app'] ?? [];
    $admin = $cfg['admin'] ?? [];
    $collaboraMode = $cfg['collabora']['mode'] ?? 'skip';
    $gpgPassphrase = (string) ($cfg['security']['gpg_passphrase'] ?? '');

    $dbHost = (string) ($db['host'] ?? '127.0.0.1');
    $dbPort = (string) ($db['port'] ?? '3306');
    $dbName = (string) ($db['name'] ?? 'pladigit');
    $rootUser = (string) ($db['root_user'] ?? 'root');
    $rootPwd = (string) ($db['root_password'] ?? '');
    $appUser = (string) ($db['app_user'] ?? 'pladigit');
    $appPwd = (string) ($db['app_password'] ?? '');

    $appUrl = (string) ($app['url'] ?? '');
    $admEmail = (string) ($admin['email'] ?? '');

    $root = PLADIGIT_ROOT;

    // 0. Preflight — vérifier l'environnement AVANT toute écriture
    ilog('Vérification de l\'environnement (preflight)...');
    $missingExt = [];
    foreach (['pdo_mysql', 'redis', 'mbstring', 'openssl', 'json', 'curl'] as $ext) {
        if (! extension_loaded($ext)) {
            $missingExt[] = $ext;
        }
    }
    if ($missingExt) {
        fail('Extensions PHP manquantes : '.implode(', ', $missingExt));
    }
    try {
        $r = new Redis;
        $r->connect('127.0.0.1', 6379, 2.0);
        $r->ping();
        $r->close();
    } catch (Throwable $e) {
        fail('Redis injoignable sur 127.0.0.1:6379 — '.$e->getMessage());
    }
    foreach ([$root.'/storage', $root.'/bootstrap/cache', $root] as $dir) {
        if (! is_writable($dir)) {
            fail('Répertoire non accessible en écriture : '.$dir);
        }
    }
    ilog('✓ Preflight OK');

    // 1. Créer la base et l'utilisateur MySQL
    ilog('Connexion à MySQL...');
    $pdo = new PDO(
        "mysql:host={$dbHost};port={$dbPort};charset=utf8mb4",
        $rootUser,
        $rootPwd,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    ilog('✓ Connexion MySQL OK');

    ilog('Création de la base de données...');
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    ilog('✓ Base de données créée');

    ilog("Création de l'utilisateur MySQL {$appUser}...");
    // CREATE USER IF NOT EXISTS puis ALTER USER pour forcer le bon mot de passe
    // même si l'utilisateur existait déjà d'une installation précédente
    $appPwdSql = str_replace("'", "''", $appPwd);
    $appUserSql = str_replace("'", "''", $appUser);
    $pdo->exec("CREATE USER IF NOT EXISTS '{$appUserSql}'@'localhost' IDENTIFIED BY '{$appPwdSql}'");
    $pdo->exec("ALTER USER '{$appUserSql}'@'localhost' IDENTIFIED BY '{$appPwdSql}'");
    $pdo->exec("GRANT ALL PRIVILEGES ON *.* TO '{$appUserSql}'@'localhost' WITH GRANT OPTION");
    $pdo->exec('FLUSH PRIVILEGES');
    ilog('✓ Utilisateur MySQL créé');

    // 2. Vérifier le .env (écrit directement par le wizard avant le lancement)
    ilog('Vérification de la configuration...');
    if (! file_exists($root.'/.env') || filesize($root.'/.env') < 50) {
        fail('.env absent ou vide.');
    }
    ilog('✓ Configuration présente');

    // 2b. Vider le cache de configuration pour forcer la lecture du nouveau .env
    // (un cache résiduel d'une installation précédente masquerait DB_PASSWORD)
    shell_exec("cd {$root} && php artisan config:clear 2>&1");
    shell_exec("cd {$root} && php artisan cache:clear 2>&1");
    ilog('✓ Cache vidé');

    // 3. Migrations de base (jobs, cache, users) — racine de migrations/
    ilog('Création des tables de base...');
    $outBase = shell_exec("cd {$root} && php artisan migrate --path=database/migrations --force 2>&1");
    ilog($outBase ?? '');

    // 3b. Migrations platform (organizations, platform_settings, etc.)
    ilog('Création des tables plateforme...');
    $out = shell_exec("cd {$root} && php artisan migrate --path=database/migrations/platform --force 2>&1");
    ilog($out ?? '');
    if (empty($out) || str_contains((string) $out, 'ERROR') || str_contains((string) $out, 'SQLSTATE')) {
        fail('Migrations platform échouées : '.($out ?? 'aucune sortie'));
    }
    ilog('✓ Tables plateforme créées');

    // 4. Optimisation
    ilog('Optimisation du cache...');
    shell_exec("cd {$root} && php artisan config:cache 2>&1");
    shell_exec("cd {$root} && php artisan route:cache 2>&1");
    shell_exec("cd {$root} && php artisan view:cache 2>&1");
    ilog('✓ Cache généré');

    // 5. Storage link
    ilog('Liens symboliques storage...');
    shell_exec("cd {$root} && php artisan storage:link 2>&1");
    ilog('✓ Storage configuré');

    // 6. Workers : posés par install.sh (root). Le wizard ne configure pas Supervisor.
    ilog('Workers : configurés par install.sh (étape système).');

    // 6bis. Migrations tenant (initialise les bases existantes)
    ilog('Migrations tenant...');
    shell_exec("cd {$root} && php artisan migrate:tenants --force 2>&1");
    ilog('✓ Migrations tenant appliquées');

    // 7. Édition de documents : choix enregistré pour install.sh (qui provisionne en root).
    //    Le wizard n'installe RIEN qui exige root — il note seulement le choix.
    if ($collaboraMode === 'local') {
        ilog('Collabora : à installer en root après le wizard avec la commande :');
        ilog('  sudo bash install.sh --add-module collabora');
    } elseif ($collaboraMode === 'external') {
        ilog('Collabora externe : configuré via le .env (aucune installation locale).');
    } else {
        ilog('Édition de documents : aucun fournisseur (activable plus tard).');
    }

    file_put_contents(LOCK_FILE, date('d/m/Y H:i:s'));
    ilog('✓ Installation sécurisée');

    // 8. Page de succès dans public/
    $appUrlHtml = htmlspecialchars($appUrl, ENT_QUOTES);
    $admEmailHtml = htmlspecialchars($admEmail, ENT_QUOTES);
    $dbNameHtml = htmlspecialchars($dbName, ENT_QUOTES);
    $appUserHtml = htmlspecialchars($appUser, ENT_QUOTES);

    $successHtml = '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Pladigit install&#233;</title>'
        .'<style>*{box-sizing:border-box;margin:0;padding:0}body{font-family:system-ui,sans-serif;background:#F4F6F9;display:flex;align-items:center;justify-content:center;min-height:100vh;padding:1rem}.card{background:#fff;border-radius:10px;padding:2.5rem;max-width:560px;width:100%;box-shadow:0 4px 16px rgba(0,0,0,.08)}.icon{font-size:3rem;text-align:center;margin-bottom:1rem}.title{font-size:1.4rem;font-weight:700;color:#1E3A5F;text-align:center;margin-bottom:.5rem}.sub{font-size:.875rem;color:#6B7A8D;text-align:center;margin-bottom:1.5rem}.box{background:#F4F6F9;border-radius:8px;padding:1.25rem;margin-bottom:1rem}.bt{font-size:.72rem;font-weight:700;color:#1E3A5F;text-transform:uppercase;letter-spacing:.05em;margin-bottom:.75rem}.row{display:flex;justify-content:space-between;padding:.4rem 0;font-size:.85rem;border-bottom:1px solid #e5e7eb}.row:last-child{border:none}.lbl{font-weight:600;color:#1E3A5F}code{background:#e5e7eb;padding:.1rem .3rem;border-radius:3px;font-size:.78rem}.btn{display:block;background:#1E3A5F;color:#fff;padding:.875rem;border-radius:6px;text-decoration:none;font-weight:700;font-size:.95rem;text-align:center;margin-top:1.25rem}.ok{background:#F0FDF4;border:1px solid #BBF7D0;border-radius:6px;padding:.75rem;font-size:.82rem;color:#16A34A;margin-bottom:1.25rem}</style>'
        .'</head><body><div class="card">'
        .'<div class="icon">&#x1F389;</div>'
        .'<div class="title">Pladigit est install&#233; !</div>'
        .'<p class="sub">Votre plateforme est pr&#234;te. Notez ces informations.</p>'
        .'<div class="ok">&#x2705; Installation reussie le '.date('d/m/Y').' &#224; '.date('H:i').'</div>'
        .'<div class="box"><div class="bt">Super Administrateur</div>'
        .'<div class="row"><span class="lbl">URL</span><span>'.$appUrlHtml.'/super-admin</span></div>'
        .'<div class="row"><span class="lbl">Email</span><span><code>'.$admEmailHtml.'</code></span></div>'
        .'<div class="row"><span class="lbl">Mot de passe</span><span><em>Celui que vous avez defini</em></span></div>'
        .'</div>'
        .'<div class="box"><div class="bt">Base de donnees</div>'
        .'<div class="row"><span class="lbl">Base</span><span><code>'.$dbNameHtml.'</code></span></div>'
        .'<div class="row"><span class="lbl">Utilisateur</span><span><code>'.$appUserHtml.'</code></span></div>'
        .'</div>'
        .'<div class="box" style="background:#fffbeb;border:1px solid #fde68a;"><div class="bt" style="color:#92400e;">&#x26A0; S&#233;curit&#233; — Mots de passe</div><p style="font-size:.82rem;color:#78350f;line-height:1.5;">Plusieurs mots de passe ont &#233;t&#233; saisis durant l\'installation (MySQL, Super Admin, GPG...). <strong>Stockez-les imm&#233;diatement</strong> dans un gestionnaire de mots de passe : <strong>Bitwarden</strong>, KeePass, Vaultwarden ou similaire. Ne les notez jamais en clair par email ou SMS.</p></div>'
        .'<a id="btn-acc" href="'.$appUrlHtml.'/super-admin" class="btn">Acc&#233;der &#224; Pladigit &#x2192;</a>'
        .'<p id="hs" style="text-align:center;font-size:.8rem;color:#6B7A8D;margin-top:.75rem">En attente de l&#39;application&hellip;</p>'
        .'<div style="text-align:center;margin-top:1rem;padding:.75rem;background:#F4F6F9;border-radius:6px">'
        .'<div style="font-size:.72rem;color:#6B7A8D;margin-bottom:.35rem">Ou copiez-collez ce lien dans votre navigateur :</div>'
        .'<code style="font-size:.85rem;color:#1E3A5F;word-break:break-all;user-select:all">'.$appUrlHtml.'/super-admin</code>'
        .'</div>'
        .'<script>(function(){var u="'.$appUrlHtml.'",s=document.getElementById("hs"),t=0;function chk(){t++;fetch(u+"/health/ping",{cache:"no-store"}).then(function(r){if(r.ok){s.style.color="#16A34A";s.textContent="\u2713 Application pr\u00eate";}else next();}).catch(next);}function next(){if(t>=60){s.textContent="Si la page ne r\u00e9pond pas, patientez une minute puis cliquez sur le bouton.";return;}setTimeout(chk,3000);}setTimeout(chk,2000);})();<\/script>'
        .'</div></body></html>';
    file_put_contents($root.'/public/install-success.html', $successHtml);
    ilog('✓ Page de succes generee');

    // 8bis. Chiffrement GPG — sauvegardes + copie .env
    ilog('Configuration du chiffrement GPG...');
    if ($gpgPassphrase !== '') {
        // Vérifier que gpg est disponible
        exec('which gpg 2>/dev/null', $gpgOut, $gpgCode);
        if ($gpgCode !== 0) {
            shell_exec('apt-get install -y -qq gnupg 2>/dev/null');
        }

        // Activer GPG dans platform_settings via artisan.
        // La passphrase transite en base64 : aucun problème d'échappement,
        // quel que soit son contenu (apostrophes, guillemets, antislashs).
        $b64 = base64_encode($gpgPassphrase);
        $tinkerCode = 'try {'
            .'$ps = App\\Models\\Platform\\PlatformSettings::firstOrCreate([]);'
            .'$ps->backup_gpg_enabled = true;'
            ."\$ps->backup_gpg_passphrase_enc = Illuminate\\Support\\Facades\\Crypt::encryptString(base64_decode('{$b64}'));"
            .'$ps->save();'
            .'echo "OK";'
            .'} catch(\\Throwable $e) { echo "ERR:" . $e->getMessage(); }';
        // HOME=/tmp : psysh (tinker) exige un HOME inscriptible, or celui de
        // www-data (/var/www) ne l'est pas — sans cela, l'activation échoue
        // silencieusement (bug historique, révélé par l'extraction du runner).
        $out = shell_exec('cd '.escapeshellarg($root).' && HOME=/tmp php artisan tinker --execute='.escapeshellarg($tinkerCode).' 2>&1');
        if (str_contains((string) $out, 'OK')) {
            ilog('✓ Chiffrement GPG activé en base');
        } else {
            ilog('⚠ GPG base : '.trim((string) $out));
        }

        // Chiffrer la copie du .env
        $envFile = $root.'/.env';
        $envBackup = '/root/.pladigit_env_backup.gpg';
        $cmd = sprintf(
            'gpg --batch --yes --symmetric --cipher-algo AES256 --passphrase %s --output %s %s 2>&1',
            escapeshellarg($gpgPassphrase),
            escapeshellarg($envBackup),
            escapeshellarg($envFile)
        );
        exec($cmd, $gpgResult, $gpgExit);
        if ($gpgExit === 0 && file_exists($envBackup)) {
            ilog('✓ Copie chiffrée du .env créée : '.$envBackup);
        } else {
            ilog('⚠ Copie .env GPG échouée — à refaire manuellement (voir docs/deploy/secrets.md)');
        }
    } else {
        ilog('⚠ Passphrase GPG absente — chiffrement non activé');
    }

    // 9. Fichier DONE — le wizard affiche le succès immédiatement
    file_put_contents(DONE_FILE, date('d/m/Y H:i:s'));
    ilog('✓ Installation terminee avec succes !');

    // 10. Nettoyage SÉLECTIF différé (10 min) — refonte point 4 :
    //     exécuté PAR LE RUNNER LUI-MÊME (plus de script /tmp ni de nohup
    //     intermédiaire : le processus qui nettoie est celui dont la survie
    //     vient d'être démontrée par l'installation complète).
    //     Supprime UNIQUEMENT les artefacts sensibles ou temporaires.
    //     CONSERVE : index.php, runner.php, modules/ (fichiers versionnés
    //     → arbre Git propre) et .lock (le wizard reste verrouillé en 403).
    sleep(600);
    foreach ([CONFIG_FILE, LOG_FILE, PID_FILE, DONE_FILE, FAIL_FILE] as $sensitive) {
        @unlink($sensitive);
    }
} catch (Throwable $ex) {
    fail($ex->getMessage());
}
