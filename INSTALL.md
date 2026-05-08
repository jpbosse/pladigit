# INSTALL.md — Pladigit

> Guide d'installation pour déployer Pladigit en production.  
> Ubuntu 24.04 LTS — Mai 2026.

---

## Deux modes d'installation

| Mode | Public cible | Durée |
|---|---|---|
| **[Installation automatique](#installation-automatique)** | Tout administrateur | ~15 min |
| **[Installation manuelle](#installation-manuelle)** | Administrateurs système expérimentés | ~45 min |

---

## Installation automatique

### Prérequis matériels

| Configuration | Usage |
|---|---|
| 2 vCPU / 4 Go RAM / 25 Go SSD | Sans Collabora |
| 4 vCPU / 8 Go RAM / 75 Go SSD | Avec Collabora |
| 8 vCPU / 16 Go RAM / 200 Go SSD | Production multi-organisations |

**OS requis :** Ubuntu 22.04 LTS ou 24.04 LTS  
**Hébergeurs recommandés :** OVH, Scaleway, Infomaniak (hébergeurs français)

### Étape 1 — Installer Ubuntu Server

Téléchargez Ubuntu Server 24.04 LTS sur [ubuntu.com/download/server](https://ubuntu.com/download/server) et installez-le sur votre serveur ou machine virtuelle.

> 💡 Lors de l'installation Ubuntu, choisissez **"Use entire disk"** pour allouer tout l'espace disque. Si vous utilisez LVM, le script d'installation Pladigit étend automatiquement le volume.

### Étape 2 — Lancer l'installation

Connectez-vous à votre serveur via SSH ou ouvrez un terminal, puis copiez-collez cette commande :

```bash
curl -fsSL https://pladigit.fr/install.sh | sudo bash
```

Le script installe automatiquement :
- PHP 8.3+ natif Ubuntu (dépôts universe)
- MySQL 8, Redis, Nginx, Supervisor, Node.js 20
- Le code source de Pladigit et ses dépendances

À la fin, le script affiche l'URL de l'assistant de configuration.

### Étape 3 — Configurer via l'assistant web

Depuis votre ordinateur, ouvrez un navigateur et accédez à l'URL affichée par le script :

```
http://ADRESSE-IP-DU-SERVEUR/install/
```

L'assistant vous guide en 8 étapes :

1. **Vérification** — le système est-il compatible ?
2. **Base de données** — connexion MySQL et création de l'utilisateur dédié
3. **Application** — URL et nom de votre organisation
4. **Email** — configuration SMTP optionnelle
5. **Collabora** — choix du mode d'installation (Docker local, instance externe, ou plus tard)
6. **Administrateur** — création du compte Super Admin
7. **Récapitulatif** — vérification des choix avant lancement
8. **Installation** — lancement automatique avec barre de progression

À la fin, une page de confirmation affiche vos identifiants de connexion.

> ⚠️ **Notez vos identifiants** affichés sur la page de confirmation — ils ne seront plus affichés ensuite.

---

## Post-installation

### Créer la première organisation

Connectez-vous sur `http://VOTRE-SERVEUR/super-admin` avec les identifiants définis lors de l'installation.

1. **Organisations** → **Nouvelle organisation**
2. Renseignez le slug (ex: `mairie-soullans`)
3. Choisissez le plan : `Communautaire`
4. Validez

L'organisation est accessible sur `http://VOTRE-SERVEUR` (ou sur son sous-domaine si vous avez configuré un domaine).

### Configurer un nom de domaine (optionnel)

Si vous disposez d'un nom de domaine, ajoutez dans votre zone DNS :

```
@    A    VOTRE-IP
*    A    VOTRE-IP
www  A    VOTRE-IP
```

Puis installez un certificat SSL :

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d votre-domaine.fr -d www.votre-domaine.fr
```

Pour un certificat wildcard (requis pour les sous-domaines tenant) :

```bash
sudo certbot certonly --manual --preferred-challenges dns \
  -d votre-domaine.fr -d "*.votre-domaine.fr"
```

### Mettre à jour Pladigit

```bash
cd /var/www/pladigit
git pull origin main
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan migrate --path=database/migrations/platform --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
sudo supervisorctl restart pladigit-worker:*
```

---

## Installation manuelle

> Pour les administrateurs système qui souhaitent contrôler chaque étape ou installer Pladigit sur un serveur existant.

### Prérequis

Même configuration matérielle que l'installation automatique. OS : Ubuntu 22.04 ou 24.04 LTS.

### 1. Connexion SSH

```bash
ssh ubuntu@VOTRE_IP
```

### 2. Mise à jour du système

```bash
sudo apt update && sudo apt upgrade -y
```

### 3. PHP 8.3+ (natif Ubuntu)

```bash
sudo add-apt-repository -y universe && sudo apt update

sudo apt install -y php8.3 php8.3-cli php8.3-fpm php8.3-common \
  php8.3-mysql php8.3-xml php8.3-curl php8.3-gd php8.3-imagick \
  php8.3-mbstring php8.3-opcache php8.3-zip php8.3-intl \
  php8.3-redis php8.3-bcmath php8.3-ldap
```

Vérification :

```bash
php8.3 --version
php8.3 -m | grep -E 'mysql|redis|mbstring|curl|zip|intl|ldap|bcmath|gd'
```

### 4. MySQL 8

```bash
sudo apt install -y mysql-server
```

Activer l'authentification native (requis pour PDO) :

```bash
sudo mysql -e "ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY ''; FLUSH PRIVILEGES;"
```

Créer l'utilisateur Pladigit :

```bash
sudo mysql -u root
```

```sql
CREATE USER 'pladigit'@'localhost' IDENTIFIED BY 'VOTRE_MOT_DE_PASSE_FORT';
GRANT ALL PRIVILEGES ON *.* TO 'pladigit'@'localhost' WITH GRANT OPTION;
FLUSH PRIVILEGES;
EXIT;
```

> ⚠️ Le `GRANT ALL PRIVILEGES ON *.*` est nécessaire car Pladigit crée dynamiquement une base par organisation.

### 5. Redis

```bash
sudo apt install -y redis-server
sudo systemctl enable redis-server
```

### 6. Composer

```bash
curl -sS https://getcomposer.org/installer | sudo php -- --install-dir=/usr/local/bin --filename=composer
```

### 7. Node.js 20

```bash
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs
```

### 8. Nginx + Supervisor

```bash
sudo apt install -y nginx supervisor
sudo systemctl enable nginx supervisor
```

### 9. Déploiement de Pladigit

```bash
sudo git clone https://github.com/jpbosse/pladigit.git /var/www/pladigit
sudo chown -R www-data:www-data /var/www/pladigit
cd /var/www/pladigit
sudo -u www-data composer install --no-dev --optimize-autoloader
sudo npm ci && sudo npm run build
```

> **⚠ Droits sur le `.env` (critique)**
> Le `.env` est créé à l'étape suivante. Une fois créé, les workers Supervisor
> (qui tournent sous `www-data`) doivent pouvoir le lire. Sans cela, les workers
> crashent silencieusement et les sauvegardes automatiques ne fonctionnent pas.
>
> Après création du `.env`, appliquer systématiquement :
> ```bash
> sudo chown $USER:www-data /var/www/pladigit/.env
> sudo chmod 640 /var/www/pladigit/.env
> ```
> Remplacer `$USER` par l'utilisateur système propriétaire du dépôt
> (ex: `ubuntu` sur OVH, `deploy` sur un serveur dédié).

### 10. Configuration de l'environnement

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

SUPER_ADMIN_EMAIL=votre@email.fr
SUPER_ADMIN_PASSWORD_HASH=           # voir ci-dessous

MAIL_MAILER=smtp
MAIL_HOST=smtp.votre-fournisseur.fr
MAIL_PORT=587
MAIL_SCHEME=tls
MAIL_USERNAME=contact@votre-domaine.fr
MAIL_PASSWORD=VOTRE_MOT_DE_PASSE_MAIL
MAIL_FROM_ADDRESS=contact@votre-domaine.fr
MAIL_FROM_NAME="Pladigit"
```

Générer le hash du mot de passe Super Admin :

```bash
php -r "echo password_hash('VotreMotDePasse', PASSWORD_BCRYPT);"
```

### 11. Migrations

```bash
sudo -u www-data php artisan migrate --force
sudo -u www-data php artisan migrate --path=database/migrations/platform --force
```

### 12. Optimisation

```bash
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache
sudo -u www-data php artisan storage:link
```

### 13. Configuration Nginx

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

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
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

### 14. Supervisor (queues)

```bash
sudo nano /etc/supervisor/conf.d/pladigit.conf
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
sudo tail -20 /var/www/pladigit/storage/logs/laravel-$(date +%Y-%m-%d).log
```

### MySQL : Access denied for user root

Sur Ubuntu, MySQL utilise l'authentification par socket par défaut. Exécutez :

```bash
sudo mysql -e "ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY ''; FLUSH PRIVILEGES;"
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

---

## OVH — Points d'attention

**SMTP sortant bloqué** — OVH bloque le port SMTP par défaut. Ouvrez un ticket support pour le déblocage ou utilisez le port 587/465.

**Clé SSH obligatoire** — Ubuntu 24.04 sur OVH n'autorise que l'authentification par clé SSH.

**Certificat wildcard** — expire dans 90 jours. Configurez le renouvellement automatique via `certbot-dns-ovh`.

---

*Pladigit — AGPL-3.0 — contact@pladigit.fr — [github.com/jpbosse/pladigit](https://github.com/jpbosse/pladigit)*
