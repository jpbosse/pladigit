# CDC Pladigit v2.2 — Cahier des Charges

> Pladigit — Plateforme de Digitalisation Interne
> Organisation : Les Bézots — Soullans (85), France
> Responsable : Jean-Pierre Bossé
> Licence : AGPL-3.0 (core) / MIT (utilitaires)
> Dépôt : github.com/jpbosse/pladigit
> Date : Mars 2026
> Planning : 26 mois — Octobre 2025 → Novembre 2027

---

## Historique des versions

| Version | Date | Changements |
|---------|------|-------------|
| v1.0 | Oct 2025 | CDC initial — fonctionnalités complètes, 14 annexes (A–N) |
| v1.1 | Fév 2026 | Ajouts : distribution, budget, monitoring, agenda (Annexe Q), personas (U) |
| v1.2 | Mars 2026 | Audit Phase 1, bugs identifiés, spécifications Phase 2, ADR |
| v1.3 | Mars 2026 | Phase 2 livrée : profil, UserRole enum, directions/services, CI vert |
| v1.4 | Mars 2026 | Document complet fusionné — toutes versions + toutes annexes |
| v2.0 | Mars 2026 | Refonte structurelle — CDC principal + annexes séparées. Phase 2 complète : LDAP, branding, sync-ldap, 237 tests. PHP 8.4, PHPStan niveau 5. |
| v2.1 | Mars 2026 | Planning révisé (1–2 mois/phase avec IA), Phase 3 Gestion de projet livrée, ADR-008–011, Annexe T, 492 tests / 1044 assertions. Livewire 4.2, DomPDF, Jitsi Meet. |
| **v2.2** | **Mars 2026** | **Phases 4 et 5 Photothèque livrées. 591 tests / 1255 assertions. ADR-012–019. Annexes E, F, O mises à jour. Phase 10 IA (Ollama+LLaVA) ajoutée au planning.** |

---

## 1. Présentation du projet

Les Bézots est une organisation de services basée à Soullans (Vendée, France). Pladigit est une plateforme SaaS de digitalisation interne, alternative souveraine open source (AGPL-3.0) aux outils Microsoft.

L'intégration de l'IA (Claude, Anthropic) depuis mars 2026 a ramené le planning initial de 48 mois à **26 mois** (livraison maximale : novembre 2027).

**Phases livrées : Phase 1 ✓ + Phase 2 ✓ + Phase 3 ✓ + Phase 4 ✓ + Phase 5 ✓**
**Phase en cours : Phase 6 — GED documentaire**
**Tests CI/CD : 591 tests / 1255 assertions — 4 checks verts ✓**

---

## 2. Objectifs du projet

### 2.1 — Objectif principal

Développer un produit fonctionnel, robuste et pérenne, proposé en mode SaaS, permettant aux organisations — collectivités locales, associations, structures du secteur public et parapublic — de se libérer progressivement des outils propriétaires Microsoft.

### 2.2 — Objectifs secondaires

- Gestion documentaire centralisée avec droits d'accès par rôle.
- Photothèque NAS avec albums hiérarchiques, synchronisation, tags, partage.
- Édition collaborative de documents en ligne via Collabora Online (formats ODF natifs).
- Remplacement de Microsoft Teams par un chat intégré temps réel.
- Interface moderne, responsive et accessible WCAG 2.1 / RGAA 4.1 niveau AA.
- Sécurité des données (RGPD, 2FA, protection anti-intrusion, audit complet).
- Architecture modulaire et multi-tenant permettant une montée en charge progressive.
- Code maintenable, documenté, testé et transférable (plan de succession).
- Contribution communautaire open source à partir de la Phase 13.

---

## 3. Modèle économique et licences

### 3.1 — Les 3 offres

