# Déploiement Collabora Online — Pladigit

Ce guide couvre les deux environnements : **VPS production** et **développement local**.

---

## Variables `.env` requises

```dotenv
# URL publique de Collabora, accessible depuis le navigateur
# VPS  : https://pladigit.fr  (Collabora proxyfié sous le même domaine)
# Local: https://pladigit.local
COLLABORA_URL=https://pladigit.fr

# URL de base des endpoints WOPI — accessible depuis le conteneur Collabora
# VPS  : https://pladigit.fr
# Local: http://172.21.0.1  (bridge Docker vers l'hôte)
WOPI_URL=https://pladigit.fr

# URL interne Collabora pour le discovery (serveur Laravel → Collabora)
# Toujours http://127.0.0.1:9980 sauf configuration exotique
COLLABORA_INTERNAL_URL=http://127.0.0.1:9980

# Secret WOPI (optionnel, pour signature des requêtes)
COLLABORA_WOPI_SECRET=

# TTL des tokens WOPI en secondes (défaut 14400 = 4 heures)
COLLABORA_TOKEN_TTL=14400
```

> **Pourquoi `COLLABORA_INTERNAL_URL` ?**
> Laravel appelle `/hosting/discovery` au premier accès à l'éditeur pour récupérer
> le chemin avec hash de version (`/browser/4610258811/cool.html`). Sans ce hash,
> le navigateur charge un JS périmé depuis son cache et l'`access_token` n'est pas
> transmis au wsd → GetFile reçoit un token vide → échec d'ouverture.

---

## VPS — Production

### 1. Prérequis

- Docker installé sur le VPS
- Nginx configuré avec SSL Let's Encrypt wildcard (`*.pladigit.fr`)
- Pladigit déployé et accessible en HTTPS

### 2. Fichier de configuration coolwsd.xml

```bash
mkdir -p /opt/collabora
```

Créer `/opt/collabora/coolwsd.xml` :

```xml
<coolwsd>
  <net>
    <!-- CSP : autorise l'iframe Collabora depuis les domaines Pladigit -->
    <content_security_policy>frame-ancestors https://pladigit.fr https://*.pladigit.fr</content_security_policy>
  </net>

  <ssl>
    <!-- SSL géré par Nginx en amont, Collabora reçoit du HTTP en interne -->
    <enable>false</enable>
    <termination>true</termination>
    <!-- Utilise le schéma du WOPISrc pour décider HTTP/HTTPS vers le storage -->
    <as_scheme>true</as_scheme>
  </ssl>

  <logging>
    <level>warning</level>
  </logging>

  <user_interface>
    <mode>compact</mode>
  </user_interface>

  <storage>
    <wopi allow="true">
      <!--
        alias_groups mode="groups" : chaque <group> définit un hôte WOPI autorisé.
        IMPORTANT : mettre chaque <host> dans son propre <group>.
        Deux <host> dans le même <group> = seul le premier est actif.
      -->
      <alias_groups mode="groups">
        <group>
          <host allow="true">https://pladigit\.fr</host>
          <alias>https://[a-zA-Z0-9\-]+\.pladigit\.fr</alias>
          <alias>https://pladigit\.duckdns\.org</alias>
        </group>
      </alias_groups>
    </wopi>
  </storage>
</coolwsd>
```

### 3. Lancer le conteneur

```bash
docker run -d \
  --name collabora \
  --restart unless-stopped \
  -p 127.0.0.1:9980:9980 \
  -v /opt/collabora/coolwsd.xml:/etc/coolwsd/coolwsd.xml:ro \
  -e "DONT_GEN_SSL_CERT=true" \
  -e "username=admin" \
  -e "password=CHANGEME_MOT_DE_PASSE_FORT" \
  --cap-add MKNOD \
  collabora/code:latest
```

### 4. Nginx — blocs à ajouter dans le vhost SSL Pladigit

```nginx
# Fichiers statiques Collabora (JS/CSS de l'éditeur)
location ^~ /browser {
    proxy_pass         http://127.0.0.1:9980;
    proxy_set_header   Host              $http_host;
    proxy_set_header   X-Forwarded-Proto https;
    proxy_read_timeout 600s;
}

# Discovery WOPI (liste des types MIME supportés)
location ^~ /hosting/discovery {
    proxy_pass       http://127.0.0.1:9980;
    proxy_set_header Host              $http_host;
    proxy_set_header X-Forwarded-Proto https;
}

# Capacités de l'instance
location ^~ /hosting/capabilities {
    proxy_pass       http://127.0.0.1:9980;
    proxy_set_header Host              $http_host;
    proxy_set_header X-Forwarded-Proto https;
}

# WebSocket — sessions d'édition temps réel
location ^~ /cool {
    proxy_pass             http://127.0.0.1:9980;
    proxy_http_version     1.1;
    proxy_set_header       Upgrade    $http_upgrade;
    proxy_set_header       Connection "Upgrade";
    proxy_set_header       Host       $http_host;
    proxy_set_header       X-Forwarded-Proto https;
    proxy_read_timeout     36000s;
    proxy_send_timeout     36000s;
    proxy_connect_timeout  36000s;
}
```

