# Chiffrement au repos MySQL InnoDB TDE — Pladigit VPS

> **Scope :** VPS de production uniquement (`demo.pladigit.fr` et futurs VPS collectivités).
> À exécuter **une seule fois** lors de la mise en production initiale, ou à posteriori
> sur un serveur existant. Non applicable à l'environnement local de développement.

---

## 1. Prérequis

- Ubuntu 24.04 LTS
- MySQL 8.0 ou supérieur (`mysql --version`)
- Accès root sur le VPS
- Sauvegardes à jour avant toute manipulation

---

## 2. Concepts clés

MySQL InnoDB TDE (Transparent Data Encryption) chiffre les fichiers de données
sur le disque (`.ibd`, redo logs, undo logs) via un keyring. Le chiffrement est
**transparent** pour l'application Laravel — aucune modification du code.

Deux composants :
- **Keyring plugin** (`keyring_file`) : stocke la clé maître dans un fichier dédié
- **Chiffrement des tablespaces** : activé table par table ou par défaut

---

## 3. Étape 1 — Installer et activer le plugin keyring

```bash
# Créer le répertoire du keyring hors du répertoire MySQL
sudo mkdir -p /etc/mysql/keyring
sudo chown mysql:mysql /etc/mysql/keyring
sudo chmod 700 /etc/mysql/keyring
```

Éditer `/etc/mysql/mysql.conf.d/mysqld.cnf` — ajouter dans la section `[mysqld]` :

```ini
[mysqld]
# ── TDE InnoDB ──────────────────────────────────────────────────────────────
early-plugin-load                = keyring_file.so
keyring_file_data                = /etc/mysql/keyring/keyring
innodb_encrypt_tables            = ON
innodb_encrypt_online_alter_logs = ON
innodb_encrypt_temporary_tables  = ON
```

Redémarrer MySQL :

```bash
sudo systemctl restart mysql
sudo systemctl status mysql
```

---

## 4. Étape 2 — Vérifier que le plugin est actif

```sql
-- Se connecter en root
sudo mysql

-- Vérifier le plugin
SELECT PLUGIN_NAME, PLUGIN_STATUS FROM information_schema.PLUGINS
WHERE PLUGIN_NAME LIKE 'keyring%';
-- Attendu : keyring_file | ACTIVE

-- Vérifier les variables TDE
SHOW VARIABLES LIKE 'innodb_encrypt%';
-- Attendu : innodb_encrypt_tables = ON
```

---

## 5. Étape 3 — Chiffrer les bases existantes

Les bases existantes ne sont pas chiffrées automatiquement. Il faut les
chiffrer explicitement :

```sql
-- Chiffrer la base platform
USE pladigit_platform;

-- Lister les tables à chiffrer
SELECT TABLE_NAME FROM information_schema.TABLES
WHERE TABLE_SCHEMA = 'pladigit_platform';

-- Chiffrer chaque table (remplacer <table> par chaque nom)
ALTER TABLE <table> ENCRYPTION='Y';
```

Pour automatiser sur toutes les tables de toutes les bases tenants :

```bash
# Script de chiffrement en masse
sudo mysql --batch --skip-column-names -e "
  SELECT CONCAT('ALTER TABLE \`', TABLE_SCHEMA, '\`.\`', TABLE_NAME, '\` ENCRYPTION=''Y'';')
  FROM information_schema.TABLES
  WHERE TABLE_SCHEMA NOT IN ('information_schema','performance_schema','mysql','sys')
    AND ENGINE = 'InnoDB'
    AND CREATE_OPTIONS NOT LIKE '%ENCRYPTION=\"Y\"%'
" | sudo mysql
```

Vérifier le résultat :

```sql
SELECT TABLE_SCHEMA, TABLE_NAME, CREATE_OPTIONS
FROM information_schema.TABLES
WHERE TABLE_SCHEMA NOT IN ('information_schema','performance_schema','mysql','sys')
  AND CREATE_OPTIONS LIKE '%ENCRYPTION%'
ORDER BY TABLE_SCHEMA, TABLE_NAME;
```

---

## 6. Étape 4 — Sauvegarder le keyring hors serveur (CRITIQUE)

> ⚠️ **Sans le keyring, les données chiffrées sont irrécupérables.**
> Le keyring DOIT être sauvegardé dans un endroit distinct du VPS.

```bash
# Copier le keyring vers le NAS ou un support externe
sudo cp /etc/mysql/keyring/keyring /chemin/vers/sauvegarde/keyring_$(date +%Y%m%d)

# Vérifier les droits du fichier keyring
ls -la /etc/mysql/keyring/keyring
# Attendu : -rw------- 1 mysql mysql ...
```

**Règle :** sauvegarder le keyring à chaque rotation de clé et lors de toute
modification de la configuration MySQL. Le conserver sur un support distinct
du VPS (NAS local, clé USB sécurisée).

---

## 7. Étape 5 — Vérifier avec la commande artisan

```bash
php8.4 artisan pladigit:check-tde
```

Résultat attendu :

```
✓ Plugin keyring_file : ACTIF
✓ innodb_encrypt_tables : ON
✓ Tables chiffrées : 47/47
✓ Fichier keyring présent : /etc/mysql/keyring/keyring
```

---

## 8. Rotation de la clé maître (maintenance annuelle)

```sql
-- Générer une nouvelle clé maître
ALTER INSTANCE ROTATE INNODB MASTER KEY;
```

Puis sauvegarder immédiatement le nouveau keyring (voir Étape 4).

---

## 9. En cas de restauration

Lors d'une restauration depuis une sauvegarde mysqldump sur un nouveau serveur :

1. Installer MySQL et activer le keyring **avant** d'importer les dumps
2. Copier le keyring sauvegardé vers `/etc/mysql/keyring/keyring`
3. Redémarrer MySQL
4. Importer les dumps SQL (`mysql < dump.sql`)

> Les dumps mysqldump exportent les données **déchiffrées** — ils sont
> indépendants du keyring. Le chiffrement est réappliqué à l'import si
> `innodb_encrypt_tables = ON` est actif.

---

## 10. Références

- ADR-041 §1.1 — Décision TDE MySQL
- [MySQL 8 InnoDB Encryption](https://dev.mysql.com/doc/refman/8.0/en/innodb-data-encryption.html)
- `docs/deploy/collabora.md` — déploiement Collabora Online
