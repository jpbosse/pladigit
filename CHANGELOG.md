# Changelog — Pladigit

Toutes les modifications notables sont documentées ici.
Format : [Keep a Changelog](https://keepachangelog.com/fr/1.0.0/) — versioning [SemVer](https://semver.org/).

---

## [Unreleased]

---

## [0.8.3] — Mai 2026

### Ajouté

- **DataGrid — Import par chunks avec progression temps réel** — lecture CSV/XLSX/ODS par lots de 500 lignes, bulk insert, barre de progression Livewire, détection automatique du séparateur CSV
- **DataGrid — Visibilité initiale à l'import** — choix publique/restreinte/privée à l'étape 3 du wizard
- **DataGrid — Aperçu valeurs détectées** — affichage des valeurs distinctes BOOLEAN et SELECT dans l'étape 2 du wizard
- **DataGrid — Conversion de type avec confirmation** — conversion TEXT→DATE/NUMBER/etc avec bandeau d'avertissement et confirmation forcée
- **DataGrid — Droits par colonne** — masquer/afficher une colonne selon le service (ex : colonne Salaire → RH uniquement)
- **DataGrid — Page droits dédiée** — interface admin tenant par rôle/département/utilisateur, `can_export` contrôlé partout
- **DataGrid — Tri par défaut configurable** — par grille, depuis l'interface admin tenant
- **DataGrid — Création/modification de structure** — renommer et réordonner les colonnes depuis l'admin tenant
- **DataGrid — Onglet colonne configurable** — onglets Données/Complémentaires paramétrables par Super Admin et admin tenant
- **DataGrid — Détection de doublons à l'import** — algorithme Levenshtein adaptatif avec contexte prénom/ville, filtre abréviations, avertissement utilisateur
- **DataGrid — Export Excel/ODS avec filtres actifs** — export respectant les filtres en cours
- **DataGrid — Export PDF fiche et liste** — via controller dédié, 100 lignes max, impression
- **DataGrid — Organisation en dossiers** — sidebar collapse/expand avec drag & drop, CRUD dossiers
- **DataGrid — Ajout manuel de ligne** — bouton + popup, valeurs par défaut par colonne
- **DataGrid — Recherche globale multicolonne** — 1 champ → toutes colonnes texte visibles, compteur résultats/total
- **DataGrid — Pagination enrichie** — compteur français, navigation «‹›», sélecteur lignes/page, 5 pages contextuelles
- **DataGrid — Éditeur colonne** — labels Vrai/Faux, options SELECT, rendu CHEMIN_FICHIER dans la grille
- **DataGrid — Popup onglets** — Données / Complémentaires / Historique (EditRowModal + AddRowModal refactorisés)
- **DataGrid — Audit log** — édition et suppression de ligne tracées
- **DataGrid — Formatage colonnes** — date, booléen, téléphone, SIRET, email, nombre dans la grille
- **DataGrid — Filtres avancés** — par type, pagination 10/20/50, vues sauvegardées
- **Bloc 0 — Migrations fondations DataGrid** — colonnes `relation_*` / `computed_*`, tables `datagrid_views` / `datagrid_folders` / `datagrid_user_preferences`, enum RELATION/NOM_PERSONNE/CHEMIN_FICHIER, `DatagridNormalizationService`
- **Sécurité — TDE MySQL InnoDB** — procédure `docs/deploy/tde-mysql.md` + commande `pladigit:check-tde` avec option `--fix-tables` (ADR-041 §1.1)
- **ADR-043** — GED vs DataGrid : règle d'unicité de source de vérité pour les fichiers tableurs (décision différée à l'ouverture, sens unique GED→DataGrid, interface d'intégrité, contrôle nocturne)

### Modifié
- ADR-038 : référence croisée ADR-043 ajoutée
- ADR-039 : référence croisée ADR-043 ajoutée
- Plan de travail : blocs 2 et 3 marqués terminés, bloc 1 enrichi (1.11 à 1.15)

### Corrigé
- `can_export` dans EditRowModal + nullsafe corrigé
- Libellé Président → Président / Maire
- Normalisation booléen, code postal, SIRET, téléphone à l'import
- Fix permissions imports/datagrid
- Fix bouton Modifier admin

---

## [0.8.2] — Mai 2026

### Ajouté
- **Rapatriement des ressources statiques en local** — polices Google Fonts (Sora, DM Sans), Trix editor, Cropper.js hébergés localement ; zéro requête vers des CDN tiers, compatible CSP stricte (ADR-033)
- **En-têtes HTTP de sécurité Nginx** — CSP (Content Security Policy), HSTS, X-Frame-Options DENY, X-Content-Type-Options, Referrer-Policy strict-origin, Permissions-Policy, `server_tokens off` (ADR-033)
- **Mécanisme de mise à jour depuis le Super Admin** — `install/update.sh` exécuté en root via sudoers, log en temps réel dans l'interface, commande artisan `pladigit:update-status` (ADR-034)
- ADR-032 : rotation des clés AES — hors périmètre (décision documentée)
- ADR-035 : audit cross-tenant — hors périmètre (décision documentée)
- **Script `install.sh`** — PHP 8.3+ natif Ubuntu (dépôts universe), sans dépôt externe (PPA Ondrej/sury.org supprimé)
- **Wizard `install/index.php`** — versionné dans le dépôt git, plus de téléchargement depuis URL externe
- **`SUPER_ADMIN_ALLOWED_IPS`** auto-détecté depuis l'IP du client lors de l'installation (wizard)
- **Hook git post-merge** sur le VPS — synchronisation automatique de `install.sh` vers `public/`
- **`session_start()` corrigé** dans le wizard — suppression du doublon

### Modifié
- `composer.lock` — compatible PHP 8.3 (Symfony 7.x) et PHP 8.4
- CDC v2.3 → v2.4 — ajout sections Sécurité production et Installation & déploiement dans Niveau 1
- Documentation complète mise à jour — toutes les références PHP 8.4 → PHP 8.3+, PPA Ondrej supprimé

---

## [0.8.1] — Mai 2026

### Ajouté
- **ARGUMENTAIRE.md** — document de référence complet pour les échanges avec les collectivités, centres de gestion et syndicats informatiques : contexte du projet, 8 argumentaires thématiques (sécurité, comparatif Nextcloud, comparatif Microsoft, CDG, cas concret, DINUM, ANSSI/RGPD, DataGrid/DataPilot)
- **OBJECTIONS.md** — questions fréquentes et réponses honnêtes, destiné à la racine du dépôt GitHub pour les visiteurs
- **public/calculateur-roi-pladigit.html** — calculateur ROI interactif en HTML autonome, accessible depuis la page d'accueil et depuis la sidebar de navigation
- **Sidebar de navigation** sur la page d'accueil (`welcome.blade.php`) — remplace la barre de navigation horizontale par une sidebar fixe à gauche (240px) avec lien vers le calculateur ROI, surlignage actif selon le scroll, burger mobile avec overlay
- **DataGrid + DataPilot** ajoutés dans la grille des modules de la page d'accueil et dans la roadmap

### Modifié
- README.md : ajout des nouvelles entrées de documentation (ARGUMENTAIRE, OBJECTIONS, calculateur ROI), SGM dans les rôles, DataGrid/DataPilot dans le tableau de remplacement Microsoft et la roadmap, section auteur enrichie
- docs/README.md : ajout des nouvelles entrées
- docs/glossaire.md : compteur ADR corrigé (22 → 31), ajout des termes SGM, DGS, TOTP, RGPD, AIPD, SecNumCloud, ODF, CalDAV, CDG

---

## [0.8.0] — Avril 2026

### Ajouté
- **Script d'installation automatique** `install.sh` — une seule commande pour installer l'environnement complet (PHP 8.3+, MySQL 8, Redis, Nginx, Supervisor, Node.js 20)
- **Wizard d'installation web** 8 étapes — interface graphique standalone PHP, sans dépendance Laravel
- **Installation Collabora Online automatique** via Docker — script `install-collabora.sh` exécuté en root via sudoers, avec progression temps réel dans le wizard
- **Menu mise à jour / réinstallation** — détection d'installation existante avec 3 choix : Mettre à jour, Réinstaller, Annuler
- **Persistance des choix wizard** via `config.json` — navigation libre entre les étapes sans perte de données
- Récapitulatif avant lancement avec détection d'installation locale
- ADR-028 : script d'installation automatique
- ADR-029 : wizard d'installation web PHP standalone
- ADR-030 : Collabora Online installation optionnelle via wizard
- ADR-031 : script `install-collabora.sh` via sudoers

### Modifié
- Identité du projet : "Les Bézots" → **Pladigit**, `lesbezots.fr` → `pladigit.fr`
- `contact@pladigit.fr` comme adresse de contact officielle
- Protection réinstallation : confirmation texte → menu 3 choix interactif
- `GRANT ALL PRIVILEGES ON *.*` pour l'utilisateur MySQL (requis pour le multi-tenant)

### Technique
- Attente automatique du verrou `apt` au démarrage (gestion `unattended-upgrades`)
- Config Nginx : `SCRIPT_FILENAME $realpath_root/index.php` pour le routage Laravel correct

---

## [0.7.0] — Avril 2026

### Ajouté
- **Collabora Online (WOPI)** — édition collaborative ODT/ODS/ODP et formats Microsoft Office
- Protocole WOPI complet : CheckFileInfo, GetFile, PutFile, Lock/Unlock/RefreshLock/GetLock
- Token d'accès multi-organisation sécurisé (`{org_slug}:{raw_token}`)
- Versioning automatique à chaque sauvegarde depuis Collabora
- Administration Collabora : URL, durée de session, test de connexion (Admin > GED > Collabora)
- ADR-021 : `access_token_ttl` comme timestamp Unix absolu en millisecondes
- ADR-022 : Collabora intégré à GED, pas de module séparé
- ADR-023 : gestion des verrous WOPI
- ADR-024 : configuration Collabora par organisation

### Technique
- 759 tests PHPUnit / 1 645 assertions — tous verts
- PHPStan niveau 5 : 0 erreur
- CI/CD GitHub Actions : 4 vérifications vertes en continu

---

## [0.6.0] — Avril 2026

### Ajouté
- **GED documentaire** — arborescence, permissions fines, upload drag & drop
- Versioning complet des documents (historique, restauration, archivage horodaté)
- Synchronisation NAS → GED (détection nouveaux fichiers, toutes les 5 minutes, job asynchrone)
- Recherche plein texte MySQL FULLTEXT (ADR-025)
- Intégration GED ↔ Projets (`ProjectGedLink`)
- Gouvernance admin : transfert de propriété, purge, intégrité des fichiers
- Suppression récursive de dossiers avec confirmation et audit
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
- Import ZIP asynchrone (file de tâches)
- Synchronisation NAS planifiée (local, SFTP, SMB)
- Miniatures automatiques, extraction EXIF, tri par date de prise de vue
- Filigrane (watermark) configurable — GD natif PHP (ADR-018)
- Déduplication SHA-256 cross-album (ADR-013)
- Droits par album (rôles globaux + surcharges utilisateur)
- Modules activables par organisation via JSON `enabled_modules` (ADR-016)
- ADR-012 : stockage NAS, pas cloud
- ADR-014 : file de tâches (queue database)
- ADR-015 : streaming HTTP adaptatif (range HTTP)

---

## [0.3.0] — Mars 2026

### Ajouté
- **Gestion de projet** — Kanban par jalon, Gantt SVG drag & drop, Liste, Charge de travail, Agenda
- Tâches : récurrence, dépendances Fin→Fin, commentaires, sous-tâches
- Budget : investissement / fonctionnement, co-financement, graphiques
- Risques, observations, parties prenantes, conduite du changement
- Export PDF tableau de bord élus, export iCal jalons (RFC 5545)
- Modèles de projet réutilisables, duplication avec décalage de dates
- Historique d'activité (15 types d'actions)
- Droits hiérarchiques projets (ADR-011) — Responsables Direction/Service en lecture automatique
- Intégration Jitsi Meet souverain (`meet.numerique.gouv.fr`)
- ADR-008 à ADR-011

