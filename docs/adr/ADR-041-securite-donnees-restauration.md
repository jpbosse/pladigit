# ADR-041 — Sécurité des données au repos, sauvegardes chiffrées et plan de restauration

## Statut
Proposé — 2026-05-08

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

**Configuration :**

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

# Sauvegarder le fichier keyring dans un endroit distinct du serveur
# (coffre-fort numérique, gestionnaire de mots de passe de la collectivité)
```

**⚠ Point critique :** le fichier `keyring` est la clé de déchiffrement.
S'il est perdu ou corrompu, les données MySQL sont irrécupérables.
Il doit être sauvegardé séparément des données (jamais dans la même
archive de sauvegarde).

#### 1.2 Chiffrement du fichier `.env`

Le `.env` contient tous les secrets de l'application. En complément
des droits stricts (`ubuntu:www-data 640`), le `.env` de production
est chiffré avec GPG en tant que sauvegarde sécurisée.

```bash
# Chiffrer le .env pour archivage sécurisé
gpg --symmetric --cipher-algo AES256 \
    --output /root/.pladigit_env_backup.gpg \
    /var/www/pladigit/.env

# Déchiffrer si besoin
gpg --decrypt /root/.pladigit_env_backup.gpg > /var/www/pladigit/.env
```

Le `.env` chiffré est stocké hors du serveur (gestionnaire de mots
de passe ou coffre-fort numérique de la collectivité).

---

### 2. Chiffrement des archives de sauvegarde

Les archives `.tar.gz` actuelles ne sont pas chiffrées. Une archive
volée contient l'intégralité des données de la collectivité.

#### 2.1 Chiffrement GPG des archives

Le `BackupService` est modifié pour chiffrer chaque archive après
création, avant envoi vers la destination :

```php
// Dans BackupService::run()
// Après création de l'archive tar.gz :

if ($this->gpgEnabled($settings)) {
    $encryptedPath = $archivePath . '.gpg';
    $this->encryptArchive($archivePath, $encryptedPath, $settings);
    unlink($archivePath); // supprimer l'archive non chiffrée
    $archivePath = $encryptedPath;
    $archiveName = $archiveName . '.gpg';
}
```

```php
private function encryptArchive(
    string $sourcePath,
    string $destPath,
    TenantSettings $settings
): void {
    $passphrase = Crypt::decryptString($settings->backup_gpg_passphrase_enc);
    $cmd = sprintf(
        'gpg --batch --yes --symmetric --cipher-algo AES256 '
        . '--passphrase %s --output %s %s 2>/dev/null',
        escapeshellarg($passphrase),
        escapeshellarg($destPath),
        escapeshellarg($sourcePath)
    );
    exec($cmd, $output, $exitCode);
    if ($exitCode !== 0) {
        throw new \RuntimeException('Chiffrement GPG de l\'archive échoué.');
    }
}
```

Nouvelles colonnes dans `platform_settings` :

```sql
ALTER TABLE platform_settings
    ADD COLUMN backup_gpg_enabled         BOOLEAN NOT NULL DEFAULT FALSE,
    ADD COLUMN backup_gpg_passphrase_enc  TEXT NULL; -- chiffré via APP_KEY