| Communautaire | Assistance | Enterprise |
|---|---|---|
| 0 € / mois | 150 € / mois | Sur devis |
| Tous modules inclus, Auth LDAP + 2FA, multi-organisations, auto-hébergé, AGPL-3.0 | Tout Communautaire + personnalisation, support email/téléphone, formation, maintenance | Tout Assistance + hébergement dédié, SLA garanti, support prioritaire, développements sur mesure |

---

## 4. Architecture et décisions techniques

### 4.1 — Stack technique (Mars 2026)

| Technologie | Version | Rôle |
|-------------|---------|------|
| PHP | 8.4 | Langage backend principal |
| Laravel | 11.x | Framework MVC — routing, Eloquent ORM, Artisan |
| Livewire | 4.2 | Composants dynamiques |
| Alpine.js | 3.x | Interactions JS légères côté client |
| MySQL | 8.0+ | SGBD principal — base dédiée par tenant |
| Redis | 7.x | Cache, sessions |
| Queue | database | Jobs asynchrones (upload, ZIP, sync NAS) |
| Soketi | 1.x | WebSocket auto-hébergé (chat temps réel — Phase 6) |
| Collabora Online | CODE 24.x | Éditeur bureautique open source (WOPI — Phase 7) |
| Docker | 24+ | Conteneurisation Collabora Online |
| Tailwind CSS | 3.x | Framework CSS utilitaire |
| DomPDF | 3.1 | Génération PDF (rapports élus, exports) |
| PHPUnit | 11.x | Tests unitaires et fonctionnels |
| Laravel Pint | 1.x | Formatage code (PSR-12) |
| PHPStan | 1.x | Analyse statique de type (niveau 5) |

### 4.2 — Décisions architecturales (ADR-001 à ADR-019)

| ADR | Décision | Phase |
|-----|----------|-------|
| ADR-001 | Stack frontend : Alpine.js + Blade (Livewire réservé aux interfaces réactives) | 1 |
| ADR-002 | Multi-tenant : base MySQL dédiée par organisation | 1 |
| ADR-003 | TDD partiel dès Phase 1 — PHPStan niveau 5 + Pint sur chaque commit | 1 |
| ADR-004 | Auth locale : bcrypt coût 12, sessions protégées CSRF | 1 |
| ADR-005 | LDAP : LDAPS (TLS) obligatoire, port 636, identifiants chiffrés AES-256 | 1 |
| ADR-006 | Hiérarchie : Direction > Service > Agent, multi-appartenance | 2 |
| ADR-007 | PHPStan + stub smbclient.php — CI continue-on-error | 2 |
| ADR-008 | Kanban par jalon — chaque jalon = sprint autonome | 3 |
| ADR-009 | Gantt SVG côté serveur — drag & drop ajouté | 3 |
| ADR-010 | Double couche droits projets : UserRole (global) + ProjectRole (local) | 3 |
| ADR-011 | Droits hiérarchiques projets — Resp. Direction/Service en lecture seule | 3 |
| ADR-012 | Stockage NAS exclusivement (pas cloud) — 3 drivers : local/SFTP/SMB | 4 |
| ADR-013 | Déduplication SHA-256 — pas de doublon en base ni sur le NAS | 4 |
| ADR-014 | Queue `database` pour les jobs d'import (pas Redis queue) | 4 |
| ADR-015 | Streaming HTTP Range pour les vidéos | 4 |
| ADR-016 | Modules activables par organisation via JSON (enabled_modules) | 4 |
| ADR-017 | 2FA TOTP uniquement (pas SMS) — codes de secours chiffrés AES-256 | 1 |
| ADR-018 | Watermark par GD natif PHP 8.4 (pas ImageMagick) | 5 |
| ADR-019 | Enforcement quota strict — upload + sync NAS bloquants si quota dépassé | 5 |

---

## 5. Fonctionnalités — Planning des phases

Planning révisé : **26 mois** — Octobre 2025 → Novembre 2027

