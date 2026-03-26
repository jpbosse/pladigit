
# Aperçu — Ce dont vous avez besoin

Ce guide couvre l'installation complète de tous les outils nécessaires pour développer et déployer Pladigit. Il est divisé en deux parties : l'environnement de développement local (votre machine) et le serveur de production (VPS hébergé en France).

ℹ  Tous les logiciels utilisés sont 100 % open source. Coût total de licences : 0 €.



# Partie 1 — Environnement de Développement Local

Suivez ces étapes dans l'ordre sur votre machine de développement (Ubuntu 24 LTS recommandé, ou WSL2 sous Windows). Chaque étape se termine par une vérification.

## Étape 1 — Mise à jour du système


# Mise à jour de l'index des paquets
$ sudo apt update && sudo apt upgrade -y
$
# Outils essentiels
$ sudo apt install -y curl wget git unzip zip gnupg2 software-properties-common
$ sudo apt install -y ca-certificates apt-transport-https lsb-release

# Vérification
$ lsb_release -a

✓  Résultat attendu : 'Ubuntu 24.04 LTS' ou version supérieure.

## Étape 2 — PHP 8.4 et ses extensions


Ubuntu 24.04 fournit PHP 8.3 par défaut. Pour garantir PHP 8.4 (version spécifiée dans le CDC), on utilise le PPA officiel.

# Ajouter le PPA PHP d'Ondřej Surý (maintenu et fiable)
$ sudo add-apt-repository ppa:ondrej/php -y
$ sudo apt update

# Installer PHP 8.4 et toutes les extensions requises par Laravel 11
$ sudo apt install -y php8.4 php8.4-cli php8.4-fpm php8.4-common \
$   php8.4-mysql php8.4-xml php8.4-xmlrpc php8.4-curl php8.4-gd \apt
$   php8.4-imagick php8.4-dev php8.4-imap php8.4-mbstring \
$   php8.4-opcache php8.4-soap php8.4-zip php8.4-intl \
$   php8.4-redis php8.4-bcmath php8.4-ldap

# Vérification
$ php8.4 --version
$ php8.4 -m | grep -E 'mysql|redis|mbstring|curl|zip|intl|ldap|bcmath'

✓  Vous devez voir toutes ces extensions listées. Si l'une manque, installez-la séparément avec apt.


📄 /etc/php/8.4/cli/php.ini  (et /etc/php/8.4/fpm/php.ini pour la prod)
; Mémoire — Laravel + imports de fichiers volumineux
memory_limit = 256M

; Taille maximale d'upload (GED — cohérent avec file_max_size_mb du CDC)
upload_max_filesize = 100M
post_max_size = 105M

; Temps d'exécution — pour les imports longs
max_execution_time = 120

; Timezone — obligatoire pour Carbon / Laravel
date.timezone = Europe/Paris

; OpCache — activer en développement aussi pour détecter les problèmes tôt
opcache.enable = 1
opcache.memory_consumption = 128
opcache.max_accelerated_files = 10000
opcache.revalidate_freq = 0   ; 0 = revalidation à chaque requête en dev

# Appliquer la config et vérifier
$ sudo systemctl restart php8.4-fpm
$ php8.4 -r "echo ini_get('memory_limit') . PHP_EOL;"

## Étape 3 — Composer 2


# Télécharger et vérifier l'installeur officiel
$ curl -sS https://getcomposer.org/installer -o /tmp/composer-setup.php
$ php8.4 /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer
$
# Vérification
$ composer --version

✓  Résultat attendu : 'Composer version 2.x.x'

⚠  Ne jamais exécuter Composer en root (sudo). Utilisez toujours votre compte utilisateur normal.

## Étape 4 — MySQL 8


# Ubuntu 24.04 fournit MySQL 8.0 dans ses dépôts officiels
$ sudo apt install -y mysql-server-8.0
$
# Démarrer et activer au boot
$ sudo systemctl start mysql
$ sudo systemctl enable mysql
$
# Vérification
$ mysql --version
$ sudo systemctl status mysql


