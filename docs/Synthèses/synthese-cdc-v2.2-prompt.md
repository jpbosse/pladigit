# Synthèse pour Claude web — CDC v2.2 + Guide utilisateur photothèque

> Document à copier-coller intégralement dans Claude web pour lui donner le contexte complet.
> Date de génération : 26 mars 2026

---

## Ce que tu dois produire

Je te demande deux documents distincts :

1. **Le CDC Pladigit v2.2** — mise à jour du CDC v2.1 existant, en intégrant toutes les modifications apportées au code depuis sa rédaction (Phase 4 complète).
2. **Un guide utilisateur de la photothèque** — document de formation destiné aux agents et administrateurs d'une collectivité, en langage simple, sans jargon technique.

---

## Contexte projet

**Nom :** Pladigit — Plateforme de Digitalisation Interne
**Organisation cliente :** Les Bézots, Soullans (Vendée, 85), France
**Responsable :** Jean-Pierre Bossé
**Licence :** AGPL-3.0
**Dépôt :** privé, non publié à ce jour

Pladigit est une plateforme SaaS multi-tenant pour collectivités locales et associations, alternative souveraine aux outils Microsoft (Teams, SharePoint, OneDrive, Word, Excel, Planner). Elle est développée en Laravel 11 / PHP 8.4, Alpine.js, MySQL, stockage NAS (Local/SFTP/SMB).

L'IA (Claude, Anthropic) est intégrée dans le processus de développement depuis mars 2026, ce qui a ramené le planning initial de 48 mois à 26 mois (livraison maximale estimée : novembre 2027).

---

## État du projet à ce jour (26 mars 2026)

### Tests
- **591 tests, 1255 assertions — 100 % verts**

### Phases livrées

| Phase | Contenu | Statut |
|-------|---------|--------|
| Phase 1 | Infrastructure, multi-tenant, auth (LDAP, 2FA TOTP), rôles, audit | ✅ Livré |
| Phase 2 | Module Projets complet (Kanban, Gantt SVG, jalons, budgets, documents, visio Jitsi) | ✅ Livré |
| Phase 3 | Finances projets (budgets multi-catégories, graphiques, exports) | ✅ Livré |
| Phase 4 | Module Photothèque complet (voir détail ci-dessous) | ✅ Livré |

### Phases planifiées (non commencées)

| Phase | Contenu estimé |
|-------|----------------|
| Phase 5 | GED (gestion électronique de documents) |
| Phase 6 | Chat temps réel (remplacement Teams) |
| Phase 7 | Collabora Online (édition ODF temps réel) |
| Phase 8 | Agenda, Kanban global, notifications push |
| Phase 9 | Module RH / Annuaire étendu |
| Phase 10 | **IA — Tagging automatique photos (Ollama + LLaVA)** ← nouveau dans v2.2 |
| Phase 11 | ERP léger (commandes, fournisseurs) |
| Phase 12 | Module Enquêtes/Sondages |
| Phase 13 | Open source public, contribution communautaire |

---

## Phase 4 — Photothèque — Détail complet de ce qui est livré

### Architecture technique
- **NasConnectorInterface** avec 3 drivers : `LocalNasDriver`, `SftpNasDriver`, `SmbNasDriver`
- Méthodes : `listFiles`, `exists`, `read`, `write`, `delete`, `mkdir`, `moveFile`, `moveDir`
- **MediaService** : upload, déduplication SHA-256, extraction EXIF, watermarking (GD natif), quota
- Jobs asynchrones : `ProcessMediaUpload`, `ProcessZipImport` (queue `database`)
- **NasSyncCommand** (`nas:sync`) : sync standard horaire, sync profonde (`--deep`) nocturne
- Verrou de sync via `Cache::lock()` pour éviter les exécutions parallèles

### Fonctionnalités utilisateur

#### Albums
- Hiérarchie illimitée (parent_id récursif)
- Visibilité : Public / Restreint / Privé
- Image de couverture configurable (champ `cover_item_id`)
- Droits d'accès par album : matrice utilisateur × permission (view/upload/manage)
- Héritage des droits vers les sous-albums

