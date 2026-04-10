# Installation — Pladigit

Ce guide couvre l'installation complète de Pladigit sur un serveur Ubuntu 24 LTS.  
Tous les logiciels utilisés sont 100 % open source. Coût total de licences : **0 €**.

---

## Prérequis matériels recommandés

| Composant | Minimum | Recommandé |
|-----------|---------|------------|
| CPU | 2 vCPU | 4 vCPU |
| RAM | 4 Go | 8 Go (16 Go avec Collabora) |
| Disque | 40 Go SSD | 200 Go SSD |
| OS | Ubuntu 22.04 LTS | Ubuntu 24.04 LTS |

> ⚠ **Collabora Online** est gourmand en ressources. Prévoir au minimum **4 Go de RAM dédiés** si vous l'activez. Sur un VPS 4 Go total, Collabora + Pladigit + MySQL + Redis seront à l'étroit.

---

## Partie 1 — Prérequis système

### 1.1 Mise à jour Ubuntu

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y curl wget git unzip zip gnupg2 \
  software-properties-common ca-certificates apt-transport-https lsb-release
```

### 1.2 PHP 8.4

Ubuntu 24.04 fournit PHP 8.3 par défaut. Utiliser le PPA d'Ondřej Surý pour PHP 8.4 :

```bash
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update
sudo apt install -y php8.4 php8.4-cli php8.4-fpm php8.4-common \
  php8.4-mysql php8.4-xml php8.4-curl php8.4-gd php8.4-imagick \
  php8.4-mbstring php8.4-opcache php8.4-zip php8.4-intl \
  php8.4-redis php8.4-bcmath php8.4-ldap php8.4-exif
php8.4 --version
```

**Configuration `/etc/php/8.4/fpm/php.ini` :**

```ini
memory_limit = 256M
upload_max_filesize = 100M
post_max_size = 105M
max_execution_time = 120
date.timezone = Europe/Paris
opcache.enable = 1
opcache.memory_consumption = 128
opcache.max_accelerated_files = 10000
```

### 1.3 MySQL 8

```bash
sudo apt install -y mysql-server
sudo mysql_secure_installation

# Créer les bases et l'utilisateur applicatif
sudo mysql -u root -p <<EOF
CREATE DATABASE pladigit_platform CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE pladigit_tenant_template CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'pladigit'@'localhost' IDENTIFIED BY 'VOTRE_MOT_DE_PASSE_FORT';
GRANT ALL PRIVILEGES ON pladigit_platform.* TO 'pladigit'@'localhost';
GRANT ALL PRIVILEGES ON pladigit_tenant_template.* TO 'pladigit'@'localhost';
GRANT ALL PRIVILEGES ON `pladigit\_%`.* TO 'pladigit'@'localhost';
FLUSH PRIVILEGES;
EOF
```

### 1.4 Redis 7

```bash
sudo apt install -y redis-server
# Activer l'authentification dans /etc/redis/redis.conf :
# requirepass VOTRE_MOT_DE_PASSE_REDIS
# bind 127.0.0.1
sudo systemctl enable --now redis-server
redis-cli ping  # doit retourner PONG
```

### 1.5 Node.js 20

```bash
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs
node --version  # v20.x
```

### 1.6 Composer 2

```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
composer --version
```

### 1.7 Nginx

```bash
sudo apt install -y nginx
sudo systemctl enable --now nginx
```

---

## Partie 2 — Installation de Pladigit

### 2.1 Utilisateur de déploiement

```bash
sudo adduser deploy
sudo usermod -aG www-data deploy
```

### 2.2 Cloner le dépôt

```bash
sudo mkdir -p /var/www/pladigit
sudo chown deploy:www-data /var/www/pladigit
sudo -u deploy git clone https://github.com/jpbosse/pladigit.git /var/www/pladigit
cd /var/www/pladigit
```

### 2.3 Dépendances

```bash
sudo -u deploy composer install --no-dev --optimize-autoloader
sudo -u deploy npm install && sudo -u deploy npm run build
```

### 2.4 Configuration

```bash
sudo -u deploy cp .env.example .env
sudo -u deploy php artisan key:generate
```

Éditer `.env` — variables essentielles :

```env
APP_NAME="Pladigit"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://pladigit.fr

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=pladigit_platform
DB_USERNAME=pladigit
DB_PASSWORD=VOTRE_MOT_DE_PASSE_FORT

TENANT_DB_USERNAME=pladigit
TENANT_DB_PASSWORD=VOTRE_MOT_DE_PASSE_FORT

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=VOTRE_MOT_DE_PASSE_REDIS
REDIS_PORT=6379

QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
CACHE_STORE=redis

BCRYPT_ROUNDS=12

# Super Admin (hors base de données)
SUPER_ADMIN_EMAIL=superadmin@pladigit.fr
SUPER_ADMIN_PASSWORD=MOT_DE_PASSE_TRES_FORT

# Collabora Online (optionnel)
COLLABORA_URL=https://collabora.pladigit.fr
WOPI_URL=https://pladigit.fr
COLLABORA_TOKEN_TTL=14400
```

### 2.5 Migrations

```bash
sudo -u deploy php artisan migrate \
  --database=mysql \
  --path=database/migrations/platform

sudo -u deploy php artisan migrate \
  --database=tenant \
  --path=database/migrations/tenant
```

### 2.6 Permissions

```bash
sudo chown -R deploy:www-data /var/www/pladigit
sudo chmod -R 755 /var/www/pladigit
sudo chmod -R 775 /var/www/pladigit/storage
sudo chmod -R 775 /var/www/pladigit/bootstrap/cache
```

### 2.7 Optimisations production

```bash
sudo -u deploy php artisan config:cache
sudo -u deploy php artisan route:cache
sudo -u deploy php artisan view:cache
sudo -u deploy php artisan event:cache
```

---

## Partie 3 — Nginx

### 3.1 Virtual host wildcard

Créer `/etc/nginx/sites-available/pladigit` :

```nginx
server {
    listen 80;
    server_name pladigit.fr *.pladigit.fr;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name pladigit.fr *.pladigit.fr;

    root /var/www/pladigit/public;
    index index.php;

    ssl_certificate /etc/letsencrypt/live/pladigit.fr/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/pladigit.fr/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;

    # Headers sécurité
    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";
    add_header Referrer-Policy "strict-origin-when-cross-origin";
    add_header Permissions-Policy "camera=(), microphone=(), geolocation=()";

    client_max_body_size 110M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 120;
    }

    location ~* \.(jpg|jpeg|png|gif|ico|css|js|woff2)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }

    location ~ /\.ht { deny all; }
}
```

```bash
sudo ln -s /etc/nginx/sites-available/pladigit /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
```

### 3.2 SSL Let's Encrypt (wildcard)

```bash
sudo apt install -y certbot python3-certbot-dns-ovh
# Ou avec python3-certbot-nginx pour un domaine simple :
sudo certbot --nginx -d pladigit.fr -d "*.pladigit.fr"
```

---

## Partie 4 — Queue worker (Supervisor)

```bash
sudo apt install -y supervisor
```

Créer `/etc/supervisor/conf.d/pladigit-worker.conf` :

```ini
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
stdout_logfile=/var/www/pladigit/storage/logs/worker.log
stopwaitsecs=3600
```

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start pladigit-worker:*
```

---

## Partie 5 — Scheduler Laravel

Ajouter au crontab de l'utilisateur `deploy` :

```bash
sudo -u deploy crontab -e
# Ajouter :
* * * * * cd /var/www/pladigit && php artisan schedule:run >> /dev/null 2>&1
```

---

## Partie 6 — Collabora Online (optionnel)

Collabora Online s'installe via Docker.

> ⚠ **Ressources requises :** 4 Go RAM minimum dédié à Collabora. Sur un VPS 8 Go, Collabora + Pladigit coexistent correctement.

```bash
sudo apt install -y docker.io docker-compose-plugin
sudo usermod -aG docker deploy
```

Utiliser le `docker-compose.yml` fourni :

```bash
cd /var/www/pladigit
docker compose up -d collabora
docker ps | grep collabora  # vérifier que le conteneur tourne
```

Configurer ensuite dans Pladigit : **Admin > GED > Collabora** — renseigner l'URL de votre instance.

---

## Partie 7 — Créer la première organisation

```bash
sudo -u deploy php artisan tenant:create \
  --name="Ma Mairie" \
  --slug="mairie" \
  --email="admin@mairie.pladigit.fr"
```

Se connecter sur `https://mairie.pladigit.fr` avec les identifiants affichés.

---

## Partie 8 — Vérifications finales

```bash
# Health check
curl https://pladigit.fr/health

# Tests (hors LDAP et intégration)
sudo -u deploy php artisan test --exclude-group ldap,integration

# Vider les caches si nécessaire
sudo -u deploy php artisan cache:clear
sudo -u deploy php artisan config:cache
sudo -u deploy php artisan route:cache
```

Voir aussi [docs/divers/checklist-mise-en-prod.md](docs/divers/checklist-mise-en-prod.md) pour la checklist complète avant ouverture aux utilisateurs.
