<?php
/**
 * Pladigit — Assistant d'installation web
 * Wizard 7 étapes — PHP standalone
 */

session_start();

define('PLADIGIT_ROOT', dirname(__DIR__));
define('ENV_FILE',      PLADIGIT_ROOT . '/.env');
define('LOCK_FILE',     PLADIGIT_ROOT . '/install/.lock');
define('INSTALL_DIR',   PLADIGIT_ROOT . '/install');
define('LOG_FILE',      PLADIGIT_ROOT . '/storage/logs/install.log');

// Sécurité : si déjà installé, bloquer avec page claire
if (file_exists(LOCK_FILE)) {
    $lockDate = trim(file_get_contents(LOCK_FILE));
    http_response_code(403);
    die('<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8">
<title>Pladigit — Déjà installé</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:-apple-system,BlinkMacSystemFont,sans-serif;background:#F4F6F9;display:flex;align-items:center;justify-content:center;min-height:100vh}
.box{background:#fff;border-radius:10px;padding:2.5rem;max-width:520px;width:100%;margin:1rem;text-align:center;border:1px solid rgba(30,58,95,.1);box-shadow:0 4px 16px rgba(30,58,95,.08)}
.icon{font-size:3rem;margin-bottom:1.25rem}
h1{color:#1E3A5F;font-size:1.35rem;margin-bottom:.75rem}
p{color:#6B7A8D;font-size:.9rem;line-height:1.6;margin-bottom:1rem}
.date{background:#F4F6F9;border-radius:6px;padding:.6rem 1rem;font-size:.82rem;color:#6B7A8D;margin-bottom:1.5rem}
.btn{display:inline-block;background:#1E3A5F;color:#fff;padding:.7rem 1.75rem;border-radius:6px;text-decoration:none;font-weight:700;font-size:.875rem}
.warn{background:#FEF3C7;border:1px solid #FDE68A;border-radius:6px;padding:.875rem;font-size:.82rem;color:#92400E;margin-bottom:1.5rem;text-align:left}
</style></head><body>
<div class="box">
  <div class="icon">🔒</div>
  <h1>Pladigit est déjà installé</h1>
  <p>L'assistant d'installation a déjà été utilisé sur ce serveur. Pour protéger votre installation, l'accès à cet assistant est maintenant bloqué.</p>
  <div class="date">📅 Installation effectuée le : ' . htmlspecialchars($lockDate) . '</div>
  <div class="warn">
    <strong>⚠️ Vous souhaitez réinstaller ?</strong><br>
    Supprimez manuellement le fichier <code>install/.lock</code> sur votre serveur,
    puis rechargez cette page. Attention : cette opération réécrit votre configuration.
  </div>
  <a href="/" class="btn">← Retourner à l'accueil</a>
</div>
</body></html>');
}

$action = $_GET['action'] ?? $_POST['action'] ?? 'welcome';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    handle_post($action);
}

render_page($action);

// =============================================================================
// HANDLERS POST
// =============================================================================
function handle_post(string $action): void {
    switch ($action) {
        case 'check':
            $_SESSION['step'] = 1;
            redirect('database');
            break;
        case 'database':
            $errors = validate_database($_POST);
            if ($errors) { $_SESSION['errors'] = $errors; redirect('database'); }
            $_SESSION['db'] = [
                'host'     => trim($_POST['db_host'] ?? '127.0.0.1'),
                'port'     => trim($_POST['db_port'] ?? '3306'),
                'name'     => trim($_POST['db_name'] ?? 'pladigit'),
                'username' => trim($_POST['db_username'] ?? ''),
                'password' => $_POST['db_password'] ?? '',
            ];
            $_SESSION['step'] = 2;
            redirect('app');
            break;
        case 'app':
            $errors = validate_app($_POST);
            if ($errors) { $_SESSION['errors'] = $errors; redirect('app'); }
            $_SESSION['app'] = [
                'url'      => rtrim(trim($_POST['app_url'] ?? ''), '/'),
                'name'     => trim($_POST['app_name'] ?? 'Pladigit'),
                'timezone' => trim($_POST['app_timezone'] ?? 'Europe/Paris'),
            ];
            $_SESSION['step'] = 3;
            redirect('smtp');
            break;
        case 'smtp':
            $_SESSION['smtp'] = [
                'host'       => trim($_POST['smtp_host'] ?? ''),
                'port'       => trim($_POST['smtp_port'] ?? '587'),
                'username'   => trim($_POST['smtp_username'] ?? ''),
                'password'   => $_POST['smtp_password'] ?? '',
                'from'       => trim($_POST['smtp_from'] ?? ''),
                'from_name'  => trim($_POST['smtp_from_name'] ?? 'Pladigit'),
                'encryption' => trim($_POST['smtp_encryption'] ?? 'tls'),
            ];
            $_SESSION['step'] = 4;
            redirect('admin');
            break;
        case 'admin':
            $errors = validate_admin($_POST);
            if ($errors) { $_SESSION['errors'] = $errors; redirect('admin'); }
            $_SESSION['admin'] = [
                'name'     => trim($_POST['admin_name'] ?? ''),
                'email'    => trim($_POST['admin_email'] ?? ''),
                'password' => $_POST['admin_password'] ?? '',
            ];
            $_SESSION['step'] = 5;
            redirect('install');
            break;
        case 'install':
            run_installation();
            break;
    }
}

// =============================================================================
// VALIDATIONS
// =============================================================================
function validate_database(array $p): array {
    $e = [];
    if (empty($p['db_host']))     $e[] = "L'hôte MySQL est requis.";
    if (empty($p['db_name']))     $e[] = "Le nom de la base est requis.";
    if (empty($p['db_username'])) $e[] = "Le nom d'utilisateur est requis.";
    if (!$e) {
        try {
            new PDO("mysql:host={$p['db_host']};port={$p['db_port']}", $p['db_username'], $p['db_password'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]);
        } catch (PDOException $ex) {
            $e[] = "Connexion MySQL impossible : " . htmlspecialchars($ex->getMessage());
        }
    }
    return $e;
}

function validate_app(array $p): array {
    $e = [];
    if (empty($p['app_url'])) $e[] = "L'URL est requise.";
    elseif (!filter_var($p['app_url'], FILTER_VALIDATE_URL)) $e[] = "URL invalide.";
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
// INSTALLATION
// =============================================================================
function run_installation(): void {
    $db    = $_SESSION['db']    ?? [];
    $app   = $_SESSION['app']   ?? [];
    $smtp  = $_SESSION['smtp']  ?? [];
    $admin = $_SESSION['admin'] ?? [];
    $log   = [];

    try {
        ilog($log, "Création de la base de données...");
        $pdo = new PDO("mysql:host={$db['host']};port={$db['port']};charset=utf8mb4",
            $db['username'], $db['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$db['name']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("GRANT ALL PRIVILEGES ON `{$db['name']}`.* TO '{$db['username']}'@'{$db['host']}'");
        $pdo->exec("FLUSH PRIVILEGES");
        ilog($log, "✓ Base de données prête");

        ilog($log, "Écriture du fichier de configuration...");
        $appKey = 'base64:' . base64_encode(random_bytes(32));
        write_env($db, $app, $smtp, $admin, $appKey);
        ilog($log, "✓ Configuration écrite");

        ilog($log, "Création des tables...");
        ilog($log, shell_exec("cd " . PLADIGIT_ROOT . " && php artisan migrate --force 2>&1") ?? '');
        ilog($log, shell_exec("cd " . PLADIGIT_ROOT . " && php artisan migrate --path=database/migrations/platform --force 2>&1") ?? '');
        ilog($log, "✓ Tables créées");

        ilog($log, "Création du Super Admin...");
        ilog($log, shell_exec(sprintf(
            "cd %s && php artisan super-admin:create --name=%s --email=%s --password=%s 2>&1",
            PLADIGIT_ROOT,
            escapeshellarg($admin['name']),
            escapeshellarg($admin['email']),
            escapeshellarg($admin['password'])
        )) ?? '');
        ilog($log, "✓ Super Admin créé");

        ilog($log, "Optimisation...");
        ilog($log, shell_exec("cd " . PLADIGIT_ROOT . " && php artisan config:cache 2>&1") ?? '');
        ilog($log, shell_exec("cd " . PLADIGIT_ROOT . " && php artisan route:cache 2>&1") ?? '');
        ilog($log, shell_exec("cd " . PLADIGIT_ROOT . " && php artisan view:cache 2>&1") ?? '');
        ilog($log, "✓ Cache généré");

        ilog($log, "Démarrage des workers...");
        write_supervisor();
        shell_exec('supervisorctl reread 2>&1 && supervisorctl update 2>&1 && supervisorctl start pladigit-worker:* 2>&1');
        ilog($log, "✓ Workers démarrés");

        file_put_contents(LOCK_FILE, date('Y-m-d H:i:s'));
        ilog($log, "✓ Installation sécurisée");

        // Suppression du dossier install/ après 30s
        $cleanup = '#!/bin/bash' . "\n" . 'sleep 30 && rm -rf ' . INSTALL_DIR . "\n";
        file_put_contents('/tmp/pladigit-cleanup.sh', $cleanup);
        chmod('/tmp/pladigit-cleanup.sh', 0755);
        shell_exec('nohup /tmp/pladigit-cleanup.sh > /dev/null 2>&1 &');

        $_SESSION['install_log']     = $log;
        $_SESSION['install_success'] = true;
        $_SESSION['app_url']         = $app['url'];
        redirect('success');

    } catch (Throwable $ex) {
        ilog($log, "✗ ERREUR : " . $ex->getMessage());
        $_SESSION['install_log']   = $log;
        $_SESSION['install_error'] = $ex->getMessage();
        redirect('install');
    }
}

function write_env(array $db, array $app, array $smtp, array $admin, string $key): void {
    $env = 'APP_NAME="' . $app['name'] . '"' . "\n"
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
         . 'DB_USERNAME=' . $db['username'] . "\n"
         . 'DB_PASSWORD=' . $db['password'] . "\n\n"
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
         . 'MAIL_FROM_NAME="' . $smtp['from_name'] . '"' . "\n\n"
         . 'SUPER_ADMIN_EMAIL=' . $admin['email'] . "\n";

    if (file_put_contents(ENV_FILE, $env) === false) {
        throw new RuntimeException("Impossible d'écrire le fichier .env. Vérifiez les permissions.");
    }
    chmod(ENV_FILE, 0640);
}

function write_supervisor(): void {
    $conf = "[program:pladigit-worker]\n"
          . "process_name=%(program_name)s_%(process_num)02d\n"
          . "command=php /var/www/pladigit/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600\n"
          . "autostart=true\nautorestart=true\nstopasgroup=true\nkillasgroup=true\n"
          . "user=www-data\nnumprocs=2\nredirect_stderr=true\n"
          . "stdout_logfile=/var/log/pladigit-worker.log\nstopwaitsecs=3600\n";
    @file_put_contents('/etc/supervisor/conf.d/pladigit.conf', $conf);
}

function ilog(array &$log, string $msg): void {
    $line = '[' . date('H:i:s') . '] ' . trim($msg);
    $log[] = $line;
    @file_put_contents(LOG_FILE, $line . "\n", FILE_APPEND);
}

function redirect(string $a): void { header("Location: ?action={$a}"); exit; }

// =============================================================================
// RENDU
// =============================================================================
function render_page(string $action): void {
    $step   = $_SESSION['step'] ?? 0;
    $errors = $_SESSION['errors'] ?? [];
    unset($_SESSION['errors']);
    $steps  = ['Bienvenue','Vérification','Base de données','Application','Email','Administrateur','Installation'];
    html_head();
    html_steps($step, $steps);
    switch ($action) {
        case 'welcome':  page_welcome();         break;
        case 'check':    page_check();           break;
        case 'database': page_database($errors); break;
        case 'app':      page_app($errors);      break;
        case 'smtp':     page_smtp($errors);     break;
        case 'admin':    page_admin($errors);    break;
        case 'install':  page_install();         break;
        case 'success':  page_success();         break;
        default:         page_welcome();
    }
    html_foot();
}

function html_head(): void { ?>
<!DOCTYPE html><html lang="fr"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Installation Pladigit</title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{--navy:#1E3A5F;--gold:#C4972A;--light:#F4F6F9;--grey:#6B7A8D;--green:#16A34A;--red:#DC2626}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;background:var(--light);color:#1A2332;min-height:100vh}
.hdr{background:var(--navy);padding:1.25rem 2rem}
.logo{font-size:1.4rem;font-weight:700;color:#fff;letter-spacing:-.02em}
.logo span{color:var(--gold)}
.logo-sub{font-size:.78rem;color:rgba(255,255,255,.5);margin-top:.1rem}
.steps-bar{background:#fff;border-bottom:1px solid rgba(30,58,95,.1);padding:.875rem 2rem;overflow-x:auto}
.steps{display:flex;align-items:center;min-width:560px}
.step{display:flex;align-items:center;gap:.4rem;flex:1}
.sn{width:26px;height:26px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.72rem;font-weight:700;flex-shrink:0;background:var(--light);color:var(--grey);border:2px solid #e5e7eb}
.step.done .sn{background:var(--green);color:#fff;border-color:var(--green)}
.step.active .sn{background:var(--navy);color:#fff;border-color:var(--navy)}
.sl{font-size:.72rem;color:var(--grey);white-space:nowrap}
.step.done .sl,.step.active .sl{color:var(--navy);font-weight:600}
.sline{flex:1;height:2px;background:#e5e7eb;margin:0 .4rem}
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
.row{display:grid;grid-template-columns:1fr 1fr;gap:1rem}
.btn{padding:.7rem 1.75rem;border-radius:6px;font-size:.875rem;font-weight:700;cursor:pointer;border:none;transition:all .2s;text-decoration:none;display:inline-flex;align-items:center;gap:.4rem}
.btn-p{background:var(--navy);color:#fff}.btn-p:hover{background:#162D4A}
.btn-s{background:var(--light);color:var(--navy);border:1px solid rgba(30,58,95,.2)}.btn-s:hover{background:#e8edf2}
.btn-g{background:var(--green);color:#fff}.btn-g:hover{background:#15803d}
.btns{display:flex;gap:.875rem;margin-top:1.75rem;justify-content:flex-end}
.alert{padding:.875rem 1.125rem;border-radius:6px;font-size:.85rem;margin-bottom:1.25rem}
.ae{background:#FEF2F2;border:1px solid #FECACA;color:var(--red)}
.as{background:#F0FDF4;border:1px solid #BBF7D0;color:var(--green)}
.ai{background:#EFF6FF;border:1px solid #BFDBFE;color:#1d4ed8}
.chk{display:flex;align-items:center;gap:.75rem;padding:.55rem 0;border-bottom:1px solid var(--light)}
.chk:last-child{border:none}
.chk-v{font-size:.8rem;color:var(--grey);margin-left:auto}
.logbox{background:#0f172a;color:#e2e8f0;border-radius:8px;padding:1.125rem;font-family:'Courier New',monospace;font-size:.75rem;line-height:1.6;max-height:280px;overflow-y:auto;margin:1.25rem 0}
.lok{color:#4ade80}.lerr{color:#f87171}
.ctab{width:100%;border-collapse:collapse;font-size:.875rem;margin:1rem 0}
.ctab td{padding:.55rem .875rem;border:1px solid #e5e7eb}
.ctab tr:nth-child(even) td{background:var(--light)}
.ctab td:first-child{font-weight:600;color:var(--navy);width:38%}
code{background:var(--light);padding:.12rem .35rem;border-radius:3px;font-size:.8rem;color:var(--navy)}
@media(max-width:600px){.row{grid-template-columns:1fr}.wrap{padding:0 1rem}.card{padding:1.5rem}}
</style></head><body>
<div class="hdr"><div class="logo">Pladi<span>git</span></div><div class="logo-sub">Assistant d'installation</div></div>
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

function html_foot(): void { ?></body></html><?php }

function errs(array $e): void {
    if (!$e) return;
    echo '<div class="alert ae"><strong>Erreur :</strong><ul style="margin:.4rem 0 0 1.1rem">';
    foreach ($e as $i) echo '<li>' . htmlspecialchars($i) . '</li>';
    echo '</ul></div>';
}

// ── Pages ─────────────────────────────────────────────────────────────────────
function page_welcome(): void { ?>
<div class="wrap"><div class="card">
<div style="text-align:center;margin-bottom:1.75rem">
  <div style="font-size:3rem;margin-bottom:.875rem">🏛</div>
  <div class="card-title" style="font-size:1.5rem">Bienvenue dans Pladigit</div>
  <p class="card-sub" style="max-width:460px;margin:.5rem auto 0">Cet assistant va configurer votre plateforme en quelques minutes. Aucune connaissance technique requise.</p>
</div>
<div style="background:var(--light);border-radius:8px;padding:1.25rem;margin-bottom:1.5rem">
  <div style="font-weight:700;color:var(--navy);margin-bottom:.75rem">Ce que nous allons faire :</div>
  <?php foreach (['🗄 Connecter la base de données MySQL','🌐 Définir l\'adresse de votre plateforme','📧 Configurer l\'envoi d\'emails (optionnel)','👤 Créer votre compte administrateur','🚀 Lancer l\'installation automatique'] as $s): ?>
  <div style="display:flex;align-items:center;gap:.6rem;padding:.35rem 0;font-size:.875rem"><?= $s ?></div>
  <?php endforeach; ?>
</div>
<div class="alert ai"><strong>Durée estimée :</strong> 5 à 10 minutes.</div>
<div class="btns" style="justify-content:center"><a href="?action=check" class="btn btn-p">Commencer l'installation →</a></div>
</div></div>
<?php }

function page_check(): void {
    $checks = [
        ['PHP ≥ 8.2', version_compare(PHP_VERSION, '8.2.0', '>='), PHP_VERSION],
        ['Extension pdo_mysql', extension_loaded('pdo_mysql'), extension_loaded('pdo_mysql') ? 'OK' : 'Manquante'],
        ['Extension mbstring', extension_loaded('mbstring'), extension_loaded('mbstring') ? 'OK' : 'Manquante'],
        ['Extension redis', extension_loaded('redis'), extension_loaded('redis') ? 'OK' : 'Manquante'],
        ['Extension gd', extension_loaded('gd'), extension_loaded('gd') ? 'OK' : 'Manquante'],
        ['Extension zip', extension_loaded('zip'), extension_loaded('zip') ? 'OK' : 'Manquante'],
        ['Extension curl', extension_loaded('curl'), extension_loaded('curl') ? 'OK' : 'Manquante'],
        ['Dossier storage/ accessible', is_writable(PLADIGIT_ROOT . '/storage'), is_writable(PLADIGIT_ROOT . '/storage') ? 'OK' : '❌ Non accessible'],
        ['Racine accessible en écriture', is_writable(PLADIGIT_ROOT), is_writable(PLADIGIT_ROOT) ? 'OK' : '❌ Non accessible'],
    ];
    $allOk = array_reduce($checks, fn($c, $i) => $c && $i[1], true);
    ?>
<div class="wrap"><div class="card">
<div class="card-title">Vérification du système</div>
<p class="card-sub">Votre serveur est-il compatible avec Pladigit ?</p>
<?php foreach ($checks as [$lbl, $ok, $val]): ?>
<div class="chk">
  <span><?= $ok ? '✅' : '❌' ?></span>
  <span style="font-size:.875rem;flex:1"><?= htmlspecialchars($lbl) ?></span>
  <span class="chk-v"><?= htmlspecialchars($val) ?></span>
</div>
<?php endforeach; ?>
<?php if (!$allOk): ?>
<div class="alert ae" style="margin-top:1.25rem">Corrigez les erreurs avant de continuer. Consultez <a href="https://pladigit.fr/docs" target="_blank">la documentation</a>.</div>
<?php else: ?>
<div class="alert as" style="margin-top:1.25rem">Tout est en ordre ! Votre serveur est compatible.</div>
<?php endif; ?>
<div class="btns">
  <a href="?action=check" class="btn btn-s">↺ Relancer</a>
  <?php if ($allOk): ?>
  <form method="POST"><input type="hidden" name="action" value="check">
  <button type="submit" class="btn btn-p">Continuer →</button></form>
  <?php endif; ?>
</div>
</div></div>
<?php }

function page_database(array $e): void { ?>
<div class="wrap"><div class="card">
<div class="card-title">🗄 Base de données</div>
<p class="card-sub">Informations de connexion à MySQL. En cas de doute, laissez les valeurs par défaut.</p>
<?php errs($e) ?>
<form method="POST"><input type="hidden" name="action" value="database">
<div class="row">
  <div class="fg"><label class="lbl">Hôte MySQL</label><input type="text" name="db_host" class="inp" value="127.0.0.1" required><div class="hint">Généralement 127.0.0.1</div></div>
  <div class="fg"><label class="lbl">Port</label><input type="text" name="db_port" class="inp" value="3306"></div>
</div>
<div class="fg"><label class="lbl">Nom de la base</label><input type="text" name="db_name" class="inp" value="pladigit" required><div class="hint">Sera créée automatiquement si elle n'existe pas.</div></div>
<div class="row">
  <div class="fg"><label class="lbl">Utilisateur MySQL</label><input type="text" name="db_username" class="inp" placeholder="root" required></div>
  <div class="fg"><label class="lbl">Mot de passe MySQL</label><input type="password" name="db_password" class="inp" placeholder="(vide si aucun)"></div>
</div>
<div class="btns">
  <a href="?action=check" class="btn btn-s">← Retour</a>
  <button type="submit" class="btn btn-p">Tester et continuer →</button>
</div>
</form></div></div>
<?php }

function page_app(array $e): void {
    $ip = $_SERVER['SERVER_ADDR'] ?? gethostbyname(gethostname()); ?>
<div class="wrap"><div class="card">
<div class="card-title">🌐 Paramètres de l'application</div>
<p class="card-sub">L'adresse à laquelle vos agents accéderont à Pladigit. Sans nom de domaine, utilisez l'adresse IP du serveur.</p>
<?php errs($e) ?>
<form method="POST"><input type="hidden" name="action" value="app">
<div class="fg"><label class="lbl">URL de l'application</label>
  <input type="text" name="app_url" class="inp" value="http://<?= htmlspecialchars($ip) ?>" required placeholder="http://192.168.1.10 ou https://pladigit.macommune.fr">
  <div class="hint">Incluez http:// ou https://</div>
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
  <a href="?action=database" class="btn btn-s">← Retour</a>
  <button type="submit" class="btn btn-p">Continuer →</button>
</div>
</form></div></div>
<?php }

function page_smtp(array $e): void { ?>
<div class="wrap"><div class="card">
<div class="card-title">📧 Configuration email</div>
<p class="card-sub">Pour les notifications et réinitialisations de mots de passe. <strong>Optionnel</strong> — configurable plus tard.</p>
<?php errs($e) ?>
<form method="POST"><input type="hidden" name="action" value="smtp">
<div class="row">
  <div class="fg"><label class="lbl">Serveur SMTP</label><input type="text" name="smtp_host" class="inp" placeholder="smtp.mail.ovh.net"></div>
  <div class="fg"><label class="lbl">Port</label>
    <select name="smtp_port" class="sel">
      <option value="587">587 — TLS (recommandé)</option>
      <option value="465">465 — SSL</option>
      <option value="25">25 — Non chiffré</option>
    </select>
  </div>
</div>
<div class="row">
  <div class="fg"><label class="lbl">Identifiant SMTP</label><input type="text" name="smtp_username" class="inp" placeholder="contact@macommune.fr"></div>
  <div class="fg"><label class="lbl">Mot de passe SMTP</label><input type="password" name="smtp_password" class="inp"></div>
</div>
<div class="row">
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
  <a href="?action=app" class="btn btn-s">← Retour</a>
  <button type="submit" name="skip" value="1" class="btn btn-s">Passer</button>
  <button type="submit" class="btn btn-p">Continuer →</button>
</div>
</form></div></div>
<?php }

function page_admin(array $e): void { ?>
<div class="wrap"><div class="card">
<div class="card-title">👤 Compte administrateur</div>
<p class="card-sub">Ce compte aura accès à toutes les fonctions d'administration de la plateforme.</p>
<?php errs($e) ?>
<form method="POST"><input type="hidden" name="action" value="admin">
<div class="fg"><label class="lbl">Nom complet</label><input type="text" name="admin_name" class="inp" placeholder="Marie Dupont" required></div>
<div class="fg"><label class="lbl">Email (identifiant de connexion)</label><input type="email" name="admin_email" class="inp" placeholder="m.dupont@macommune.fr" required></div>
<div class="row">
  <div class="fg"><label class="lbl">Mot de passe</label><input type="password" name="admin_password" class="inp" placeholder="12 caractères minimum" required minlength="12"></div>
  <div class="fg"><label class="lbl">Confirmer</label><input type="password" name="admin_password_confirm" class="inp" required></div>
</div>
<div class="alert ai"><strong>Conseil :</strong> exemple de mot de passe solide : <code>Mairie-2025-Pladigit!</code></div>
<div class="btns">
  <a href="?action=smtp" class="btn btn-s">← Retour</a>
  <button type="submit" class="btn btn-p">Lancer l'installation →</button>
</div>
</form></div></div>
<?php }

function page_install(): void {
    $log   = $_SESSION['install_log']   ?? [];
    $error = $_SESSION['install_error'] ?? null; ?>
<div class="wrap"><div class="card">
<?php if ($error): ?>
  <div class="card-title">❌ Erreur</div>
  <div class="alert ae"><?= htmlspecialchars($error) ?></div>
  <div class="logbox"><?php foreach ($log as $l): ?><div class="<?= str_contains($l,'✓')?'lok':(str_contains($l,'✗')?'lerr':'') ?>"><?= htmlspecialchars($l) ?></div><?php endforeach; ?></div>
  <div class="btns"><a href="?action=admin" class="btn btn-s">← Retour</a></div>
<?php elseif ($log): ?>
  <div class="card-title">⏳ Installation en cours...</div>
  <div class="logbox" id="lb"><?php foreach ($log as $l): ?><div><?= htmlspecialchars($l) ?></div><?php endforeach; ?></div>
<?php else: ?>
  <div class="card-title" style="text-align:center">🚀 Tout est prêt !</div>
  <p class="card-sub" style="text-align:center">Cliquez pour lancer l'installation. Cela prendra quelques minutes.</p>
  <div class="btns" style="justify-content:center">
    <form method="POST"><input type="hidden" name="action" value="install">
    <button type="submit" class="btn btn-g" onclick="this.disabled=true;this.textContent='⏳ Installation...'">🚀 Lancer l'installation</button>
    </form>
  </div>
<?php endif; ?>
</div></div>
<script>var b=document.getElementById('lb');if(b)b.scrollTop=b.scrollHeight;</script>
<?php }

function page_success(): void {
    $url   = $_SESSION['app_url'] ?? '';
    $admin = $_SESSION['admin']   ?? []; ?>
<div class="wrap"><div class="card">
<div style="text-align:center;font-size:3.5rem;margin-bottom:1.25rem">🎉</div>
<div class="card-title" style="text-align:center;font-size:1.5rem">Pladigit est installé !</div>
<p class="card-sub" style="text-align:center">Notez ces informations — elles ne seront plus affichées.</p>
<div class="alert as"><strong>Installation réussie !</strong> Le dossier d'installation sera supprimé automatiquement dans 30 secondes.</div>
<table class="ctab">
  <tr><td>URL</td><td><a href="<?= htmlspecialchars($url) ?>" target="_blank"><?= htmlspecialchars($url) ?></a></td></tr>
  <tr><td>Email admin</td><td><code><?= htmlspecialchars($admin['email'] ?? '') ?></code></td></tr>
  <tr><td>Mot de passe</td><td><em>Celui que vous avez défini</em></td></tr>
</table>
<div class="btns" style="justify-content:center;margin-top:1.75rem">
  <a href="<?= htmlspecialchars($url) ?>/super-admin" class="btn btn-p" target="_blank">Accéder à Pladigit →</a>
</div>
</div></div>
<script>setTimeout(function(){window.location.href='<?= htmlspecialchars($url) ?>/super-admin';},30000);</script>
<?php }
