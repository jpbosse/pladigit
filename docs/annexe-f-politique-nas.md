# Annexe F — Politique de synchronisation NAS

> Documentation technique pour les administrateurs système et les responsables informatiques.
> Version : Phase 4/5 — Mars 2026

---

## Table des matières

1. [Principe de fonctionnement](#1-principe-de-fonctionnement)
2. [Drivers NAS supportés](#2-drivers-nas-supportés)
3. [Configuration](#3-configuration)
4. [Algorithme de synchronisation](#4-algorithme-de-synchronisation)
5. [Déduplication par SHA-256](#5-déduplication-par-sha-256)
6. [Commandes artisan](#6-commandes-artisan)
7. [Planification automatique (cron)](#7-planification-automatique-cron)
8. [Politique de nommage recommandée](#8-politique-de-nommage-recommandée)
9. [Réorganisation depuis Pladigit](#9-réorganisation-depuis-pladigit)
10. [Limites et précautions](#10-limites-et-précautions)

---

## 1. Principe de fonctionnement

La synchronisation NAS est le mécanisme par lequel Pladigit **ingère les fichiers déposés sur un partage réseau** (NAS, serveur de fichiers) et les rend accessibles dans le module Photothèque.

```
NAS / Serveur de fichiers
   └── Photos/
         ├── 2024/
         │     ├── Janvier/  ←── dossier NAS
         │     └── Février/
         └── Évènements/
                └── Vœux 2024/

          ↕  synchronisation (cron ou manuelle)

Pladigit — Photothèque
   └── Photos/
         ├── 2024/
         │     ├── Janvier/  ←── album Pladigit
         │     └── Février/
         └── Évènements/
                └── Vœux 2024/
```

La synchronisation est **non-destructive** : elle n'efface jamais de fichiers sur le NAS. Elle peut en revanche supprimer des entrées en base de données si un fichier a disparu du NAS.

---

## 2. Drivers NAS supportés

Pladigit supporte trois drivers via l'abstraction `NasConnectorInterface` :

| Driver | Protocole | Usage typique |
|--------|-----------|---------------|
| `local` | Système de fichiers local | NAS monté en CIFS/NFS sur le serveur |
| `sftp` | SSH / SFTP | NAS exposé via SSH |
| `smb` | SMB / Samba | Partage Windows, NAS Synology/QNAP |

Le driver est sélectionné dans `config/nas.php` via la clé `driver`. Un seul driver est actif par organisation.

---

## 3. Configuration

### `config/nas.php`

```php
return [
    'driver' => env('NAS_DRIVER', 'local'),   // local | sftp | smb

    'local' => [
        'root' => env('NAS_LOCAL_ROOT', '/mnt/nas/photos'),
    ],

    'sftp' => [
        'host'       => env('NAS_SFTP_HOST'),
        'port'       => env('NAS_SFTP_PORT', 22),
        'username'   => env('NAS_SFTP_USER'),
        'password'   => env('NAS_SFTP_PASS'),    // ou privateKey
        'root'       => env('NAS_SFTP_ROOT', '/photos'),
    ],

    'smb' => [
        'host'       => env('NAS_SMB_HOST'),
        'share'      => env('NAS_SMB_SHARE'),
        'username'   => env('NAS_SMB_USER'),
        'password'   => env('NAS_SMB_PASS'),
        'workgroup'  => env('NAS_SMB_WORKGROUP', 'WORKGROUP'),
    ],

    'allowed_mimes' => [
        'image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/heic',
        'video/mp4', 'video/quicktime', 'video/x-msvideo',
        'application/pdf',
    ],

    'max_file_size_mb' => env('NAS_MAX_FILE_MB', 200),
];
```

### Variables d'environnement `.env`

```env
NAS_DRIVER=sftp
NAS_SFTP_HOST=nas.mairie-exemple.fr
NAS_SFTP_PORT=22
NAS_SFTP_USER=pladigit
NAS_SFTP_PASS=motdepasse_securise
NAS_SFTP_ROOT=/volume1/photos
```

---

## 4. Algorithme de synchronisation

La synchronisation suit ces étapes pour chaque dossier NAS associé à un album :

```
Pour chaque album avec nas_path défini :
  1. Lister les fichiers présents sur le NAS (listFiles)
  2. Pour chaque fichier :
     a. Vérifier si le chemin existe déjà en base
        → Oui : vérifier si le SHA-256 a changé (fichier modifié)
        → Non : nouveau fichier → créer l'entrée
     b. Générer la miniature (si image)
     c. Extraire les métadonnées EXIF
     d. Calculer le SHA-256 pour la déduplication
  3. Détecter les fichiers supprimés du NAS :
     → Supprimer les entrées orphelines (soft delete)
  4. Récursivité sur les sous-dossiers :
     → Créer les sous-albums si nécessaire
```

### Mode standard vs mode profond

| Mode | Commande | Comportement |
|------|----------|-------------|
| Standard | `nas:sync` | Détecte les ajouts et suppressions par chemin |
| Profond (`--deep`) | `nas:sync --deep` | Recalcule le SHA-256 de chaque fichier, détecte les modifications de contenu |

Le mode standard est rapide (pas de lecture de chaque fichier). Le mode profond est plus lent mais détecte les fichiers modifiés.

---

## 5. Déduplication par SHA-256

Pladigit calcule l'empreinte SHA-256 de chaque fichier ingéré. Si un fichier identique existe déjà en base de données (même hash), il est marqué comme **doublon** sans être dupliqué sur le NAS.

- Les doublons sont signalés par un badge rouge **DOUBLON** dans la grille de l'album
- Ils restent accessibles et téléchargeables
- L'administrateur peut les supprimer manuellement ou les conserver

> **Attention** : la déduplication est basée sur le contenu, pas sur le nom. Deux photos de noms différents mais de contenu identique seront détectées comme doublons.

---

## 6. Commandes artisan

### Synchronisation standard

```bash
php artisan nas:sync
```
Synchronise tous les albums de toutes les organisations ayant un `nas_path` défini.

### Synchronisation profonde

```bash
php artisan nas:sync --deep
```
Recalcule le SHA-256 de chaque fichier. À utiliser après des modifications manuelles de fichiers sur le NAS.

### Synchronisation d'une organisation spécifique

```bash
php artisan nas:sync --org=mairie-exemple
```

### Re-extraction EXIF

```bash
php artisan media:refresh-exif
```
Ré-extrait les métadonnées EXIF de tous les médias images (utile après une migration ou si l'extraction a échoué lors de la sync initiale).

---

## 7. Planification automatique (cron)

La synchronisation est planifiée dans `routes/console.php` (scheduler Laravel) :

```php
// Sync standard toutes les heures (jours ouvrés)
Schedule::command('nas:sync')->hourly()->weekdays();

// Sync profonde toutes les nuits
Schedule::command('nas:sync --deep')->dailyAt('02:00');
```

### Prérequis cron système

Sur le serveur, la tâche cron Laravel doit être active :

```cron
* * * * * www-data php /var/www/pladigit/artisan schedule:run >> /dev/null 2>&1
```

---

## 8. Politique de nommage recommandée

Pour que la synchronisation produise une arborescence d'albums propre dans Pladigit, il est recommandé d'adopter une convention de nommage des dossiers NAS.

### Convention recommandée

```
Photos/
├── YYYY-MM — Description/          ← format date + tiret + libellé
│     ex : 2024-01 — Vœux du maire
├── YYYY — Thème/
│     ex : 2024 — Travaux voirie
└── Thème/                           ← dossiers sans date (archives permanentes)
      ex : Patrimoine bâti
```

### À éviter

| Problème | Exemple | Impact |
|----------|---------|--------|
| Caractères spéciaux | `Réunion & Conseil/` | Risque d'erreur selon le driver |
| Espaces en début/fin | ` Photos /` | Miniatures mal indexées |
| Noms trop longs | 191 caractères max pour le `nas_path` en base | Troncature |
| Casse incohérente | `Photos/` et `photos/` | Doublons sur certains systèmes de fichiers |

### Attribution des fichiers importés via sync

Lorsqu'un fichier est ingéré par la synchronisation (et non par upload direct), il est attribué :
- À l'utilisateur ayant créé l'album, si l'album a été créé manuellement
- Au premier administrateur de l'organisation, si l'album a été créé automatiquement par la sync

---

## 9. Réorganisation depuis Pladigit

### Principe

L'objectif stratégique de Pladigit est de **remplacer l'usage direct du NAS** comme outil de travail quotidien. La réorganisation des albums doit donc se faire depuis Pladigit, et non plus en déplaçant des dossiers dans l'Explorateur Windows.

Quand un administrateur réorganise un album (drag-and-drop dans Pladigit) :

1. Pladigit déplace le dossier correspondant **sur le NAS** (opération physique)
2. Met à jour le `parent_id` et le `nas_path` de l'album et de tous ses descendants en base
3. La prochaine synchronisation trouve les dossiers à leur nouvel emplacement — aucun doublon n'est créé

### Flux recommandé pour les agents

```
Agent dépose des photos sur le NAS (via réseau)
         ↓
Synchronisation automatique (cron horaire)
         ↓
Photos apparaissent dans Pladigit
         ↓
Agent réorganise depuis Pladigit (drag-and-drop)
         ↓
NAS reflète la nouvelle organisation
```

Ce flux garantit que **le NAS et Pladigit sont toujours cohérents**, sans divergence entre ce qu'on voit dans l'Explorateur Windows et ce qu'on voit dans Pladigit.

---

## 10. Limites et précautions

### Taille maximale par fichier

Les fichiers de plus de **200 Mo** sont ignorés lors de la synchronisation (configurable via `NAS_MAX_FILE_MB`).

### Formats non supportés

Seuls les MIME types listés dans `config/nas.php` (`allowed_mimes`) sont ingérés. Les autres fichiers sont ignorés silencieusement.

### Verrou de synchronisation

Une synchronisation en cours pose un verrou pour éviter les exécutions parallèles. Si une sync reste bloquée (crash serveur), le verrou peut être levé manuellement :

```bash
php artisan cache:forget nas_sync_lock
```

### Modification directe sur le NAS pendant une sync

Il est déconseillé de déplacer ou renommer des dossiers sur le NAS **pendant** une synchronisation en cours. Cela peut créer des entrées orphelines temporaires, qui seront nettoyées à la prochaine sync.

### Quota de stockage

La sync ne vérifie pas le quota de stockage de l'organisation avant d'ingérer des fichiers. Le quota est informatif (affiché dans la sidebar). Si le quota est dépassé, un avertissement est affiché mais les fichiers continuent d'être indexés.

### Pas de sauvegarde automatique

Pladigit ne sauvegarde pas les fichiers NAS — il les indexe. La politique de sauvegarde du NAS (snapshots, RAID, sauvegardes externes) reste de la responsabilité de l'organisation.