#### Upload / Import
- Upload multi-fichiers par drag-and-drop ou sélection
- Import ZIP (extraction et ingestion asynchrone)
- Vérification quota avant écriture NAS
- Déduplication SHA-256 (badge DOUBLON sur les doublons)
- Formats : JPEG, PNG, WebP, GIF, HEIC, MP4, MOV, AVI, PDF — max 200 Mo

#### Navigation et affichage
- Sidebar avec arbre lazy-load (chargement des enfants à la demande)
- Recherche dans l'arbre (dès 2 caractères, délai 300 ms)
- Vue grille (colonnes configurables) et vue liste
- Pagination : 5 / 10 / 20 / tout
- Tri : nom, taille, date EXIF, date import, type
- Filtrage par tag

#### Visionneuse
- Lightbox plein écran avec navigation clavier (←→, Échap, Espace)
- Diaporama automatique (2s / 3s / 5s)

#### Édition
- Rotation 90° gauche/droite (modifie le fichier NAS, régénère miniature)
- Recadrage (crop) avec sélection interactive
- Légende/caption (500 caractères)

#### Tags manuels
- Tags libres normalisés en minuscules
- Autocomplete sur les tags existants dans la photothèque
- Badge 🏷 N sur les cartes de la grille
- Badges dans la vue liste
- Éditeur inline dans le panneau de détail (ajout + retrait sans rechargement)
- Filtrage de l'album par tag (clic sur un badge)
- Modèle : `media_tags`, pivot `media_item_tag`

#### Drag-and-drop des photos
- Glisser une photo sur une carte sous-album (dans la grille)
- Glisser une photo sur n'importe quel album de la sidebar
- Sélection multiple + bouton "↗ Déplacer vers…" → modal avec recherche
- Déplacement physique sur le NAS (`moveFile`) + mise à jour DB (`file_path`, `thumb_path`)

#### Drag-and-drop des albums (réorganisation hiérarchie)
- Réservé aux rôles Admin, Président, DGS
- Glisser un album sur un autre dans la sidebar → devient sous-album
- Zone "Déposer ici pour mettre à la racine" en haut de la sidebar
- Déplacement physique NAS (`moveDir`) + mise à jour récursive en DB (tous les descendants : `nas_path`, et tous les médias : `file_path`, `thumb_path`)
- Protection anti-boucle circulaire (validation serveur)
- Verrou `Cache::lock()` pendant le déplacement

#### Partage par lien temporaire
- Lien avec date d'expiration (obligatoire), mot de passe optionnel
- Option "Autoriser le téléchargement ZIP"
- Page publique sans compte (lightbox, download individuel ou ZIP)

#### Export ZIP
- Génération à la volée (streaming, pas de surcharge mémoire)
- Limite : 500 Mo par album
- Disponible aussi via liens de partage

#### Recherche globale
- Critères : mot-clé (nom + légende + tags), type, date de prise de vue
- Résultats en grille (48 par page)

#### Quota de stockage
- Champ `organizations.storage_quota_mb` (défaut 10 240 Mo, min 512 Mo)
- **Enforcement strict** : upload bloqué + sync NAS ignore les fichiers si quota dépassé
- Notifications internes aux admins à 80 %, 90 %, 95 % (anti-spam : une par seuil)
- Barre de progression colorée dans la sidebar (vert < 80 %, orange 80–94 %, rouge ≥ 95 %)

#### Synchronisation NAS
- Commande `nas:sync` (standard) et `nas:sync --deep` (SHA-256)
- Planifiée : hourly jours ouvrés + daily 02h00 (deep)
- Déclenchable manuellement (bouton admin dans la photothèque)
- Respecte le quota : fichiers ignorés avec log warning si quota plein