| État | Phase | Période | Contenu |
|------|-------|---------|---------|
| ✓ | 1 | Oct–Déc 2025 | Socle technique, CI/CD, auth locale, 2FA TOTP, multi-tenant, audit |
| ✓ | 2 | Jan–Mar 2026 | Profil, directions/services, LDAP, invitation email, branding, dashboard |
| ✓ | 3 | Mars 2026 | Gestion de projet — Kanban, Gantt (drag & drop), Liste, Charge, budget, risques, conduite du changement, agenda projet, documents, notifications, historique, exports PDF/iCal, modèles, Jitsi Meet |
| ✓ | 4 | Mars–Avr 2026 | Photothèque NAS — albums hiérarchiques, upload multi-fichiers, import ZIP, sync NAS (Local/SFTP/SMB), miniatures, EXIF, watermark, droits par album, modules activables, queue database |
| ✓ | 5 | Avr 2026 | Photothèque avancée — tags libres, drag & drop photos/albums, partage lien temporaire, export ZIP, recherche globale, quota strict (80/90/95%), barre progression sidebar, visionneuse lightbox, diaporama, rotation/recadrage, légende |
| 📋 | 6 | Mai–Jun 2026 | GED documentaire — arborescence, versionning, recherche plein texte |
| 📋 | 7 | Jul–Aoû 2026 | Collabora Online — intégration WOPI, édition collaborative ODF |
| 📋 | 8 | Sep–Oct 2026 | Chat temps réel — canaux, 1:1, Soketi WebSocket |
| 📋 | 9 | Nov–Déc 2026 | Agenda global — inter-projets, CalDAV, récurrence, export iCal |
| 📋 | 10 | Jan–Fév 2027 | IA — Tagging automatique photos (Ollama + LLaVA), suggestions validées humainement, déploiement local ou API distante |
| 📋 | 11 | Mar–Avr 2027 | ERP léger — commandes, fournisseurs, DataGrid no-code |
| 📋 | 12 | Mai–Jun 2027 | Sondages & questionnaires, fil d'actualités RSS |
| 📋 | 13 | Jul–Aoû 2027 | Production — VPS, monitoring, PRA, audit sécurité externe |
| 📋 | 14 | Sep–Nov 2027 | Open source — publication, communauté, documentation |

### 5.1 — Remplacement des outils Microsoft

| Outil Microsoft | Alternative Pladigit | Phase | Statut |
|----------------|---------------------|-------|--------|
| Microsoft Word | Collabora Online (ODT) | 7 | 📋 Planifié |
| Microsoft Excel | Collabora Online (ODS) | 7 | 📋 Planifié |
| Microsoft PowerPoint | Collabora Online (ODP) | 7 | 📋 Planifié |
| SharePoint / OneDrive | GED Pladigit | 6 | 📋 Planifié |
| Microsoft Teams | Chat Pladigit | 8 | 📋 Planifié |
| Microsoft Planner | Gestion de projet Pladigit | 3 | ✓ Livré |
| Microsoft Forms | Sondages Pladigit | 12 | 📋 Planifié |
| Outlook Calendrier | Agenda Pladigit + iCal | 9 | 📋 Planifié |
| OneDrive Photos | Photothèque Pladigit | 4–5 | ✓ Livré |

---

## 6. Gestion des rôles et de la hiérarchie

### 6.1 — Architecture des rôles (UserRole enum)

| Rôle | Niveau | Périmètre |
|------|--------|-----------|
| Super Admin | Plateforme | Toutes les organisations — technique uniquement |
| Admin | Organisation | Son organisation — utilisateurs et paramètres |
| Président | Organisation | Vision globale, validation stratégique |
| DGS | Organisation | Toutes directions — supervision opérationnelle |
| Resp. Direction | Organisation | Sa direction et ses services enfants |
| Resp. Service | Organisation | Son service et ses agents |
| Utilisateur | Organisation | Selon son service d'appartenance |

---

## 7. Sécurité et conformité

