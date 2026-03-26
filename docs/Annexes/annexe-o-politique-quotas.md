# Annexe O — Politique de quotas de stockage

> Documentation destinée aux administrateurs de collectivité et aux super-administrateurs Pladigit.
> Version : Phase 4 — Mars 2026

---

## Table des matières

1. [Principe et objectif](#1-principe-et-objectif)
2. [Définition du quota](#2-définition-du-quota)
3. [Ce qui est compté dans le quota](#3-ce-qui-est-compté-dans-le-quota)
4. [Enforcement — upload direct](#4-enforcement--upload-direct)
5. [Enforcement — synchronisation NAS](#5-enforcement--synchronisation-nas)
6. [Notifications de seuil](#6-notifications-de-seuil)
7. [Affichage de l'utilisation](#7-affichage-de-lutilisation)
8. [Configuration par le super-administrateur](#8-configuration-par-le-super-administrateur)
9. [Actions disponibles en cas de dépassement](#9-actions-disponibles-en-cas-de-dépassement)
10. [Limites et précautions](#10-limites-et-précautions)

---

## 1. Principe et objectif

Chaque organisation Pladigit dispose d'un **quota de stockage** qui plafonne l'espace total occupé par les médias dans la photothèque. Ce quota remplit trois fonctions :

1. **Isolation** : empêcher qu'une organisation monopolise l'espace disque du serveur mutualisé.
2. **Prévisibilité** : permettre au super-administrateur de dimensionner l'infrastructure en fonction du parc d'organisations.
3. **Alertes précoces** : notifier les administrateurs locaux avant d'atteindre la saturation, afin de permettre un nettoyage ou une demande d'augmentation en temps utile.

---

## 2. Définition du quota

Le quota est exprimé en **mégaoctets** et stocké dans la colonne `organizations.storage_quota_mb` (base plateforme).

| Paramètre | Valeur par défaut | Minimum |
|-----------|-------------------|---------|
| `storage_quota_mb` | 10 240 Mo (10 Go) | 512 Mo |

La valeur par défaut de 10 Go convient à une petite commune (< 20 000 habitants) avec une activité photographique modérée. Les grandes collectivités peuvent demander une augmentation au super-administrateur.

---

## 3. Ce qui est compté dans le quota

Le quota porte sur la somme des `file_size_bytes` des entrées **non supprimées** (soft-delete exclu) dans `media_items` :

```sql
SELECT SUM(file_size_bytes)
FROM media_items
WHERE deleted_at IS NULL;
```

**Ce qui est compté :**
- Fichiers images (JPEG, PNG, WebP, HEIC…)
- Vidéos (MP4, MOV, AVI)
- PDF

**Ce qui n'est pas compté :**
- Miniatures (thumbnails) — stockées sur le NAS mais non enregistrées dans `media_items`
- Fichiers supprimés (soft-delete)
- Doublons marqués `is_duplicate = true` sont comptés (ils occupent de l'espace disque réel)

---

## 4. Enforcement — upload direct

Lors d'un upload via le formulaire Pladigit, le système vérifie **avant d'écrire sur le NAS** que le fichier entrant ne ferait pas dépasser le quota :

```
Quota utilisé + taille du fichier entrant > quota total
        ↓
Exception levée → upload refusé avec message d'erreur
```

**Message affiché à l'utilisateur :**
> "Quota de stockage dépassé. Utilisé : X Mo / Y Mo. Espace libre : Z Mo. Fichier : W Mo."

L'upload est refusé proprement — aucun fichier n'est écrit sur le NAS. Les autres fichiers d'un import par lot (ZIP) qui rentrent dans le quota restent importés.

---

## 5. Enforcement — synchronisation NAS

La commande `nas:sync` respecte également le quota lors de l'ingestion de nouveaux fichiers depuis le NAS :

- Avant d'ingérer un fichier, le service vérifie si `usedBytes + fileSize > quotaBytes`.
- Si oui, le fichier est **ignoré** (non ingéré) et un avertissement est inscrit dans les logs Laravel.
- La synchronisation continue sur les autres fichiers.

```
[WARNING] MediaService::ingestNasFile — quota dépassé, fichier ignoré
  path: album-exemple/photo.jpg
  file_size_mb: 12.4
  quota_mb: 10240
```

Le fichier reste physiquement sur le NAS — il sera re-tenté lors de la prochaine sync si de l'espace a été libéré entre-temps.

---

## 6. Notifications de seuil

Pladigit envoie des **notifications internes** aux administrateurs de l'organisation quand la consommation franchit un seuil :

| Seuil | Déclenchement |
|-------|---------------|
| 80 % | Première alerte — planifier un nettoyage |
| 90 % | Alerte importante — agir rapidement |
| 95 % | Seuil critique — uploads bientôt bloqués |

**Règle anti-spam** : une seule notification est émise par seuil franchi. Tant que la consommation reste au-dessus du seuil (sans redescendre en dessous puis remonter), aucune notification supplémentaire n'est émise.

Les notifications apparaissent dans la cloche de notification Pladigit. Elles ne génèrent pas d'email (fonctionnalité email à activer en Phase suivante).

---

## 7. Affichage de l'utilisation

### Sidebar photothèque

La sidebar affiche en permanence une barre de progression de l'utilisation :

```
Stockage
████████░░  82 %
8 396 Mo / 10 240 Mo — 1 844 Mo libres
```

La barre change de couleur :
- Vert : < 80 %
- Orange : 80–94 %
- Rouge : ≥ 95 %

### Page Réglages (admin)

La page `Admin > Réglages > Médias` affiche les mêmes informations avec plus de détail, accessible uniquement aux rôles Admin, Président et DGS.

---

## 8. Configuration par le super-administrateur

Le quota est modifiable depuis le **Super-Admin Pladigit** (`/super-admin/organizations/{id}/edit`) :

| Champ | Type | Contrainte |
|-------|------|------------|
| `storage_quota_mb` | entier | minimum 512 Mo |

Un quota à `null` est ramené à 10 240 Mo par défaut. Il n'est pas possible de désactiver le quota.

### Procédure d'augmentation de quota

1. L'administrateur local constate que la barre d'utilisation est proche de 100 % ou reçoit une notification de seuil.
2. Il contacte le super-administrateur (Jean-Pierre Bossé ou responsable informatique de la collectivité).
3. Le super-admin augmente `storage_quota_mb` depuis l'interface Super-Admin.
4. Les fichiers ignorés lors des dernières syncs NAS seront automatiquement ingérés à la prochaine exécution de `nas:sync`.

---

## 9. Actions disponibles en cas de dépassement

Si le quota est atteint et qu'aucune augmentation n'est immédiatement possible :

### Libérer de l'espace

| Action | Effet |
|--------|-------|
| Supprimer des médias depuis la photothèque | Soft-delete → les bytes sont libérés du compteur immédiatement |
| Supprimer les doublons (`is_duplicate = true`) | Libère de l'espace si les fichiers existent en plusieurs exemplaires |
| Vider la corbeille (suppression définitive) | Libère également l'espace sur le NAS |

> **Note :** le soft-delete libère le quota compteur (la somme `file_size_bytes` ne compte que les entrées non supprimées), mais le fichier reste physiquement sur le NAS jusqu'à la suppression définitive. Pour libérer l'espace disque réel, il faut effectuer une suppression définitive.

### Vérifier les imports volumineux

La page de détail d'un album affiche la taille des fichiers. Les vidéos et PDF sont souvent les fichiers les plus volumineux.

---

## 10. Limites et précautions

### Comptage après suppression définitive

Après une suppression définitive d'un média (hard delete), la colonne `file_size_bytes` est retirée du compteur. Cependant, si le fichier physique a déjà été supprimé du NAS, une prochaine `nas:sync` ne recréera pas l'entrée.

### Quota et déduplication

Un fichier marqué `is_duplicate` occupe **quand même** du quota (le fichier physique existe sur le NAS). La déduplication SHA-256 évite les doublons lors des futurs uploads, mais ne supprime pas les doublons déjà présents.

### Quotas et imports ZIP

Un import ZIP contenant plusieurs fichiers vérifie le quota **avant chaque fichier**. Si le quota est dépassé à mi-chemin, les fichiers déjà importés sont conservés, les suivants sont rejetés. Un rapport récapitulatif indique les fichiers ignorés.

### Pas de purge automatique

Pladigit n'efface jamais automatiquement des fichiers pour libérer du quota. La suppression reste toujours une action manuelle de l'administrateur.
