# Pladigit — Plateforme de Digitalisation Interne

> Alternative souveraine et open source aux outils Microsoft (Teams, SharePoint, OneDrive, Word, Excel, Planner) — conçue pour les collectivités locales, associations et structures du secteur parapublic.

![PHP](https://img.shields.io/badge/PHP-8.4-777BB4?logo=php&logoColor=white)
![Laravel](https://img.shields.io/badge/Laravel-11.x-FF2D20?logo=laravel&logoColor=white)
![Alpine.js](https://img.shields.io/badge/Alpine.js-3.x-8BC0D0?logo=alpine.js&logoColor=white)
![Tests](https://img.shields.io/badge/Tests-757%20passed-brightgreen)
![Licence](https://img.shields.io/badge/Licence-AGPL--3.0-blue)
![CI](https://github.com/jpbosse/pladigit/actions/workflows/ci.yml/badge.svg?branch=main)

---

## Table des matières

1. [Présentation](#présentation)
2. [Fonctionnalités](#fonctionnalités)
3. [Stack technique](#stack-technique)
4. [Installation](#installation)
5. [Configuration](#configuration)
6. [Tests & qualité](#tests--qualité)
7. [Phases du projet](#phases-du-projet)
8. [Licence](#licence)
9. [Auteur](#auteur)

---

## Présentation

**Pladigit** est une plateforme SaaS multi-tenant destinée aux organisations publiques et parapubliques françaises souhaitant reprendre le contrôle de leurs outils numériques.

Chaque organisation cliente dispose d'un espace **isolé, sécurisé et personnalisé**, hébergé en France, sans aucune dépendance à un cloud propriétaire (AWS, Azure, GCP).

### Pourquoi Pladigit ?

- **Souveraineté numérique** — hébergement en France, données hors UE impossibles
- **Open source** — code auditable, pas de vendor lock-in, formats ouverts (ODF)
- **Conçu pour les collectivités** — mairies, communautés de communes, associations
- **Offre gratuite** — auto-hébergeable sous licence AGPL-3.0
- **Développement accéléré par IA** — planning ramené de 48 à 26 mois

### Les 3 offres

| Communautaire | Assistance | Enterprise |
|---------------|------------|------------|
| **0 € / mois** | **150 € / mois** | **Sur devis** |
| Tous les modules, LDAP, 2FA, multi-tenant, auto-hébergé | + Support, formation, mises à jour | + Hébergement dédié, SLA, développements sur mesure |

---

## Fonctionnalités

### Livrées (Phases 1 à 7 — Octobre 2025 → Avril 2026)

#### Phase 1 — Socle technique
- Authentification locale sécurisée (bcrypt coût 12, sessions, verrouillage compte)
- Double authentification TOTP (Google Authenticator, Aegis) avec codes de secours
- Architecture multi-tenant — base MySQL dédiée par organisation
- CI/CD GitHub Actions (PHPUnit, Pint, PHPStan niveau 5, Composer audit)

#### Phase 2 — Organisations & utilisateurs
- Authentification LDAP/Active Directory (LDAPS obligatoire, chiffrement AES-256, circuit breaker)
- Gestion des rôles : Admin, Président, DGS, Resp. Direction, Resp. Service, Agent
- Structure organisationnelle hiérarchique : Directions > Services > Agents
- Profil utilisateur, invitation par email, personnalisation visuelle (logo, couleurs)
- Dashboard avec statistiques et widgets par rôle

#### Phase 3 — Gestion de projet *(remplace Microsoft Planner)*
- Vues : Kanban par jalon, Gantt SVG avec drag & drop, Liste, Charge de travail
- Tâches : récurrence, dépendances, commentaires, dates, assignation
- Budget : investissement / fonctionnement, co-financement, graphiques
- Risques, observations, parties prenantes, conduite du changement
- Export PDF, modèles de projet, duplication
- Droits : double couche UserRole (global) + ProjectRole (par projet)

#### Phases 4 & 5 — Photothèque NAS *(remplace OneDrive/SharePoint photos)*
- Galerie d'albums avec navigation par dossier et vue mosaïque
- Upload drag & drop, traitement asynchrone (job queue)
- Déduplication SHA-256, extraction EXIF, watermark configurable
- Partage par lien sécurisé, export ZIP, streaming range HTTP
- Synchronisation planifiée depuis un NAS (local, SFTP ou SMB)
- Droits par album (rôles globaux + overrides utilisateur)
- Quotas de stockage stricts par organisation

#### Phases 6 & 7 — GED + Collabora Online *(remplace SharePoint + Microsoft Office)*

La GED et l'édition collaborative forment un module cohérent livré ensemble.

**GED documentaire :**
- Arborescence de dossiers avec permissions fines (rôle, direction, service, utilisateur)
- Upload drag & drop, prévisualisation inline (PDF, images)
- Versioning complet — historique, restauration, archivage horodaté
- Synchronisation NAS → GED (détection nouveaux fichiers, correction permissions)
- Suppression récursive de dossiers avec confirmation et audit
- Recherche plein texte
- Gouvernance admin : transfert de propriété, purge, intégrité des fichiers

**Collabora Online (WOPI) :**
- Édition collaborative des formats ODF (ODT, ODS, ODP) et Microsoft Office
- Protocole WOPI complet : CheckFileInfo, GetFile, PutFile, Lock/Unlock/RefreshLock/GetLock
- Token d'accès multi-tenant (`{org_slug}:{raw_token}`) — un seul aliasgroup Collabora pour tous les tenants
- Versioning automatique à chaque sauvegarde
- Administration : URL, URL WOPI, TTL session, test de connexion

### Planifiées (Phases 8–13 — Été 2026 → Novembre 2027)

| Phase | Période | Module |
|-------|---------|--------|
| 8 | Mai–Jun 2026 | ERP DataGrid — tables no-code, audit trail, export |
| 9 | Jul–Aoû 2026 | Chat temps réel — canaux, 1:1, WebSocket |
| 10 | Sep–Oct 2026 | Agenda global — CalDAV, récurrence, export iCal |
| 11 | Nov 2026 | Fil RSS + Sondages & questionnaires |
| 12 | Jan–Mar 2027 | Production — VPS, monitoring, PRA, audit sécurité |
| 13 | Sep–Nov 2027 | Open source — publication, communauté, documentation |

### Remplacement des outils Microsoft

| Microsoft | Alternative Pladigit | Statut |
|-----------|---------------------|--------|
| Planner | Gestion de projet | ✅ Livré (Phase 3) |
| OneDrive / Photos | Photothèque NAS | ✅ Livré (Phases 4–5) |
| SharePoint | GED Pladigit | ✅ Livré (Phase 6) |
| Word / Excel / PowerPoint | Collabora Online | ✅ Livré (Phase 7) |
| Teams | Chat Pladigit | Planifié (Phase 9) |
| Outlook Calendrier | Agenda + iCal | Planifié (Phase 10) |
| Forms | Sondages Pladigit | Planifié (Phase 11) |

---

## Stack technique

| Technologie | Version | Rôle |
|-------------|---------|------|
| PHP | 8.4 | Langage backend principal |
| Laravel | 11.x | Framework MVC — routing, Eloquent ORM, Artisan, jobs |
| Alpine.js | 3.x | Interactivité frontend (modales, drag & drop, upload) |
| MySQL | 8.0+ | SGBD — base plateforme + base dédiée par tenant |
| Redis | 7.x | Cache, files de tâches (queue), sessions |
| Tailwind CSS | 3.x | Framework CSS utilitaire |
| DomPDF | 3.1 | Génération PDF (rapports, exports) |
| Collabora Online | CODE 24.x | Éditeur bureautique open source — protocole WOPI |
| Docker | 24+ | Conteneurisation Collabora Online |
| PHPUnit | 11.x | Tests unitaires et fonctionnels (757 tests) |
| PHPStan | 1.x | Analyse statique niveau 5 |
| Laravel Pint | 1.x | Formatage PSR-12 |

---

## Installation

### Prérequis

- PHP 8.4 avec extensions : `pdo_mysql`, `redis`, `gd`, `exif`, `intl`, `mbstring`
- MySQL 8.0+
- Redis 7+
- Node.js 20+ et npm
- Composer 2+
- Docker (pour Collabora Online)

### Étapes

```bash
# 1. Cloner le dépôt
git clone https://github.com/jpbosse/pladigit.git
cd pladigit

# 2. Dépendances PHP
composer install --no-dev --optimize-autoloader

# 3. Dépendances JS et compilation des assets
npm install && npm run build

# 4. Environnement
cp .env.example .env
php artisan key:generate

# 5. Migrations plateforme
php artisan migrate --database=mysql --path=database/migrations/platform

# 6. Créer un tenant de démonstration
php artisan tenant:create --name="Demo" --slug="demo" --email="admin@demo.pladigit.fr"

# 7. Collabora Online (optionnel — module COLLABORA)
docker compose up -d collabora
```

Pour plus de détails, voir [INSTALL.md](INSTALL.md).

---

## Configuration

### Variables d'environnement principales (`.env`)

```env
APP_NAME=Pladigit
APP_ENV=production
APP_URL=https://pladigit.fr

# Base centrale (organisations)
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=pladigit_platform

# Redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379

# Super Admin (hors base de données)
SUPER_ADMIN_EMAIL=superadmin@pladigit.fr

# bcrypt (12 en production, 4 en test)
BCRYPT_ROUNDS=12

# Collabora Online
COLLABORA_URL=https://collabora.mairie.fr
WOPI_URL=https://pladigit.fr
COLLABORA_TOKEN_TTL=14400  # secondes (4h)
```

### Infrastructure de production recommandée

```
VPS Ubuntu 24 LTS (8 vCPU, 16 Go RAM, 200 Go SSD) ~600 €/an
├── Nginx (reverse proxy + SSL Let's Encrypt)
├── PHP-FPM 8.4
├── MySQL 8 (bases dédiées par tenant)
├── Redis 7
└── Docker → Collabora Online CODE
```

---

## Tests & qualité

```bash
# Tests PHPUnit (hors groupes ldap et integration)
php artisan test --exclude-group ldap,integration

# Formatage PSR-12
./vendor/bin/pint

# Analyse statique niveau 5
./vendor/bin/phpstan analyse --memory-limit=512M

# Audit des dépendances
composer audit
```

### État CI/CD — Avril 2026 (Phases 1 à 7 livrées)

| Check | Outil | Résultat |
|-------|-------|----------|
| Tests | PHPUnit 11 | **757 tests / 1640 assertions ✅** |
| Style | Laravel Pint | PSR-12 ✅ |
| Types | PHPStan niveau 5 | 0 erreur ✅ |
| Sécurité | Composer audit | 0 vulnérabilité ✅ |

---

## Phases du projet

```
Oct 2025 ────────────────────────────────────────── Nov 2027
│ Ph.1 │ Ph.2 │ Ph.3 │ Ph.4–5 │ Ph.6–7 │ Ph.8–13 │
│Socle │Users │Projets│Phototh.│GED+Col.│  Suite  │
│  ✅  │  ✅  │  ✅  │  ✅   │   ✅   │   📋   │
```

Planning : **26 mois** (Octobre 2025 → Novembre 2027) — accéléré par l'intégration d'un assistant IA dans le développement (planning initial : 48 mois).

Voir le [Cahier des Charges v2.3](docs/CDC_Pladigit_v2.2.md) pour le détail complet.

---

## Licence

- **Code source** — [AGPL-3.0](LICENSE)

> Le code source est fourni gratuitement. Les interventions humaines (installation, formation, développement sur mesure) relèvent des offres Assistance ou Enterprise.

---

## Auteur

**Jean-Pierre Bossé** — [Les Bézots](https://lesbezots.fr), Soullans (Vendée, France)

- GitHub : [@jpbosse](https://github.com/jpbosse)
- Email : jpbosse1@gmail.com

---

*Pladigit — Reprendre le contrôle de votre numérique.*
