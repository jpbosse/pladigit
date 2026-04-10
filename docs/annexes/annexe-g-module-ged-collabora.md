# Annexe G — Module GED et Collabora Online

> Documentation technique — Phases 6 et 7 — Avril 2026  
> Remplace SharePoint + Microsoft Office dans l'écosystème Pladigit

---

## Table des matières

1. [Présentation du module](#1-présentation-du-module)
2. [Architecture des données](#2-architecture-des-données)
3. [Système de permissions](#3-système-de-permissions)
4. [Stockage — GedStorageInterface](#4-stockage--gedstorageinterface)
5. [Synchronisation NAS → GED](#5-synchronisation-nas--ged)
6. [Versioning des documents](#6-versioning-des-documents)
7. [Intégration GED ↔ Projets](#7-intégration-ged--projets)
8. [Collabora Online — protocole WOPI](#8-collabora-online--protocole-wopi)
9. [Locks WOPI — édition simultanée](#9-locks-wopi--édition-simultanée)
10. [Gouvernance admin](#10-gouvernance-admin)
11. [Recherche plein texte](#11-recherche-plein-texte)
12. [Décisions architecturales](#12-décisions-architecturales)

---

## 1. Présentation du module

Le module GED (Gestion Électronique de Documents) de Pladigit est l'alternative souveraine à SharePoint et Microsoft Office. Il permet à une organisation de centraliser, organiser, partager et éditer ses documents en ligne, sans aucune dépendance à un cloud propriétaire.

Collabora Online est une fonctionnalité intégrée au module GED — pas un module séparé (ADR-022). Dès que la GED est activée et qu'une URL Collabora est configurée, le bouton "Ouvrir dans Collabora" apparaît automatiquement pour les formats supportés.

**Formats supportés par Collabora :**

| Format | Type |
|--------|------|
| ODT, ODS, ODP, ODG | Formats ODF natifs |
| DOCX, XLSX, PPTX | Microsoft Office (conversion à la volée) |
| DOC, XLS, PPT | Anciens formats Microsoft Office |

---

## 2. Architecture des données

### 2.1 — Table `ged_folders`

Arborescence de dossiers hiérarchique illimitée via `parent_id` récursif.

| Colonne | Type | Description |
|---------|------|-------------|
| `id` | int | Clé primaire |
| `name` | string | Nom affiché |
| `slug` | string | Identifiant URL |
| `path` | string | Chemin logique complet (ex: `/direction-rh/recrutement`) |
| `nas_path` | string\|null | Chemin NAS correspondant — null si dossier créé manuellement |
| `parent_id` | int\|null | Dossier parent — null = racine |
| `is_private` | bool | Dossier privé (visible Admin/DGS + créateur uniquement) |
| `created_by` | int | Utilisateur créateur |
| `deleted_at` | datetime\|null | Soft delete |

### 2.2 — Table `ged_documents`

| Colonne | Type | Description |
|---------|------|-------------|
| `id` | int | Clé primaire |
| `folder_id` | int | Dossier parent |
| `name` | string | Nom affiché du fichier |
| `disk_path` | string | Chemin physique sur le driver de stockage |
| `mime_type` | string | Type MIME détecté à l'upload |
| `size_bytes` | int | Taille en octets |
| `current_version` | int | Numéro de version courante (commence à 1) |
| `created_by` | int | Utilisateur ayant uploadé |
| `deleted_at` | datetime\|null | Soft delete — corbeille configurable |

**Convention de chemin :** `ged/{org_slug}/{YYYY}/{MM}/{uuid}.{ext}`

### 2.3 — Table `ged_document_versions`

Table immuable — chaque version archivée. Aucun `updated_at`.

| Colonne | Type | Description |
|---------|------|-------------|
| `id` | int | Clé primaire |
| `document_id` | int | Document parent |
| `version_number` | int | Numéro de version archivée |
| `disk_path` | string | Chemin physique de cette version sur le stockage |
| `size_bytes` | int | Taille de cette version |
| `mime_type` | string | Type MIME de cette version |
| `uploaded_by` | int | Utilisateur ayant créé cette version |
| `created_at` | datetime | Date de création (horodatage d'origine conservé) |

### 2.4 — Table `ged_wopi_tokens`

Tokens d'accès temporaires pour Collabora Online.

| Colonne | Type | Description |
|---------|------|-------------|
| `id` | int | Clé primaire |
| `document_id` | int | Document concerné |
| `user_id` | int | Utilisateur éditeur |
| `token` | string | Token brut (hash aléatoire) |
| `expires_at` | datetime | Expiration (TTL configurable, défaut 4h) |

### 2.5 — Relations Eloquent

```
GedFolder
  ├── parent()          → BelongsTo<GedFolder>
  ├── children()        → HasMany<GedFolder>
  ├── allChildren()     → HasMany<GedFolder> (récursif eager-load)
  ├── documents()       → HasMany<GedDocument>
  ├── permissions()     → HasMany<GedFolderPermission>
  └── userPermissions() → HasMany<GedFolderUserPermission>

GedDocument
  ├── folder()          → BelongsTo<GedFolder>
  ├── creator()         → BelongsTo<User>
  ├── versions()        → HasMany<GedDocumentVersion> (orderByDesc version_number)
  └── projectLinks()    → HasMany<ProjectGedLink>
```

---

## 3. Système de permissions

### 3.1 — Niveaux de permission (`GedPermissionLevel`)

```
none (0)     → Aucun droit
view (1)     → Visualisation inline uniquement
download (2) → Téléchargement autorisé
upload (3)   → Téléverser + modifier des documents
admin (4)    → Gestion complète du dossier + permissions
```

La comparaison est numérique — `atLeast(Download)` vérifie `level >= 2`.  
En cas de permissions multiples applicables, la plus élevée l'emporte (`GedPermissionLevel::max()`).

### 3.2 — Deux tables de permissions

**`ged_folder_permissions`** — permissions par sujet collectif :

| `subject_type` | `subject_id` | `subject_role` | Signification |
|----------------|-------------|----------------|---------------|
| `role` | null | `admin` | Tous les Admins |
| `role` | null | `president` | Tous les Présidents |
| `direction` | 12 | null | Toute la Direction RH |
| `service` | 7 | null | Tout le Service Technique |

**`ged_folder_user_permissions`** — overrides individuels par utilisateur.  
Priorité maximale — écrase toujours les permissions collectives.

### 3.3 — Héritage hiérarchique

Un dossier hérite des permissions de son dossier parent si aucune permission explicite n'est définie. La résolution remonte l'arbre jusqu'à la racine. Cela permet de définir les droits une seule fois au niveau d'une Direction et de les propager automatiquement à tous les sous-dossiers.

### 3.4 — Dossiers privés (`is_private`)

Un dossier `is_private = true` est visible uniquement par :
- Son créateur
- Les rôles Admin et DGS (visibilité totale implicite)

Les permissions héritées et collectives sont ignorées pour les dossiers privés.

---

## 4. Stockage — GedStorageInterface

Le code métier (upload, download, versioning, WOPI) ne dépend jamais d'un driver de stockage particulier. Tout passe par `GedStorageInterface` (ADR-020).

### 4.1 — Interface

```php
interface GedStorageInterface
{
    public function put(string $path, mixed $contents): bool;
    public function get(string $path): string|false;
    public function readStream(string $path): mixed;   // resource|false
    public function delete(string $path): bool;
    public function exists(string $path): bool;
    public function size(string $path): int;
    public function mimeType(string $path): string|false;
    public function mkdir(string $path): bool;
    public function listDirectory(string $path): array;
}
```

### 4.2 — Drivers disponibles

| Driver | Cas d'usage |
|--------|------------|
| `LocalGedDriver` | Stockage local — développement, petites installations |
| `SftpGedDriver` | NAS Linux/FreeBSD — connexion SFTP |
| `SmbGedDriver` | NAS Windows/Samba — partage SMB/CIFS |

La factory `NasManager::gedDriver()` instancie le bon driver depuis `TenantSettings` (colonnes `nas_ged_*`). Le binding Laravel est enregistré dans `AppServiceProvider` — `GedStorageInterface` est résolu automatiquement par injection de dépendance.

### 4.3 — Configuration par tenant

```
tenant_settings.nas_ged_driver       → 'local' | 'sftp' | 'smb'
tenant_settings.nas_ged_host         → Hôte NAS
tenant_settings.nas_ged_path         → Chemin racine GED sur le NAS
tenant_settings.nas_ged_username     → Identifiant
tenant_settings.nas_ged_password     → Mot de passe (chiffré AES-256)
```

---

## 5. Synchronisation NAS → GED

### 5.1 — Principe

Les utilisateurs peuvent continuer à déposer des fichiers directement sur le NAS (via Windows Explorer, macOS Finder, etc.). La commande `ged:sync` détecte silencieusement les nouveaux fichiers et les indexe dans Pladigit.

C'est un choix délibéré : forcer les utilisateurs à passer uniquement par Pladigit dès le premier jour est irréaliste. La coexistence NAS + Pladigit est assumée sur le long terme.

### 5.2 — Commande Artisan

```bash
# Tous les tenants actifs
php artisan ged:sync

# Un tenant spécifique
php artisan ged:sync --tenant=mairie-soullans

# Sous-dossier NAS spécifique
php artisan ged:sync --root=documents/RH
```

**Planification** : toutes les heures via le scheduler Laravel (`routes/console.php`).

### 5.3 — Comportement

- Scan récursif de l'arborescence NAS GED
- **Nouveaux dossiers NAS** → création automatique de `GedFolder` avec `nas_path` renseigné
- **Dossiers créés manuellement** (`nas_path = null`) → jamais touchés par la sync
- **Nouveaux fichiers** → création de `GedDocument` avec détection du MIME
- **Fichiers disparus** → soft-delete du `GedDocument` correspondant
- **Fichiers ignorés** → MIME non autorisé ou taille dépassant la limite configurée

### 5.4 — Job asynchrone `SyncGedJob`

La sync peut aussi être déclenchée manuellement depuis l'interface admin (bouton "Synchroniser"). Elle est alors dispatchée via le job `SyncGedJob` sur la queue Redis pour ne pas bloquer la requête HTTP.

---

## 6. Versioning des documents

### 6.1 — À l'upload

Chaque upload crée un nouveau `GedDocument` avec `current_version = 1`. Si un document du même nom existe déjà dans le dossier, une nouvelle version est créée.

### 6.2 — À la sauvegarde Collabora (PutFile)

Quand Collabora sauvegarde un document via WOPI `PutFile` :

1. L'ancienne version (`disk_path` actuel) est archivée dans `ged_document_versions`
2. Le contenu reçu est écrit sur un nouveau chemin UUID
3. `ged_documents.disk_path` est mis à jour vers le nouveau chemin
4. `current_version` est incrémenté
5. Un log d'audit est enregistré

```
Version 1 → archivée dans ged_document_versions (disk_path conservé)
Version 2 → devient la version courante dans ged_documents
```

### 6.3 — Restauration

L'interface admin permet de restaurer n'importe quelle version archivée. La version courante est d'abord archivée, puis la version cible devient la version courante.

---

## 7. Intégration GED ↔ Projets

La table `project_ged_links` permet de lier un document GED à n'importe quelle entité projet :

```
project_ged_links
  ├── ged_document_id   → GedDocument
  ├── documentable_type → 'App\Models\Tenant\Project' | 'App\Models\Tenant\Task' | ...
  └── documentable_id   → ID de l'entité liée
```

Depuis un projet, l'onglet Documents affiche les documents GED liés. Depuis un document GED, `linkedProjects()` et `linkedTasks()` retournent les entités associées.

---

## 8. Collabora Online — protocole WOPI

### 8.1 — Flux d'ouverture

```
Utilisateur clique "Ouvrir dans Collabora"
  ↓
GedEditorController::show()
  → Génère un WopiToken (TTL configurable, défaut 4h)
  → Construit l'URL d'action : {COLLABORA_URL}/browser/dist/cool.html?WOPISrc={wopiSrc}&lang=fr
  → Retourne la vue editor.blade.php
    ↓
Navigateur soumet un formulaire POST vers Collabora avec access_token
  ↓
Collabora appelle les endpoints WOPI sur Pladigit
```

### 8.2 — Endpoints WOPI implémentés

| Méthode | Route | Endpoint | Description |
|---------|-------|----------|-------------|
| GET | `/wopi/files/{id}` | `checkFileInfo` | Métadonnées du document |
| GET | `/wopi/files/{id}/contents` | `getFile` | Contenu binaire |
| POST | `/wopi/files/{id}/contents` | `putFile` | Sauvegarde du contenu |
| POST | `/wopi/files/{id}` | `lockFile` | Lock/Unlock/RefreshLock/GetLock |

Ces routes sont **publiques** (hors middleware auth Laravel). Le token WOPI est le mécanisme de sécurité. Le CSRF est exempt sur `/wopi/*`.

### 8.3 — Token d'accès multi-tenant

Format : `{org_slug}:{raw_token}`

Collabora n'a besoin que d'un seul `aliasgroup` fixe pointant vers `WOPI_URL`, quelle que soit l'organisation. Le tenant est résolu depuis le préfixe du token dans `WopiController::resolveToken()`.

### 8.4 — `access_token_ttl` — timestamp absolu (ADR-021)

⚠ Piège connu : Collabora interprète `access_token_ttl` comme un **timestamp Unix absolu en millisecondes**, pas une durée.

```php
// CORRECT — timestamp futur en ms
public function ttlMs(): int
{
    $ttl = (int) config('collabora.token_ttl', 14400);
    return (int) (now()->addSeconds($ttl)->timestamp * 1000);
}

// FAUX — durée en ms → session expire immédiatement
return $ttl * 1000;
```

### 8.5 — `checkFileInfo` — champs clés retournés

```json
{
  "BaseFileName": "deliberation-2026-04.odt",
  "Size": 24576,
  "Version": "3",
  "OwnerId": "12",
  "UserId": "7",
  "UserFriendlyName": "Marie Dupont",
  "UserCanWrite": true,
  "ReadOnly": false,
  "SupportsLocks": true
}
```

`UserCanWrite` est déterminé par `GedPermissionService::canUpload($user, $folder)` — cohérent avec les droits GED.

---

## 9. Locks WOPI — édition simultanée

### 9.1 — Pourquoi les locks sont nécessaires

Sans locks, deux utilisateurs ouvrant le même document simultanément peuvent s'écraser mutuellement silencieusement. Les locks WOPI permettent à Collabora de détecter les conflits et d'alerter l'utilisateur.

### 9.2 — Table `ged_wopi_locks`

| Colonne | Type | Description |
|---------|------|-------------|
| `document_id` | int | Document verrouillé |
| `lock_id` | string | Identifiant du lock (fourni par Collabora) |
| `expires_at` | datetime | Expiration automatique |
| `locked_by` | int | Utilisateur détenteur du lock |

### 9.3 — Opérations WOPI sur les locks

Toutes dispatché via l'header `X-WOPI-Override` sur `POST /wopi/files/{id}` :

| `X-WOPI-Override` | Action | Réponse succès |
|-------------------|--------|----------------|
| `LOCK` | Verrouille le document | `200 OK` |
| `UNLOCK` | Libère le verrou | `200 OK` |
| `REFRESH_LOCK` | Prolonge l'expiration | `200 OK` |
| `GET_LOCK` | Retourne le `lock_id` actuel | `200 OK` + header `X-WOPI-Lock` |

En cas de conflit (lock existant avec un `lock_id` différent) : `409 Conflict` + header `X-WOPI-Lock` avec le lock actuel.

---

## 10. Gouvernance admin

### 10.1 — Interface Admin GED (`/admin/ged`)

Accessible aux rôles Admin uniquement. Fonctionnalités :

- **Intégrité des fichiers** — vérifie que chaque `disk_path` en base existe physiquement sur le stockage
- **Transfert de propriété** — réassigne tous les documents d'un utilisateur à un autre (départ d'un agent)
- **Purge de la corbeille** — suppression définitive des documents soft-deleted depuis plus de N jours (configurable)
- **Statistiques** — espace occupé par dossier, nombre de versions archivées

### 10.2 — Commande de purge

```bash
# Purger les documents supprimés depuis plus de 30 jours (valeur par défaut)
php artisan pladigit:purge-expired-data

# Planifiée quotidiennement via le scheduler
```

### 10.3 — Configuration Collabora (Admin > GED > Collabora)

| Paramètre | Description |
|-----------|-------------|
| URL Collabora | `https://collabora.mairie.fr` — vide = bouton absent |
| URL WOPI | URL fixe accessible depuis le serveur Collabora |
| TTL session | Durée de validité du token (minutes) — défaut : 240 min (4h) |
| Tester la connexion | Ping `{COLLABORA_URL}/hosting/capabilities` |

---

## 11. Recherche plein texte

La recherche GED utilise MySQL FULLTEXT (ADR-014) sur les colonnes `name` et `mime_type` de `ged_documents`.

**Avantages du choix FULLTEXT :**
- Aucun service externe (pas de Meilisearch, pas d'Elasticsearch)
- Opérationnel immédiatement sur n'importe quelle installation MySQL 8
- Suffisant pour rechercher par nom de fichier et type

**Limites assumées :**
- Ne recherche pas dans le contenu des fichiers (texte des PDF, ODT, etc.)
- La recherche sémantique par contenu est prévue en Phase IA (Ollama + Mistral)

La recherche est accessible depuis la barre de recherche globale et depuis l'interface GED (`/ged/search`).

---

## 12. Décisions architecturales

| ADR | Décision |
|-----|----------|
| [ADR-020](../adr/ADR-020-ged-storage-interface.md) | Abstraction du stockage via `GedStorageInterface` |
| [ADR-021](../adr/ADR-021-wopi-access-token-ttl-timestamp-absolu.md) | `access_token_ttl` est un timestamp Unix absolu en ms |
| [ADR-022](../adr/ADR-022-collabora-integre-ged-pas-module-separe.md) | Collabora intégré à GED, pas de module séparé |

---

*Pladigit — jpbosse/pladigit — AGPL-3.0 — Avril 2026*
