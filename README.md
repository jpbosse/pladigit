# Pladigit — Plateforme de Digitalisation Interne

> Alternative souveraine et open source aux outils Microsoft (Teams, SharePoint, OneDrive, Word, Excel, Planner)  
> Conçue pour les collectivités locales, associations et structures du secteur parapublic français.

![PHP](https://img.shields.io/badge/PHP-8.4-777BB4?logo=php&logoColor=white)
![Laravel](https://img.shields.io/badge/Laravel-11.x-FF2D20?logo=laravel&logoColor=white)
![Tests](https://img.shields.io/badge/Tests-759%20passed-brightgreen)
![Licence](https://img.shields.io/badge/Licence-AGPL--3.0-blue)
![CI](https://github.com/jpbosse/pladigit/actions/workflows/ci.yml/badge.svg?branch=main)

---

## Présentation

**Pladigit** est une plateforme SaaS multi-tenant destinée aux organisations publiques et parapubliques françaises souhaitant reprendre le contrôle de leurs outils numériques.

Chaque organisation dispose d'un espace **isolé, sécurisé et personnalisé**, hébergé en France, sans aucune dépendance à un cloud propriétaire.

### Pourquoi Pladigit ?

- **Souveraineté numérique** — hébergement en France, données hors UE impossibles, formats ouverts (ODF)
- **Open source AGPL-3.0** — code auditable, pas de vendor lock-in, déployable sur vos serveurs
- **Conçu pour les collectivités** — mairies, communautés de communes, associations, secteur parapublic
- **Zéro abonnement logiciel** — aucune licence Microsoft, aucun abonnement cloud

---

## Fonctionnalités livrées

### Socle (Phases 1–2)
- Authentification locale sécurisée — bcrypt coût 12, verrouillage de compte, politique de mot de passe configurable
- Double authentification TOTP (Google Authenticator, Aegis) avec codes de secours chiffrés AES-256
- Authentification LDAP / Active Directory (LDAPS obligatoire, circuit breaker, sync automatique)
- Architecture multi-tenant — base MySQL dédiée par organisation, isolation totale
- Gestion des rôles hiérarchiques — Admin, Président, DGS, Resp. Direction, Resp. Service, Agent
- Structure organisationnelle — Directions > Services > Agents
- Journalisation complète — audit trail avec export CSV/JSON, rétention configurable (RGPD)
- CI/CD GitHub Actions — PHPUnit, Pint PSR-12, PHPStan niveau 5, Composer audit

### Gestion de projet (Phase 3) — *remplace Microsoft Planner*
- Vues : Kanban par jalon, Gantt SVG avec drag & drop, Liste, Charge de travail, Agenda
- Tâches : récurrence, dépendances (Fin→Fin), commentaires, sous-tâches, assignation
- Budget : investissement / fonctionnement, co-financement, graphiques
- Risques, observations, parties prenantes, conduite du changement
- Export PDF élus, export iCal jalons, modèles de projet, duplication
- Droits : UserRole global + ProjectRole par projet (ADR-010, ADR-011)

### Photothèque NAS (Phases 4–5) — *remplace OneDrive Photos*
- Albums hiérarchiques, upload drag & drop, traitement asynchrone (queue)
- Déduplication SHA-256 cross-album, extraction EXIF, watermark configurable
- Partage par lien sécurisé temporaire, export ZIP, streaming range HTTP
- Synchronisation planifiée depuis NAS (local, SFTP, SMB)
- Droits par album, quotas de stockage stricts par organisation

### GED documentaire (Phase 6) — *remplace SharePoint*
- Arborescence de dossiers avec permissions fines (rôle, direction, service, utilisateur)
- Upload drag & drop, prévisualisation inline, versioning complet
- Synchronisation NAS → GED (détection nouveaux fichiers, mtime 5 min)
- Recherche plein texte (MySQL FULLTEXT — ADR-014)
- Gouvernance admin : transfert de propriété, purge, intégrité des fichiers
- Intégration GED ↔ Projets (ProjectGedLink)

### Collabora Online (Phase 7) — *remplace Microsoft Office*
- Édition collaborative des formats ODF (ODT, ODS, ODP) et Microsoft Office
- Protocole WOPI complet : CheckFileInfo, GetFile, PutFile, Lock/Unlock/RefreshLock/GetLock
- Token d'accès multi-tenant sécurisé — un seul aliasgroup Collabora pour tous les tenants
- Versioning automatique à chaque sauvegarde Collabora
- Administration : URL, TTL session, test de connexion depuis l'interface admin

---

## Remplacement des outils Microsoft

| Microsoft | Alternative Pladigit | Statut |
|-----------|---------------------|--------|
| Planner | Gestion de projet | ✅ Livré |
| OneDrive / Photos | Photothèque NAS | ✅ Livré |
| SharePoint | GED Pladigit | ✅ Livré |
| Word / Excel / PowerPoint | Collabora Online | ✅ Livré |
| Teams | Chat Pladigit | Planifié |
| Outlook Calendrier | Agenda global + CalDAV | Planifié |
| Forms | Sondages Pladigit | Planifié |

---

## Stack technique

| Technologie | Version | Rôle |
|-------------|---------|------|
| PHP | 8.4 | Langage backend |
| Laravel | 11.x | Framework MVC |
| Alpine.js | 3.x | Interactivité frontend |
| Livewire | 4.2 | Composants réactifs |
| MySQL | 8.0+ | SGBD multi-tenant |
| Redis | 7.x | Cache, queues, sessions |
| Tailwind CSS | 3.x | Framework CSS |
| Collabora Online | CODE 24.x | Éditeur bureautique WOPI |
| Docker | 24+ | Conteneurisation Collabora |
| PHPUnit | 11.x | Tests (759 tests / 1645 assertions) |
| PHPStan | 1.x | Analyse statique niveau 5 |

---

## Installation rapide

```bash
git clone https://github.com/jpbosse/pladigit.git && cd pladigit
composer install --no-dev --optimize-autoloader
npm install && npm run build
cp .env.example .env && php8.4 artisan key:generate
php8.4 artisan migrate --force
php8.4 artisan migrate --path=database/migrations/platform --force
php8.4 artisan tenant:create --name="Demo" --slug="demo" --email="admin@demo.pladigit.fr"
```

Pour l'installation complète : [INSTALL.md](INSTALL.md)

---

## Tests & qualité

```bash
php8.4 artisan test --exclude-group ldap,integration   # 759 tests
./vendor/bin/pint                                    # PSR-12
./vendor/bin/phpstan analyse --memory-limit=512M     # PHPStan niveau 5
composer audit                                       # 0 vulnérabilité
```

| Check | Résultat |
|-------|----------|
| PHPUnit 11 | **759 tests / 1645 assertions ✅** |
| Laravel Pint | PSR-12 ✅ |
| PHPStan niveau 5 | 0 erreur ✅ |
| Composer audit | 0 vulnérabilité ✅ |

---

## Documentation

| Document | Description |
|----------|-------------|
| [INSTALL.md](INSTALL.md) | Installation complète — prérequis, configuration, production |
| [CONTRIBUTING.md](CONTRIBUTING.md) | Comment contribuer |
| [SECURITY.md](SECURITY.md) | Signaler une vulnérabilité |
| [CHANGELOG.md](CHANGELOG.md) | Historique des versions |
| [docs/](docs/README.md) | Documentation technique complète |
| [docs/guides/guide-utilisateur-phototheque.md](docs/guides/guide-utilisateur-phototheque.md) | Guide utilisateur Photothèque |
| [docs/guides/guide-utilisateur-ged.md](docs/guides/guide-utilisateur-ged.md) | Guide utilisateur GED + Collabora |

---

## Roadmap

```
Oct 2025          Avr 2026               2027+
│                 │                      │
├─ Ph.1 Socle ✅  ├─ Ph.6 GED ✅        ├─ Chat (remplace Teams)
├─ Ph.2 Users ✅  ├─ Ph.7 Collabora ✅  ├─ Agenda global + CalDAV
├─ Ph.3 Projets ✅│                      ├─ Sondages
├─ Ph.4-5 Photo ✅│                      └─ Notifications push
```

Voir [ROADMAP.md](ROADMAP.md) pour le détail.

---

## Instance de démonstration

Une instance est disponible sur **[demo.pladigit.fr](https://demo.pladigit.fr)** à titre de démonstration.

**Mot de passe commun à tous les comptes : `demo1234`**

| Rôle | Nom | Email |
|------|-----|-------|
| Administrateur | Admin Démo | `admin@demo.pladigit.fr` |
| Président | Marie Dupont | `president@demo.pladigit.fr` |
| DGS | Jean-Pierre Martin | `dgs@demo.pladigit.fr` |
| Resp. Direction | Sophie Lambert | `resp.direction@demo.pladigit.fr` |
| Resp. Service | Thomas Bernard | `resp.service@demo.pladigit.fr` |
| Agent | Lucie Moreau | `user@demo.pladigit.fr` |

> ℹ️ Instance réinitialisée automatiquement toutes les 2 heures. Ne pas y déposer de données sensibles.

---

## Contribuer

Les contributions sont les bienvenues — code, documentation, traductions, retours d'usage.

Voir [CONTRIBUTING.md](CONTRIBUTING.md) pour démarrer.

Si ce projet vous est utile, vous pouvez soutenir son développement via [GitHub Sponsors](https://github.com/sponsors/jpbosse).

---

## Licence

- **Code source** — [AGPL-3.0](LICENSE)
- **Documentation** — [CC BY-SA 4.0](https://creativecommons.org/licenses/by-sa/4.0/)

---

## Auteur

**Jean-Pierre Bossé** — Soullans (Vendée, France)

- GitHub : [@jpbosse](https://github.com/jpbosse)
- Email : contact@pladigit.fr

---

*Pladigit — Reprendre le contrôle de votre numérique.*