- Hachage bcrypt coût 12 (BCRYPT_ROUNDS=4 en test uniquement).
- Rate limiting sur /login (throttle:10,1) et /2fa/verify (throttle:5,10).
- Sessions régénérées après authentification.
- CSRF protection sur tous les formulaires POST.
- Circuit breaker LDAP : protection contre la désactivation de masse lors d'une sync partielle.
- Chiffrement AES-256 des secrets sensibles (clé LDAP, secrets TOTP, mots de passe SMTP).
- Données multi-tenant : base dédiée, isolation totale garantie.
- VPS hébergé en France (OVH, Scaleway ou équivalent) — aucune donnée hors UE.
- Audit logs avec rétention configurable (3/6/12/24/36 mois) et export CSV/JSON.

---

## 8. Qualité logicielle

### 8.1 — État des tests (Mars 2026 — Phases 1 à 5 livrées)

| Groupe | Tests | Périmètre |
|--------|-------|-----------|
| Auth & Sécurité | 48 | 2FA, throttle, LDAP, sessions, politique MDP |
| Utilisateurs & Org | 78 | CRUD users, rôles, directions, invitation, branding |
| Infrastructure | 35 | TenantManager, provisioning, migrate:tenants, health |
| Modules | 27 | ModuleKey, RequireModule middleware |
| Gestion de projet | 166 | Projets, tâches, jalons, budget, risques, dépendances, iCal |
| Photothèque | 120 | Albums, items, EXIF, tags, move, droits, watermark, quota |
| Divers | 117 | ProjectRole, modèles, audit, récurrence, synchronisation |
| **TOTAL** | **591** | **1255 assertions — CI/CD GitHub Actions ✓** |

### 8.2 — Pipeline CI/CD

| Outil | Commande | Résultat |
|-------|----------|---------|
| PHPUnit | php artisan test --exclude-group ldap,integration | 591 tests / 1255 assertions ✓ |
| Laravel Pint | ./vendor/bin/pint --test | 0 style issue ✓ |
| PHPStan | ./vendor/bin/phpstan analyse --memory-limit=512M | 0 erreur — niveau 5 ✓ |
| Composer audit | composer audit | 0 vulnérabilité ✓ |

---

## 9. Monitoring et performance

- Statping (open source) : monitoring HTTP toutes les 5 minutes, alertes SMS/email.
- Health check natif : GET /health → JSON {status, checks:{database,redis,disk}}.
- Sentry auto-hébergé — capture des exceptions en production.
- Laravel Telescope en staging — profiling des requêtes SQL lentes (> 100 ms).
- Service systemd `pladigit-queue` — queue database, User=deploy, Restart=always.
- Objectif SLA : 99,5 % de disponibilité mensuelle.

---

## 10. Risques et mesures d'atténuation

| Risque | Probabilité | Impact | Mesure d'atténuation |
|--------|------------|--------|---------------------|
| Indisponibilité développeur | Moyenne | Critique | Plan de succession (Annexe Q), code documenté, ADR, IA disponible |
| Faille sécurité critique | Moyenne | Critique | PHPStan, tests, audit externe Phase 13, MàJ rapide |
| Compatibilité Collabora Online | Faible | Élevé | Version CODE stable, tests d'intégration WOPI |
| Perte de données NAS | Faible | Critique | PRA (Annexe M), sauvegardes quotidiennes chiffrées |
| Montée en charge imprévisible | Moyenne | Moyen | Architecture scalable, monitoring, plan migration multi-VPS |
| Abandon d'une dépendance | Faible | Moyen | Dépendances standards (Laravel, MySQL), pas de vendor lock-in |
| Évolution réglementaire RGPD | Faible | Moyen | Veille juridique, architecture données conformes dès v1 |

---

## 11. Liste des annexes