---

## [0.2.0] — Janvier–Mars 2026

### Ajouté
- **Authentification LDAP/Active Directory** — LDAPS obligatoire, circuit breaker, synchronisation automatique
- Gestion des rôles hiérarchiques (Admin, Président, DGS, Responsable Direction, Responsable Service, Agent)
- Structure organisationnelle Directions > Services > Agents
- Invitation par email (jeton valable 72 heures)
- Personnalisation visuelle par organisation (logo, couleurs)
- Tableau de bord avec statistiques et widgets par rôle
- Profil utilisateur complet
- ADR-005 à ADR-007

---

## [0.1.0] — Octobre–Décembre 2025

### Ajouté
- Socle technique Laravel 11 + PHP 8.3+ + MySQL 8 + Redis 7
- Architecture multi-organisation : base MySQL dédiée par organisation (ADR-002)
- Authentification locale — bcrypt coût 12, verrouillage de compte (ADR-004)
- Double authentification TOTP — Google Authenticator, Aegis, codes de secours chiffrés AES-256 (ADR-017)
- Politique de mot de passe configurable (longueur, complexité, expiration, historique)
- Journalisation complète — audit trail, export CSV/JSON, rétention RGPD configurable (12 mois par défaut, extensible à 36 mois)
- Super Admin isolé (identifiants `.env` uniquement, hors base de données)
- CI/CD GitHub Actions : PHPUnit, Pint PSR-12, PHPStan niveau 5, Composer audit (ADR-003)
- ADR-001 à ADR-004