```bash
nginx -t && systemctl reload nginx
```

### 5. Configuration dans Pladigit (interface admin)

**Admin > Paramètres > GED / Collabora** :

| Champ | Valeur |
|---|---|
| URL Collabora | `https://pladigit.fr` |
| URL WOPI | `https://pladigit.fr` |
| TTL token | 240 minutes (défaut) |

Cliquer **Tester la connexion** → doit retourner ✓.

### 6. Droits du répertoire de stockage GED

```bash
# Créer le répertoire de stockage GED s'il n'existe pas
mkdir -p /var/www/pladigit/storage/app/private/ged

# www-data (PHP-FPM) doit pouvoir lire/écrire
chmod 775 /var/www/pladigit/storage/app/private/ged
chown deploy:www-data /var/www/pladigit/storage/app/private/ged
```

> **Important** : le disk `local` de Laravel 11 pointe sur `storage/app/private/`
> (et non `storage/app/`). Sans `chmod 775`, PHP-FPM (www-data) ne peut pas
> lire les fichiers → GetFile retourne 404.

> **Sur une installation démo** : ne pas copier les fichiers manuellement.
> `php artisan demo:reset` s'en charge automatiquement — il lit `storage/demo_ged/`
> (source immuable, versionnée dans git), génère un UUID par fichier et écrit dans
> `storage/app/private/ged/`. À chaque reset, ce répertoire est effacé et
> recréé proprement. `storage/demo_ged/` n'est jamais modifié.

---

## Développement local

### Différences avec le VPS

| | VPS | Local |
|---|---|---|
| `COLLABORA_URL` | `https://pladigit.fr` | `https://pladigit.local` |
| `WOPI_URL` | `https://pladigit.fr` | `http://172.21.0.1` |
| Accès WOPI depuis Collabora | Via HTTPS public | Via bridge Docker (`172.21.0.1:80`) |
| SSL | Cert valide Let's Encrypt | Cert `pladigit.fr` réutilisé (warning navigateur normal) |

### 1. coolwsd.xml local

Modifier **dans le conteneur** (pas dans le repo git) :

```bash
# Copier le fichier existant
docker cp collabora:/etc/coolwsd/coolwsd.xml /tmp/coolwsd.xml
# Éditer /tmp/coolwsd.xml, puis
docker cp /tmp/coolwsd.xml collabora:/etc/coolwsd/coolwsd.xml
docker restart collabora
```

Modifications à appliquer dans `/etc/coolwsd/coolwsd.xml` :

**CSP frame-ancestors** — inclure HTTP et HTTPS local :
```xml
<content_security_policy>frame-ancestors
  http://pladigit.local http://*.pladigit.local
  https://pladigit.local https://*.pladigit.local
  https://pladigit.fr https://*.pladigit.fr
  http://172.21.0.1
</content_security_policy>
```

**alias_groups** — ajouter un groupe dédié dev local :
```xml
<alias_groups mode="groups">
  <!-- Groupe 1 : production -->
  <group>
    <host allow="true">https://pladigit\.fr</host>
    <alias>https://[a-zA-Z0-9\-]+\.pladigit\.fr</alias>
    <alias>https://pladigit\.duckdns\.org</alias>
  </group>
  <!-- Groupe 2 : dev local — bridge Docker -->
  <group>
    <host allow="true">http://172\.21\.0\.1</host>
    <alias>http://pladigit\.local</alias>
  </group>
</alias_groups>
```

> **Piège connu** : deux `<host>` dans le même `<group>` XML → seul le premier est
> actif. Un groupe par hôte principal.

### 2. Nginx local — vhost 172.21.0.1

Ce vhost permet à Collabora (dans le conteneur Docker) d'atteindre les
endpoints WOPI de Laravel via le bridge Docker.

Ajouter dans `/etc/nginx/sites-available/pladigit.conf` :

