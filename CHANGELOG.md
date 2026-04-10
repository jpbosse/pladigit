# Changelog — Pladigit

Toutes les modifications notables sont documentées ici.  
Format : [Keep a Changelog](https://keepachangelog.com/fr/1.0.0/) — versioning [SemVer](https://semver.org/).

---

## [0.7.0] — Avril 2026

### Ajouté
- **Collabora Online (WOPI)** — édition collaborative ODT/ODS/ODP et formats Microsoft Office
- Protocole WOPI complet : CheckFileInfo, GetFile, PutFile, Lock/Unlock/RefreshLock/GetLock
- Token d'accès multi-tenant sécurisé (`{org_slug}:{raw_token}`)
- Versioning automatique à chaque sauvegarde depuis Collabora
- Administration Collabora : URL, TTL session, test de connexion (Admin > GED > Collabora)
- ADR-021 : `access_token_ttl` comme timestamp Unix absolu en millisecondes
- ADR-022 : Collabora intégré à GED, pas de module séparé

### Technique
- 759 tests PHPUnit / 1645 assertions — tous verts
- PHPStan niveau 5 : 0 erreur
- CI/CD GitHub Actions : 4 checks verts en continu

---

## [0.6.0] — Avril 2026

### Ajouté
- **GED documentaire** — arborescence, permissions fines, upload drag & drop
- Versioning complet des documents (historique, restauration, archivage horodaté)
- Synchronisation NAS → GED (détection nouveaux fichiers, mtime 5 min, job async)
- Recherche plein texte MySQL FULLTEXT (ADR-014)
- Intégration GED ↔ Projets (`ProjectGedLink`)
- Gouvernance admin : transfert de propriété, purge, intégrité des fichiers
- Suppression récursive de dossiers avec confirmation et audit
- Module gating `COLLABORA` intégré à `GED`
- ADR-020 : abstraction du stockage GED via `GedStorageInterface`

---

## [0.5.0] — Mars–Avril 2026

### Ajouté
- **Photothèque avancée** — tags libres, drag & drop photos et albums
- Partage par lien sécurisé temporaire avec expiration configurable
- Export ZIP d'album complet
- Recherche globale dans la photothèque
- Quota strict par organisation avec alertes à 80/90/95 %
- Barre de progression quota dans la sidebar
- Visionneuse lightbox et diaporama
- ADR-019 : enforcement quota strict

---

## [0.4.0] — Mars 2026

### Ajouté
- **Photothèque NAS** — albums hiérarchiques, upload multi-fichiers, traitement asynchrone
- Import ZIP asynchrone (job queue)
- Synchronisation NAS planifiée (local, SFTP, SMB)
- Miniatures automatiques, extraction EXIF, tri par date de prise de vue
- Watermark configurable (GD natif — ADR-018)
- Déduplication SHA-256 cross-album
- Droits par album (rôles globaux + overrides utilisateur)
- Modules activables par organisation via JSON `enabled_modules` (ADR-016)
- ADR-012 : stockage NAS, pas cloud
- ADR-013 : déduplication SHA-256
- ADR-014 : queue database
- ADR-015 : streaming range HTTP

---

## [0.3.0] — Mars 2026

### Ajouté
- **Gestion de projet** — Kanban par jalon, Gantt SVG drag & drop, Liste, Charge, Agenda
- Tâches : récurrence, dépendances Fin→Fin, commentaires, sous-tâches
- Budget : investissement / fonctionnement, co-financement, graphiques
- Risques, observations, parties prenantes, conduite du changement
- Export PDF tableau de bord élus, export iCal jalons
- Modèles de projet réutilisables, duplication avec décalage de dates
- Historique d'activité (15 types d'actions)
- Droits hiérarchiques projets (ADR-011) — Resp. Direction/Service en lecture automatique
- Intégration Jitsi Meet souverain (`meet.numerique.gouv.fr`)
- ADR-008 à ADR-011

---

## [0.2.0] — Janvier–Mars 2026

### Ajouté
- **Authentification LDAP/Active Directory** — LDAPS obligatoire, circuit breaker, sync automatique
- Gestion des rôles hiérarchiques (Admin, Président, DGS, Resp. Direction, Resp. Service, Agent)
- Structure organisationnelle Directions > Services > Agents
- Invitation par email (token 72h)
- Personnalisation visuelle par organisation (logo, couleurs — branding)
- Dashboard avec statistiques et widgets par rôle
- Profil utilisateur complet
- ADR-005 à ADR-007

---

## [0.1.0] — Octobre–Décembre 2025

### Ajouté
- Socle technique Laravel 11 + PHP 8.4 + MySQL 8 + Redis 7
- Architecture multi-tenant : base MySQL dédiée par organisation (ADR-002)
- Authentification locale — bcrypt coût 12, verrouillage de compte (ADR-004)
- Double authentification TOTP — Google Authenticator, Aegis, codes de secours chiffrés AES-256 (ADR-017)
- Politique de mot de passe configurable (longueur, complexité, expiration, historique)
- Journalisation complète — audit trail, export CSV/JSON, rétention RGPD configurable
- Super Admin isolé (credentials `.env` uniquement, hors base de données)
- CI/CD GitHub Actions : PHPUnit, Pint PSR-12, PHPStan niveau 5, Composer audit (ADR-003)
- ADR-001 à ADR-004
