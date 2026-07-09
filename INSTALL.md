# INSTALL.md — Pladigit

> Guide d'installation pour déployer Pladigit en production.
> Ubuntu 24.04 LTS — Juin 2026.

---

## Deux modes d'installation

| Mode | Public cible | Durée |
|------|-------------|-------|
| **Installation automatique** | Tout administrateur | ~15 min |
| **Installation manuelle** | Administrateurs système expérimentés | ~45 min |

---

## Installation automatique

### Prérequis matériels

| Configuration | Usage |
|--------------|-------|
| 2 vCPU / 4 Go RAM / 25 Go SSD | Sans Collabora |
| 4 vCPU / 8 Go RAM / 75 Go SSD | Avec Collabora |
| 8 vCPU / 16 Go RAM / 200 Go SSD | Production multi-organisations |

**OS requis :** Ubuntu 24.04 LTS (recommandé) ou 22.04 LTS
**Hébergeurs recommandés :** OVH, Scaleway, Infomaniak (hébergeurs français)

### Étape 1 — Installer Ubuntu Server

Téléchargez Ubuntu Server 24.04 LTS sur [ubuntu.com/download/server](https://ubuntu.com/download/server).

> Lors de l'installation Ubuntu, choisissez **"Use entire disk"** pour allouer tout l'espace disque. Si vous utilisez LVM, le script d'installation Pladigit étend automatiquement le volume.

### Étape 2 — Lancer l'installation

Connectez-vous à votre serveur via SSH, puis exécutez :

```bash
curl -fsSL https://pladigit.fr/install.sh | sudo bash
```

Le script installe automatiquement :
- PHP 8.4 natif Ubuntu (dépôts universe)
- MySQL 8, Redis, Nginx, Supervisor, Node.js 20
- Le code source de Pladigit et ses dépendances

> **Note imagick :** si vous observez une erreur PHP imagick sur PHP 8.4/8.5, vérifiez avec `php -m | grep imagick`. Le script tente l'installation mais l'extension peut être indisponible selon la version PHP — elle n'est pas bloquante pour le fonctionnement de Pladigit.

À la fin, le script affiche l'URL de l'assistant de configuration.

### Étape 3 — Configurer via l'assistant web

Depuis votre navigateur, accédez à l'URL affichée par le script :

```
http://ADRESSE-IP-DU-SERVEUR/install/
```

L'assistant vous guide en quelques étapes :

1. **Vérification** — le système est-il compatible ?
2. **Application** — URL et nom de votre organisation
3. **Email** — configuration SMTP optionnelle
4. **Collabora** — choix du mode (Docker local, instance externe, ou plus tard)
5. **Sécurité** — configuration GPG pour le chiffrement des sauvegardes
6. **Administrateur** — création du compte Super Admin
7. **Récapitulatif** — vérification avant lancement
8. **Installation** — lancement automatique avec barre de progression

> ⚠ **Notez vos identifiants et votre passphrase GPG** affichés sur la page de confirmation. Ils ne seront plus affichés ensuite. Conservez la passphrase GPG dans un gestionnaire de mots de passe — sans elle, vos sauvegardes chiffrées sont irrécupérables.

### Étape 4 — Installer Collabora (optionnel)

Collabora Online (édition collaborative de documents) est un module optionnel qui nécessite Docker. Il ne s'installe pas automatiquement lors de l'installation standard.

Pour l'activer après installation :

```bash
sudo bash /var/www/pladigit/install.sh --collabora-only https://votre-domaine.fr /var/www/pladigit
```

---

## Post-installation

### Vérifier que l'installeur est verrouillé

Après installation, l'accès à `/install/` doit retourner 403 :

```bash
curl -sk -o /dev/null -w "%{http_code}\n" https://votre-domaine.fr/install/
# Doit retourner : 403
```

### Créer la première organisation

Connectez-vous sur `https://votre-domaine.fr/super-admin` avec les identifiants définis lors de l'installation.

1. **Organisations** → **Nouvelle organisation**
2. Renseignez le slug (ex : `mairie-soullans`) — **définitif, impossible à modifier**
3. Choisissez le plan : `Communautaire`
4. Validez

L'organisation est accessible sur `https://slug.votre-domaine.fr`.

### Configurer un nom de domaine

Si vous disposez d'un nom de domaine, ajoutez dans votre zone DNS :

```
@    A    VOTRE-IP
*    A    VOTRE-IP
www  A    VOTRE-IP
```

Pour un certificat wildcard (requis pour les sous-domaines tenant) via OVH :

```bash
sudo pip3 install certbot-dns-ovh --break-system-packages
sudo certbot certonly --dns-ovh --dns-ovh-credentials /etc/ovh.ini \
  -d votre-domaine.fr -d "*.votre-domaine.fr"
```

### Mettre à jour Pladigit

Les mises à jour se font depuis l'interface Super Admin → Mise à jour, sans accès SSH.

En cas de mise à jour manuelle :

```bash
cd /var/www/pladigit
sudo git pull origin main
sudo -u www-data composer install --no-dev --optimize-autoloader
sudo -u www-data php artisan migrate --force
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache
sudo supervisorctl restart pladigit-worker:*
```

---

## Installation manuelle

> Pour les techniciens qui souhaitent contrôler chaque étape ou installer Pladigit sur un serveur existant.

### Prérequis

Même configuration matérielle que l'installation automatique. OS : Ubuntu 24.04 LTS.

### 1. Mise à jour du système

```bash
sudo apt update && sudo apt upgrade -y
```

### 2. PHP 8.4 (natif Ubuntu)

```bash
sudo add-apt-repository -y universe && sudo apt update

sudo apt install -y php8.4 php8.4-cli php8.4-fpm php8.4-common \
  php8.4-mysql php8.4-xml php8.4-curl php8.4-gd \
  php8.4-mbstring php8.4-opcache php8.4-zip php8.4-intl \
  php8.4-redis php8.4-bcmath php8.4-ldap
```

Vérification :

```bash
php8.4 --version
php8.4 -m | grep -E 'mysql|redis|mbstring|curl|zip|intl|ldap|bcmath|gd'
```

> **Note imagick :** `php8.4-imagick` peut être indisponible selon les dépôts. Tenter l'installation — si elle échoue, Pladigit fonctionne sans.

### 3. MySQL 8

```bash
sudo apt install -y mysql-server
```

MySQL sur Ubuntu utilise l'authentification par socket pour root — **ne pas modifier cela**. Créer uniquement l'utilisateur applicatif Pladigit :

```bash
sudo mysql << 'EOF'
CREATE USER 'pladigit'@'localhost' IDENTIFIED BY 'VOTRE_MOT_DE_PASSE_FORT';
GRANT ALL PRIVILEGES ON *.* TO 'pladigit'@'localhost' WITH GRANT OPTION;
FLUSH PRIVILEGES;
EOF
```

> `GRANT ALL PRIVILEGES ON *.*` est nécessaire car Pladigit crée dynamiquement une base par organisation.

### 4. Redis

```bash
sudo apt install -y redis-server
sudo systemctl enable redis-server
```

### 5. Composer

```bash
curl -sS https://getcomposer.org/installer | sudo php -- --install-dir=/usr/local/bin --filename=composer
```

### 6. Node.js 20

```bash
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs
```

### 7. Nginx + Supervisor

```bash
sudo apt install -y nginx supervisor
sudo systemctl enable nginx supervisor
```

### 8. Déploiement de Pladigit

```bash
sudo git clone https://github.com/jpbosse/pladigit.git /var/www/pladigit
sudo chown -R www-data:www-data /var/www/pladigit
cd /var/www/pladigit
sudo -u www-data composer install --no-dev --optimize-autoloader
sudo npm ci && sudo npm run build
```

### 9. Configuration de l'environnement

```bash
sudo -u www-data cp .env.example .env
sudo -u www-data php artisan key:generate
sudo nano /var/www/pladigit/.env
```

Variables essentielles :

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://votre-domaine.fr

DB_DATABASE=pladigit
DB_USERNAME=pladigit
DB_PASSWORD=VOTRE_MOT_DE_PASSE_FORT

SESSION_DOMAIN=.votre-domaine.fr
QUEUE_CONNECTION=redis

SUPER_ADMIN_EMAIL=votre@email.fr
SUPER_ADMIN_ALLOWED_IPS=VOTRE_IP_ADMIN

MAIL_MAILER=smtp
MAIL_HOST=smtp.votre-fournisseur.fr
MAIL_PORT=587
MAIL_SCHEME=tls
MAIL_USERNAME=contact@votre-domaine.fr
MAIL_PASSWORD=VOTRE_MOT_DE_PASSE_MAIL
MAIL_FROM_ADDRESS=contact@votre-domaine.fr
MAIL_FROM_NAME="Pladigit"
```

> **Droits sur le `.env` (critique)**
> Les workers Supervisor tournent sous `www-data` et doivent pouvoir lire le `.env` :
> ```bash
> sudo chown www-data:www-data /var/www/pladigit/.env
> sudo chmod 640 /var/www/pladigit/.env
> ```

### 10. Migrations

```bash
sudo -u www-data php artisan migrate --force
sudo -u www-data php artisan migrate --path=database/migrations/platform --force
```

### 11. Optimisation

```bash
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache
sudo -u www-data php artisan storage:link
```

### 12. Configuration Nginx

```bash
sudo nano /etc/nginx/sites-available/pladigit
```

```nginx
server {
    listen 80;
    server_name votre-domaine.fr www.votre-domaine.fr *.votre-domaine.fr;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl;
    server_name votre-domaine.fr www.votre-domaine.fr *.votre-domaine.fr;

    ssl_certificate /etc/letsencrypt/live/votre-domaine.fr-0001/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/votre-domaine.fr-0001/privkey.pem;
    include /etc/letsencrypt/options-ssl-nginx.conf;
    ssl_dhparam /etc/letsencrypt/ssl-dhparams.pem;

    root /var/www/pladigit/public;
    index index.php;
    charset utf-8;
    client_max_body_size 100M;

    # Bloquer l'accès à l'installeur après installation
    location ^~ /install/ {
        deny all;
        return 403;
    }

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root/index.php;
        fastcgi_param SCRIPT_NAME /index.php;
        include fastcgi_params;
    }

    location ~ /\.ht {
        deny all;
    }
}
```

```bash
sudo ln -s /etc/nginx/sites-available/pladigit /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t && sudo systemctl restart nginx
```

### 13. Supervisor (queues)

```bash
sudo nano /etc/supervisor/conf.d/pladigit-worker.conf
```

```ini
[program:pladigit-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/pladigit/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/pladigit-worker.log
stopwaitsecs=3600
```

```bash
sudo supervisorctl reread && sudo supervisorctl update
sudo supervisorctl start pladigit-worker:*
```

---

## Dépannage

### Erreur 500 après installation

```bash
sudo -u www-data php /var/www/pladigit/artisan config:clear
sudo -u www-data php /var/www/pladigit/artisan config:cache
sudo tail -50 /var/www/pladigit/storage/logs/laravel.log
```

### Workers Supervisor en erreur

```bash
sudo tail -50 /var/log/pladigit-worker.log
sudo supervisorctl status
sudo supervisorctl restart pladigit-worker:*
```

### MySQL : Access denied

Sur Ubuntu, MySQL utilise l'authentification par socket pour root. Toujours utiliser `sudo mysql` sans mot de passe :

```bash
sudo mysql -u root pladigit -e "SHOW TABLES;"
```

### Permissions storage

```bash
sudo chown -R www-data:www-data /var/www/pladigit/storage
sudo chmod -R 775 /var/www/pladigit/storage
```

### Espace disque insuffisant (LVM)

Ubuntu Server alloue ~50 % du volume LVM par défaut. Pour étendre :

```bash
sudo lvextend -l +100%FREE /dev/mapper/ubuntu--vg-ubuntu--lv
sudo resize2fs /dev/mapper/ubuntu--vg-ubuntu--lv
```

### GNUPG — erreur GPG lors des sauvegardes

Si les sauvegardes GPG échouent avec "code 2", vérifier que le répertoire GNUPG de www-data est accessible :

```bash
ls -la /var/www/pladigit/storage/.gnupg
# Si le dossier n'existe pas :
sudo -u www-data mkdir -p /var/www/pladigit/storage/.gnupg
sudo chmod 700 /var/www/pladigit/storage/.gnupg
```

---

## OVH — Points d'attention

**SMTP sortant bloqué** — OVH bloque le port SMTP 25 par défaut. Utiliser le port 587 (TLS) ou 465 (SSL). Si nécessaire, ouvrir un ticket support pour le déblocage.

**Clé SSH obligatoire** — Ubuntu 24.04 sur OVH n'autorise que l'authentification par clé SSH.

**Certificat wildcard** — expire dans 90 jours. Configurer le renouvellement automatique via `certbot-dns-ovh` :

```bash
echo "0 0 1 * * root certbot renew --quiet" | sudo tee /etc/cron.d/certbot-renew
```

---

*Pladigit — AGPL-3.0 — contact@pladigit.fr — [github.com/jpbosse/pladigit](https://github.com/jpbosse/pladigit)*