```nginx
# ─── Callbacks WOPI depuis Collabora Docker ───────────────────────────────────
server {
    listen 80;
    server_name 172.21.0.1;

    access_log /var/log/nginx/wopi_access.log;
    error_log  /var/log/nginx/wopi_error.log;

    root /var/www/pladigit/public;

    location ^~ /wopi/ {
        fastcgi_pass   unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param  SCRIPT_FILENAME /var/www/pladigit/public/index.php;
        fastcgi_param  QUERY_STRING    $query_string;
        fastcgi_param  REQUEST_URI     $request_uri;
        fastcgi_param  PATH_INFO       $fastcgi_path_info;
        include        fastcgi_params;
    }

    location / { return 404; }
}
```

### 3. Variables `.env` locales

```dotenv
APP_URL=http://pladigit.local
COLLABORA_URL=https://pladigit.local
COLLABORA_INTERNAL_URL=http://127.0.0.1:9980
WOPI_URL=http://172.21.0.1
```

### 4. Droits du répertoire GED (même chose qu'en prod)

```bash
mkdir -p /var/www/pladigit/storage/app/private/ged
chmod 775 /var/www/pladigit/storage/app/private/ged
```

---

## Vérification

```bash
# Collabora répond (VPS)
curl -s https://pladigit.fr/hosting/capabilities | python3 -m json.tool | head -5

# Collabora répond (local)
curl -sk https://pladigit.local/hosting/capabilities | python3 -m json.tool | head -5

# Version Collabora avec hash de version (doit contenir un hash numérique)
curl -s http://127.0.0.1:9980/hosting/discovery | grep -o 'browser/[0-9]*/cool.html' | head -1

# Conteneur actif
docker ps --filter name=collabora --format "table {{.Names}}\t{{.Status}}"

# Logs (filtrer le bruit de démarrage)
docker logs collabora --tail 20 2>&1 | grep -E ' (ERR|WRN) ' | \
  grep -v 'systemplate\|chroot\|jails\|kit_spare\|proof_key'
```

---

## Commandes utiles

```bash
# Redémarrer après modification de coolwsd.xml
docker restart collabora

# Vider le cache du chemin discovery (si Collabora a été mis à jour)
php artisan cache:forget collabora.discovery_editor_path

# Mettre à jour l'image Collabora
docker pull collabora/code:latest
docker stop collabora && docker rm collabora
# puis relancer le docker run

# Inspecter le coolwsd.xml actif dans le conteneur
docker exec collabora cat /etc/coolwsd/coolwsd.xml | grep -A5 'alias_groups'
```

---

## Diagnostics fréquents

### "Échec de lecture du document depuis le stockage"

Vérifier dans l'ordre :

1. **Fichier physique existe** — le `disk_path` en DB pointe vers un fichier réel :
   ```bash
   # Trouver le disk_path via tinker
   php artisan tinker --execute="
   \$org = \App\Models\Platform\Organization::where('slug','demo')->first();
   app(\App\Services\TenantManager::class)->connectTo(\$org);
   \App\Models\Tenant\GedDocument::find(1)->disk_path;
   "
   # Vérifier que le fichier existe dans storage/app/private/
   ls storage/app/private/ged/
   ```

2. **Permissions** — `storage/app/private/ged/` doit être lisible par `www-data` :
   ```bash
   ls -la storage/app/private/ged/
   # Doit afficher drwxrwxr-x (775) ou drwxr-xr-x (755)
   ```

3. **Hash discovery** — sans hash, le JS est périmé, le token n'est pas transmis :
   ```bash
   # Vider le cache et vérifier que la prochaine ouverture charge le bon chemin
   php artisan cache:forget collabora.discovery_editor_path
   # Dans les logs Collabora, "version mismatch" ne doit pas apparaître
   ```

4. **alias_groups mal formés** — deux `<host>` dans le même `<group>` = seul le premier actif :
   ```bash
   docker exec collabora grep -A3 'alias_groups' /etc/coolwsd/coolwsd.xml
   ```

5. **Logs Collabora** — erreur précise :
   ```bash
   docker logs collabora --since=5m 2>&1 | grep -E ' ERR '
   ```

### Token vide dans GetFile (`access_token=`)

Cause : le navigateur avait un JS Collabora périmé en cache.
Fix : vider le cache discovery (point 3 ci-dessus) puis Ctrl+Shift+R dans le navigateur.

### 404 HTML sur les endpoints WOPI

Cause probable : le fichier n'existe pas dans `storage/app/private/ged/`
ou les permissions empêchent PHP-FPM de le lire.

---

## Notes

- Collabora CODE (gratuit) : 20 connexions simultanées max. Au-delà, licence payante.
- `coolwsd.xml` en `:ro` dans Docker — toute modification requiert `docker restart collabora`.
- Le cache discovery (`collabora.discovery_editor_path`) expire après 24h ou manuellement
  via `php artisan cache:forget collabora.discovery_editor_path`.
