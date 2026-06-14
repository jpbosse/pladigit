# Guide de maintenance — Pladigit

Ce guide couvre les opérations de maintenance courantes pour un déploiement Pladigit en production.

---

## 1. Base de données

### Architecture
- `pladigit_platform` — base centrale : organisations, modules, configuration SMTP
- `pladigit_{slug}` — une base dédiée par organisation (users, projets, GED, médias, audit)
- `pladigit_tenant_template` — base modèle pour le provisionnement

### Migrations

```bash
# Migrer la base plateforme
sudo -u deploy php artisan migrate \
  --path=database/migrations/platform \
  --database=mysql

# Migrer toutes les bases tenant
sudo -u deploy php artisan migrate:tenants

# ⚠ Ne JAMAIS utiliser migrate:fresh sans préciser --database et --path
# Un migrate:fresh sans paramètres peut écraser pladigit_platform
```

### Sauvegardes manuelles

```bash
# Base plateforme
mysqldump -u pladigit -p pladigit_platform | gzip > /backups/platform_$(date +%Y%m%d).sql.gz

# Toutes les bases tenant
for db in $(mysql -u pladigit -p -N -e "SHOW DATABASES LIKE 'pladigit_%'"); do
  mysqldump -u pladigit -p "$db" | gzip > /backups/${db}_$(date +%Y%m%d).sql.gz
done
```

### Restauration

```bash
gunzip < /backups/pladigit_platform_20260401.sql.gz | mysql -u pladigit -p pladigit_platform
```

---

## 2. Application Laravel

### Vider les caches

```bash
sudo -u deploy php artisan cache:clear
sudo -u deploy php artisan config:clear
sudo -u deploy php artisan route:clear
sudo -u deploy php artisan view:clear
```

### Reconstruire les caches production

```bash
sudo -u deploy php artisan config:cache
sudo -u deploy php artisan route:cache
sudo -u deploy php artisan view:cache
sudo -u deploy php artisan event:cache
```

### Logs

```bash
# Logs Laravel
tail -f /var/www/pladigit/storage/logs/laravel.log

# Logs Nginx
tail -f /var/log/nginx/access.log
tail -f /var/log/nginx/error.log

# Logs queue worker
tail -f /var/www/pladigit/storage/logs/worker.log
```

---

## 3. Queue workers (Supervisor)

```bash
# État des workers
sudo supervisorctl status pladigit-worker:*

# Redémarrer après déploiement
sudo supervisorctl restart pladigit-worker:*

# En cas de blocage
sudo supervisorctl stop pladigit-worker:*
sudo supervisorctl start pladigit-worker:*
```

### Vérifier les jobs en échec

```bash
sudo -u deploy php artisan queue:failed
sudo -u deploy php artisan queue:retry all   # relancer tous les jobs échoués
sudo -u deploy php artisan queue:flush       # supprimer les jobs échoués
```

---

## 4. Scheduler Laravel

```bash
# Vérifier que le cron tourne
sudo -u deploy crontab -l

# Lancer manuellement les commandes planifiées
sudo -u deploy php artisan nas:sync
sudo -u deploy php artisan ged:sync
sudo -u deploy php artisan pladigit:sync-ldap
sudo -u deploy php artisan pladigit:generate-recurring-tasks
sudo -u deploy php artisan pladigit:purge-expired-data
```

---

## 5. Services système

```bash
# Redémarrer tous les services
sudo systemctl restart nginx php8.3-fpm mysql redis

# Vérifier l'état
sudo systemctl status nginx php8.3-fpm mysql redis

# Collabora Online
docker ps | grep collabora
docker restart collabora   # si le conteneur est figé
```

---

## 6. Déploiement d'une mise à jour

```bash
cd /var/www/pladigit

# 1. Mettre en maintenance
sudo -u deploy php artisan down

# 2. Récupérer le code
sudo -u deploy git pull origin main

# 3. Mettre à jour les dépendances
sudo -u deploy composer install --no-dev --optimize-autoloader
sudo -u deploy npm install && sudo -u deploy npm run build

# 4. Migrer
sudo -u deploy php artisan migrate --force
sudo -u deploy php artisan migrate:tenants --force

# 5. Reconstruire les caches
sudo -u deploy php artisan config:cache
sudo -u deploy php artisan route:cache
sudo -u deploy php artisan view:cache
sudo -u deploy php artisan event:cache

# 6. Redémarrer les workers
sudo supervisorctl restart pladigit-worker:*

# 7. Remettre en ligne
sudo -u deploy php artisan up
```

---

## 7. Gestion des organisations (Super Admin)

```bash
# Créer une nouvelle organisation
sudo -u deploy php artisan tenant:create \
  --name="Mairie de Soullans" \
  --slug="soullans" \
  --email="admin@soullans.pladigit.fr"

# Lister les organisations
sudo -u deploy php artisan tenant:list

# Migrer une organisation spécifique
sudo -u deploy php artisan tenant:migrate --tenant=soullans
```

---

## 8. Health check & monitoring

```bash
# Vérification rapide
curl -s https://pladigit.fr/health | python3 -m json.tool

# Résultat attendu :
# {
#   "status": "ok",
#   "checks": {
#     "database": "ok",
#     "redis": "ok",
#     "disk": "ok"
#   }
# }
```

---

## 9. Certificat SSL

```bash
# Vérifier l'expiration
sudo certbot certificates

# Renouveler manuellement
sudo certbot renew --nginx

# Le renouvellement automatique est géré par le cron certbot
```

---

## 10. Nettoyage

```bash
# Purger les logs d'audit expirés (selon rétention configurée par tenant)
sudo -u deploy php artisan pladigit:purge-expired-data

# Nettoyer les anciens logs Laravel (> 30 jours)
find /var/www/pladigit/storage/logs -name "*.log" -mtime +30 -delete

# Vérifier l'espace disque
df -h /var/www/pladigit/storage
```

---

## Alias bash recommandés

Ajouter dans `~/.bashrc` de l'utilisateur `deploy` :

```bash
alias pla='cd /var/www/pladigit'
alias platest='php artisan test --exclude-group ldap,integration'
alias plalog='tail -f storage/logs/laravel.log'
alias plarestart='sudo supervisorctl restart pladigit-worker:* && sudo systemctl reload nginx'
alias plastatus='sudo supervisorctl status && sudo systemctl status nginx mysql redis --no-pager'
```