| Annexe | Titre | État |
|--------|-------|------|
| A | Personas et parcours utilisateurs | ✓ Stable |
| B | Architecture multi-tenant | ✓ Stable |
| C | Matrice des droits | ✓ Stable |
| D | Structure organisationnelle | ✓ Stable |
| E | Module Photothèque | ✓ Mise à jour Phase 4–5 |
| F | Politique de synchronisation NAS | ✓ Mise à jour Phase 4–5 |
| J | Module Agenda global | 📋 Phase 9 |
| K | CI/CD et tests automatisés | ✓ Stable |
| M | Plan de Reprise d'Activité (PRA) | ✓ Stable |
| O | Politique de quotas de stockage | ✓ Nouveau Phase 5 |
| Q | Plan de succession | ✓ Stable |
| T | Module Gestion de projet | ✓ Phase 3 |

---

## Contexte technique pour Claude Code

### Commandes essentielles

```bash
# Tests (CI)
php artisan test --exclude-group ldap,integration

# Tests complets avec LDAP (OpenLDAP Docker requis)
php artisan test --group ldap

# Migrations tous les tenants
php artisan migrate:tenants --force

# Sync NAS standard
php artisan nas:sync

# Sync NAS profonde (SHA-256)
php artisan nas:sync --deep

# Récurrence tâches (schedulé 06h00)
php artisan pladigit:generate-recurring-tasks

# PHPStan
./vendor/bin/phpstan analyse --memory-limit=512M

# Pint
./vendor/bin/pint
```

### Règles de développement critiques

- **Toujours** `chown -R deploy:deploy storage/ bootstrap/cache/` après `sudo artisan`
- **www-data** doit être dans le groupe `deploy` (usermod -aG deploy www-data)
- **TenantManager** : injecter via constructeur, jamais `connectTo()` statiquement
- **persistCurrentOrg()** : utiliser `updateOrCreate(['slug' => ...], [...])` jamais `forceCreate()`
- **cleanDatabase()** : TRUNCATE (réinitialise AUTO_INCREMENT), jamais DELETE
- **pladigit.css** : entry point Vite séparé, jamais @import dans app.css
- **Migrations tenant** : `migrate:tenants --force` si `Nothing to migrate`
- **Queue jobs** : reconnecter TenantManager via constructeur avant Eloquent
- **Modals/styles** : toujours styles inline si pas de recompilation Vite prévue
- **PHPStan** : `--memory-limit=512M` obligatoire
- **Groupes exclus CI** : `--exclude-group ldap,integration`

### Structure des modules activables (enabled_modules JSON)

```json
["media", "projects"]
```

Modules disponibles : `media`, `projects`, `ged` (Phase 6), `collabora` (Phase 7), `chat` (Phase 8), `agenda` (Phase 9), `erp` (Phase 11), `surveys` (Phase 12)

### Architecture Photothèque (Phases 4–5)

```
NasConnectorInterface
  ├── LocalNasDriver
  ├── SftpNasDriver
  └── SmbNasDriver

MediaService
  ├── upload() → ProcessMediaUpload (queue)
  ├── importZip() → ProcessZipImport (queue)
  ├── syncNas() → NasSyncCommand
  ├── watermark() → WatermarkService (GD natif)
  └── quota check → organizations.storage_quota_mb

AlbumPermissionService
  └── Résolution droits : user individuel > service > direction > rôle > parent album

MediaItem
  ├── processing_status : pending | done | failed
  ├── sha256 (déduplication)
  ├── exif_data (JSON)
  └── tags (BelongsToMany via media_item_tag)
```

### serve() — comportement MediaItemController

- **Vidéo** : toujours `stream()` (HTTP Range)
- **Non-vidéo > seuil** (`media_stream_threshold_mb`, défaut 10 Mo) : `stream()`
- **Thumb** : toujours `readFile()`
- **Seuil 0** : streaming désactivé
- Paramètre administrable : Admin > Paramètres > Photothèque

---

*Les Bézots — Soullans (85) — github.com/jpbosse/pladigit — AGPL-3.0*
*CDC Pladigit v2.2 — Mars 2026 — ⚠ DOCUMENT CONFIDENTIEL*
