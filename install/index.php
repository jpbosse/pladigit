<?php
/**
 * Pladigit — Assistant d'installation web v2.0
 * Wizard 6 étapes — PHP standalone, sans framework
 * Compatible PHP 8.2+
 */

session_start();

define('PLADIGIT_ROOT', dirname(__DIR__));
define('ENV_FILE',    PLADIGIT_ROOT . '/.env');
define('LOCK_FILE',   PLADIGIT_ROOT . '/install/.lock');
define('INSTALL_DIR', PLADIGIT_ROOT . '/install');
define('LOG_FILE',    INSTALL_DIR . '/install.log');
define('PID_FILE',    INSTALL_DIR . '/install.pid');
define('DONE_FILE',   INSTALL_DIR . '/install.done');
define('CONFIG_FILE',  INSTALL_DIR . '/config.json');
define('FAIL_FILE',   INSTALL_DIR . '/install.fail');


// =============================================================================
// CONFIG JSON — persistance des choix wizard indépendante de la session
// =============================================================================
function save_config(array $data): void {
    $current = file_exists(CONFIG_FILE)
        ? (json_decode(file_get_contents(CONFIG_FILE), true) ?? [])
        : [];
    $merged = array_merge($current, $data);
    file_put_contents(CONFIG_FILE, json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function load_config(): array {
    if (!file_exists(CONFIG_FILE)) return [];
    return json_decode(file_get_contents(CONFIG_FILE), true) ?? [];
}

// ── Les appels API passent toujours (même après installation) ────────────────
$apiAction = $_GET['action'] ?? '';
if (in_array($apiAction, ['api_log', 'api_status', 'api_run'])) {
    // Laisser passer — géré plus bas
} elseif (file_exists(LOCK_FILE) && !empty($_SESSION['install_success'])) {
    header('Location: ?action=success');
    exit;
} elseif (file_exists(LOCK_FILE)) {
    $d = trim(@file_get_contents(LOCK_FILE) ?: 'date inconnue');
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    echo locked_page($d);
    exit;
}

// ── Router ────────────────────────────────────────────────────────────────────
$action = $_GET['action'] ?? $_POST['action'] ?? 'welcome';

// API endpoints (appelés en AJAX)
if ($action === 'api_log')    { api_log();    exit; }
if ($action === 'api_status') { api_status(); exit; }
if ($action === 'api_run')    { api_run();    exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') handle_post($action);

render_page($action);

// =============================================================================
// PAGE "DÉJÀ INSTALLÉ"
// =============================================================================
function locked_page(string $date): string {
    return '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8">
<title>Pladigit — Déjà installé</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:system-ui,sans-serif;background:#F4F6F9;display:flex;align-items:center;justify-content:center;min-height:100vh}
.box{background:#fff;border-radius:10px;padding:2.5rem;max-width:500px;width:100%;margin:1rem;text-align:center;box-shadow:0 4px 16px rgba(0,0,0,.08)}
h1{color:#1E3A5F;font-size:1.3rem;margin:.875rem 0 .5rem}
p{color:#6B7A8D;font-size:.875rem;line-height:1.6;margin-bottom:1rem}
.date{background:#F4F6F9;border-radius:6px;padding:.5rem 1rem;font-size:.8rem;color:#6B7A8D;margin-bottom:1.25rem}
.warn{background:#FEF3C7;border:1px solid #FDE68A;border-radius:6px;padding:.875rem;font-size:.82rem;color:#92400E;margin-bottom:1.25rem;text-align:left;line-height:1.6}
.btn{display:inline-block;background:#1E3A5F;color:#fff;padding:.65rem 1.5rem;border-radius:6px;text-decoration:none;font-weight:700;font-size:.875rem}
code{background:#eee;padding:.1rem .35rem;border-radius:3px;font-size:.78rem}
</style></head><body><div class="box">
<div style="font-size:2.5rem">&#x1F512;</div>
<h1>Pladigit est déjà installé</h1>
<p>Cet assistant a déjà été utilisé sur ce serveur.</p>
<div class="date">&#x1F4C5; Installé le : ' . htmlspecialchars($date) . '</div>
<div class="warn"><strong>&#x26A0;&#xFE0F; Réinstaller ?</strong><br>
Supprimez le fichier <code>install/.lock</code> sur votre serveur, puis rechargez cette page.<br>
<strong>Attention :</strong> votre fichier <code>.env</code> sera réécrit.</div>
<a href="/" class="btn">&#x2190; Retourner à l\'accueil</a>
</div></body></html>';
}

// =============================================================================
// API AJAX
// =============================================================================
function api_log(): void {
    header('Content-Type: application/json');
    $offset = (int)($_GET['offset'] ?? 0);
    if (!file_exists(LOG_FILE)) { echo json_encode(['lines'=>[],'offset'=>0,'done'=>false,'error'=>false]); return; }
    $content = file_get_contents(LOG_FILE);
    $lines   = array_filter(explode("\n", substr($content, $offset)));
    echo json_encode([
        'lines'  => array_values($lines),
        'offset' => strlen($content),
        'done'   => file_exists(DONE_FILE),
        'error'  => file_exists(FAIL_FILE),
    ]);
}

function api_status(): void {
    header('Content-Type: application/json');
    $done  = file_exists(DONE_FILE);
    $error = file_exists(FAIL_FILE);
    if ($done) {
        $_SESSION['install_success'] = true;
        $_SESSION['app_url']         = $_SESSION['app']['url'] ?? '';
        $_SESSION['step']            = 7;
    }
    echo json_encode([
        'done'     => $done,
        'error'    => $error,
        'msg'      => $error ? trim(@file_get_contents(FAIL_FILE) ?: '') : '',
        'redirect' => $done ? '?action=success' : null,
    ]);
}

function api_run(): void {
    header('Content-Type: application/json');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['ok'=>false]); return; }

    // Écrire le runner depuis le JSON — source de vérité unique
    write_runner();

    // Nettoyer les fichiers précédents
    @unlink(LOG_FILE);
    @unlink(DONE_FILE);
    @unlink(FAIL_FILE);
    @unlink(CONFIG_FILE);

    // Lancer l'installation en arrière-plan
    $script = escapeshellarg(INSTALL_DIR . '/runner.php');
    $log    = escapeshellarg(LOG_FILE);
    $cmd    = "php {$script} > {$log} 2>&1 &";
    shell_exec($cmd);

    echo json_encode(['ok' => true]);
}

// =============================================================================
// HANDLERS POST
// =============================================================================
function handle_post(string $action): void {
    switch ($action) {
        case 'check':
            $_SESSION['step'] = 2;
            redirect('database');
            break;

        case 'database':
            $errors = validate_database($_POST);
            if ($errors) { $_SESSION['errors'] = $errors; redirect('database'); }
            $db = [
                'host'          => trim($_POST['db_host'] ?? '127.0.0.1'),
                'port'          => trim($_POST['db_port'] ?? '3306'),
                'name'          => trim($_POST['db_name'] ?? 'pladigit'),
                'root_user'     => trim($_POST['db_root_user'] ?? 'root'),
                'root_password' => $_POST['db_root_password'] ?? '',
                'app_user'      => trim($_POST['db_app_user'] ?? 'pladigit'),
                'app_password'  => $_POST['db_app_password'] ?? '',
            ];
            $_SESSION['db'] = $db;
            save_config(['db' => $db]);
            $_SESSION['step'] = 3;
            redirect('app');
            break;

        case 'app':
            $errors = validate_app($_POST);
            if ($errors) { $_SESSION['errors'] = $errors; redirect('app'); }
            $app = [
                'url'      => rtrim(trim($_POST['app_url'] ?? ''), '/'),
                'name'     => trim($_POST['app_name'] ?? 'Pladigit'),
                'timezone' => trim($_POST['app_timezone'] ?? 'Europe/Paris'),
            ];
            $_SESSION['app'] = $app;
            save_config(['app' => $app]);
            $_SESSION['step'] = 4;
            redirect('smtp');
            break;

        case 'smtp':
            $smtp = [
                'host'       => trim($_POST['smtp_host'] ?? ''),
                'port'       => trim($_POST['smtp_port'] ?? '587'),
                'username'   => trim($_POST['smtp_username'] ?? ''),
                'password'   => $_POST['smtp_password'] ?? '',
                'from'       => trim($_POST['smtp_from'] ?? ''),
                'from_name'  => trim($_POST['smtp_from_name'] ?? 'Pladigit'),
                'encryption' => trim($_POST['smtp_encryption'] ?? 'tls'),
            ];
            $_SESSION['smtp'] = $smtp;
            save_config(['smtp' => $smtp]);
            $_SESSION['step'] = 5;
            redirect('collabora');
            break;

        case 'collabora':
            $collabora = [
                'mode' => $_POST['collabora_mode'] ?? 'skip',
                'url'  => trim($_POST['collabora_url'] ?? ''),
            ];
            $_SESSION['collabora'] = $collabora;
            save_config(['collabora' => $collabora]);
            $_SESSION['step'] = 6;
            redirect('admin');
            break;

        case 'admin':
            $errors = validate_admin($_POST);
            if ($errors) { $_SESSION['errors'] = $errors; redirect('admin'); }
            $admin = [
                'name'     => trim($_POST['admin_name'] ?? ''),
                'email'    => trim($_POST['admin_email'] ?? ''),
                'password' => $_POST['admin_password'] ?? '',
            ];
            $_SESSION['admin'] = $admin;
            save_config(['admin' => $admin]);
            $_SESSION['step'] = 7;
            redirect('install');
            break;
    }
}

// =============================================================================
// VALIDATIONS
// =============================================================================
function validate_database(array $p): array {
    $e = [];
    if (empty($p['db_host']))       $e[] = "L'hôte MySQL est requis.";
    if (empty($p['db_name']))       $e[] = "Le nom de la base est requis.";
    if (empty($p['db_root_user']))  $e[] = "L'utilisateur root MySQL est requis.";
    if (empty($p['db_app_user']))   $e[] = "L'utilisateur applicatif est requis.";
    if (empty($p['db_app_password'])) $e[] = "Le mot de passe applicatif est requis (min. 8 caractères).";
    elseif (strlen($p['db_app_password']) < 8) $e[] = "Le mot de passe MySQL doit faire au moins 8 caractères.";

    if (!$e) {
        try {
            new PDO(
                "mysql:host={$p['db_host']};port={$p['db_port']};charset=utf8mb4",
                $p['db_root_user'], $p['db_root_password'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]
            );
        } catch (\PDOException $ex) {
            $e[] = "Connexion MySQL impossible : " . htmlspecialchars($ex->getMessage());
        }
    }
    return $e;
}

function validate_app(array $p): array {
    $e = [];
    if (empty($p['app_url'])) $e[] = "L'URL est requise.";
    elseif (!filter_var($p['app_url'], FILTER_VALIDATE_URL)) $e[] = "URL invalide (ex: http://192.168.1.10).";
    return $e;
}

function validate_admin(array $p): array {
    $e = [];
    if (empty($p['admin_name']))  $e[] = "Le nom est requis.";
    if (empty($p['admin_email']) || !filter_var($p['admin_email'], FILTER_VALIDATE_EMAIL)) $e[] = "Email invalide.";
    if (strlen($p['admin_password'] ?? '') < 12) $e[] = "Mot de passe : 12 caractères minimum.";
    if (($p['admin_password'] ?? '') !== ($p['admin_password_confirm'] ?? '')) $e[] = "Les mots de passe ne correspondent pas.";
    return $e;
}

// =============================================================================
// RUNNER — script PHP exécuté en arrière-plan
// =============================================================================
function write_runner(): void {
    $cfg       = load_config();
    $db        = $cfg['db']        ?? $_SESSION['db']        ?? [];
    $app       = $cfg['app']       ?? $_SESSION['app']       ?? [];
    $smtp      = $cfg['smtp']      ?? $_SESSION['smtp']      ?? [];
    $admin     = $cfg['admin']     ?? $_SESSION['admin']     ?? [];
    $collabora = $cfg['collabora'] ?? $_SESSION['collabora'] ?? [];

    $appKey      = 'base64:' . base64_encode(random_bytes(32));
    $passwordHash = password_hash($admin['password'], PASSWORD_BCRYPT);

    $envContent = build_env($db, $app, $smtp, $admin, $appKey, $passwordHash);

    // Écriture directe du .env depuis le wizard (fiable, pas d'échappement)
    file_put_contents(PLADIGIT_ROOT . '/.env', $envContent);
    chmod(PLADIGIT_ROOT . '/.env', 0640);

    $envEscaped  = addslashes($envContent);

    $root    = addslashes(PLADIGIT_ROOT);
    $done    = addslashes(DONE_FILE);
    $fail    = addslashes(FAIL_FILE);
    $lock    = addslashes(LOCK_FILE);
    $appUrl  = addslashes($app['url'] ?? '');
    $appName = addslashes($app['name'] ?? 'Pladigit');
    $admEmail = addslashes($admin['email'] ?? '');
    $dbNm    = addslashes($db['name'] ?? 'pladigit');
    $dbUsr   = addslashes($db['app_user'] ?? 'pladigit');
    $appPwd = addslashes($db['app_password']);
    $rootPwd = addslashes($db['root_password']);
    $dbHost = addslashes($db['host']);
    $dbPort = addslashes($db['port']);
    $dbName = addslashes($db['name']);
    $appUser = addslashes($db['app_user']);
    $rootUser = addslashes($db['root_user']);

    $script = <<<RUNNER
<?php
/**
 * Pladigit Install Runner — exécuté en arrière-plan par le wizard
 */
set_time_limit(0);
ini_set('display_errors', 0);

function ilog(string \$msg): void {
    \$line = '[' . date('H:i:s') . '] ' . trim(\$msg) . "\n";
    file_put_contents('{$done}' === '' ? '/tmp/install.log' : dirname('{$done}') . '/install.log', \$line, FILE_APPEND);
}

function fail(string \$msg): void {
    ilog('✗ ERREUR : ' . \$msg);
    file_put_contents('{$fail}', \$msg);
    exit(1);
}

\$logFile = dirname('{$done}') . '/install.log';

try {
    // 1. Créer la base et l'utilisateur MySQL
    ilog('Connexion à MySQL...');
    \$pdo = new PDO(
        'mysql:host={$dbHost};port={$dbPort};charset=utf8mb4',
        '{$rootUser}', '{$rootPwd}',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    ilog('✓ Connexion MySQL OK');

    ilog('Création de la base de données...');
    \$pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    ilog('✓ Base de données créée');

    ilog("Création de l'utilisateur MySQL {$appUser}...");
    \$pdo->exec("CREATE USER IF NOT EXISTS '{$appUser}'@'localhost' IDENTIFIED BY '{$appPwd}'");
    \$pdo->exec("GRANT ALL PRIVILEGES ON *.* TO '{$appUser}'@'localhost' WITH GRANT OPTION");
    \$pdo->exec("FLUSH PRIVILEGES");
    ilog('✓ Utilisateur MySQL créé');

    // 2. Vérifier le .env (écrit directement par le wizard)
    ilog('Vérification de la configuration...');
    if (!file_exists('{$root}/.env') || filesize('{$root}/.env') < 50) {
        fail('.env absent ou vide.');
    }
    ilog('✓ Configuration présente');

    // 3. Migrations
    ilog('Création des tables (migrations)...');
    \$out = shell_exec('cd {$root} && php artisan migrate --force 2>&1');
    ilog(\$out ?? '');
    ilog('✓ Tables créées');

    ilog('Migrations plateforme...');
    \$out = shell_exec('cd {$root} && php artisan migrate --path=database/migrations/platform --force 2>&1');
    ilog(\$out ?? '');
    ilog('✓ Tables plateforme créées');

    // 4. Optimisation
    ilog('Optimisation du cache...');
    shell_exec('cd {$root} && php artisan config:cache 2>&1');
    shell_exec('cd {$root} && php artisan route:cache 2>&1');
    shell_exec('cd {$root} && php artisan view:cache 2>&1');
    ilog('✓ Cache généré');

    // 5. Storage link
    ilog('Liens symboliques storage...');
    shell_exec('cd {$root} && php artisan storage:link 2>&1');
    ilog('✓ Storage configuré');

    // 6. Supervisor
    ilog('Configuration des workers...');
    \$supervisorConf = "[program:pladigit-worker]\nprocess_name=%(program_name)s_%(process_num)02d\ncommand=php {$root}/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600\nautostart=true\nautorestart=true\nstopasgroup=true\nkillasgroup=true\nuser=www-data\nnumprocs=2\nredirect_stderr=true\nstdout_logfile=/var/log/pladigit-worker.log\nstopwaitsecs=3600\n";
    @file_put_contents('/etc/supervisor/conf.d/pladigit.conf', \$supervisorConf);
    shell_exec('supervisorctl reread 2>&1 && supervisorctl update 2>&1');
    ilog('✓ Workers configurés');

    // 7. Collabora Online (si demandé)
    \$collaboraMode = '{$collaboraMode}';
    if (\$collaboraMode === 'local') {
        ilog('Collabora : démarrage de l\'installation Docker...');
        ilog('Collabora : cette étape peut durer 10 à 20 minutes (téléchargement ~1.5 Go)');

        \$collaboraScript = '{$root}/install/install-collabora.sh';
        \$logFile = dirname('{$done}') . '/install.log';

        if (!file_exists(\$collaboraScript) || !is_executable(\$collaboraScript)) {
            ilog('✗ ERREUR : install-collabora.sh absent ou non exécutable.');
            ilog('  → Collabora non installé. Activez-le manuellement depuis les paramètres.');
        } else {
            \$cmd = "sudo {$root}/install/install-collabora.sh {\$logFile} {$appUrl} {$root} 2>&1";
            \$handle = popen(\$cmd, 'r');
            if (\$handle) {
                while (!feof(\$handle)) {
                    fread(\$handle, 1024);
                    usleep(500000);
                }
                \$exitCode = pclose(\$handle);
                if (\$exitCode !== 0) {
                    ilog('⚠ Collabora : installation incomplète (code ' . \$exitCode . ')');
                    ilog('  → Pladigit fonctionnera sans Collabora. Activez-le depuis les paramètres.');
                }
            } else {
                ilog('✗ Impossible de lancer install-collabora.sh (sudo non configuré ?)');
                ilog('  → Collabora non installé. Activez-le depuis les paramètres.');
            }
        }
    } elseif (\$collaboraMode === 'external' && !empty('{$collaboraUrl}')) {
        \$env = file_get_contents('{$root}/.env');
        \$collUrl = '{$collaboraUrl}';
        if (strpos(\$env, 'COLLABORA_URL') === false) {
            \$env .= "\nCOLLABORA_URL={\$collUrl}\n";
        } else {
            \$env = preg_replace('/COLLABORA_URL=.*/', "COLLABORA_URL={\$collUrl}", \$env);
        }
        file_put_contents('{$root}/.env', \$env);
        ilog('✓ COLLABORA_URL externe configurée dans .env');
    }

    file_put_contents('{$lock}', date('d/m/Y H:i:s'));
    ilog('✓ Installation sécurisée');

    // 8. Page de succès dans public/

    \$successHtml = '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Pladigit install&#233;</title>'
        . '<style>*{box-sizing:border-box;margin:0;padding:0}body{font-family:system-ui,sans-serif;background:#F4F6F9;display:flex;align-items:center;justify-content:center;min-height:100vh;padding:1rem}.card{background:#fff;border-radius:10px;padding:2.5rem;max-width:560px;width:100%;box-shadow:0 4px 16px rgba(0,0,0,.08)}.icon{font-size:3rem;text-align:center;margin-bottom:1rem}.title{font-size:1.4rem;font-weight:700;color:#1E3A5F;text-align:center;margin-bottom:.5rem}.sub{font-size:.875rem;color:#6B7A8D;text-align:center;margin-bottom:1.5rem}.box{background:#F4F6F9;border-radius:8px;padding:1.25rem;margin-bottom:1rem}.bt{font-size:.72rem;font-weight:700;color:#1E3A5F;text-transform:uppercase;letter-spacing:.05em;margin-bottom:.75rem}.row{display:flex;justify-content:space-between;padding:.4rem 0;font-size:.85rem;border-bottom:1px solid #e5e7eb}.row:last-child{border:none}.lbl{font-weight:600;color:#1E3A5F}code{background:#e5e7eb;padding:.1rem .3rem;border-radius:3px;font-size:.78rem}.btn{display:block;background:#1E3A5F;color:#fff;padding:.875rem;border-radius:6px;text-decoration:none;font-weight:700;font-size:.95rem;text-align:center;margin-top:1.25rem}.ok{background:#F0FDF4;border:1px solid #BBF7D0;border-radius:6px;padding:.75rem;font-size:.82rem;color:#16A34A;margin-bottom:1.25rem}</style>'
        . '</head><body><div class="card">'
        . '<div class="icon">&#x1F389;</div>'
        . '<div class="title">Pladigit est install&#233; !</div>'
        . '<p class="sub">Votre plateforme est pr&#234;te. Notez ces informations.</p>'
        . '<div class="ok">&#x2705; Installation reussie le ' . date('d/m/Y') . ' &#224; ' . date('H:i') . '</div>'
        . '<div class="box"><div class="bt">Super Administrateur</div>'
        . '<div class="row"><span class="lbl">URL</span><span>' . '{$appUrl}' . '/super-admin</span></div>'
        . '<div class="row"><span class="lbl">Email</span><span><code>' . '{$admEmail}' . '</code></span></div>'
        . '<div class="row"><span class="lbl">Mot de passe</span><span><em>Celui que vous avez defini</em></span></div>'
        . '</div>'
        . '<div class="box"><div class="bt">Base de donnees</div>'
        . '<div class="row"><span class="lbl">Base</span><span><code>' . '{$dbNm}' . '</code></span></div>'
        . '<div class="row"><span class="lbl">Utilisateur</span><span><code>' . '{$dbUsr}' . '</code></span></div>'
        . '</div>'
        . '<a href="' . '{$appUrl}' . '/super-admin" class="btn">Acc&#233;der &#224; Pladigit &#x2192;</a>'
        . '</div></body></html>';
    file_put_contents('{$root}/public/install-success.html', \$successHtml);
    ilog('✓ Page de succes generee');

    // 9. Fichier DONE
    file_put_contents('{$done}', date('d/m/Y H:i:s'));
    ilog('✓ Installation terminee avec succes !');

    // 9. Nettoyage dans 60s
    \$cleanup = "#!/bin/bash\nsleep 600 && rm -rf " . dirname('{$done}') . "\n";
    file_put_contents('/tmp/pladigit-cleanup.sh', \$cleanup);
    chmod('/tmp/pladigit-cleanup.sh', 0755);
    shell_exec('nohup /tmp/pladigit-cleanup.sh > /dev/null 2>&1 &');

} catch (\Throwable \$ex) {
    fail(\$ex->getMessage());
}
RUNNER;

    file_put_contents(INSTALL_DIR . '/runner.php', $script);
}

function build_env(array $db, array $app, array $smtp, array $admin, string $key, string $hash): string {
    return 'APP_NAME="' . addslashes($app['name']) . '"' . "\n"
        . 'APP_ENV=production' . "\n"
        . 'APP_KEY=' . $key . "\n"
        . 'APP_DEBUG=false' . "\n"
        . 'APP_URL=' . $app['url'] . "\n"
        . 'APP_TIMEZONE=' . $app['timezone'] . "\n\n"
        . 'LOG_CHANNEL=daily' . "\n"
        . 'LOG_LEVEL=error' . "\n\n"
        . 'DB_CONNECTION=mysql' . "\n"
        . 'DB_HOST=' . $db['host'] . "\n"
        . 'DB_PORT=' . $db['port'] . "\n"
        . 'DB_DATABASE=' . $db['name'] . "\n"
        . 'DB_USERNAME=' . $db['app_user'] . "\n"
        . 'DB_PASSWORD=' . $db['app_password'] . "\n\n"
        . 'CACHE_DRIVER=redis' . "\n"
        . 'QUEUE_CONNECTION=redis' . "\n"
        . 'SESSION_DRIVER=redis' . "\n"
        . 'SESSION_LIFETIME=120' . "\n\n"
        . 'REDIS_HOST=127.0.0.1' . "\n"
        . 'REDIS_PASSWORD=null' . "\n"
        . 'REDIS_PORT=6379' . "\n\n"
        . 'MAIL_MAILER=smtp' . "\n"
        . 'MAIL_HOST=' . $smtp['host'] . "\n"
        . 'MAIL_PORT=' . $smtp['port'] . "\n"
        . 'MAIL_USERNAME=' . $smtp['username'] . "\n"
        . 'MAIL_PASSWORD=' . $smtp['password'] . "\n"
        . 'MAIL_SCHEME=' . $smtp['encryption'] . "\n"
        . 'MAIL_FROM_ADDRESS=' . $smtp['from'] . "\n"
        . 'MAIL_FROM_NAME="' . addslashes($smtp['from_name']) . '"' . "\n\n"
        . 'SUPER_ADMIN_EMAIL=' . $admin['email'] . "\n"
        . 'SUPER_ADMIN_PASSWORD_HASH=' . $hash . "\n";
}

function redirect(string $a): void { header("Location: ?action={$a}"); exit; }

// =============================================================================
// RENDU HTML
// =============================================================================
function render_page(string $action): void {
    $step   = $_SESSION['step'] ?? 0;
    $errors = $_SESSION['errors'] ?? [];
    unset($_SESSION['errors']);
    $steps  = ['Bienvenue', 'Vérification', 'Base de données', 'Application', 'Email', 'Collabora', 'Administrateur', 'Installation'];

    html_open();
    html_steps($step, $steps);

    switch ($action) {
        case 'welcome':  page_welcome();         break;
        case 'check':    $_SESSION['step'] = 1; page_check(); break;
        case 'database': page_database($errors); break;
        case 'app':      page_app($errors);      break;
        case 'smtp':     page_smtp();            break;
        case 'collabora': page_collabora();        break;
        case 'admin':    page_admin($errors);    break;
        case 'install':  page_install();         break;
        case 'success':  page_success();         break;
        default:         page_welcome();
    }

    html_close();
}

function html_open(): void { ?>
<!DOCTYPE html><html lang="fr"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Installation Pladigit</title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{--navy:#1E3A5F;--gold:#C4972A;--light:#F4F6F9;--grey:#6B7A8D;--green:#16A34A;--red:#DC2626;--white:#fff}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;background:var(--light);color:#1A2332;min-height:100vh}
.hdr{background:var(--navy);padding:1.25rem 2rem}
.logo{font-size:1.4rem;font-weight:700;color:#fff;letter-spacing:-.02em}
.logo span{color:var(--gold)}
.logo-sub{font-size:.78rem;color:rgba(255,255,255,.5);margin-top:.15rem}
.steps-bar{background:#fff;border-bottom:1px solid rgba(30,58,95,.1);padding:.875rem 2rem;overflow-x:auto}
.steps{display:flex;align-items:center;min-width:560px}
.step{display:flex;align-items:center;gap:.4rem;flex:1}
.sn{width:26px;height:26px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.72rem;font-weight:700;flex-shrink:0;background:var(--light);color:var(--grey);border:2px solid #e5e7eb;transition:all .3s}
.step.done .sn{background:var(--green);color:#fff;border-color:var(--green)}
.step.active .sn{background:var(--navy);color:#fff;border-color:var(--navy)}
.sl{font-size:.72rem;color:var(--grey);white-space:nowrap}
.step.done .sl,.step.active .sl{color:var(--navy);font-weight:600}
.sline{flex:1;height:2px;background:#e5e7eb;margin:0 .4rem;transition:background .3s}
.step.done .sline{background:var(--green)}
.wrap{max-width:660px;margin:2.5rem auto;padding:0 1.5rem}
.card{background:#fff;border-radius:10px;border:1px solid rgba(30,58,95,.1);padding:2.5rem;box-shadow:0 2px 8px rgba(30,58,95,.06)}
.card-title{font-size:1.35rem;font-weight:700;color:var(--navy);margin-bottom:.35rem}
.card-sub{font-size:.9rem;color:var(--grey);line-height:1.6;margin-bottom:1.75rem}
.fg{margin-bottom:1.15rem}
.lbl{display:block;font-size:.8rem;font-weight:600;color:var(--navy);margin-bottom:.3rem}
.hint{font-size:.73rem;color:var(--grey);margin-top:.2rem}
.inp,.sel{width:100%;padding:.6rem .85rem;border:1px solid #d1d5db;border-radius:6px;font-size:.875rem;outline:none;transition:border-color .2s;background:#fff}
.inp:focus,.sel:focus{border-color:var(--navy);box-shadow:0 0 0 3px rgba(30,58,95,.08)}
.row2{display:grid;grid-template-columns:1fr 1fr;gap:1rem}
.row3{display:grid;grid-template-columns:2fr 1fr 1fr;gap:1rem}
.btn{padding:.7rem 1.75rem;border-radius:6px;font-size:.875rem;font-weight:700;cursor:pointer;border:none;transition:all .2s;text-decoration:none;display:inline-flex;align-items:center;gap:.4rem}
.btn-p{background:var(--navy);color:#fff}.btn-p:hover{background:#162D4A}
.btn-s{background:var(--light);color:var(--navy);border:1px solid rgba(30,58,95,.2)}.btn-s:hover{background:#e8edf2}
.btn-g{background:var(--green);color:#fff;font-size:1rem;padding:.875rem 2.5rem}.btn-g:hover{background:#15803d}
.btns{display:flex;gap:.875rem;margin-top:1.75rem;justify-content:flex-end}
.alert{padding:.875rem 1.125rem;border-radius:6px;font-size:.85rem;margin-bottom:1.25rem;line-height:1.6}
.ae{background:#FEF2F2;border:1px solid #FECACA;color:var(--red)}
.as{background:#F0FDF4;border:1px solid #BBF7D0;color:var(--green)}
.ai{background:#EFF6FF;border:1px solid #BFDBFE;color:#1d4ed8}
.aw{background:#FFFBEB;border:1px solid #FDE68A;color:#92400E}
.chk{display:flex;align-items:center;gap:.75rem;padding:.55rem 0;border-bottom:1px solid var(--light)}
.chk:last-child{border:none}
.chk-v{font-size:.8rem;color:var(--grey);margin-left:auto}
/* Barre de progression */
.prog-wrap{background:#e5e7eb;border-radius:999px;height:10px;overflow:hidden;margin:1.25rem 0}
.prog-bar{height:100%;background:var(--green);border-radius:999px;transition:width .4s ease;width:0%}
.prog-label{font-size:.82rem;color:var(--grey);margin-bottom:.4rem;display:flex;justify-content:space-between}
/* Log */
.log-toggle{font-size:.78rem;color:var(--navy);cursor:pointer;text-decoration:underline;margin-bottom:.5rem;display:inline-block}
.logbox{display:none;background:#0f172a;color:#e2e8f0;border-radius:8px;padding:1rem;font-family:'Courier New',monospace;font-size:.72rem;line-height:1.6;max-height:250px;overflow-y:auto;margin-top:.5rem}
.logbox.visible{display:block}
.lok{color:#4ade80}.lerr{color:#f87171}.linfo{color:#93c5fd}
/* Étapes visuelles */
.install-step{display:flex;align-items:center;gap:.75rem;padding:.5rem 0;font-size:.875rem}
.install-step .icon{width:24px;text-align:center;font-size:1rem}
.install-step.pending{color:#9ca3af}
.install-step.running{color:var(--navy);font-weight:600}
.install-step.done2{color:var(--green)}
.install-step.error2{color:var(--red)}
/* Spinner */
@keyframes spin{to{transform:rotate(360deg)}}
.spin{display:inline-block;animation:spin 1s linear infinite}
/* Success */
.cred-box{background:var(--light);border-radius:8px;padding:1.25rem 1.5rem;margin:1rem 0}
.cred-row{display:flex;align-items:center;justify-content:space-between;padding:.4rem 0;font-size:.875rem;border-bottom:1px solid #e5e7eb}
.cred-row:last-child{border:none}
.cred-label{font-weight:600;color:var(--navy)}
code{background:var(--light);padding:.12rem .35rem;border-radius:3px;font-size:.8rem;color:var(--navy)}
@media(max-width:600px){.row2,.row3{grid-template-columns:1fr}.wrap{padding:0 1rem}.card{padding:1.5rem}}
</style></head><body>
<div class="hdr">
  <div class="logo">Pladi<span>git</span></div>
  <div class="logo-sub">Assistant d'installation</div>
</div>
<?php }

function html_close(): void { ?>
</body></html>
<?php }

function html_steps(int $cur, array $steps): void { ?>
<div class="steps-bar"><div class="steps">
<?php foreach ($steps as $i => $l):
    $c = $i < $cur ? 'done' : ($i === $cur ? 'active' : ''); ?>
<div class="step <?= $c ?>">
  <div class="sn"><?= $i < $cur ? '✓' : ($i+1) ?></div>
  <div class="sl"><?= htmlspecialchars($l) ?></div>
  <?php if ($i < count($steps)-1): ?><div class="sline"></div><?php endif; ?>
</div>
<?php endforeach; ?>
</div></div>
<?php }

function errs(array $e): void {
    if (!$e) return;
    echo '<div class="alert ae"><strong>Erreur :</strong><ul style="margin:.4rem 0 0 1.1rem">';
    foreach ($e as $i) echo '<li>' . htmlspecialchars($i) . '</li>';
    echo '</ul></div>';
}

// =============================================================================
// PAGES
// =============================================================================
function page_welcome(): void { ?>
<div class="wrap"><div class="card">
<div style="text-align:center;margin-bottom:1.75rem">
  <div style="font-size:3rem;margin-bottom:.875rem">&#x1F3DB;</div>
  <div class="card-title" style="font-size:1.5rem">Bienvenue dans Pladigit</div>
  <p class="card-sub" style="max-width:460px;margin:.5rem auto 0">Cet assistant configure votre plateforme en quelques minutes.<br><strong>Aucune connaissance technique requise.</strong></p>
</div>
<div style="background:var(--light);border-radius:8px;padding:1.25rem;margin-bottom:1.5rem">
  <div style="font-weight:700;color:var(--navy);margin-bottom:.75rem">Ce que nous allons faire :</div>
  <?php foreach ([
      ['&#x1F5C4;', 'Connecter la base de données MySQL'],
      ['&#x1F310;', "Définir l'adresse de votre plateforme"],
      ['&#x1F4E7;', "Configurer l'envoi d'emails (optionnel)"],
      ['&#x1F464;', 'Créer votre compte administrateur'],
      ['&#x1F680;', "Lancer l'installation automatique"],
  ] as [$icon, $label]): ?>
  <div style="display:flex;align-items:center;gap:.6rem;padding:.35rem 0;font-size:.875rem">
    <span><?= $icon ?></span><span><?= htmlspecialchars($label) ?></span>
  </div>
  <?php endforeach; ?>
</div>
<div class="alert ai"><strong>Durée estimée :</strong> 5 à 10 minutes.</div>
<div class="btns" style="justify-content:center">
  <a href="?action=check" class="btn btn-p">Commencer l'installation &#x2192;</a>
</div>
</div></div>
<?php }

function page_check(): void {
    $checks = [
        ['PHP >= 8.2',            version_compare(PHP_VERSION, '8.2.0', '>='), PHP_VERSION],
        ['Extension pdo_mysql',   extension_loaded('pdo_mysql'),   extension_loaded('pdo_mysql')   ? 'OK' : 'Manquante'],
        ['Extension mbstring',    extension_loaded('mbstring'),    extension_loaded('mbstring')    ? 'OK' : 'Manquante'],
        ['Extension redis',       extension_loaded('redis'),       extension_loaded('redis')       ? 'OK' : 'Manquante'],
        ['Extension gd',          extension_loaded('gd'),          extension_loaded('gd')          ? 'OK' : 'Manquante'],
        ['Extension zip',         extension_loaded('zip'),         extension_loaded('zip')         ? 'OK' : 'Manquante'],
        ['Extension curl',        extension_loaded('curl'),        extension_loaded('curl')        ? 'OK' : 'Manquante'],
        ['Extension ldap',        extension_loaded('ldap'),        extension_loaded('ldap')        ? 'OK' : 'Manquante'],
        ['storage/ accessible',   is_writable(PLADIGIT_ROOT . '/storage'), is_writable(PLADIGIT_ROOT . '/storage') ? 'OK' : 'Non accessible'],
        ['Racine en écriture',    is_writable(PLADIGIT_ROOT),     is_writable(PLADIGIT_ROOT)      ? 'OK' : 'Non accessible'],
        ['shell_exec disponible', function_exists('shell_exec'),  function_exists('shell_exec')   ? 'OK' : 'Désactivé'],
    ];
    $allOk = array_reduce($checks, fn($c, $i) => $c && $i[1], true);
    ?>
<div class="wrap"><div class="card">
<div class="card-title">Vérification du système</div>
<p class="card-sub">Votre serveur est-il prêt pour Pladigit ?</p>
<?php foreach ($checks as [$lbl, $ok, $val]): ?>
<div class="chk">
  <span><?= $ok ? '&#x2705;' : '&#x274C;' ?></span>
  <span style="font-size:.875rem;flex:1"><?= htmlspecialchars($lbl) ?></span>
  <span class="chk-v"><?= htmlspecialchars($val) ?></span>
</div>
<?php endforeach; ?>
<?php if (!$allOk): ?>
<div class="alert ae" style="margin-top:1.25rem">Corrigez les erreurs avant de continuer.</div>
<?php else: ?>
<div class="alert as" style="margin-top:1.25rem">Tout est en ordre ! Votre serveur est compatible.</div>
<?php endif; ?>
<div class="btns">
  <a href="?action=check" class="btn btn-s">&#x21BA; Relancer</a>
  <?php if ($allOk): ?>
  <form method="POST"><input type="hidden" name="action" value="check">
  <button type="submit" class="btn btn-p">Continuer &#x2192;</button></form>
  <?php endif; ?>
</div>
</div></div>
<?php }

function page_database(array $e): void {
    $ip = $_SERVER['SERVER_ADDR'] ?? '127.0.0.1';
    ?>
<div class="wrap"><div class="card">
<div class="card-title">&#x1F5C4; Base de données</div>
<p class="card-sub">Connexion à MySQL. L'utilisateur "root" sert uniquement à créer la base. Pladigit utilisera ensuite un compte dédié que vous définissez ci-dessous.</p>
<?php errs($e) ?>
<form method="POST"><input type="hidden" name="action" value="database">

<div style="background:var(--light);border-radius:8px;padding:1rem 1.25rem;margin-bottom:1.25rem">
  <div style="font-size:.8rem;font-weight:700;color:var(--navy);margin-bottom:.75rem;text-transform:uppercase;letter-spacing:.05em">Connexion administrateur MySQL (root)</div>
  <div class="row3">
    <div class="fg"><label class="lbl">Hôte MySQL</label><input type="text" name="db_host" class="inp" value="127.0.0.1" required><div class="hint">Généralement 127.0.0.1</div></div>
    <div class="fg"><label class="lbl">Port</label><input type="text" name="db_port" class="inp" value="3306"></div>
    <div class="fg"><label class="lbl">Utilisateur root</label><input type="text" name="db_root_user" class="inp" value="root" required></div>
  </div>
  <div class="fg"><label class="lbl">Mot de passe root</label><input type="password" name="db_root_password" class="inp" placeholder="Laisser vide si aucun"><div class="hint">&#x1F4A1; Sur une nouvelle installation Ubuntu, ce mot de passe est <strong>vide</strong> — laissez le champ vide. Pour sécuriser votre serveur après installation, lancez : <code>sudo mysql_secure_installation</code></div></div>
</div>

<div style="background:var(--light);border-radius:8px;padding:1rem 1.25rem;margin-bottom:1.25rem">
  <div style="font-size:.8rem;font-weight:700;color:var(--navy);margin-bottom:.75rem;text-transform:uppercase;letter-spacing:.05em">Compte dédié Pladigit</div>
  <div class="fg"><label class="lbl">Nom de la base de données</label><input type="text" name="db_name" class="inp" value="pladigit" required><div class="hint">Sera créée automatiquement.</div></div>
  <div class="row2">
    <div class="fg"><label class="lbl">Nom d'utilisateur</label><input type="text" name="db_app_user" class="inp" value="pladigit" required></div>
    <div class="fg"><label class="lbl">Mot de passe (min. 8 car.)</label><input type="password" name="db_app_password" class="inp" placeholder="Choisissez un mot de passe" required minlength="8"></div>
  </div>
</div>

<div class="btns">
  <a href="?action=check" class="btn btn-s">&#x2190; Retour</a>
  <button type="submit" class="btn btn-p">Tester et continuer &#x2192;</button>
</div>
</form></div></div>
<?php }

function page_app(array $e): void {
    $ip = $_SERVER['SERVER_ADDR'] ?? '127.0.0.1';
    ?>
<div class="wrap"><div class="card">
<div class="card-title">&#x1F310; Paramètres de l'application</div>
<p class="card-sub">L'adresse à laquelle vos agents accéderont à Pladigit.</p>
<?php errs($e) ?>
<form method="POST"><input type="hidden" name="action" value="app">
<div class="fg"><label class="lbl">URL de l'application</label>
  <input type="text" name="app_url" class="inp" value="http://<?= htmlspecialchars($ip) ?>" required>
  <div class="hint">Incluez http:// ou https://. Ex : https://pladigit.macommune.fr</div>
</div>
<div class="fg"><label class="lbl">Nom de votre organisation</label>
  <input type="text" name="app_name" class="inp" value="Pladigit" placeholder="Mairie de Saint-Aubin-les-Communes">
</div>
<div class="fg"><label class="lbl">Fuseau horaire</label>
  <select name="app_timezone" class="sel">
    <option value="Europe/Paris" selected>Europe/Paris (France métropolitaine)</option>
    <option value="America/Martinique">Martinique</option>
    <option value="America/Guadeloupe">Guadeloupe</option>
    <option value="Indian/Reunion">La Réunion</option>
    <option value="Indian/Mayotte">Mayotte</option>
    <option value="Pacific/Noumea">Nouvelle-Calédonie</option>
  </select>
</div>
<div class="btns">
  <a href="?action=database" class="btn btn-s">&#x2190; Retour</a>
  <button type="submit" class="btn btn-p">Continuer &#x2192;</button>
</div>
</form></div></div>
<?php }

function page_smtp(): void { ?>
<div class="wrap"><div class="card">
<div class="card-title">&#x1F4E7; Configuration email</div>
<p class="card-sub">Pour les notifications et réinitialisations de mots de passe. <strong>Optionnel</strong> — configurable plus tard dans les paramètres.</p>
<form method="POST"><input type="hidden" name="action" value="smtp">
<div class="row2">
  <div class="fg"><label class="lbl">Serveur SMTP</label><input type="text" name="smtp_host" class="inp" placeholder="smtp.mail.ovh.net"></div>
  <div class="fg"><label class="lbl">Port</label>
    <select name="smtp_port" class="sel">
      <option value="587">587 — TLS (recommandé)</option>
      <option value="465">465 — SSL</option>
      <option value="25">25 — Non chiffré</option>
    </select>
  </div>
</div>
<div class="row2">
  <div class="fg"><label class="lbl">Identifiant SMTP</label><input type="text" name="smtp_username" class="inp" placeholder="contact@macommune.fr"></div>
  <div class="fg"><label class="lbl">Mot de passe SMTP</label><input type="password" name="smtp_password" class="inp"></div>
</div>
<div class="row2">
  <div class="fg"><label class="lbl">Adresse expéditeur</label><input type="email" name="smtp_from" class="inp" placeholder="noreply@macommune.fr"></div>
  <div class="fg"><label class="lbl">Nom expéditeur</label><input type="text" name="smtp_from_name" class="inp" value="Pladigit"></div>
</div>
<div class="fg"><label class="lbl">Chiffrement</label>
  <select name="smtp_encryption" class="sel">
    <option value="tls">TLS (port 587)</option>
    <option value="smtps">SMTPS (port 465)</option>
    <option value="">Aucun</option>
  </select>
</div>
<div class="btns">
  <a href="?action=app" class="btn btn-s">&#x2190; Retour</a>
  <button type="submit" name="skip" value="1" class="btn btn-s">Passer cette étape</button>
  <button type="submit" class="btn btn-p">Continuer &#x2192;</button>
</div>
</form></div></div>
<?php }


function page_collabora(): void {
    $freeBytes   = disk_free_space('/');
    $freeGb      = round($freeBytes / 1024 / 1024 / 1024, 1);
    $enough      = $freeGb >= 4;
    $tight       = $freeGb >= 2 && $freeGb < 4;
    $cfg         = load_config();
    $savedMode   = $cfg['collabora']['mode'] ?? ($_SESSION['collabora']['mode'] ?? ($enough ? 'local' : 'skip'));
    $savedUrl    = $cfg['collabora']['url']  ?? ($_SESSION['collabora']['url']  ?? '');
    ?>
<div class="wrap"><div class="card">
<div class="card-title">&#x1F4DD; Collabora Online</div>
<p class="card-sub">Collabora permet d'éditer des documents ODT, ODS et Microsoft Office directement dans le navigateur, sans installer de logiciel sur les postes.</p>

<div style="background:var(--light);border-radius:8px;padding:1rem 1.25rem;margin-bottom:1.5rem">
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.5rem">
    <span style="font-size:.875rem;font-weight:600;color:var(--navy)">Espace disque disponible</span>
    <span style="font-size:.875rem;font-weight:700;color:<?= $enough ? "var(--green)" : ($tight ? "#D97706" : "var(--red)") ?>">
      <?= $freeGb ?> Go libres
    </span>
  </div>
  <?php if ($enough): ?>
  <div class="alert as" style="margin:0;padding:.6rem .875rem">&#x2705; Suffisant — installation recommandée (~2 Go requis)</div>
  <?php elseif ($tight): ?>
  <div class="alert aw" style="margin:0;padding:.6rem .875rem">&#x26A0;&#xFE0F; Juste — possible mais surveillez l'espace disque</div>
  <?php else: ?>
  <div class="alert ae" style="margin:0;padding:.6rem .875rem">&#x274C; Insuffisant — utilisez une instance externe ou passez</div>
  <?php endif; ?>
</div>

<form method="POST"><input type="hidden" name="action" value="collabora">
<div class="fg">
  <label class="lbl">Que souhaitez-vous faire ?</label>
  <div style="display:flex;flex-direction:column;gap:.75rem;margin-top:.5rem">

    <label style="display:flex;align-items:flex-start;gap:.875rem;background:var(--light);border-radius:8px;padding:1rem;cursor:pointer">
      <input type="radio" name="collabora_mode" value="local" <?= $savedMode === 'local' ? 'checked' : '' ?> onchange="toggleUrl(this.value)" style="margin-top:.2rem;flex-shrink:0">
      <div>
        <div style="font-weight:600;font-size:.875rem;color:var(--navy)">&#x1F4E6; Installer sur ce serveur (Docker)</div>
        <div style="font-size:.78rem;color:var(--grey);margin-top:.2rem">Collabora sera installé automatiquement via Docker. Nécessite ~2 Go d'espace disque et <strong>10 à 20 minutes supplémentaires</strong> selon votre connexion (téléchargement de l'image Docker).</div>
      </div>
    </label>

    <label style="display:flex;align-items:flex-start;gap:.875rem;background:var(--light);border-radius:8px;padding:1rem;cursor:pointer">
      <input type="radio" name="collabora_mode" value="external" <?= $savedMode === 'external' ? 'checked' : '' ?> onchange="toggleUrl(this.value)" style="margin-top:.2rem;flex-shrink:0">
      <div style="flex:1">
        <div style="font-weight:600;font-size:.875rem;color:var(--navy)">&#x1F310; Utiliser une instance existante</div>
        <div style="font-size:.78rem;color:var(--grey);margin-top:.2rem">Si vous disposez déjà d'un serveur Collabora Online.</div>
        <div id="collabora-url" style="display:none;margin-top:.75rem">
          <input type="text" name="collabora_url" class="inp" value="<?= htmlspecialchars($savedUrl) ?>" placeholder="https://collabora.macommune.fr">
        </div>
      </div>
    </label>

    <label style="display:flex;align-items:flex-start;gap:.875rem;background:var(--light);border-radius:8px;padding:1rem;cursor:pointer">
      <input type="radio" name="collabora_mode" value="skip" <?= $savedMode === 'skip' ? 'checked' : '' ?> onchange="toggleUrl(this.value)" style="margin-top:.2rem;flex-shrink:0">
      <div>
        <div style="font-weight:600;font-size:.875rem;color:var(--navy)">&#x23F0; Passer — configurer plus tard</div>
        <div style="font-size:.78rem;color:var(--grey);margin-top:.2rem">Vous pourrez activer Collabora depuis les paramètres.</div>
      </div>
    </label>

  </div>
</div>
<div class="btns">
  <a href="?action=smtp" class="btn btn-s">&#x2190; Retour</a>
  <button type="submit" class="btn btn-p">Continuer &#x2192;</button>
</div>
</form>
</div></div>
<script>
function toggleUrl(val) {
    document.getElementById('collabora-url').style.display = val === 'external' ? 'block' : 'none';
}
var checked = document.querySelector('input[name="collabora_mode"]:checked');
if (checked) toggleUrl(checked.value);
</script>
<?php }

function page_admin(array $e): void { ?>
<div class="wrap"><div class="card">
<div class="card-title">&#x1F464; Compte Super Administrateur</div>
<p class="card-sub">Ce compte permet d'administrer toute la plateforme Pladigit (création des organisations, gestion des abonnements).</p>
<?php errs($e) ?>
<form method="POST"><input type="hidden" name="action" value="admin">
<div class="fg"><label class="lbl">Nom complet</label><input type="text" name="admin_name" class="inp" placeholder="Marie Dupont" required></div>
<div class="fg"><label class="lbl">Email (identifiant de connexion)</label><input type="email" name="admin_email" class="inp" placeholder="m.dupont@macommune.fr" required></div>
<div class="row2">
  <div class="fg"><label class="lbl">Mot de passe (min. 12 car.)</label><input type="password" name="admin_password" class="inp" required minlength="12"></div>
  <div class="fg"><label class="lbl">Confirmer</label><input type="password" name="admin_password_confirm" class="inp" required></div>
</div>
<div class="alert ai"><strong>Conseil :</strong> exemple : <code>Mairie-2025-Pladigit!</code></div>
<div class="btns">
  <a href="?action=collabora" class="btn btn-s">&#x2190; Retour</a>
  <button type="submit" class="btn btn-p">Lancer l'installation &#x2192;</button>
</div>
</form></div></div>
<?php }

function page_install(): void { ?>
<div class="wrap"><div class="card" id="install-card">

<div id="waiting">
  <div class="card-title" style="text-align:center">&#x1F680; Tout est prêt !</div>
  <p class="card-sub" style="text-align:center">Cliquez pour démarrer l'installation automatique.</p>
  <div class="btns" style="justify-content:center">
    <button id="start-btn" class="btn btn-g" onclick="startInstall()">&#x1F680; Lancer l'installation</button>
  </div>
</div>

<div id="running" style="display:none">
  <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:1.5rem">
    <span class="spin" style="font-size:1.5rem">&#x2699;&#xFE0F;</span>
    <div class="card-title" style="margin:0">Installation en cours...</div>
  </div>

  <div class="prog-label">
    <span id="prog-text">Démarrage...</span>
    <span id="prog-pct">0%</span>
  </div>
  <div class="prog-wrap"><div class="prog-bar" id="prog-bar"></div></div>

  <div id="install-steps">
    <?php
    $installSteps = [
        'mysql'      => 'Connexion et configuration MySQL',
        'env'        => 'Écriture de la configuration',
        'migrate'    => 'Création des tables',
        'cache'      => 'Optimisation du cache',
        'storage'    => 'Configuration du stockage',
        'supervisor' => 'Démarrage des workers',
        'lock'       => 'Finalisation',
    ];
    foreach ($installSteps as $key => $label): ?>
    <div class="install-step pending" id="step-<?= $key ?>">
      <span class="icon" id="icon-<?= $key ?>">&#x23F3;</span>
      <span><?= htmlspecialchars($label) ?></span>
    </div>
    <?php endforeach; ?>
  </div>

  <div style="margin-top:1.25rem">
    <span class="log-toggle" onclick="toggleLog()">&#x1F50D; Voir les détails techniques</span>
    <div class="logbox" id="logbox"></div>
  </div>
</div>

<div id="error-panel" style="display:none">
  <div class="card-title" style="color:var(--red)">&#x274C; Erreur d'installation</div>
  <div class="alert ae" id="error-msg" style="margin-top:1rem"></div>
  <div style="margin-top:.875rem">
    <span class="log-toggle" onclick="toggleLog()">&#x1F50D; Voir les détails</span>
    <div class="logbox visible" id="logbox-err"></div>
  </div>
  <div class="btns"><a href="?action=admin" class="btn btn-s">&#x2190; Retour</a></div>
</div>

</div></div>

<script>
var logOffset  = 0;
var logVisible = false;
var pollTimer   = null;
var statusTimer = null;
var stepMap    = {
    'Connexion': 'mysql', 'Création de la base': 'mysql', "Création de l'utilisateur": 'mysql',
    'Écriture': 'env',
    'migration': 'migrate', 'tables': 'migrate',
    'cache': 'cache', 'Optimisation': 'cache',
    'Storage': 'storage', 'Liens': 'storage',
    'worker': 'supervisor', 'Supervisor': 'supervisor',
    'sécurisée': 'lock', 'terminée': 'lock',
    'Docker': 'collabora', 'Collabora': 'collabora', 'proxy': 'collabora'
};
var stepOrder  = ['mysql','env','migrate','cache','storage','supervisor','collabora','lock'];
var stepDone   = {};
var currentPct = 0;

function startInstall() {
    document.getElementById('waiting').style.display = 'none';
    document.getElementById('running').style.display  = 'block';
    fetch('?action=api_run', {method:'POST'})
        .then(r => r.json())
        .then(d => { if(d.ok) {
            pollTimer  = setInterval(pollLog, 1500);
            statusTimer = setInterval(checkStatus, 3000);
        } });
}

function pollLog() {
    fetch('?action=api_log&offset=' + logOffset)
        .then(r => r.json())
        .then(data => {
            logOffset = data.offset;
            if (data.lines && data.lines.length) {
                data.lines.forEach(line => {
                    appendLog(line);
                    updateSteps(line);
                });
            }
            if (data.done || data.redirect) {
                clearInterval(pollTimer);
                setProgress(100, 'Installation terminée !');
                document.getElementById('prog-text').textContent = 'Installation terminée — redirection...';
                setTimeout(() => { window.location.href = '/install-success.html'; }, 2000);
                return;
            }
            if (data.error) {
                clearInterval(pollTimer);
                showError(data.lines ? data.lines.join('\n') : 'Erreur inconnue');
                return;
            }
            // Vérification supplémentaire via api_status toutes les 5 secondes
        });
}

var statusCheckCount = 0;
function checkStatus() {
    fetch('?action=api_status')
        .then(r => r.json())
        .then(data => {
            statusCheckCount++;
            if (data.done) {
                clearInterval(pollTimer);
                clearInterval(statusTimer);
                setProgress(100, 'Installation terminée — redirection...');
                setTimeout(() => { window.location.href = '/install-success.html'; }, 1500);
            }
            if (data.error) {
                clearInterval(pollTimer);
                clearInterval(statusTimer);
                showError(data.msg || 'Erreur inconnue');
            }
        });
}

function appendLog(line) {
    var box1 = document.getElementById('logbox');
    var box2 = document.getElementById('logbox-err');
    var cls  = line.includes('✓') ? 'lok' : (line.includes('✗') ? 'lerr' : 'linfo');
    var html = '<div class="' + cls + '">' + escHtml(line) + '</div>';
    box1.innerHTML += html;
    box2.innerHTML += html;
    box1.scrollTop = box1.scrollHeight;
    box2.scrollTop = box2.scrollHeight;
}

function updateSteps(line) {
    for (var kw in stepMap) {
        if (line.includes(kw)) {
            var sid = stepMap[kw];
            if (!stepDone[sid]) {
                if (line.includes('✓')) markStep(sid, 'done2', '✅');
                else markStep(sid, 'running', '&#x23F3;');
            }
        }
    }
    if (line.includes('✓')) {
        var doneCount = Object.keys(stepDone).length;
        var pct = Math.min(95, Math.round((doneCount + 1) / stepOrder.length * 95));
        if (pct > currentPct) {
            setProgress(pct, line.replace(/\[\d{2}:\d{2}:\d{2}\]/, '').replace('✓','').trim());
        }
    }
}

function markStep(id, cls, icon) {
    var el   = document.getElementById('step-' + id);
    var icEl = document.getElementById('icon-' + id);
    if (!el) return;
    el.className = 'install-step ' + cls;
    icEl.innerHTML = icon;
    if (cls === 'done2') stepDone[id] = true;
}

function setProgress(pct, label) {
    currentPct = pct;
    document.getElementById('prog-bar').style.width = pct + '%';
    document.getElementById('prog-pct').textContent = pct + '%';
    if (label) document.getElementById('prog-text').textContent = label.substring(0, 60);
}

function showError(msg) {
    document.getElementById('running').style.display     = 'none';
    document.getElementById('error-panel').style.display = 'block';
    document.getElementById('error-msg').textContent     = msg;
}

function toggleLog() {
    logVisible = !logVisible;
    document.getElementById('logbox').className = 'logbox' + (logVisible ? ' visible' : '');
}

function escHtml(s) {
    return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}
</script>
<?php }

function page_success(): void {
    $url   = $_SESSION['app_url']         ?? '';
    $admin = $_SESSION['admin']           ?? [];
    $db    = $_SESSION['db']              ?? [];
    ?>
<div class="wrap"><div class="card">
<div style="text-align:center;font-size:3.5rem;margin-bottom:1.25rem">&#x1F389;</div>
<div class="card-title" style="text-align:center;font-size:1.5rem">Pladigit est installé !</div>
<p class="card-sub" style="text-align:center">Notez ces informations — elles ne seront plus affichées.</p>

<div class="alert as">Installation réussie ! Le dossier d'installation sera supprimé dans 60 secondes.</div>

<div class="cred-box">
  <div style="font-size:.8rem;font-weight:700;color:var(--navy);text-transform:uppercase;letter-spacing:.05em;margin-bottom:.75rem">Accès à la plateforme</div>
  <div class="cred-row"><span class="cred-label">URL</span><a href="<?= htmlspecialchars($url) ?>" target="_blank"><?= htmlspecialchars($url) ?></a></div>
  <div class="cred-row"><span class="cred-label">Super Admin</span><code><?= htmlspecialchars($admin['email'] ?? '') ?></code></div>
  <div class="cred-row"><span class="cred-label">Mot de passe</span><em>Celui que vous avez défini</em></div>
</div>

<div class="cred-box">
  <div style="font-size:.8rem;font-weight:700;color:var(--navy);text-transform:uppercase;letter-spacing:.05em;margin-bottom:.75rem">Base de données</div>
  <div class="cred-row"><span class="cred-label">Base</span><code><?= htmlspecialchars($db['name'] ?? 'pladigit') ?></code></div>
  <div class="cred-row"><span class="cred-label">Utilisateur</span><code><?= htmlspecialchars($db['app_user'] ?? 'pladigit') ?></code></div>
  <div class="cred-row"><span class="cred-label">Mot de passe</span><em>Celui que vous avez défini</em></div>
</div>

<div class="btns" style="justify-content:center;margin-top:2rem">
  <a href="<?= htmlspecialchars($url) ?>/super-admin" class="btn btn-p" target="_blank">Accéder à Pladigit &#x2192;</a>
</div>
</div></div>
<script>setTimeout(function(){window.location.href='<?= htmlspecialchars($url) ?>/super-admin';}, 60000);</script>
<?php }
