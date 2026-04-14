# Déploiement Collabora Online — Pladigit

## Prérequis

- Docker installé sur le VPS
- Nginx configuré avec SSL (Let's Encrypt)
- Pladigit déployé et accessible via HTTPS

---

## 1. Fichier de configuration Collabora

Créer le répertoire et le fichier de config :

```bash
mkdir -p /root/collabora-config
```

Créer `/root/collabora-config/coolwsd.xml` :

```xml
<coolwsd>
  <net>
    <!-- Autorise l'iframe Collabora depuis tous les sous-domaines Pladigit -->
    <content_security_policy>frame-ancestors https://pladigit.fr https://*.pladigit.fr</content_security_policy>
    <post_allow>
      <host>::ffff:127\.0\.0\.1</host>
    </post_allow>
    <lok_allow>
      <host>pladigit\.fr</host>
      <host>.+\.pladigit\.fr</host>
    </lok_allow>
  </net>
  <ssl>
    <!-- SSL géré par Nginx en amont, pas par Collabora -->
    <enable>false</enable>
    <termination>true</termination>
  </ssl>
  <logging>
    <level>warning</level>
  </logging>
  <user_interface>
    <!-- Mode compact sans sidebar -->
    <mode>compact</mode>
    <sidebar>false</sidebar>
  </user_interface>
  <storage>
    <!-- Hôtes WOPI autorisés (regex) -->
    <wopi allow="true">
      <host allow="true">pladigit\.fr</host>
      <host allow="true">.+\.pladigit\.fr</host>
    </wopi>
  </storage>
</coolwsd>
```

> **Multi-tenant** : les patterns `pladigit\.fr` et `.+\.pladigit\.fr` couvrent le domaine principal et tous les sous-domaines tenants (`demo.pladigit.fr`, `mairie.pladigit.fr`, etc.).

---

## 2. Lancer le conteneur Collabora

```bash
docker run -d \
  --name collabora \
  --restart unless-stopped \
  -p 127.0.0.1:9980:9980 \
  -v /root/collabora-config/coolwsd.xml:/etc/coolwsd/coolwsd.xml:ro \
  -e "DONT_GEN_SSL_CERT=true" \
  -e "username=admin" \
  -e "password=CHANGEME" \
  --cap-add MKNOD \
  collabora/code:latest
```

> Le port `9980` est exposé **uniquement en local** (`127.0.0.1`). Nginx fait le reverse proxy vers l'extérieur.

---

## 3. Configuration Nginx

Ajouter ces blocs dans le vhost SSL de Pladigit (`/etc/nginx/sites-enabled/pladigit`) :

```nginx
# Collabora Online — reverse proxy
location /browser {
    proxy_pass http://127.0.0.1:9980$request_uri;
    proxy_set_header Host $host;
    proxy_set_header X-Forwarded-Proto https;
    proxy_set_header X-Forwarded-Host $host;
}
location /hosting {
    proxy_pass http://127.0.0.1:9980$request_uri;
    proxy_set_header Host $host;
    proxy_set_header X-Forwarded-Proto https;
    proxy_set_header X-Forwarded-Host $host;
}
location /cool {
    proxy_pass http://127.0.0.1:9980;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "Upgrade";
    proxy_set_header Host $host;
    proxy_set_header X-Forwarded-Proto https;
    proxy_set_header X-Forwarded-Host $host;
    proxy_read_timeout 3600s;
}
```

```bash
nginx -t && systemctl reload nginx
```

---

## 4. Configuration dans Pladigit

Dans **Admin > GED > Collabora** :

- **URL Collabora** : `https://pladigit.fr` (domaine principal, pas de slash final)
- **URL WOPI** : `https://pladigit.fr`
- **TTL** : laisser la valeur par défaut (240 minutes)

Cliquer **Tester la connexion** — doit retourner ✓.

---

## 5. Vérification

```bash
# Collabora répond
curl -s https://pladigit.fr/hosting/capabilities | head -c 100

# CSP correcte (doit contenir https://pladigit.fr et https://*.pladigit.fr)
curl -s http://127.0.0.1:9980/browser/dist/cool.html -I | grep "frame-ancestors"

# Conteneur actif
docker ps | grep collabora
```

---

## 6. Commandes utiles

```bash
# Voir les logs
docker logs collabora --tail 50

# Redémarrer après modification du coolwsd.xml
docker restart collabora

# Mettre à jour l'image Collabora
docker pull collabora/code:latest
docker stop collabora && docker rm collabora
# puis relancer le docker run ci-dessus
```

---

## Notes

- Collabora CODE est gratuit jusqu'à 20 connexions simultanées. Au-delà, une licence Collabora Online est nécessaire.
- Le fichier `coolwsd.xml` monté en `:ro` (read-only) — toute modification nécessite un `docker restart collabora`.
- En environnement local (DDEV), Collabora tourne en HTTP pur avec `ssl.termination=true` également — voir la config locale dans `.ddev/`.