### Endpoints API (routes web)
```
GET    media/albums                     → index (liste + arbre racine)
GET    media/albums/{album}             → show (contenu album)
POST   media/albums                     → store
PUT    media/albums/{album}             → update
DELETE media/albums/{album}             → destroy
GET    media/albums/{album}/children    → enfants (lazy-load sidebar)
GET    media/albums/search              → recherche albums
PUT    media/albums/{album}/cover/{item}→ définir couverture
POST   media/albums/{album}/move-album  → déplacer album dans hiérarchie
POST   media/albums/{album}/permissions → gérer droits

GET    media/albums/{album}/items/{item}→ show (page détail média)
POST   media/albums/{album}/items       → upload
DELETE media/albums/{album}/items/{item}→ supprimer
POST   media/albums/{album}/items/move  → déplacer photos vers album
POST   media/albums/{album}/items/{item}/rotate → rotation
POST   media/albums/{album}/items/{item}/crop   → recadrage
POST   media/albums/{album}/items/{item}/caption→ légende

POST   media/items/{item}/tags          → ajouter tag
DELETE media/items/{item}/tags/{tag}    → retirer tag
GET    media/tags/suggest               → autocomplete tags

GET    media/albums/{album}/share       → gestionnaire liens
POST   media/albums/{album}/share       → créer lien
DELETE media/albums/{album}/share/{link}→ supprimer lien
GET    media/albums/{album}/zip         → télécharger ZIP

GET    media/search                     → recherche globale
GET    media/items/{item}/serve/{size}  → servir fichier (thumb/original)
GET    media/items/{item}/download      → forcer téléchargement
```

### ADRs liés au module Photothèque
- **ADR-012** — Stockage NAS, pas cloud (3 drivers : local/SFTP/SMB)
- **ADR-013** — Déduplication SHA-256
- **ADR-014** — Queue `database` (pas Redis) pour les jobs d'import
- **ADR-015** — Streaming HTTP Range pour les vidéos
- **ADR-018** — Watermark par GD natif (pas ImageMagick)
- **ADR-019** — Enforcement quota strict (upload + sync NAS bloquants)

---

## Modifications à apporter dans le CDC v2.2

### §1 — Présentation
- Mettre à jour : "Les phases 1, 2, 3 et **4** sont livrées"
- Mettre à jour le compteur de tests : **591 tests, 1255 assertions**
- Mentionner l'accélération confirmée : 1 à 2 mois par phase

### §4.2 — Décisions architecturales
- Lister les ADR-012 à ADR-019 (actuellement limité à ADR-011 dans le v2.1)

### §5 — Fonctionnalités / Planning des phases
- Marquer **Phase 4** comme livrée avec résumé du contenu
- Ajouter **Phase 10 — IA (Ollama + LLaVA)** dans le tableau des phases planifiées :
  - Tagging automatique des photos par analyse visuelle (LLaVA)
  - Déploiement Ollama local ou API distante configurable
  - Suggestions de tags IA en arrière-plan, validation humaine
  - Infrastructure légère : ne nécessite pas de GPU dédié (mode CPU acceptable pour les collectivités)
  - Justification du report : valeur émergente seulement après 1-2 ans d'accumulation de données

### §8 — Qualité logicielle
- Mettre à jour les compteurs de tests (591/1255)
- Mentionner les nouveaux modules de tests : MediaTagTest, MediaItemMoveTest, MediaAlbumMoveTest, MediaExifTest

### §11 — Liste des annexes
Ajouter les annexes Phase 4 :
- **Annexe E** — Module Photothèque (mise à jour complète Phase 4)
- **Annexe F** — Politique de synchronisation NAS (mise à jour : quota enforced)
- **Annexe O** — Politique de quotas de stockage (nouvelle)

---

## Annexes existantes (liste complète)

| Annexe | Titre | État |
|--------|-------|------|
| A | Personas utilisateurs | Stable |
| B | Architecture multi-tenant | Stable |
| C | Matrice des droits | Stable |
| D | Structure organisationnelle | Stable |
| E | Module Photothèque | Mise à jour Phase 4 ✅ |
| F | Politique de synchronisation NAS | Mise à jour Phase 4 ✅ |
| J | Module Agenda | Phase 8 |
| K | CI/CD GitHub Actions | Stable |
| M | Plan de reprise d'activité (PRA) | Stable |
| O | Politique de quotas | Nouveau Phase 4 ✅ |
| Q | Plan de succession | Stable |
| T | Gestion de projet | Stable |

