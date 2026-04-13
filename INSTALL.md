# INSTALL.md — Pladigit

> Guide d'installation complet pour déployer Pladigit en production.  
> Basé sur un déploiement réel sur VPS OVH Ubuntu 24.04 LTS — Avril 2026.

---

## Prérequis

### Serveur recommandé

| Configuration | Usage |
|---|---|
| 2 vCPU / 4 Go RAM / 40 Go SSD | Sans Collabora |
| 4 vCPU / 8 Go RAM / 75 Go SSD | Avec Collabora (démo) |
| 8 vCPU / 16 Go RAM / 200 Go SSD | Production multi-organisations |

**OS recommandé :** Ubuntu 24.04 LTS  
**Hébergeur recommandé :** OVH, Scaleway, Infomaniak (hébergeurs français)

### Nom de domaine

Vous avez besoin d'un domaine avec :
- Un enregistrement `A` pointant vers votre IP (`@` → IP)
- Un enregistrement `A` wildcard (`*` → IP) pour les sous-domaines tenant
- Un enregistrement `A` pour `www` (`www` → IP)

---

## 1. Connexion SSH

Connectez-vous à votre serveur avec votre clé SSH :

```bash
ssh ubuntu@VOTRE_IP
```

> ⚠️ Sur OVH, Ubuntu 24.04 utilise l'authentification par clé SSH uniquement.  
> Générez votre clé lors de la commande ou via `ssh-keygen -t ed25519`.

---

## 2. Mise à jour du système

```bash
sudo apt update && sudo apt upgrade -y
sudo reboot
```

Reconnectez-vous après le reboot.

---

## 3. PHP 8.4

```bash
sudo add-apt-repository ppa:ondrej/php -y && sudo apt update

sudo apt install -y php8.4 php8.4-cli php8.4-fpm php8.4-common \
  php8.4-mysql php8.4-xml php8.4-curl php8.4-gd php8.4-imagick \
  php8.4-imap php8.4-mbstring php8.4-opcache php8.4-soap \
  php8.4-zip php8.4-intl php8.4-redis php8.4-bcmath php8.4-ldap
```

Vérification :

```bash
php8.4 --version
php8.4 -m | grep -E 'mysql|redis|mbstring|curl|zip|intl|ldap|bcmath|gd'
```

---

## 4. MySQL 8

```bash
sudo apt install -y mysql-server
sudo mysql_secure_installation
```

Répondez aux questions :
- Validate password plugin → `n`
- Remove anonymous users → `y`
- Disallow root login remotely → `y`
- Remove test database → `y`
- Reload privilege tables → `y`

Créez l'utilisateur et les bases :

```bash
sudo mysql -u root
```

```sql
CREATE DATABASE pladigit_platform CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'pladigit'@'localhost' IDENTIFIED BY 'VOTRE_MOT_DE_PASSE_FORT';
GRANT ALL PRIVILEGES ON *.* TO 'pladigit'@'localhost' WITH GRANT OPTION;
FLUSH PRIVILEGES;
EXIT;
```

> ⚠️ Le `GRANT ALL PRIVILEGES ON *.*` est nécessaire car Pladigit crée dynamiquement
> une base de données par organisation lors du provisioning.

---

## 5. Redis

```bash
sudo apt install -y redis-server
sudo systemctl enable redis-server
sudo systemctl status redis-server
```

---

## 6. Composer

```bash
curl -sS https://getcomposer.org/installer -o /tmp/composer-setup.php
sudo php8.4 /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer
composer --version
```

---

## 7. Node.js 20

```bash
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs
node --version && npm --version
```

---

## 8. Nginx

```bash
sudo apt install -y nginx
sudo systemctl enable nginx
```

---

## 9. Supervisor

```bash
sudo apt install -y supervisor
sudo systemctl enable supervisor
```

---

## 10. Déploiement de Pladigit

### Cloner le dépôt

```bash
cd /var/www
sudo git clone https://github.com/jpbosse/pladigit.git
sudo chown -R ubuntu:www-data /var/www/pladigit
cd /var/www/pladigit
```

### Installer les dépendances

```bash
composer install --no-dev --optimize-autoloader
npm install && npm run build
```

### Configurer l'environnement

```bash
cp .env.example .env
php8.4 artisan key:generate
nano .env
```

Variables essentielles à configurer :

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://votre-domaine.fr

DB_DATABASE=pladigit_platform
DB_USERNAME=pladigit
DB_PASSWORD=VOTRE_MOT_DE_PASSE_FORT

SESSION_DOMAIN=.votre-domaine.fr

SUPER_ADMIN_EMAIL=votre@email.fr
SUPER_ADMIN_PASSWORD_HASH=           # voir section suivante

MAIL_HOST=smtp.votre-fournisseur.fr
MAIL_PORT=465
MAIL_SCHEME=ssl
MAIL_USERNAME=contact@votre-domaine.fr
MAIL_PASSWORD=VOTRE_MOT_DE_PASSE_MAIL
MAIL_FROM_ADDRESS=contact@votre-domaine.fr
MAIL_FROM_NAME="Pladigit"
```

> ⚠️ Ne pas mettre de commentaire `#` sur la même ligne que les valeurs — Laravel
> les interpréterait comme faisant partie de la valeur.

### Générer le hash du mot de passe Super Admin

```bash
php8.4 artisan tinker
```

```php
echo bcrypt('VotreMotDePasseSuperAdmin');
```

Copiez le résultat dans `SUPER_ADMIN_PASSWORD_HASH=` sans espace ni commentaire.

