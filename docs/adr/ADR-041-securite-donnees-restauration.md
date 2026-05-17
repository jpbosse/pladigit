# ADR-041 — Sécurité des données au repos, sauvegardes chiffrées et plan de restauration

## Statut
Accepté — 2026-05-08
Amendé — 2026-05-17 (§1.1 délégué prestataire/communauté ; §1.2 et §2.1 automatisés par le wizard d'installation)

## Contexte

Pladigit héberge des données sensibles de collectivités territoriales :
coordonnées d'élus et d'agents, documents officiels, données personnelles
sous régime RGPD. Un accès non autorisé au serveur donne potentiellement
accès à l'ensemble de ces données.

Les vecteurs d'attaque réalistes pour une collectivité de moins de
20 000 habitants sont :

- **Compromission SSH** : clé privée volée, mot de passe faible
- **Exploitation d'une vulnérabilité applicative** : injection SQL,
  RCE (Remote Code Execution) via une dépendance compromise
- **Accès physique** : vol du serveur ou de son disque dur
- **Compromission du compte OVH** : accès au panneau de contrôle
  et reinstallation ou snapshot du VPS

Dans tous ces scénarios, l'attaquant accède à :
- Le fichier `.env` → `APP_KEY`, credentials MySQL, SMTP, clés SFTP
- Les bases MySQL → toutes les données de toutes les collectivités
- Les fichiers GED et photos → documents officiels, photos d'agents
- Les archives de sauvegarde → réplique complète de tout ce qui précède

Par ailleurs, une sauvegarde sans procédure de restauration testée
ne vaut rien. Le jour d'une perte de données réelle, découvrir que
les archives sont corrompues ou que la procédure est inconnue
est catastrophique — en particulier pour une collectivité qui
doit assurer la continuité du service public.

### Principe directeur — Secure by default, transparent pour l'opérateur

La réalité du terrain des collectivités de moins de 20 000 habitants
impose un constat sans concession : le "prestataire informatique"
local peut être quelqu'un dont la compétence principale est la
maintenance de postes Windows, sans aucune expérience Unix ou de la
ligne de commande. Concevoir la sécurité en supposant un opérateur
qualifié, c'est concevoir une sécurité qui ne sera pas appliquée.

Ce principe guide toutes les décisions de sécurité de Pladigit :

| Catégorie | Exemple | Mise en œuvre |
|-----------|---------|---------------|
| **Automatique** | GPG sauvegardes, GPG `.env`, logrotate, en-têtes HTTP | Wizard ou `install.sh` — aucune action requise |
| **Guidé, impossible à ignorer** | Passphrase GPG | Étape dédiée du wizard, bouton "Suivant" bloqué sans confirmation |
| **Documenté, non bloquant** | TDE MySQL | Souhaitable, non prérequis — contribution communauté bienvenue |

Cet ADR définit les mesures de protection des données au repos,
le chiffrement des sauvegardes, et les procédures de restauration
à différentes granularités.

---

## Décision

### 1. Chiffrement des données au repos

#### 1.1 MySQL InnoDB Transparent Data Encryption (TDE)

MySQL 8 supporte le chiffrement des tablespaces InnoDB au niveau
du moteur de stockage. Le chiffrement est **transparent pour
l'application** — Laravel ne voit aucune différence, aucun
code n'est modifié.

**Ce que TDE protège :**
- Les fichiers de données MySQL sur disque (`/var/lib/mysql/`)
- Les fichiers de logs MySQL (`ib_logfile*`, `binlog`)
- Les fichiers temporaires MySQL

**Ce que TDE ne protège pas :**
- Les données en transit réseau (couvert par SSL/TLS)
- Les données en mémoire RAM (hors périmètre)
- Un attaquant qui accède à MySQL via un compte authentifié
  (TDE chiffre le disque, pas l'accès applicatif)

**Analyse du risque résiduel sans TDE :**
Le scénario couvert exclusivement par TDE est le vol physique du
disque du VPS. Pour une infrastructure OVH, ce vecteur est très peu
probable. Les autres vecteurs d'attaque (SSH, exploit web,
compromission OVH) ne sont pas couverts par TDE de toute façon.
Le risque résiduel est documenté, assumé, et réévalué à chaque
version majeure.

> **⚠ Périmètre d'intervention — administrateur système qualifié**
>
> La mise en œuvre de TDE nécessite la modification de fichiers
> système MySQL (`mysqld.cnf`), la création d'un répertoire keyring
> avec droits stricts, et un redémarrage de MySQL en production.
> Ces opérations sont **réservées à un administrateur système
> qualifié maîtrisant l'environnement Unix/Linux**. Une erreur
> (keyring mal sauvegardé, fichier corrompu, redémarrage raté)
> peut rendre l'intégralité des données MySQL irrécupérables.
>
> **TDE n'est pas un prérequis au déploiement de Pladigit.**
> Les autres mesures (chiffrement GPG des sauvegardes, droits stricts
> sur le `.env`, accès SSH par clé, Fail2ban) constituent un niveau
> de protection suffisant pour une première mise en production.
>
> **Appel à la communauté open source :**
> Un script bash automatisant l'activation de TDE de manière sécurisée
> (vérification de la version MySQL, création du keyring, sauvegarde
> automatique de la clé, test de redémarrage, rollback en cas d'échec)
> serait une contribution précieuse. Voir `CONTRIBUTING.md` —
> label `help wanted / security`.

**Configuration de référence** (pour administrateur qualifié) :

```bash
# /etc/mysql/mysql.conf.d/mysqld.cnf
[mysqld]
early-plugin-load=keyring_file.so
keyring_file_data=/etc/mysql/keyring/keyring
innodb_encrypt_tables=ON
innodb_encrypt_log=ON
innodb_encryption_threads=4
```

```bash
# Créer le répertoire keyring avec droits stricts
sudo mkdir -p /etc/mysql/keyring
sudo chown mysql:mysql /etc/mysql/keyring
sudo chmod 750 /etc/mysql/keyring

# ⚠ Sauvegarder le fichier keyring HORS du serveur immédiatement
# Sa perte rend les données MySQL irrécupérables.
```

#### 1.2 Chiffrement du fichier `.env`

Le `.env` contient tous les secrets de l'application (`APP_KEY`,
credentials MySQL, SMTP, clés SFTP). Sa compromission est l'incident
le plus critique possible.

**Mise en œuvre — automatisée par le wizard d'installation :**

Le runner d'installation (`install/runner.php`) chiffre automatiquement
une copie du `.env` avec GPG immédiatement après sa génération,
en utilisant **la même passphrase** que celle des sauvegardes
(générée et confirmée à l'étape "Sécurité" du wizard — voir §2.1).

L'opérateur n'a rien à faire. La copie chiffrée est créée à
`/root/.pladigit_env_backup.gpg` et sa localisation est rappelée
sur la page de succès finale du wizard.

**Procédure de déchiffrement en cas de besoin :**

```bash
gpg --decrypt /root/.pladigit_env_backup.gpg > /var/www/pladigit/.env
# → Saisir la passphrase GPG conservée hors du serveur
```

Procédure complète dans `docs/deploy/secrets.md`.

**Mise à jour de la copie chiffrée :**
À chaque modification du `.env` (rotation de secrets, changement SMTP,
etc.), utiliser le bouton Super Admin → Paramètres → Sécurité →
"Recréer la copie chiffrée du .env", ou relancer manuellement :

```bash
gpg --batch --yes --symmetric --cipher-algo AES256 \
    --passphrase "VOTRE_PASSPHRASE_GPG" \
    --output /root/.pladigit_env_backup.gpg \
    /var/www/pladigit/.env
```

---

### 2. Chiffrement des archives de sauvegarde

#### 2.1 Chiffrement GPG des archives — activé par défaut

Le chiffrement GPG est **activé automatiquement** lors de l'installation.
Il ne s'agit pas d'une option : toute archive produite par Pladigit
est chiffrée avant d'être stockée ou transmise.

**Génération de la passphrase — étape dédiée et bloquante du wizard :**

Le wizard d'installation (`install/index.php`) comporte une étape
"Sécurité" positionnée juste avant la phase d'installation finale.
Cette étape génère automatiquement une passphrase forte et lisible
(format mémorable : `Mot-Chiffre-Mot-Mot-Année-Pladigit`) et
l'affiche dans un encadré avec confirmation obligatoire en trois
points. Le bouton "Continuer vers l'installation" est désactivé
tant que les trois cases ne sont pas cochées.

Cette passphrase est ensuite :
1. Stockée chiffrée en base (`platform_settings.backup_gpg_passphrase_enc`
   via `APP_KEY` Laravel) pour que le `BackupService` puisse l'utiliser
2. Utilisée pour chiffrer automatiquement la copie de secours du `.env`
3. Affichée **une dernière fois** sur la page de succès finale du wizard,
   dans un encadré distinct des autres informations d'accès

**Un seul secret à retenir et à conserver hors serveur.**
La passphrase GPG protège à la fois les sauvegardes et la copie
du `.env`. Deux protections, une seule passphrase à gérer.

**Implémentation dans `BackupService` :**
Le chiffrement GPG est intégré dans `app/Services/BackupService.php`.
Les colonnes `backup_gpg_enabled` (activé par défaut à `true`) et
`backup_gpg_passphrase_enc` sont présentes dans `platform_settings`.

**⚠ Point critique absolu :** si la passphrase GPG est perdue,
les archives de sauvegarde et la copie du `.env` sont définitivement
irrécupérables. Ni le prestataire, ni l'équipe Pladigit ne peuvent
aider. La stocker dans un gestionnaire de mots de passe ET la
remettre **en main propre** à une seconde personne de confiance
à la mairie (DGS, responsable informatique) est une obligation,
pas une option. **Jamais par email ou SMS** — ces canaux laissent
une copie en clair sur des serveurs que vous ne contrôlez pas.

#### 2.2 Vérification d'intégrité SHA-256

À chaque sauvegarde, un fichier de somme de contrôle SHA-256 est
généré automatiquement à côté de l'archive :

```
backup_2026-05-08_000001_demo.tar.gz.gpg
backup_2026-05-08_000001_demo.tar.gz.gpg.sha256
```

La restauration commence toujours par vérifier la somme de contrôle :

```bash
sha256sum -c backup_2026-05-08_000001_demo.tar.gz.gpg.sha256
```

L'interface Super Admin permet de déclencher cette vérification
sans ligne de commande (bouton "Vérifier l'intégrité" sur la liste
des sauvegardes).

---

### 3. Séparation des privilèges MySQL

L'utilisateur MySQL `pladigit` dispose de `GRANT ALL ON *.*` —
nécessaire pour créer dynamiquement les bases tenant lors de
l'inscription d'une nouvelle organisation.

**Risque assumé et documenté :**
Un attaquant qui compromet les credentials MySQL de `pladigit`
a accès à l'ensemble des bases de données de toutes les organisations.

**Justification du choix :**
La création dynamique de bases tenant est une fonctionnalité centrale
de Pladigit (ADR-002). La seule alternative sans `GRANT ALL` serait
de pré-créer les bases manuellement par le Super Admin, ce qui est
incompatible avec l'objectif d'autonomie des collectivités.

**Mesures de réduction du risque :**
- Connexion MySQL uniquement depuis `127.0.0.1` (jamais exposée réseau)
- Mot de passe MySQL long et aléatoire (32 caractères minimum)
- `bind-address = 127.0.0.1` dans `mysqld.cnf`
- Accès SSH uniquement par clé (mot de passe SSH désactivé)
- TDE activé si possible (§1.1)

**Ce risque est réévalué** à chaque montée de version majeure.

---

### 4. Logs d'audit d'accès

En cas d'intrusion détectée, les logs permettent de reconstituer
ce qui a été accédé ou modifié.

#### 4.1 Logs Nginx — conservation 90 jours

Configuré automatiquement par `install.sh` :

```nginx
# /etc/nginx/nginx.conf
access_log /var/log/nginx/access.log combined;
error_log  /var/log/nginx/error.log warn;
```

```bash
# /etc/logrotate.d/nginx — généré par install.sh
/var/log/nginx/*.log {
    daily
    rotate 90
    compress
    delaycompress
    missingok
    notifempty
}
```

#### 4.2 Logs MySQL — requêtes lentes et erreurs

Configuré automatiquement par `install.sh` :

```ini
[mysqld]
slow_query_log        = 1
slow_query_log_file   = /var/log/mysql/slow.log
long_query_time       = 2
log_error             = /var/log/mysql/error.log
```

Pas de `general_log` en production (trop verbeux).

#### 4.3 Audit trail applicatif

L'audit trail Pladigit (`datagrid_audit_logs`, logs Laravel) est
déjà en place pour les actions métier. Les exports DataGrid
(Excel, PDF) sont également loggés (qui, quoi, quand) pour
conformité RGPD.

#### 4.4 Fail2ban

Déjà installé et configuré par `install.sh`. Vérification
périodique recommandée :

```bash
sudo fail2ban-client status && sudo fail2ban-client status sshd
```

---

### 5. Procédure de restauration complète (nouveau VPS)

**RPO :** données de la veille à minuit.
**RTO estimé :** 2 à 4 heures.

```bash
# Étape 1 — Préparer le nouveau serveur (suivre INSTALL.md)
php8.4 -v && mysql --version && redis-server --version && nginx -v

# Étape 2 — Récupérer et déchiffrer la sauvegarde
scp user@nas:/backup/pladigit/demo/backup_YYYY-MM-DD_*.tar.gz.gpg /tmp/
sha256sum -c /tmp/backup_YYYY-MM-DD_*.tar.gz.gpg.sha256
gpg --decrypt /tmp/backup_YYYY-MM-DD_*.tar.gz.gpg > /tmp/backup.tar.gz
mkdir /tmp/restore && tar -xzf /tmp/backup.tar.gz -C /tmp/restore

# Étape 3 — Restaurer le .env
cp /tmp/restore/env.txt /var/www/pladigit/.env
sudo chown ubuntu:www-data /var/www/pladigit/.env && sudo chmod 640 /var/www/pladigit/.env
php artisan config:clear && php artisan cache:clear

# Étape 4 — Restaurer les bases MySQL
gunzip -c /tmp/restore/db_platform.sql.gz | mysql -u pladigit -p pladigit_platform
gunzip -c /tmp/restore/db_demo.sql.gz     | mysql -u pladigit -p pladigit_demo

# Étape 5 — Restaurer les fichiers GED
cp -r /tmp/restore/ged/ /var/www/pladigit/storage/app/private/ged/
sudo chown -R www-data:www-data /var/www/pladigit/storage/app/private/ged/

# Étape 6 — Relancer les services
php artisan config:cache && php artisan route:cache && php artisan view:cache
sudo systemctl restart php8.4-fpm nginx
sudo supervisorctl restart pladigit-worker:*
```

---

### 6. Procédure de restauration partielle (un tenant)

```bash
# Sauvegarde préventive de l'état actuel
php artisan pladigit:backup --force --slug=demo

# Stopper les workers
sudo supervisorctl stop pladigit-worker:*

# Déchiffrer et restaurer
gpg --decrypt backup_YYYY-MM-DD_demo.tar.gz.gpg > /tmp/restore_demo.tar.gz
mkdir /tmp/restore_demo && tar -xzf /tmp/restore_demo.tar.gz -C /tmp/restore_demo
gunzip -c /tmp/restore_demo/db_demo.sql.gz | mysql -u pladigit -p pladigit_demo

# Redémarrer
sudo supervisorctl start pladigit-worker:*
php artisan cache:clear
```

---

### 7. Procédure de restauration d'un fichier GED

```bash
# Déchiffrer la sauvegarde
gpg --decrypt backup_YYYY-MM-DD_demo.tar.gz.gpg > /tmp/restore_ged.tar.gz

# Lister les fichiers GED sans tout extraire
tar -tzf /tmp/restore_ged.tar.gz | grep "ged/"

# Extraire uniquement le fichier recherché
tar -xzf /tmp/restore_ged.tar.gz -C /tmp/ \
    ged/organisations/demo/documents/2026/05/le_fichier.pdf

# Copier vers la GED et corriger les droits
cp /tmp/ged/organisations/demo/documents/2026/05/le_fichier.pdf \
   /var/www/pladigit/storage/app/private/ged/organisations/demo/documents/2026/05/
sudo chown www-data:www-data \
   /var/www/pladigit/storage/app/private/ged/organisations/demo/documents/2026/05/le_fichier.pdf
```

---

### 8. Test de restauration périodique

**Une sauvegarde non testée n'est pas une sauvegarde.**

Le tableau de bord sécurité Super Admin permet de vérifier l'intégrité
d'une archive et de lister son contenu sans restaurer, directement
depuis l'interface, avec consignation automatique dans le journal
des tests (plan de travail 1.B.5 et 1.B.6).

#### 8.1 Test mensuel recommandé (sur environnement de test)

```bash
# Déchiffrer et vérifier
gpg --batch --passphrase "PASSPHRASE" \
    --decrypt backup_*.tar.gz.gpg > /tmp/test.tar.gz
tar -tzf /tmp/test.tar.gz | wc -l
tar -tzf /tmp/test.tar.gz | grep "db_platform.sql.gz"

# Restaurer sur instance de test
mkdir /tmp/test_restore && tar -xzf /tmp/test.tar.gz -C /tmp/test_restore
gunzip -c /tmp/test_restore/db_platform.sql.gz \
    | mysql -u pladigit -p pladigit_test_platform
mysql -u pladigit -p pladigit_test_platform \
    -e "SELECT COUNT(*) FROM organizations;"

# Consigner et nettoyer
echo "$(date) — Test restauration OK — $(tar -tzf /tmp/test.tar.gz | wc -l) fichiers" \
    >> /var/log/pladigit_restore_tests.log
rm -rf /tmp/test_restore /tmp/test.tar.gz
```

#### 8.2 Checklist de validation

```
☐ L'archive GPG se déchiffre sans erreur
☐ La somme de contrôle SHA-256 est correcte
☐ L'archive contient db_platform.sql.gz
☐ L'archive contient au moins un db_*.sql.gz (tenant)
☐ Le dump platform se restaure sans erreur MySQL
☐ La table organizations contient les bonnes entrées
☐ Le dossier ged/ est présent dans l'archive
☐ Date et résultat consignés dans le journal
```

---

### 9. RTO et RPO

| Indicateur | Valeur | Condition |
|------------|--------|-----------|
| **RPO** (perte de données max) | 24 heures | Sauvegarde quotidienne à minuit |
| **RPO amélioré** | 1 heure | Si `backup_schedule = hourly` dans PlatformSettings |
| **RTO restauration complète** | 2 à 4 heures | Nouveau VPS + connexion 100 Mbps |
| **RTO restauration partielle** | 30 à 60 minutes | Tenant uniquement, VPS opérationnel |
| **RTO restauration fichier GED** | 10 à 20 minutes | Fichier unique |

---

### 10. Plan de réponse à incident

#### 10.1 Signes d'une compromission

- Connexions SSH depuis des IP inconnues (`/var/log/auth.log`)
- Processus inconnus consommant CPU ou réseau (`top`, `netstat`)
- Fichiers modifiés récemment dans `/var/www/pladigit`
- Logs applicatifs avec erreurs inhabituelles
- **Alerte email Pladigit** : tentative de connexion Super Admin
  depuis IP non autorisée (plan de travail 1.B.1)

#### 10.2 Procédure d'urgence immédiate

```bash
# 1. Isoler le serveur
sudo ufw deny in on eth0

# 2. Snapshot OVH depuis le panneau de contrôle

# 3. Changer tous les mots de passe et secrets :
#    root SSH, clés SSH (vérifier authorized_keys),
#    mot de passe MySQL, APP_KEY (voir §10.3), passphrase GPG

# 4. Vérifier les accès récents
last -20
grep "Failed password" /var/log/auth.log | tail -20
grep "Accepted" /var/log/auth.log | tail -20

# 5. Notifier la collectivité (DGS ou responsable informatique)
```

#### 10.3 Rotation d'urgence de l'APP_KEY

**⚠ Critique :** changer l'APP_KEY invalide tous les secrets chiffrés
(TOTP, passphrase GPG, mots de passe SFTP). Ordre strict :

```bash
cp /var/www/pladigit/.env /root/.env_before_rotation
php artisan key:generate --force
# Re-chiffrer les secrets : Super Admin → Paramètres → Sécurité
php artisan cache:clear && php artisan config:clear
php artisan session:flush 2>/dev/null || true
```

#### 10.4 Communication de crise

En cas de compromission avérée, notification CNIL obligatoire dans
les **72 heures** (RGPD article 33). Éléments à fournir :
date et heure estimées, nature des données exposées, nombre
d'enregistrements, mesures prises.

---

### 11. Tableau de bord sécurité — vérification périodique

La majorité de ces vérifications sont automatisées dans le tableau
de bord sécurité Super Admin (plan de travail 1.B.5). La checklist
bash reste utile pour un audit ponctuel ou en cas d'inaccessibilité
de l'interface.

```bash
ls -la /var/www/pladigit/.env           # Attendu : -rw-r----- ubuntu www-data
sudo supervisorctl status               # Attendu : RUNNING
sudo fail2ban-client status sshd
cat ~/.ssh/authorized_keys              # Vérifier l'absence d'entrées inconnues
last -10
# Test de restauration mensuel → voir §8.1
```

---

## Conséquences

### Positives
- GPG sauvegardes et `.env` activés automatiquement — aucune
  compétence technique requise de l'opérateur
- Un seul secret à retenir et à conserver hors serveur
  (passphrase GPG protège les deux)
- La passphrase ne peut pas être ignorée lors de l'installation —
  le wizard bloque jusqu'à confirmation explicite
- Les procédures de restauration documentées réduisent le stress
  lors d'un incident réel
- Le test périodique détecte les sauvegardes corrompues avant
  qu'on en ait besoin
- Notification CNIL dans les 72h réalisable avec les éléments
  techniques pré-identifiés

### Points de vigilance
- **La passphrase GPG doit être stockée hors du serveur ET transmise
  à une seconde personne de confiance** — sa perte est définitive
- La rotation d'APP_KEY invalide les secrets chiffrés — ne jamais
  la faire sous pression sans suivre la procédure §10.3
- TDE MySQL (§1.1) n'est pas automatisé — risque résiduel
  (vol physique du disque) documenté et assumé pour V1
- Le test mensuel de restauration demande 30 minutes — à planifier

### Ce que cette ADR ne couvre pas
- La sécurité du poste de travail de l'administrateur (hors périmètre)
- Les attaques par ingénierie sociale (phishing) ciblant les agents
- La supervision temps réel (SIEM) — hors périmètre pour V1

---

## Références
- ADR-002 : Architecture multi-tenant base dédiée
- ADR-017 : Double authentification TOTP
- ADR-026 : Déploiement production VPS
- ADR-033 : Ressources locales et en-têtes HTTP sécurité
- ADR-037 : Gouvernance des données personnelles RGPD
- RGPD article 33 : notification de violation de données à la CNIL
- ANSSI — Guide d'hygiène informatique pour les collectivités
- MySQL 8 InnoDB TDE : https://dev.mysql.com/doc/refman/8.0/en/innodb-data-encryption.html
- `docs/deploy/secrets.md` : procédures de gestion des secrets
- `docs/deploy/troubleshooting.md` : incidents déjà résolus en production
- `CONTRIBUTING.md` : label `help wanted / security` — script TDE