```

**⚠ Point critique :** la passphrase GPG doit être stockée hors du
serveur. Si elle est perdue, les archives sont irrécupérables.

#### 2.2 Vérification d'intégrité

À chaque sauvegarde, un fichier de somme de contrôle SHA-256 est
généré à côté de l'archive :

```bash
backup_2026-05-08_000001_demo.tar.gz.gpg
backup_2026-05-08_000001_demo.tar.gz.gpg.sha256
```

La restauration commence toujours par vérifier la somme de contrôle :

```bash
sha256sum -c backup_2026-05-08_000001_demo.tar.gz.gpg.sha256
```

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
- TDE activé (§1.1) — les fichiers MySQL sur disque sont chiffrés

**Ce risque est réévalué** à chaque montée de version majeure de
Pladigit pour étudier des alternatives architecturales.

---

### 4. Logs d'audit d'accès

En cas d'intrusion détectée, les logs permettent de reconstituer
ce qui a été accédé ou modifié.

#### 4.1 Logs Nginx

Conservation 90 jours :

```nginx
# /etc/nginx/nginx.conf
access_log /var/log/nginx/access.log combined;
error_log  /var/log/nginx/error.log warn;
```

```bash
# /etc/logrotate.d/nginx
/var/log/nginx/*.log {
    daily
    rotate 90
    compress
    delaycompress
    missingok
    notifempty
}
```

#### 4.2 Logs MySQL — accès suspects

Activer le log des requêtes lentes et des erreurs (pas le general_log
qui est trop verbeux en production) :

```bash
# /etc/mysql/mysql.conf.d/mysqld.cnf
[mysqld]
slow_query_log = 1
slow_query_log_file = /var/log/mysql/slow.log
long_query_time = 2
log_error = /var/log/mysql/error.log
```

#### 4.3 Audit trail applicatif

L'audit trail Pladigit (`datagrid_audit_logs`, logs Laravel) est
déjà en place pour les actions métier. En cas d'incident, il permet
de savoir quelles données ont été consultées ou modifiées via l'application.

#### 4.4 Fail2ban

Déjà installé (ADR-026). Vérifie régulièrement que les jails
SSH et Nginx sont actifs :

```bash
sudo fail2ban-client status
sudo fail2ban-client status sshd
```

---

### 5. Procédure de restauration complète (nouveau VPS)

**Scénario :** le VPS est inaccessible ou corrompu. On repart de zéro.

**RPO (Recovery Point Objective) :** données de la veille à minuit
(dernière sauvegarde quotidienne réussie).

**RTO (Recovery Time Objective) estimé :** 2 à 4 heures selon
la taille des données et la connexion réseau.

#### Étape 1 — Préparer le nouveau serveur

Suivre l'`INSTALL.md` jusqu'à l'étape "Déploiement de Pladigit"
incluse, sans exécuter les migrations.

```bash
# Vérifier que le stack est en place
php8.4 -v
mysql --version
redis-server --version
nginx -v
```

#### Étape 2 — Récupérer et déchiffrer la dernière sauvegarde

```bash
# Copier l'archive depuis la destination de sauvegarde (SFTP ou support)
scp user@nas:/backup/pladigit/demo/backup_YYYY-MM-DD_*.tar.gz.gpg /tmp/

# Vérifier l'intégrité
sha256sum -c /tmp/backup_YYYY-MM-DD_*.tar.gz.gpg.sha256

# Déchiffrer
gpg --batch --passphrase "VOTRE_PASSPHRASE_GPG" \
    --decrypt /tmp/backup_YYYY-MM-DD_*.tar.gz.gpg \
    > /tmp/backup.tar.gz

# Extraire
mkdir /tmp/restore
tar -xzf /tmp/backup.tar.gz -C /tmp/restore
ls /tmp/restore/
# → db_platform.sql.gz  db_demo.sql.gz  ged/  nas/  env.txt
```

#### Étape 3 — Restaurer le `.env`

```bash
cp /tmp/restore/env.txt /var/www/pladigit/.env
sudo chown ubuntu:www-data /var/www/pladigit/.env
sudo chmod 640 /var/www/pladigit/.env

# Vider les caches Laravel
php artisan config:clear
php artisan cache:clear
```

#### Étape 4 — Restaurer les bases MySQL

```bash
# Base platform
gunzip -c /tmp/restore/db_platform.sql.gz | mysql -u pladigit -p pladigit_platform

# Base tenant (une par organisation)
gunzip -c /tmp/restore/db_demo.sql.gz | mysql -u pladigit -p pladigit_demo
```

#### Étape 5 — Restaurer les fichiers GED et NAS

```bash
# GED
cp -r /tmp/restore/ged/ /var/www/pladigit/storage/app/private/ged/
sudo chown -R www-data:www-data /var/www/pladigit/storage/app/private/ged/

# NAS médias (si driver local)
cp -r /tmp/restore/nas/ /chemin/nas/local/
```

#### Étape 6 — Relancer les services

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
sudo systemctl restart php8.4-fpm nginx
sudo supervisorctl restart pladigit-worker:*
```

#### Étape 7 — Vérifier

```bash
# Accéder à https://demo.pladigit.fr
# Vérifier la connexion en tant que Super Admin
# Vérifier que les données d'une organisation sont présentes
# Vérifier que les workers sont RUNNING
sudo supervisorctl status
```

---

### 6. Procédure de restauration partielle (un tenant)

**Scénario :** une organisation a perdu des données suite à une
fausse manipulation ou un bug. Les autres organisations ne sont
pas affectées.

```bash
# Identifier la sauvegarde à restaurer
ls -la /var/www/pladigit/storage/app/private/backup_complet/demo/
# → backup_2026-05-07_000001_demo.tar.gz.gpg  (hier)
# → backup_2026-05-06_000001_demo.tar.gz.gpg  (avant-hier)

# Déchiffrer et extraire la sauvegarde choisie
gpg --batch --passphrase "VOTRE_PASSPHRASE_GPG" \
    --decrypt backup_2026-05-07_000001_demo.tar.gz.gpg \
    > /tmp/restore_demo.tar.gz

mkdir /tmp/restore_demo
tar -xzf /tmp/restore_demo.tar.gz -C /tmp/restore_demo

# ⚠ ATTENTION : cette opération écrase les données actuelles du tenant
# Prendre une sauvegarde de l'état actuel avant de procéder
php artisan pladigit:backup --force --slug=demo

# Restaurer uniquement la base du tenant
# Stopper les workers pendant la restauration
sudo supervisorctl stop pladigit-worker:*

gunzip -c /tmp/restore_demo/db_demo.sql.gz | mysql -u pladigit -p pladigit_demo

# Redémarrer les workers
sudo supervisorctl start pladigit-worker:*

# Vider les caches
php artisan cache:clear
```

---

### 7. Procédure de restauration d'un fichier GED

**Scénario :** un document a été supprimé par erreur dans la GED.

```bash
# Identifier quelle sauvegarde contient le fichier
# (chercher dans la sauvegarde d'hier en premier)
gpg --batch --passphrase "VOTRE_PASSPHRASE_GPG" \
    --decrypt backup_YYYY-MM-DD_demo.tar.gz.gpg \
    > /tmp/restore_ged.tar.gz

# Lister les fichiers GED dans l'archive sans tout extraire
tar -tzf /tmp/restore_ged.tar.gz | grep "ged/"

# Extraire uniquement le fichier recherché
tar -xzf /tmp/restore_ged.tar.gz -C /tmp/ \
    ged/organisations/demo/documents/2026/05/le_fichier.pdf

# Copier vers la GED
cp /tmp/ged/organisations/demo/documents/2026/05/le_fichier.pdf \
   /var/www/pladigit/storage/app/private/ged/organisations/demo/documents/2026/05/

sudo chown www-data:www-data \
   /var/www/pladigit/storage/app/private/ged/organisations/demo/documents/2026/05/le_fichier.pdf
```

---

### 8. Test de restauration périodique

**Une sauvegarde non testée n'est pas une sauvegarde.**

#### 8.1 Test mensuel recommandé

Une fois par mois, exécuter cette procédure sur un environnement
de test (pas en production) :

```bash
# 1. Prendre la dernière sauvegarde de production
# 2. La déchiffrer
gpg --batch --passphrase "PASSPHRASE" --decrypt backup_*.tar.gz.gpg > /tmp/test.tar.gz

# 3. Vérifier que l'archive est lisible et complète
tar -tzf /tmp/test.tar.gz | wc -l  # doit retourner un nombre > 0
tar -tzf /tmp/test.tar.gz | grep "db_platform.sql.gz"  # doit trouver le dump
tar -tzf /tmp/test.tar.gz | grep "db_"  # doit trouver les dumps tenant

# 4. Restaurer sur une instance MySQL de test
mkdir /tmp/test_restore
tar -xzf /tmp/test.tar.gz -C /tmp/test_restore
gunzip -c /tmp/test_restore/db_platform.sql.gz | mysql -u pladigit -p pladigit_test_platform

# 5. Vérifier que les données sont lisibles
mysql -u pladigit -p pladigit_test_platform -e "SELECT COUNT(*) FROM organizations;"

# 6. Consigner le résultat dans le journal de tests
echo "$(date) — Test restauration OK — $(wc -l < <(tar -tzf /tmp/test.tar.gz)) fichiers" \
    >> /var/log/pladigit_restore_tests.log

# 7. Nettoyer
rm -rf /tmp/test_restore /tmp/test.tar.gz
```

#### 8.2 Checklist de validation du test

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

Ces valeurs sont indicatives. Elles doivent être validées par un
test de restauration réel (§8) et ajustées selon la taille réelle
des données de la collectivité.

**Amélioration du RPO :**
Pour les collectivités qui ne peuvent pas se permettre 24h de perte,
passer `backup_schedule` à `hourly` dans PlatformSettings — les
sauvegardes se déclenchent toutes les heures.

---

### 10. Plan de réponse à incident

#### 10.1 Signes d'une compromission

- Connexions SSH depuis des IP inconnues (vérifier `/var/log/auth.log`)
- Processus inconnus consommant CPU ou réseau (`top`, `netstat`)
- Fichiers modifiés récemment dans `/var/www/pladigit` (`find . -newer .env -type f`)
- Logs applicatifs avec des erreurs inhabituelles ou des requêtes suspectes
- Sauvegarde échouant avec "Permission denied" sur des fichiers non modifiés

#### 10.2 Procédure d'urgence immédiate

```bash
# 1. Isoler le serveur — couper le trafic entrant
sudo ufw deny in on eth0

# 2. Prendre un snapshot de l'état actuel (pour investigation)
# Depuis le panneau OVH : Instances → Créer un snapshot

# 3. Changer immédiatement tous les mots de passe et secrets :
#    - Mot de passe root SSH
#    - Clés SSH autorisées (vérifier ~/.ssh/authorized_keys)
#    - Mot de passe MySQL pladigit
#    - APP_KEY Laravel (régénérer + recrypter les secrets)
#    - Passphrase GPG des sauvegardes

# 4. Vérifier les accès récents
last -20                          # dernières connexions
grep "Failed password" /var/log/auth.log | tail -20
grep "Accepted" /var/log/auth.log | tail -20

# 5. Notifier la collectivité (responsable informatique ou DGS)
```

#### 10.3 Rotation d'urgence de l'APP_KEY

**⚠ Critique :** changer l'APP_KEY invalide tous les secrets chiffrés
(TOTP, mots de passe SFTP des sauvegardes). Procédure à suivre
dans l'ordre strict :

```bash
# 1. Sauvegarder le .env actuel
cp /var/www/pladigit/.env /root/.env_before_rotation

# 2. Déchiffrer et noter les secrets avant rotation
php artisan tinker --execute="
echo 'Secrets actuels :' . PHP_EOL;
// Lister les secrets chiffrés à re-chiffrer après rotation
"

# 3. Générer une nouvelle APP_KEY
php artisan key:generate --force

# 4. Re-chiffrer les secrets avec la nouvelle clé
# (via l'interface Super Admin → Paramètres → Sécurité)

# 5. Vérifier que les 2FA fonctionnent encore
# (les utilisateurs devront peut-être re-scanner leur QR code)

# 6. Vider tous les caches et sessions
php artisan cache:clear
php artisan config:clear
php artisan session:flush 2>/dev/null || true
```

#### 10.4 Communication de crise

En cas de compromission avérée de données personnelles, le RGPD
impose une notification à la CNIL dans les **72 heures** (article 33).

Le responsable de traitement de la collectivité (le Maire ou le DGS)
doit être informé immédiatement. Pladigit fournit les éléments
techniques pour constituer le dossier de notification :

- Date et heure estimées de la compromission (logs)
- Nature des données potentiellement exposées
- Nombre d'enregistrements concernés
- Mesures prises pour contenir l'incident

---

### 11. Checklist de sécurité — vérification périodique

À exécuter mensuellement :

```bash
# Droits sur le .env
ls -la /var/www/pladigit/.env
# Attendu : -rw-r----- ubuntu www-data

# Workers Supervisor
sudo supervisorctl status
# Attendu : RUNNING

# Fail2ban
sudo fail2ban-client status sshd
# Attendu : Currently banned > 0 si attaques en cours

# Dernière sauvegarde réussie
php artisan tinker --execute="
\$ps = App\Models\Platform\PlatformSettings::first();
echo \$ps->backup_last_status . ' — ' . \$ps->backup_last_run_at . PHP_EOL;
"
# Attendu : success — date < 25h

# Clés SSH autorisées (vérifier l'absence d'entrées inconnues)
cat ~/.ssh/authorized_keys

# Connexions SSH récentes
last -10

# Test de restauration (mensuel)
# → Suivre §8.1
```

---

## Conséquences

### Positives
- TDE MySQL protège les données si le disque est volé physiquement
- Le chiffrement GPG des sauvegardes protège en cas de vol d'archive
- Les procédures de restauration documentées réduisent le stress
  et les erreurs lors d'un incident réel
- Le test périodique détecte les sauvegardes corrompues avant qu'on
  en ait besoin
- Le plan de réponse à incident guide des actions calmes et ordonnées
  dans un moment potentiellement paniqué
- La notification CNIL dans les 72h devient réalisable avec les
  éléments techniques pré-identifiés

### Points de vigilance
- **Le fichier keyring MySQL (TDE) et la passphrase GPG doivent être
  stockés hors du serveur** — leur perte rend les données et les
  sauvegardes irrécupérables. Les stocker dans un gestionnaire
  de mots de passe séparé (Bitwarden, KeePass) est obligatoire.
- La rotation d'APP_KEY invalide les secrets chiffrés — procédure
  strictement ordonnée, ne jamais la faire sous pression
- TDE MySQL ajoute une charge CPU faible mais non nulle (~5%) —
  acceptable sur VPS-1 OVH pour les volumes de collectivités cibles
- Le test mensuel de restauration demande 30 minutes — à planifier
  dans l'agenda de maintenance

### Ce que cette ADR ne couvre pas
- La sécurité du poste de travail de l'administrateur (hors périmètre)
- Les attaques par ingénierie sociale (phishing) ciblant les agents
- La supervision temps réel (SIEM) — hors périmètre pour V1,
  à envisager si Pladigit est adopté par plusieurs collectivités

---

## Références
- ADR-002 : Architecture multi-tenant base dédiée
- ADR-017 : Double authentification TOTP
- ADR-026 : Déploiement production VPS
- ADR-033 : Ressources locales et en-têtes HTTP sécurité
- ADR-037 : Gouvernance des données personnelles RGPD
- RGPD article 33 : notification de violation de données à la CNIL
- ANSSI — Guide d'hygiène informatique pour les collectivités
- MySQL 8 InnoDB Transparent Data Encryption :
  https://dev.mysql.com/doc/refman/8.0/en/innodb-data-encryption.html
- `docs/deploy/troubleshooting.md` : incidents déjà résolus en production