### Lancer les migrations

```bash
# Tables de base Laravel
php8.4 artisan migrate --force

# Tables de la plateforme Pladigit
php8.4 artisan migrate --path=database/migrations/platform --force
```

> ⚠️ Les deux commandes sont nécessaires — ne pas oublier la seconde.

### Permissions

```bash
sudo chown -R ubuntu:www-data /var/www/pladigit
sudo chmod -R 775 /var/www/pladigit/storage
sudo chmod -R 775 /var/www/pladigit/bootstrap/cache
```

### Optimiser Laravel

```bash
php8.4 artisan config:cache
php8.4 artisan route:cache
php8.4 artisan view:cache
```

---

## 11. Configuration Nginx

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
        fastcgi_pass unix:/var/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
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
sudo nginx -t
sudo systemctl restart nginx
```

---

## 12. Certificat SSL wildcard (Let's Encrypt)

```bash
sudo apt install -y certbot python3-certbot-nginx

sudo certbot certonly --manual --preferred-challenges dns \
  -d votre-domaine.fr -d "*.votre-domaine.fr"
```

Certbot vous demande d'ajouter un enregistrement TXT dans votre zone DNS :
- Sous-domaine : `_acme-challenge`
- Type : `TXT`
- Valeur : la valeur affichée par Certbot

Attendez la propagation DNS (2-3 minutes), vérifiez :

```bash
dig TXT _acme-challenge.votre-domaine.fr
```

Quand la valeur apparaît, appuyez sur Entrée dans Certbot.

> ⚠️ Le certificat wildcard expire dans 90 jours. Le renouvellement automatique
> nécessite un plugin DNS (certbot-dns-ovh pour OVH). À configurer pour la production.

Mettez à jour Nginx avec le chemin du nouveau certificat (noter le `-0001`) :

```nginx
ssl_certificate /etc/letsencrypt/live/votre-domaine.fr-0001/fullchain.pem;
ssl_certificate_key /etc/letsencrypt/live/votre-domaine.fr-0001/privkey.pem;
```

---

## 13. Configuration Supervisor (queues)

```bash
sudo nano /etc/supervisor/conf.d/pladigit.conf
```

```ini
[program:pladigit-worker]
process_name=%(program_name)s_%(process_num)02d
command=php8.4 /var/www/pladigit/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=ubuntu
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/pladigit/storage/logs/worker.log
stopwaitsecs=3600
```

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start pladigit-worker:*
sudo supervisorctl status
```

---

## 14. Accès Super Admin

> ⛔ **OBLIGATOIRE — sans cette étape vous obtiendrez un 403 sur `/super-admin/login`**

Ajoutez votre IP publique dans le middleware. Pour la connaître :

```bash
curl ifconfig.me
```

Puis éditez le fichier :

```bash
nano /var/www/pladigit/app/Http/Middleware/CheckSuperAdmin.php
```

```php
private array $allowedIps = [
    '127.0.0.1',
    '::1',
    'VOTRE_IP_PUBLIQUE',  // ex: 82.67.203.161
];
```

> ⚠️ **Après chaque `git pull` ou `git reset --hard`**, ce fichier est écrasé et votre IP disparaît.
> Vous devrez la rajouter manuellement — sinon vous obtiendrez un 403.
>
> À terme, cette liste sera déplacée dans le `.env` via `SUPER_ADMIN_ALLOWED_IPS`.

Accédez au Super Admin : `https://votre-domaine.fr/super-admin/login`

---

## 15. Première organisation

Depuis le Super Admin :
1. **Organisations** → **Nouvelle organisation**
2. Renseignez le slug (ex: `mairie-soullans`)
3. Choisissez le plan : `Communautaire`
4. Validez

L'organisation est accessible sur `https://mairie-soullans.votre-domaine.fr`

Les migrations tenant sont lancées automatiquement lors du provisioning.

---

## 16. OVH — Points d'attention spécifiques

### SMTP sortant bloqué
OVH bloque par défaut les connexions SMTP sortantes sur les VPS.
Ouvrez un ticket support OVH pour demander le déblocage du port SMTP.

### Clé SSH obligatoire
Ubuntu 24.04 sur OVH n'autorise que l'authentification par clé SSH.
Générez votre clé avant la commande du VPS.

### Certificat wildcard
Le certificat wildcard `*.votre-domaine.fr` nécessite une validation DNS manuelle.
Planifiez le renouvellement 90 jours après l'installation.

---

## Mise à jour de Pladigit

> ⚠️ Après le `git pull`, vérifiez que votre IP est toujours dans `CheckSuperAdmin.php` (voir section 14).

```bash
cd /var/www/pladigit
git pull origin main
composer install --no-dev --optimize-autoloader
npm install && npm run build
php8.4 artisan migrate --path=database/migrations/platform --force
php8.4 artisan config:cache
php8.4 artisan route:cache
php8.4 artisan view:cache
sudo supervisorctl restart pladigit-worker:*
```

---

## Environnement de développement local

Pour développer en local, utilisez un domaine `.local` distinct :

Dans `/etc/hosts` :
```
127.0.0.1   votre-domaine.local
127.0.0.1   demo.votre-domaine.local
127.0.0.1   tenant1.votre-domaine.local
```

Dans `.env` local :
```env
APP_URL=http://votre-domaine.local
SESSION_DOMAIN=.votre-domaine.local
```

Cela permet d'avoir local et production simultanément sans conflit DNS.

---

*Pladigit — AGPL-3.0 — contact@pladigit.fr — github.com/jpbosse/pladigit*
