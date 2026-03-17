# Pladigit — Plateforme de Digitalisation Interne

> Alternative souveraine et open source aux outils Microsoft (Teams, SharePoint, OneDrive, Word, Excel) — conçue pour les collectivités locales, associations et structures du secteur parapublic.

![PHP](https://img.shields.io/badge/PHP-8.2+-777BB4?logo=php&logoColor=white)
![Laravel](https://img.shields.io/badge/Laravel-11.x-FF2D20?logo=laravel&logoColor=white)
![Licence](https://img.shields.io/badge/Licence-AGPL--3.0-blue)
![CI](https://github.com/jpbosse/pladigit/actions/workflows/ci.yml/badge.svg?branch=develop)

---

## Table des matières

1. [Présentation](#présentation)
2. [Fonctionnalités](#fonctionnalités)
3. [Stack technique](#stack-technique)
4. [Installation](#installation)
5. [Configuration](#configuration)
6. [Utilisation](#utilisation)
7. [Tests](#tests)
8. [Phases du projet](#phases-du-projet)
9. [Contribution](#contribution)
10. [Licence](#licence)
11. [Auteur](#auteur)
12. [Liens utiles](#liens-utiles)

---

## Présentation

**Pladigit** est une plateforme SaaS multi-tenant destinée aux organisations publiques et parapubliques françaises souhaitant reprendre le contrôle de leurs outils numériques.

Chaque organisation cliente dispose d'un espace **isolé, sécurisé et personnalisé**, hébergé en France, sans aucune dépendance à un cloud propriétaire (AWS, Azure, GCP).

### Pourquoi Pladigit ?

- 🇫🇷 **Souveraineté numérique** — hébergement en France, données hors UE impossibles
- 🔓 **Open source** — code auditable, pas de vendor lock-in, formats ouverts (ODF)
- 🏛️ **Conçu pour les collectivités** — mairies, communautés de communes, associations
- 💶 **Offre gratuite** — auto-hébergeable sous licence AGPL-3.0

---

## Fonctionnalités

### Disponibles (Phases 1 & 2 — livrées)

- ✅ Authentification locale sécurisée (bcrypt coût 12, sessions, verrouillage compte)
- ✅ Double authentification TOTP (Google Authenticator, Aegis) avec codes de secours
- ✅ Authentification LDAP/Active Directory (LDAPS obligatoire, chiffrement AES-256)
- ✅ Architecture multi-tenant — base MySQL dédiée par organisation
- ✅ Gestion des rôles : Admin, Président, DGS, Resp. Direction, Resp. Service, Agent
- ✅ Structure organisationnelle : Directions > Services > Agents
- ✅ Profil utilisateur avec gestion 2FA et changement de mot de passe
- ✅ Invitation par email avec token d'activation (72h)
- ✅ Personnalisation visuelle par organisation (logo, couleurs, nom)
- ✅ Photothèque connectée au NAS (galerie, upload, EXIF, sync automatique, watermark, queue)
- ✅ Gestion de projet — Kanban, Gantt, tâches, jalons, agenda (Phase 5bis)
- ✅ CI/CD GitHub Actions — 399 tests / ~900 assertions

### Planifiées / En cours (Phases 3–13)

| Module | Phase | Période | Statut |
|--------|-------|---------|--------|
| Photothèque avancée (albums, EXIF, ZIP, partage) | 3–4 | Avr–Sep 2026 | 🔧 En cours |
| **Gestion de projet** — Kanban, Gantt, agenda | **5bis** | **Oct–Déc 2026** | **📋 CDC prêt** |
| GED & recherche plein texte | 5 | Jan–Mar 2027 | 📋 Planifié |
| Éditeur Collabora Online (WOPI) | 6 | Avr–Jun 2027 | 📋 Planifié |
| ERP DataGrid no-code | 7 | Jul–Sep 2027 | 📋 Planifié |
| Chat temps réel (WebSocket Soketi) | 8 | Oct–Déc 2027 | 📋 Planifié |
| Fil d'actualités RSS | 9 | Jan–Mar 2028 | 📋 Planifié |
| Sondages & questionnaires | 10 | Avr–Jun 2028 | 📋 Planifié |
| Production & audit sécurité | 11 | Jul–Sep 2028 | 📋 Planifié |
| Publication open source & communauté | 12 | Oct 2028–Sep 2029 | 📋 Planifié |

---

## Stack technique

| Technologie | Version | Rôle |
|-------------|---------|------|
| PHP | 8.2+ | Langage backend |
| Laravel | 11.x | Framework MVC |
| Livewire | 3.x | Composants dynamiques sans API REST |
| Alpine.js | 3.x | Interactions JS légères |
| MySQL | 8.0+ | Base dédiée par tenant |
| Redis | 7.x | Cache, queues, sessions |
| Tailwind CSS | 3.x | Framework CSS utilitaire |
| Soketi | 1.x | WebSocket auto-hébergé (Phase 9) |
| Collabora Online | CODE 24.x | Éditeur bureautique open source (Phase 6) |
| Docker | 24+ | Conteneurisation Collabora Online |

---

## Installation

### Prérequis

- PHP 8.2+ avec extensions : `pdo_mysql`, `redis`, `ssh2`, `gd`, `exif`
- MySQL 8.0+
- Redis 7+
- Node.js 18+ et npm
- Composer 2+

### Étapes

```bash
# 1. Cloner le dépôt
git clone https://github.com/jpbosse/pladigit.git
cd pladigit

# 2. Installer les dépendances PHP
composer install

# 3. Installer les dépendances JS et compiler les assets
npm install && npm run build

# 4. Configurer l'environnement
cp .env.example .env
php artisan key:generate

# 5. Créer les bases de données MySQL
mysql -u root -p < database/sql/create_databases.sql

# 6. Lancer les migrations plateforme
php artisan migrate --database=mysql --path=database/migrations/platform

# 7. Créer un tenant de démonstration
php artisan tenant:create --name="Demo" --slug="demo" --email="admin@demo.pladigit.fr"
```

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

# Chiffrement AES-256 (secrets LDAP, TOTP)
APP_KEY=base64:...

# bcrypt
BCRYPT_ROUNDS=12
```

### Infrastructure de production

```
VPS Ubuntu 24 LTS
├── Nginx (reverse proxy)
├── PHP-FPM 8.2
├── MySQL 8 (bases dédiées par tenant)
├── Redis 7
├── Soketi (WebSocket — Phase 9)
└── Docker → Collabora Online CODE (Phase 6)
```

---

## Utilisation

### Lancer en développement

```bash
php artisan serve
npm run dev
```

### Synchronisation NAS

```bash
# Sync légère (nouveaux fichiers par date de modification)
php artisan nas:sync

# Sync complète (vérification SHA-256)
php artisan nas:sync --deep

# Un seul tenant
php artisan nas:sync --tenant=demo
```

### Accès Super Admin

```
https://{votre-domaine}/super-admin
```

Identifiants définis dans `.env` via `SUPER_ADMIN_EMAIL`.

### Accès organisation

```
https://{slug}.pladigit.fr
```

---

## Tests

```bash
# Lancer tous les tests
php artisan test

# Avec détail
php artisan test --no-coverage

# Un fichier spécifique
php artisan test tests/Feature/Auth/LoginTest.php
```

### État CI/CD (Mars 2026)

| Check | Outil | Résultat |
|-------|-------|----------|
| Tests | PHPUnit 11 | 399 tests / ~900 assertions ✅ |
| Style | Laravel Pint | PSR-12 ✅ |
| Types | PHPStan niveau 5 | ✅ |
| Sécurité | Composer audit | 0 vulnérabilité ✅ |

---

## Phases du projet

```
Oct 2025 ──────────────────────────────────────────────── Sep 2029
│ Ph.1  │ Ph.2  │ Ph.3-4   │ Ph.5bis │ Ph.5  │ ... │ Ph.12 │
│ Socle │ Auth  │ Photothèq│ Projets │ GED   │     │ Prod  │
│  ✅   │  ✅   │   🔧     │   📋    │  📋   │     │  📋   │
```

Voir le [Cahier des Charges complet](docs/) pour le détail des 13 phases.

---

## Contribution

Le code sera publié en open source à partir de la **Phase 12 (octobre 2028)**.

En attendant, les contributions sont acceptées via :

1. **Issues GitHub** — signalement de bugs ou suggestions
2. **Pull Requests** — sur la branche `develop` uniquement
3. **Discussion** — via les Issues pour toute proposition majeure

### Standards de code

```bash
# Formatage automatique (PSR-12)
./vendor/bin/pint

# Analyse statique
./vendor/bin/phpstan analyse

# Tests avant tout commit
php artisan test
```

Les commits suivent la convention **Conventional Commits** :
`feat:`, `fix:`, `chore:`, `style:`, `docs:`, `test:`

---

## Licence

- **Modules critiques** (authentification, multi-tenant, GED) — [AGPL-3.0](LICENSE)
- **Composants utilitaires** — MIT

> Le code source est fourni gracieusement à toutes les organisations, y compris l'offre Communautaire à 0 €. Les interventions humaines (installation, formation, développement sur mesure) relèvent des offres Assistance ou Enterprise.

---

## Auteur

**Jean-Pierre Bossé** — [Les Bézots](https://lesbezots.fr), Soullans (Vendée, France)

- GitHub : [@jpbosse](https://github.com/jpbosse)
- Email : jpbosse1@gmail.com

---

## Liens utiles

- 📋 [Cahier des Charges (CDC v1.4)](docs/)
- 🏗️ [ADR — Décisions d'architecture](docs/adr/)
- 🐛 [Issues & suggestions](https://github.com/jpbosse/pladigit/issues)
- 🌐 Site web : *à venir*

---

*Pladigit — Reprendre le contrôle de votre numérique.*