# Script de sécurisation interactif (répondre Y à toutes les questions)
$ sudo mysql_secure_installation

# Connexion root pour créer l'utilisateur applicatif
$ sudo mysql -u root -p

📄 Commandes SQL à exécuter dans le shell MySQL
-- Créer l'utilisateur applicatif (remplacer 'MotDePasseFort' par un vrai mot de passe)
CREATE USER IF NOT EXISTS 'pladigit'@'localhost'
IDENTIFIED BY 'MotDePasseFort_A_Changer!';

-- Droits sur la base platform et tous les tenants futurs
GRANT ALL PRIVILEGES ON `pladigit_platform`.* TO 'pladigit'@'localhost';
GRANT ALL PRIVILEGES ON `pladigit_%`.*          TO 'pladigit'@'localhost';

-- Appliquer les droits
FLUSH PRIVILEGES;

-- Créer les bases initiales
CREATE DATABASE IF NOT EXISTS `pladigit_platform`
CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE DATABASE IF NOT EXISTS `pladigit_tenant_template`
CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Vérification
SHOW DATABASES LIKE 'pladigit%';
EXIT;

# Test de connexion avec le nouvel utilisateur
$ mysql -u pladigit -p -e "SHOW DATABASES;"

✓  Vous devez voir pladigit_platform et pladigit_tenant_template dans la liste.

## Étape 5 — Redis 7


# Installer Redis depuis les dépôts Ubuntu
$ sudo apt install -y redis-server
$
# Démarrer et activer au boot
$ sudo systemctl start redis
$ sudo systemctl enable redis
$
# Vérification — doit retourner PONG
$ redis-cli ping


📄 /etc/redis/redis.conf — modifications à appliquer
# Écouter uniquement sur localhost (jamais exposer Redis sur Internet)
bind 127.0.0.1 ::1

# Activer la persistance AOF pour ne pas perdre les queues au redémarrage
appendonly yes
appendfilename "appendonly.aof"

# Mémoire maximale (adapter selon la RAM disponible)
maxmemory 256mb
maxmemory-policy allkeys-lru

# Mot de passe (optionnel en dev local, OBLIGATOIRE en production)
# requirepass VotreMotDePasseRedis

$ sudo systemctl restart redis
$ redis-cli ping   # → PONG
$ redis-cli info server | grep redis_version

## Étape 6 — Node.js 20 LTS et npm


# Ajouter le dépôt officiel NodeSource pour Node.js 20 LTS
$ curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
$ sudo apt install -y nodejs
$
# Vérification
$ node --version    # → v20.x.x
$ npm --version     # → 10.x.x

