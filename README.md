# Pladigit — Plateforme de Digitalisation Interne

> Alternative souveraine et open source aux outils Microsoft (Teams, SharePoint, OneDrive, Word, Excel, Planner) — conçue pour les collectivités locales, associations et structures du secteur parapublic.

![PHP](https://img.shields.io/badge/PHP-8.4-777BB4?logo=php&logoColor=white)
![Laravel](https://img.shields.io/badge/Laravel-11.x-FF2D20?logo=laravel&logoColor=white)
![Livewire](https://img.shields.io/badge/Livewire-4.2-4E56A6?logo=livewire&logoColor=white)
![Tests](https://img.shields.io/badge/Tests-512%20passed-brightgreen)
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
8. [Contribution](#contribution)
9. [Licence](#licence)
10. [Auteur](#auteur)

---

## Présentation

**Pladigit** est une plateforme SaaS multi-tenant destinée aux organisations publiques et parapubliques françaises souhaitant reprendre le contrôle de leurs outils numériques.

Chaque organisation cliente dispose d'un espace **isolé, sécurisé et personnalisé**, hébergé en France, sans aucune dépendance à un cloud propriétaire (AWS, Azure, GCP).

### Pourquoi Pladigit ?

- 🇫🇷 **Souveraineté numérique** — hébergement en France, données hors UE impossibles
- 🔓 **Open source** — code auditable, pas de vendor lock-in, formats ouverts (ODF)
- 🏛️ **Conçu pour les collectivités** — mairies, communautés de communes, associations
- 💶 **Offre gratuite** — auto-hébergeable sous licence AGPL-3.0
- 🤖 **Développement accéléré par IA** — planning ramené de 48 à 26 mois

### Les 3 offres

| 🌍 Communautaire | 🤝 Assistance | 🏢 Enterprise |
|-----------------|---------------|---------------|
| **0 € / mois** | **150 € / mois** | **Sur devis** |
| Tous les modules, LDAP, 2FA, multi-tenant, auto-hébergé | + Support, formation, mises à jour | + Hébergement dédié, SLA, développements sur mesure |

---

## Fonctionnalités

### ✅ Livrées (Phases 1, 2 et 3 — Mars 2026)

**Phase 1 — Socle technique**
- Authentification locale sécurisée (bcrypt coût 12, sessions, verrouillage compte)
- Double authentification TOTP (Google Authenticator, Aegis) avec codes de secours
- Architecture multi-tenant — base MySQL dédiée par organisation
- CI/CD GitHub Actions (PHPUnit, Pint, PHPStan niveau 5, Composer audit)

**Phase 2 — Organisations & utilisateurs**
- Authentification LDAP/Active Directory (LDAPS obligatoire, chiffrement AES-256, circuit breaker)
- Gestion des rôles : Admin, Président, DGS, Resp. Direction, Resp. Service, Agent
- Structure organisationnelle hiérarchique : Directions > Services > Agents
- Profil utilisateur, invitation par email, personnalisation visuelle (logo, couleurs)
- Dashboard avec statistiques et widgets par rôle

**Phase 3 — Gestion de projet** *(remplace Microsoft Planner)*
- Vues : Kanban par jalon, Gantt SVG avec drag & drop, Liste, Charge de travail
- Tâches : récurrence, dépendances, commentaires, dates, assignation
- Jalons & phases de projet
- Budget : investissement / fonctionnement, co-financement, graphiques (donut + histogramme)
- Risques, observations, parties prenantes, conduite du changement
- Agenda projet avec export iCal
- Documents attachés (fichiers & liens URL) par projet, jalon ou tâche
- Notifications, historique des modifications
- Export PDF (rapport élus), modèles de projet, duplication
- Droits : double couche UserRole (global) + ProjectRole (local) — ADR-008 à ADR-011

### 📋 Planifiées (Phases 4–13 — Avr 2026 → Nov 2027)

| Phase | Période | Module |
|-------|---------|--------|
| 4 | Avr–Mai 2026 | Photothèque NAS — galerie, upload, droits, watermark, albums |
| 5 | Jun–Jul 2026 | Photothèque avancée — EXIF, partage, export ZIP |
| 6 | Aoû–Sep 2026 | GED documentaire — arborescence, versionning, recherche plein texte |
| 7 | Oct–Nov 2026 | Collabora Online — édition collaborative WOPI (ODT, ODS, ODP) |
| 8 | Déc 2026–Jan 2027 | ERP DataGrid — tables no-code, audit trail, export |
| 9 | Fév–Mar 2027 | Chat temps réel — canaux, 1:1, WebSocket Soketi |
| 10 | Avr 2027 | Agenda global — CalDAV, récurrence, export iCal |
| 11 | Mai–Jun 2027 | Fil RSS + Sondages & questionnaires |
| 12 | Jul–Aoû 2027 | Production — VPS, monitoring, PRA, audit sécurité |
| 13 | Sep–Nov 2027 | Open source — publication, communauté, documentation |

### Remplacement des outils Microsoft

| Microsoft | Alternative Pladigit | Statut |
|-----------|---------------------|--------|
| Planner | Gestion de projet | ✅ Livré (Phase 3) |
| SharePoint / OneDrive | GED Pladigit | 📋 Phase 6 |
| Word / Excel / PowerPoint | Collabora Online | 📋 Phase 7 |
| Teams | Chat Pladigit | 📋 Phase 9 |
| Outlook Calendrier | Agenda Pladigit + iCal | 📋 Phase 10 |
| Forms | Sondages Pladigit | 📋 Phase 11 |

---

## Stack technique

| Technologie | Version | Rôle |
|-------------|---------|------|
| PHP | 8.4 | Langage backend principal |
| Laravel | 11.x | Framework MVC — routing, Eloquent ORM, Artisan |
| Livewire | 4.2 | Composants dynamiques (interfaces réactives) |
| Alpine.js | 3.x | Interactions JS légères côté client |
| MySQL | 8.0+ | SGBD — base dédiée par tenant |
| Redis | 7.x | Cache, files de tâches, sessions |
| Tailwind CSS | 3.x | Framework CSS utilitaire |
| DomPDF | 3.1 | Génération PDF (rapports, exports) |
| Soketi | 1.x | WebSocket auto-hébergé (Phase 9) |
| Collabora Online | CODE 24.x | Éditeur bureautique open source WOPI (Phase 7) |
| Docker | 24+ | Conteneurisation Collabora Online |
| PHPUnit | 11.x | Tests unitaires et fonctionnels |
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

# 5. Bases de données MySQL
mysql -u root -p < database/sql/create_databases.sql

# 6. Migrations plateforme
php artisan migrate --database=mysql --path=database/migrations/platform

# 7. Créer un tenant de démonstration
php artisan tenant:create --name="Demo" --slug="demo" --email="admin@demo.pladigit.fr"
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

# Chiffrement AES-256 (secrets LDAP, TOTP)
APP_KEY=base64:...

# bcrypt (12 en production, 4 en test)
BCRYPT_ROUNDS=12
```

### Infrastructure de production recommandée

```
VPS Ubuntu 24 LTS (8 vCPU, 16 Go RAM, 200 Go SSD) ~600 €/an
├── Nginx (reverse proxy + SSL Let's Encrypt)
├── PHP-FPM 8.4
├── MySQL 8 (bases dédiées par tenant)
├── Redis 7
├── Soketi (WebSocket — Phase 9)
└── Docker → Collabora Online CODE (Phase 7)
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

### État CI/CD — Mars 2026 (Phases 1, 2 et 3 livrées)

| Check | Outil | Résultat |
|-------|-------|----------|
| Tests | PHPUnit 11 | **512 tests / 1087 assertions ✅** |
| Style | Laravel Pint | PSR-12 ✅ |
| Types | PHPStan niveau 5 | 0 erreur ✅ |
| Sécurité | Composer audit | 0 vulnérabilité ✅ |

---

## Phases du projet

```
Oct 2025 ──────────────────────────────────── Nov 2027
│ Ph.1 │ Ph.2 │ Ph.3 │ Ph.4-5 │ Ph.6 │ ... │ Ph.13 │
│Socle │Users │Projets│Phototh.│  GED │     │  OSS  │
│  ✅  │  ✅  │  ✅  │   📋   │  📋  │     │  📋   │
```

Planning : **26 mois** (Octobre 2025 → Novembre 2027) — accéléré par l'intégration d'un assistant IA dans le développement (planning initial : 48 mois).

Voir le [Cahier des Charges v2.1](docs/) pour le détail complet des 13 phases et leurs annexes.

---

## Contribution

Le code sera publié en open source à partir de la **Phase 13 (septembre 2027)**.

En attendant, les contributions sont acceptées via :

1. **Issues GitHub** — signalement de bugs ou suggestions
2. **Pull Requests** — sur la branche `develop` uniquement
3. **Discussions** — pour toute proposition d'architecture majeure

### Standards de code

```bash
./vendor/bin/pint                                      # Formatage PSR-12
./vendor/bin/phpstan analyse --memory-limit=512M       # Analyse statique
php artisan test --exclude-group ldap,integration      # Tests
```

Les commits suivent la convention **Conventional Commits** :
`feat:`, `fix:`, `chore:`, `style:`, `docs:`, `test:`

---

## Licence

- **Modules critiques** (authentification, multi-tenant, GED) — [AGPL-3.0](LICENSE)
- **Composants utilitaires** — MIT

> Le code source est fourni gratuitement. Les interventions humaines (installation, formation, développement sur mesure) relèvent des offres Assistance ou Enterprise.

---

## Auteur

**Jean-Pierre Bossé** — [Les Bézots](https://lesbezots.fr), Soullans (Vendée, France)

- GitHub : [@jpbosse](https://github.com/jpbosse)
- Email : jpbosse1@gmail.com

---

*Pladigit — Reprendre le contrôle de votre numérique.*
