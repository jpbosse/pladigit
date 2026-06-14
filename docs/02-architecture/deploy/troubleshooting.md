# Troubleshooting — Pladigit production

Ce document recense les problèmes rencontrés en production et leurs solutions.

---

## Sauvegardes automatiques — workers Supervisor en FATAL

**Date détectée :** 2026-04-30  
**Date résolue :** 2026-05-08

### Symptômes

- `sudo supervisorctl status` affiche les workers en `FATAL`
- `backup_last_status = failed` dans PlatformSettings
- Message d'erreur : `copy(/var/www/pladigit/.env): Failed to open stream: Permission denied`
- Aucune sauvegarde créée depuis la dernière exécution réussie

### Cause

Les workers Supervisor tournent sous l'utilisateur `www-data`. Le fichier `.env`
appartenait à `ubuntu:ubuntu` avec les droits `640` — `www-data` ne pouvait
pas le lire. Sans accès au `.env`, le worker récupérait un mot de passe MySQL
vide, ce qui provoquait un refus de connexion MySQL et un crash immédiat.

```
SQLSTATE[HY000] [1045] Access denied for user 'pladigit'@'localhost' (using password: NO)
```

### Solution

```bash
# Corriger les droits sur le .env
sudo chown ubuntu:www-data /var/www/pladigit/.env
sudo chmod 640 /var/www/pladigit/.env

# Redémarrer les workers
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl restart pladigit-worker:*

# Vérifier
sudo supervisorctl status
```

### Prévention

Lors de toute mise à jour du `.env` (ajout de variable, rotation de clé),
vérifier que les droits sont corrects :

```bash
ls -la /var/www/pladigit/.env
# Attendu : -rw-r----- 1 ubuntu www-data
```

L'`INSTALL.md` mentionne cette exigence dans la section "Permissions".
Si ce n'est pas le cas, l'y ajouter.

### Diagnostic rapide

```bash
# État des workers
sudo supervisorctl status

# Logs des workers
tail -50 /var/www/pladigit/storage/logs/worker.log

# État de la dernière sauvegarde
php artisan tinker --execute="
\$ps = App\Models\Platform\PlatformSettings::first();
echo 'status: '.\$ps->backup_last_status.PHP_EOL;
echo 'message: '.\$ps->backup_last_message.PHP_EOL;
echo 'last_run: '.\$ps->backup_last_run_at.PHP_EOL;
"

# Forcer une sauvegarde manuelle
php artisan pladigit:backup --force
```

---

## Sauvegardes automatiques — poste de développement local

**Comportement normal — pas un bug.**

Le scheduler Laravel (`pladigit:backup`) se déclenche uniquement à minuit
(fréquence `daily`). Si le poste local est éteint à minuit, la sauvegarde
ne se déclenche pas.

Pour forcer une sauvegarde manuelle en local :

```bash
php artisan pladigit:backup --force --slug=cedbos
sleep 15
ls -la /var/www/pladigit/storage/app/private/backup_complet/cedbos/
```