✓  Node.js 20 est requis pour Vite (compilateur d'assets de Laravel 11) et pour les scripts docx/pptx si vous en générez.

## Étape 7 — Git et configuration


$ sudo apt install -y git
$ git --version   # → git version 2.x.x

# Configuration globale (remplacer par vos infos)
$ git config --global user.name  "Jean-Pierre Bossé"
$ git config --global user.email "jp@lesbezots.fr"
$ git config --global init.defaultBranch main
$ git config --global core.editor nano
$
# Vérification
$ git config --list


# Générer la paire de clés ED25519 (plus moderne et sécurisée que RSA)
$ ssh-keygen -t ed25519 -C "jp@lesbezots.fr" -f ~/.ssh/id_ed25519
$
# Afficher la clé publique à copier dans GitHub
$ cat ~/.ssh/id_ed25519.pub

ℹ  Copiez la sortie et ajoutez-la dans GitHub → Settings → SSH and GPG keys → New SSH key.

# Tester la connexion SSH à GitHub
$ ssh -T git@github.com
# Résultat attendu : 'Hi username! You've successfully authenticated.'


# Partie 2 — Serveur de Production (VPS)

Configuration d'un VPS Ubuntu 24.04 LTS hébergé en France (OVH, Scaleway ou équivalent). Ces étapes sont à réaliser en tant qu'administrateur système sur le serveur de production.

⚠  Ne jamais utiliser le compte root pour les opérations courantes. Créez un utilisateur dédié dès la première connexion.

## Étape 8 — Sécurisation initiale du serveur


# Connexion initiale en root (depuis votre terminal local)
$ ssh root@IP_DU_SERVEUR
$
# Sur le serveur : créer l'utilisateur deploy
$ adduser deploy
$ usermod -aG sudo deploy
$
# Copier votre clé SSH publique pour l'utilisateur deploy
$ mkdir -p /home/deploy/.ssh
$ cp /root/.ssh/authorized_keys /home/deploy/.ssh/
$ chown -R deploy:deploy /home/deploy/.ssh
$ chmod 700 /home/deploy/.ssh
$ chmod 600 /home/deploy/.ssh/authorized_keys

# Tester la connexion avec le nouvel utilisateur (depuis votre terminal local)
$ ssh deploy@IP_DU_SERVEUR


# Installer et configurer UFW
$ sudo apt install -y ufw
$
# Règles de base — n'ouvrir que le strict nécessaire
$ sudo ufw default deny incoming
$ sudo ufw default allow outgoing
$
# SSH (votre port, changer si vous utilisez un port non standard)
$ sudo ufw allow 22/tcp
$
# HTTP et HTTPS (requis pour Let's Encrypt et le trafic web)
$ sudo ufw allow 80/tcp
$ sudo ufw allow 443/tcp
$
# Activer le pare-feu
$ sudo ufw enable
$ sudo ufw status verbose

⚠  Ne jamais fermer votre session SSH avant d'avoir vérifié que la règle SSH est active. Risque de blocage.


📄 /etc/ssh/sshd_config — modifications à appliquer
# Désactiver le login root
PermitRootLogin no

# Authentification par mot de passe désactivée (clé SSH uniquement)
PasswordAuthentication no
PubkeyAuthentication yes

# Limiter les tentatives de connexion
MaxAuthTries 3
LoginGraceTime 20

# Désactiver les tunnels X11 inutiles
X11Forwarding no

$ sudo systemctl restart sshd
# Tester la connexion dans un NOUVEAU terminal avant de fermer l'actuel

## Étape 9 — PHP-FPM 8.4 et Nginx (production)


$ sudo add-apt-repository ppa:ondrej/php -y && sudo apt update
$ sudo apt install -y php8.4-fpm php8.4-cli php8.4-mysql php8.4-redis \
$   php8.4-xml php8.4-curl php8.4-mbstring php8.4-zip php8.4-intl \
$   php8.4-bcmath php8.4-ldap php8.4-gd php8.4-imagick php8.4-opcache
$
$ sudo apt install -y nginx
$
# Démarrer les services
$ sudo systemctl start php8.4-fpm nginx
$ sudo systemctl enable php8.4-fpm nginx


📄 /etc/nginx/sites-available/pladigit
# Configuration Nginx pour Pladigit (multi-tenant via sous-domaines wildcard)
server {
listen 80;
server_name *.pladigit.fr pladigit.fr;

# Redirection HTTPS — Let's Encrypt gèrera ça automatiquement
return 301 https://$host$request_uri;
}

server {
listen 443 ssl http2;
server_name *.pladigit.fr pladigit.fr;

# Certificat SSL (rempli par Certbot)
ssl_certificate     /etc/letsencrypt/live/pladigit.fr/fullchain.pem;
ssl_certificate_key /etc/letsencrypt/live/pladigit.fr/privkey.pem;
include /etc/letsencrypt/options-ssl-nginx.conf;
ssl_dhparam /etc/letsencrypt/ssl-dhparams.pem;

root /var/www/pladigit/public;
index index.php index.html;

# Taille max upload (cohérent avec php.ini)
client_max_body_size 105M;

# Headers de sécurité
add_header X-Frame-Options "DENY" always;
add_header X-Content-Type-Options "nosniff" always;
add_header X-XSS-Protection "1; mode=block" always;
add_header Referrer-Policy "strict-origin-when-cross-origin" always;
add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;

# Logs par sous-domaine (multi-tenant)
access_log /var/log/nginx/pladigit-access.log;
error_log  /var/log/nginx/pladigit-error.log;

location / {
try_files $uri $uri/ /index.php?$query_string;
}

location = /favicon.ico { access_log off; log_not_found off; }
location = /robots.txt  { access_log off; log_not_found off; }

error_page 404 /index.php;

location ~ .php$ {
fastcgi_pass unix:/var/run/php/php8.4-fpm.sock;
fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
include fastcgi_params;
fastcgi_read_timeout 120;
}

# Bloquer l'accès aux fichiers cachés (.env, .git, etc.)
location ~ /. {
deny all;
}
}

# Activer le site
$ sudo ln -s /etc/nginx/sites-available/pladigit /etc/nginx/sites-enabled/
$ sudo nginx -t   # Vérifier la syntaxe
$ sudo systemctl reload nginx

## Étape 10 — MySQL 8 et Redis sur le serveur


$ sudo apt install -y mysql-server-8.0
$ sudo mysql_secure_installation
$ sudo mysql -u root -p

📄 Commandes SQL d'initialisation production
-- Utilisateur applicatif avec mot de passe fort
CREATE USER 'pladigit'@'localhost'
IDENTIFIED BY 'MotDePasseTresLongEtComplexe!2026@Prod';

-- Permissions strictes (jamais de GRANT ALL sur *.*)
GRANT ALL PRIVILEGES ON `pladigit_platform`.*  TO 'pladigit'@'localhost';
GRANT ALL PRIVILEGES ON `pladigit_%`.*          TO 'pladigit'@'localhost';
FLUSH PRIVILEGES;

-- Bases initiales
CREATE DATABASE `pladigit_platform`
CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE `pladigit_tenant_template`
CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;


$ sudo apt install -y redis-server
$ sudo systemctl enable redis

📄 /etc/redis/redis.conf — paramètres production (compléter l'étape 8)
# Production : mot de passe obligatoire
requirepass VotreMotDePasseRedisProduction!

# Ne pas exposer Redis sur le réseau
bind 127.0.0.1

# Persistance AOF (ne pas perdre les jobs de queue)
appendonly yes

# Limite mémoire adaptée au VPS (8 Go RAM → 512 Mo Redis)
maxmemory 512mb
maxmemory-policy allkeys-lru

$ sudo systemctl restart redis
$ redis-cli -a VotreMotDePasseRedisProduction! ping   # → PONG

## Étape 11 — Certificat SSL Let's Encrypt


Le certificat wildcard couvre *.pladigit.fr, ce qui permet à tous les sous-domaines tenant (mairie-olonne.pladigit.fr, etc.) d'être automatiquement couverts par HTTPS.

# Installer Certbot via snap (méthode recommandée par Let's Encrypt)
$ sudo snap install --classic certbot
$ sudo ln -s /snap/bin/certbot /usr/bin/certbot
$
# Obtenir un certificat wildcard (nécessite une validation DNS)
$ sudo certbot certonly --manual --preferred-challenges dns \
$   -d pladigit.fr -d '*.pladigit.fr'

ℹ  Certbot vous demandera d'ajouter un enregistrement DNS TXT (_acme-challenge.pladigit.fr) chez votre registrar. Attendez la propagation DNS (1-5 min) avant de valider.

# Tester le renouvellement automatique
$ sudo certbot renew --dry-run
$
# Le cron de renouvellement est automatiquement créé par Certbot
$ sudo systemctl status snap.certbot.renew.timer

✓  Le certificat Let's Encrypt est valable 90 jours. Le renouvellement automatique se fait 30 jours avant expiration.

## Étape 12 — Structure de déploiement


# Créer le répertoire web avec les bons droits
$ sudo mkdir -p /var/www/pladigit
$ sudo chown deploy:www-data /var/www/pladigit
$ sudo chmod 755 /var/www/pladigit
$
# Répertoires de logs Laravel
$ sudo mkdir -p /var/log/pladigit
$ sudo chown deploy:www-data /var/log/pladigit

# Cloner le dépôt (en tant qu'utilisateur deploy)
$ cd /var/www
$ git clone git@github.com:votre-org/pladigit.git pladigit
$ cd pladigit
$
# Installer les dépendances PHP (production, sans dev-dependencies)
$ composer install --no-dev --optimize-autoloader
$
# Compiler les assets front-end
$ npm ci && npm run build
$
# Copier et configurer le .env de production
$ cp .env.example .env
$ php artisan key:generate

📄 .env — Variables spécifiques production
APP_NAME=Pladigit
APP_ENV=production
APP_DEBUG=false
APP_URL=https://pladigit.fr

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=pladigit_platform
DB_USERNAME=pladigit
DB_PASSWORD=MotDePasseTresLongEtComplexe!2026@Prod

CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=database

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=VotreMotDePasseRedisProduction!
REDIS_PORT=6379

SESSION_LIFETIME=120
SESSION_ENCRYPT=true

# Super Admin (jamais en base)
SUPER_ADMIN_EMAIL=admin@lesbezots.fr
SUPER_ADMIN_PASSWORD=MotDePasseSuperAdminTresLong!

# Droits sur le storage Laravel
$ sudo chown -R deploy:www-data /var/www/pladigit/storage
$ sudo chown -R deploy:www-data /var/www/pladigit/bootstrap/cache
$ sudo chmod -R 775 /var/www/pladigit/storage
$ sudo chmod -R 775 /var/www/pladigit/bootstrap/cache
$
# Lancer les migrations
$ php artisan migrate --force
$
# Mettre en cache la configuration (obligatoire en production)
$ php artisan config:cache
$ php artisan route:cache
$ php artisan view:cache

## Étape 13 — Queue Worker Laravel (Supervisor)


Supervisor est un gestionnaire de processus qui relance automatiquement le worker de queue si il tombe. Indispensable pour la synchronisation LDAP et les notifications asynchrones.

$ sudo apt install -y supervisor

📄 /etc/supervisor/conf.d/pladigit-worker.conf
[program:pladigit-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/pladigit/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=deploy
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/pladigit/worker.log
stopwaitsecs=3600

$ sudo supervisorctl reread
$ sudo supervisorctl update
$ sudo supervisorctl start pladigit-worker:*
$
# Vérification
$ sudo supervisorctl status

## Étape 14 — Sauvegardes automatiques


📄 /usr/local/bin/pladigit-backup.sh
#!/bin/bash
# Sauvegarde quotidienne Pladigit — bases de données + config
set -euo pipefail

DATE=$(date +%Y%m%d_%H%M)
BACKUP_DIR="/var/backups/pladigit"
MYSQL_USER="pladigit"
MYSQL_PASS="VotreMotDePasseBase"

mkdir -p "${BACKUP_DIR}"

# Sauvegarder toutes les bases pladigit_*
mysql -u "${MYSQL_USER}" -p"${MYSQL_PASS}"   -e "SHOW DATABASES LIKE 'pladigit%';" --skip-column-names |   while read DB; do
mysqldump -u "${MYSQL_USER}" -p"${MYSQL_PASS}"       --single-transaction --routines --triggers       "${DB}" | gzip > "${BACKUP_DIR}/${DB}_${DATE}.sql.gz"
echo "✓ ${DB} sauvegardé"
done

# Sauvegarder le .env (chiffré)
tar czf "${BACKUP_DIR}/config_${DATE}.tar.gz"   /var/www/pladigit/.env

# Nettoyer les sauvegardes de plus de 30 jours
find "${BACKUP_DIR}" -name "*.gz" -mtime +30 -delete

echo "Sauvegarde ${DATE} terminée."

# Optionnel : envoyer vers un stockage S3-compatible (Scaleway Object Storage)
# rclone copy "${BACKUP_DIR}" scaleway:pladigit-backups/

$ sudo chmod +x /usr/local/bin/pladigit-backup.sh
$
# Planifier via cron — sauvegarde chaque nuit à 3h00
$ echo '0 3 * * * deploy /usr/local/bin/pladigit-backup.sh >> /var/log/pladigit/backup.log 2>&1' | sudo tee -a /etc/crontab
$
# Test manuel
$ sudo -u deploy /usr/local/bin/pladigit-backup.sh


# Vérification Globale de l'Installation

Exécutez ces commandes pour valider que tous les prérequis sont correctement installés avant de commencer le développement.

## Script de diagnostic rapide

📄 check_prereqs.sh — À exécuter depuis le projet Laravel
#!/bin/bash
echo "========================================="
echo " PLADIGIT — Diagnostic des prérequis"
echo "========================================="

check() {
local label=$1; local cmd=$2; local expect=$3
result=$(eval "$cmd" 2>/dev/null)
if echo "$result" | grep -q "$expect"; then
echo "  ✓ $label : $result"
else
echo "  ✗ $label : MANQUANT ou version incorrecte (reçu: '$result')"
fi
}

echo ""
echo "--- Langages & outils ---"
check "PHP 8.4"      "php8.4 -r 'echo PHP_VERSION;'"   "8.4"
check "Composer 2"   "composer --version"               "Composer version 2"
check "Node.js 20"   "node --version"                   "v20"
check "npm"          "npm --version"                    "10"
check "Git"          "git --version"                    "git version 2"

echo ""
echo "--- Extensions PHP ---"
for EXT in pdo_mysql redis mbstring curl zip intl bcmath ldap gd; do
php8.4 -m | grep -q "^$EXT$"     && echo "  ✓ $EXT"     || echo "  ✗ $EXT MANQUANTE → sudo apt install php8.4-$EXT"
done

echo ""
echo "--- Services ---"
check "MySQL"  "mysql --version"    "mysql  Ver 8"
check "Redis"  "redis-cli ping"     "PONG"
check "Nginx"  "nginx -v 2>&1"      "nginx"

echo ""
echo "--- Connexion MySQL ---"
mysql -u pladigit -pVotreMotDePasse   -e "SHOW DATABASES LIKE 'pladigit%';" 2>/dev/null   && echo "  ✓ Connexion MySQL pladigit OK"   || echo "  ✗ Connexion MySQL ÉCHOUÉE — vérifier le mot de passe"

echo ""
echo "--- Laravel ---"
if [ -f "artisan" ]; then
php artisan --version && echo "  ✓ Laravel détecté"
else
echo "  ℹ Pas de projet Laravel dans ce répertoire (normal si pré-installation)"
fi

echo ""
echo "========================================="

$ chmod +x check_prereqs.sh && ./check_prereqs.sh

## Checklist finale

### Environnement de développement local
- Ubuntu 24.04 LTS à jour
- PHP 8.4 avec toutes les extensions (mysql, redis, mbstring, curl, zip, intl, bcmath, ldap, gd, imagick, opcache)
- Composer 2 installé globalement
- MySQL 8 démarré, base pladigit_platform et pladigit_tenant_template créées
- Utilisateur MySQL 'pladigit' créé avec les bons droits
- Redis 7 démarré et répond PONG
- Node.js 20 LTS et npm installés
- Git configuré avec nom, email et clé SSH GitHub

### Serveur de production
- Utilisateur deploy créé, root désactivé
- UFW : ports 22, 80, 443 autorisés, reste bloqué
- SSH : authentification par mot de passe désactivée
- PHP 8.4-FPM configuré avec php.ini production
- Nginx configuré avec VirtualHost wildcard et headers sécurité
- MySQL 8 avec mot de passe fort pour l'utilisateur applicatif
- Redis 7 avec mot de passe et bind localhost
- Certificat SSL Let's Encrypt wildcard (*.pladigit.fr) obtenu
- Structure /var/www/pladigit créée avec bons droits (deploy:www-data)
- Supervisor configuré pour les queues Laravel (2 workers)
- Sauvegarde quotidienne planifiée via cron

✓  Une fois cette checklist complétée à 100 %, vous pouvez passer au Guide d'Implémentation Phase 1 & 2.


— Fin du Guide d'Installation des Prérequis —