---

## ADRs complets (ADR-001 à ADR-019)

| ADR | Titre | Phase |
|-----|-------|-------|
| 001 | Stack frontend : Alpine.js + Blade (pas Vue/React) | 1 |
| 002 | Multi-tenant par base dédiée (pas par schéma) | 1 |
| 003 | TDD partiel (Feature tests, pas de unit mocks) | 1 |
| 004 | Auth locale bcrypt (pas JWT) | 1 |
| 005 | LDAP/LDAPS obligatoire (pas LDAP clair) | 1 |
| 006 | Hiérarchie Direction → Service → Agent | 2 |
| 007 | PHPStan avec stub smbclient | 2 |
| 008 | Kanban par jalons (pas par sprint) | 2 |
| 009 | Gantt SVG serveur (pas JS) | 2 |
| 010 | Double couche droits projets (UserRole + ProjectRole) | 2 |
| 011 | Droits hiérarchiques projets | 2 |
| 012 | Stockage NAS (pas cloud) | 4 |
| 013 | Déduplication SHA-256 | 4 |
| 014 | Queue database (pas Redis) | 4 |
| 015 | Streaming HTTP Range | 4 |
| 016 | Modules JSON par organisation | 4 |
| 017 | 2FA TOTP (pas SMS) | 1 |
| 018 | Watermark GD natif (pas ImageMagick) | 4 |
| 019 | Enforcement quota strict | 4 |

---

## Pour le guide utilisateur — Photothèque

Ce guide est destiné aux agents et administrateurs d'une collectivité (mairie, communauté de communes, etc.). Il doit être :
- En langage simple, sans jargon technique
- Illustré par des exemples concrets (vœux du maire, réunion de conseil, travaux voirie…)
- Structuré en deux parties : utilisateur ordinaire, puis actions réservées aux gestionnaires/admins
- Format : Markdown, avec une table des matières, des encadrés "Bon à savoir", des listes étapes numérotées

### Public cible
- **Agent** : peut uploader des photos, les voir, les télécharger — ne peut pas supprimer ni déplacer
- **Responsable de service / Resp. direction** : selon les droits accordés sur chaque album
- **Administrateur / Président / DGS** : accès complet, réorganisation, droits, quotas, NAS

### Contenu à couvrir dans le guide

**Partie 1 — Pour tous les utilisateurs**
1. Accéder à la photothèque
2. Naviguer dans les albums (sidebar, dépliage, recherche)
3. Uploader des photos (drag-and-drop, sélection, ZIP)
4. Voir une photo en grand (visionneuse)
5. Lancer un diaporama
6. Télécharger une photo ou un album entier (ZIP)
7. Rechercher une photo

**Partie 2 — Pour les gestionnaires (Resp. service et au-dessus)**
8. Créer et organiser les albums
9. Ajouter des légendes et des tags
10. Déplacer des photos vers un autre album
11. Définir une image de couverture
12. Partager un album par lien (avec ou sans mot de passe)
13. Gérer les droits d'accès à un album

**Partie 3 — Pour les administrateurs**
14. Réorganiser la hiérarchie des albums (drag-and-drop)
15. Synchroniser le NAS
16. Surveiller l'espace de stockage
17. Comprendre les alertes de quota

### Ton et style
- Tutoyer (le guide s'adresse directement à l'utilisateur)
- Phrases courtes, active voice
- Exemple : "Pour créer un album : clique sur **+ Nouvel album** en haut de la sidebar. Donne-lui un nom et choisis sa visibilité (Public, Restreint ou Privé). Valide — l'album apparaît immédiatement dans l'arbre."
- Utiliser des icônes texte (⭐, 🏷, 🔗, 📥…) pour repérer les boutons dont on parle
