# ADR-026 — Déploiement production : VPS, Supervisor, certificat wildcard

**Date :** Avril 2026  
**Statut :** Accepté  
**Auteur :** Jean-Pierre Bossé

---

## Contexte

Lors du premier déploiement en production de Pladigit (niveau 1 complet), plusieurs décisions techniques ont été prises concernant l'infrastructure, la gestion des queues et le certificat SSL. Ces décisions sont documentées ici pour guider les futurs déployeurs.

---

## Décisions

### 1. Supervisor avec 2 workers pour les queues Laravel

**Décision :** Utiliser Supervisor avec `numprocs=2` pour gérer les queues Laravel.

**Raisons :**
- Les queues gèrent des tâches asynchrones critiques : envoi d'emails, synchronisation NAS, traitement des uploads photos, jobs GED
- Sans Supervisor, les workers s'arrêtent au déconnexion SSH
- 2 workers offrent un traitement parallèle sans surcharger un VPS 4 vCPU / 8 Go RAM
- Supervisor redémarre automatiquement les workers après un crash ou un reboot

**Alternative rejetée :** `php artisan queue:work` en arrière-plan via `nohup` — fragile, ne survit pas au reboot.

---

### 2. Certificat SSL wildcard manuel (Let's Encrypt DNS challenge)

**Décision :** Utiliser un certificat wildcard `*.domaine.fr` via validation DNS manuelle plutôt qu'un certificat par sous-domaine.

**Raisons :**
- L'architecture multi-tenant crée dynamiquement des sous-domaines (`tenant.pladigit.fr`)
- Un certificat par sous-domaine nécessiterait une intervention manuelle à chaque nouveau tenant — incompatible avec le provisioning automatique
- Le wildcard couvre tous les tenants présents et futurs sans intervention

**Contrainte :** Le certificat wildcard expire tous les 90 jours et nécessite une validation DNS manuelle. À automatiser via `certbot-dns-ovh` pour la production.

**Commande utilisée :**
```bash
sudo certbot certonly --manual --preferred-challenges dns \
  -d domaine.fr -d "*.domaine.fr"
```

---

### 3. GRANT ALL PRIVILEGES pour l'utilisateur MySQL pladigit

**Décision :** Accorder `ALL PRIVILEGES ON *.*` à l'utilisateur MySQL `pladigit` plutôt que des droits limités à une base spécifique.

**Raisons :**
- Le Super Admin provisionne dynamiquement une nouvelle base MySQL par organisation (`CREATE DATABASE pladigit_{slug}`)
- `CREATE DATABASE` requiert le privilège global `CREATE` qui n'est pas accordé par `GRANT ALL ON pladigit_platform.*`
- Sans ce droit, le provisioning échoue avec `Access denied to database 'pladigit_{slug}'`

**Risque :** L'utilisateur `pladigit` peut créer/supprimer n'importe quelle base. Acceptable car MySQL n'est accessible que localement (`127.0.0.1`) et l'utilisateur n'a pas accès shell.

**Évolution future :** Créer un utilisateur dédié au provisioning avec droits restreints au `CREATE DATABASE` uniquement, séparé de l'utilisateur applicatif.

---

### 4. Séparation environnement local / production via domaine `.local`

**Décision :** Utiliser `*.pladigit.local` en développement et `*.pladigit.fr` en production, configurés via `/etc/hosts`.

**Raisons :**
- Permet d'avoir les deux environnements actifs simultanément sur la même machine
- Évite les conflits DNS entre le PC de développement et le VPS
- `SESSION_DOMAIN=.pladigit.local` en local, `SESSION_DOMAIN=.pladigit.fr` en production
- Aucune modification DNS OVH nécessaire pour le développement

**Piège évité :** Commenter les entrées `/etc/hosts` de `pladigit.fr` sans vider le cache DNS provoque des résolutions incorrectes persistantes. Utiliser `sudo resolvectl flush-caches` après modification.

---

### 5. Double migration obligatoire lors du déploiement

**Décision :** Deux commandes `migrate` distinctes sont nécessaires lors de l'installation initiale.

```bash
# 1. Tables Laravel de base (users, cache, jobs)
php8.3 artisan migrate --force

# 2. Tables de la plateforme Pladigit (organizations, audit_logs...)
php8.3 artisan migrate --path=database/migrations/platform --force
```

**Raison :** Les migrations sont organisées en trois dossiers distincts :
- `database/migrations/` — tables Laravel standard
- `database/migrations/platform/` — tables de la base centrale Pladigit
- `database/migrations/tenant/` — tables des bases organisation (lancées automatiquement lors du provisioning)

**Piège :** Oublier la seconde commande provoque une erreur `Table 'pladigit_platform.organizations' doesn't exist` au premier accès au Super Admin.

---

## Conséquences

- L'INSTALL.md documente ces décisions sous forme de procédure étape par étape
- Le renouvellement du certificat wildcard doit être planifié (90 jours)
- L'automatisation via `certbot-dns-ovh` est à prévoir pour le niveau 2

---

*Pladigit — ADR-026 — Avril 2026*
