# Checklist de mise en production — Pladigit

À valider intégralement avant d'ouvrir la plateforme aux utilisateurs.  
`[ ]` = à faire · `[x]` = validé

---

## 1. Serveur & Infrastructure

### Système
- [ ] Ubuntu 24.04 LTS à jour (`apt upgrade`)
- [ ] Utilisateur `deploy` créé, connexion root SSH désactivée
- [ ] Authentification SSH par clé uniquement (mot de passe désactivé)
- [ ] UFW actif — ports 22, 80, 443 autorisés, tout le reste bloqué
- [ ] Fail2ban installé et actif

### PHP 8.4
- [ ] Extensions requises présentes : `pdo_mysql redis gd exif intl mbstring zip bcmath ldap`
- [ ] `memory_limit = 256M`
- [ ] `upload_max_filesize = 100M` / `post_max_size = 105M`
- [ ] `max_execution_time = 120`
- [ ] `date.timezone = Europe/Paris`
- [ ] OPcache activé

### Nginx
- [ ] Virtual host wildcard configuré (`*.pladigit.fr`)
- [ ] SSL/TLS Let's Encrypt actif — certificat wildcard
- [ ] Redirection HTTP → HTTPS
- [ ] Headers sécurité : `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`
- [ ] `client_max_body_size 110M`
- [ ] `mod_status` désactivé ou protégé

---

## 2. Base de données

- [ ] MySQL 8 — authentification par mot de passe fort
- [ ] Base `pladigit_platform` créée
- [ ] Base `pladigit_tenant_template` créée
- [ ] Utilisateur `pladigit` avec droits limités à `pladigit_*`
- [ ] Accès root MySQL désactivé depuis l'extérieur
- [ ] Sauvegardes automatiques configurées (voir section 6)

---

## 3. Application Laravel

### Configuration
- [ ] `.env` complet — `APP_DEBUG=false`, `APP_ENV=production`
- [ ] `APP_KEY` généré et sauvegardé hors serveur
- [ ] `SESSION_DOMAIN=.pladigit.fr` (avec le point)
- [ ] `SUPER_ADMIN_PASSWORD` fort et unique
- [ ] `BCRYPT_ROUNDS=12`
- [ ] Redis configuré avec mot de passe

### Optimisations
- [ ] `php artisan config:cache`
- [ ] `php artisan route:cache`
- [ ] `php artisan view:cache`
- [ ] `php artisan event:cache`
- [ ] Assets compilés en production (`npm run build`)

### Permissions
- [ ] `storage/` et `bootstrap/cache/` — droits `deploy:www-data 775`
- [ ] Reste du projet — droits `deploy:www-data 755`

---

## 4. Scheduler & Queues

- [ ] Cron `deploy` configuré : `* * * * * php artisan schedule:run`
- [ ] Supervisor installé et actif
- [ ] Worker `pladigit-worker` démarré (2 processus minimum)
- [ ] `supervisorctl status pladigit-worker:*` → RUNNING

---

## 5. Sécurité

- [ ] Aucune clé d'API ou secret dans le code source
- [ ] `.env` hors du webroot et non accessible via HTTP
- [ ] `APP_DEBUG=false` vérifié
- [ ] Politique de mot de passe configurée dans Admin > Sécurité
- [ ] 2FA activé pour le compte Super Admin et les comptes Admin Organisation
- [ ] Rate limiting Nginx actif sur `/login`
- [ ] Logs d'audit activés et rétention configurée (Admin > Audit)
- [ ] `composer audit` — 0 vulnérabilité

---

## 6. NAS & Stockage

- [ ] Connexion NAS testée depuis le serveur (local / SFTP / SMB selon config)
- [ ] Quota de stockage défini par organisation
- [ ] Répertoire de stockage GED accessible en lecture/écriture par `deploy`
- [ ] Sauvegardes NAS configurées

---

## 7. Collabora Online (si activé)

- [ ] Docker installé et démon actif
- [ ] Conteneur Collabora démarré : `docker ps | grep collabora`
- [ ] URL Collabora configurée dans Admin > GED > Collabora
- [ ] Test de connexion validé depuis l'interface admin
- [ ] RAM serveur suffisante — minimum 4 Go dédiés à Collabora

---

## 8. Sauvegardes

- [ ] Script de dump MySQL quotidien configuré (toutes les bases `pladigit_*`)
- [ ] Sauvegarde des fichiers `.env` et `config/` hors serveur
- [ ] Sauvegarde des clés de chiffrement (Vaultwarden ou équivalent)
- [ ] Rotation des sauvegardes configurée (30 jours minimum)
- [ ] Test de restauration effectué sur environnement staging

---

## 9. CI/CD & Tests

- [ ] Branche `main` protégée — merge uniquement depuis `develop` après CI vert
- [ ] GitHub Actions secrets configurés
- [ ] `php artisan test --exclude-group ldap,integration` → tous verts
- [ ] Health check accessible : `GET /health` → `{"status":"ok"}`

---

## 10. Première organisation

- [ ] Organisation de démonstration créée (`php artisan tenant:create`)
- [ ] Connexion testée sur `https://[slug].pladigit.fr`
- [ ] Admin Organisation : invitation envoyée, connexion validée
- [ ] 2FA configuré sur le premier compte admin
- [ ] Modules activés selon les besoins de l'organisation

---

## 11. Documentation & Communication

- [ ] URL d'accès communiquée aux administrateurs organisations
- [ ] Guide Super Admin transmis à l'opérateur
- [ ] Guide Admin Organisation transmis aux administrateurs locaux

---

*Document généré par Pladigit — jpbosse/pladigit — AGPL-3.0*
