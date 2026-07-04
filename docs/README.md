# Documentation — Pladigit

Bienvenue dans la documentation technique et fonctionnelle de Pladigit.

---

## Démarrage rapide

| Je veux… | Document |
|----------|----------|
| Installer Pladigit (non-informaticien) | [Guide illustré avec captures d'écran](GUIDE-INSTALLATION.html) |
| Installer Pladigit sur un serveur existant | [INSTALL.md](../INSTALL.md) |
| Calculer ce que je vais économiser | [Calculateur ROI](../public/calculateur-roi-pladigit.html) |
| Comprendre le projet et son contexte | [ARGUMENTAIRE.md](../ARGUMENTAIRE.md) |
| Répondre aux questions fréquentes | [OBJECTIONS.md](../OBJECTIONS.md) |
| Connaître l'avancement et les prochaines étapes | [ROADMAP.md](../ROADMAP.md) |
| Lire le cahier des charges complet | [CDC_Pladigit_v2.4.md](01-product/CDC_Pladigit_v2.4.md) |
| Comprendre la vision long terme | [vision-2030.md](01-product/vision-2030.md) |
| Trouver la bonne annexe technique | [index-annexes.md](02-architecture/annexes/index-annexes.md) |
| Comprendre un terme technique ou métier | [glossaire.md](glossaire.md) |
| Comprendre l'architecture multi-organisation | [Annexe B — Multi-tenant](02-architecture/annexes/annexe-b-multitenant.md) |
| Configurer les droits et rôles | [Annexe C — Matrice des droits](02-architecture/annexes/annexe-c-matrice-droits.md) |
| Comprendre l'installeur (spécification complète) | [installation.md](02-architecture/installation.md) |
| Comprendre pourquoi telle décision technique | [docs/04-adr/](04-adr/) |
| Mettre en production | [checklist-mise-en-prod.md](05-exploitation/checklist-mise-en-prod.md) |
| Former les utilisateurs | [docs/03-guides/](03-guides/) |
| Dérouler les tests fonctionnels | [checklist-tests-pladigit.md](checklist-tests-pladigit.md) |

---

## Structure complète

```
docs/
├── README.md                        ← ce fichier
├── glossaire.md                     ← glossaire des termes techniques et métier
├── plan-de-travail.md               ← plan de travail courant
├── checklist-tests-pladigit.md      ← checklist de tests fonctionnels
├── GUIDE-INSTALLATION.html          ← guide illustré pas-à-pas (s'ouvre dans le navigateur)
│
├── 01-product/                      ← Produit : cahier des charges, vision, roadmap
│   ├── CDC_Pladigit_v2.4.md         ← cahier des charges complet v2.4
│   ├── ROADMAP.md                   ← renvoi vers la ROADMAP canonique (racine du projet)
│   ├── charte-choix-technologiques.md ← critères de choix des technologies
│   ├── module-system.md             ← système de modules activables
│   └── vision-2030.md               ← vision long terme du projet
│
├── 02-architecture/                 ← Architecture, installation, exploitation technique
│   ├── installation.md              ← spécification complète de l'installeur (référence)
│   ├── guide-installation-prerequis.md ← prérequis détaillés
│   ├── guide-maintenance.md         ← guide de maintenance courante
│   ├── annexes/                     ← documentation technique par module
│   │   ├── index-annexes.md         ← index et rôles de toutes les annexes
│   │   ├── annexe-a-personas.md     ← personas et parcours utilisateurs
│   │   ├── annexe-b-multitenant.md  ← architecture multi-organisation
│   │   ├── annexe-c-matrice-droits.md ← matrice complète des droits et rôles
│   │   ├── annexe-d-org-structure.md ← structure Direction > Service > Agent
│   │   ├── annexe-e-module-phototheque.md ← module photothèque NAS
│   │   ├── annexe-f-politique-nas.md ← politique de stockage NAS
│   │   ├── annexe-g-module-ged-collabora.md ← module GED et Collabora Online
│   │   ├── annexe-k-cicd.md         ← pipeline CI/CD GitHub Actions
│   │   ├── annexe-m-pra.md          ← plan de reprise d'activité
│   │   ├── annexe-o-politique-quotas.md ← politique de quotas de stockage
│   │   ├── annexe-q-succession.md   ← continuité du projet (succession)
│   │   └── annexe-t-gestion-projet.md ← module gestion de projet
│   └── deploy/                      ← procédures de déploiement spécifiques
│       ├── collabora.md             ← installation Collabora Online
│       ├── secrets.md               ← gestion des secrets
│       ├── tde-mysql.md             ← chiffrement MySQL au repos
│       └── troubleshooting.md       ← dépannage
│
├── 03-guides/                       ← Guides utilisateurs par profil et par module
│   ├── guide-utilisateurs.md        ← guide général tous agents
│   ├── guide-admin-organisation.md  ← guide administrateur organisation (SGM, DGS)
│   ├── guide-super-admin.md         ← guide super administrateur plateforme
│   ├── guide-utilisateur-gestion-projet.md ← guide module projets
│   ├── guide-utilisateur-phototheque.md ← guide module photothèque
│   ├── guide-utilisateur-ged.md     ← guide module GED + Collabora
│   └── guide-utilisateur-datagrid.md ← guide module DataGrid
│
├── 04-adr/                          ← Décisions architecturales — ADR-001 à ADR-043
│   └── (voir tableau complet ci-dessous)
│
└── 05-exploitation/                 ← Exploitation
    └── checklist-mise-en-prod.md    ← checklist avant mise en production
```

---

## Décisions architecturales (ADR)

Les ADR (fiches de décision architecturale) documentent les choix techniques : pourquoi telle décision a été prise, quelles alternatives ont été considérées, quelles en sont les conséquences. Ils permettent à tout contributeur ou prestataire de comprendre non seulement *quoi*, mais *pourquoi*.

| ADR | Sujet |
|-----|-------|
| [ADR-001](04-adr/ADR-001-stack-frontend.md) | Stack frontend : Livewire + Alpine.js |
| [ADR-002](04-adr/ADR-002-multi-tenant-base-dedier.md) | Multi-organisation : base MySQL dédiée par organisation |
| [ADR-003](04-adr/ADR-003-tdd-partiel.md) | Tests : TDD partiel sur vraies bases MySQL |
| [ADR-004](04-adr/ADR-004-auth-locale-bcrypt.md) | Authentification locale bcrypt coût 12 |
| [ADR-005](04-adr/ADR-005-ldap-ldaps-obligatoire.md) | Annuaire : LDAPS obligatoire (connexion chiffrée) |
| [ADR-006](04-adr/ADR-006-hierarchie-direction-service-agent.md) | Hiérarchie Direction > Service > Agent |
| [ADR-007](04-adr/ADR-007-phpstan-smbclient-stub.md) | PHPStan niveau 5 : stub smbclient |
| [ADR-008](04-adr/ADR-008-kanban-par-jalon.md) | Kanban organisé par jalon de projet |
| [ADR-009](04-adr/ADR-009-gantt-svg-serveur.md) | Diagramme de Gantt SVG généré côté serveur |
| [ADR-010](04-adr/ADR-010-double-couche-droits-projets.md) | Double couche de droits sur les projets |
| [ADR-011](04-adr/ADR-011-droits-hierarchiques-projets.md) | Droits hiérarchiques sur les projets |
| [ADR-012](04-adr/ADR-012-stockage-nas-pas-cloud.md) | Stockage NAS local, pas cloud |
| [ADR-013](04-adr/ADR-013-deduplication-sha256.md) | Déduplication des fichiers par empreinte SHA-256 |
| [ADR-014](04-adr/ADR-014-queue-database.md) | File de tâches asynchrones (queue) |
| [ADR-015](04-adr/ADR-015-streaming-range-http.md) | Streaming HTTP adaptatif (Range requests) |
| [ADR-016](04-adr/ADR-016-modules-json-par-organisation.md) | Modules activables par organisation via JSON |
| [ADR-017](04-adr/ADR-017-2fa-totp-pas-sms.md) | Double authentification TOTP, pas SMS |
| [ADR-018](04-adr/ADR-018-watermark-gd-natif.md) | Filigrane (watermark) via GD natif PHP |
| [ADR-019](04-adr/ADR-019-enforcement-quota-strict.md) | Enforcement strict des quotas de stockage |
| [ADR-020](04-adr/ADR-020-ged-storage-interface.md) | GED : abstraction du stockage via interface |
| [ADR-021](04-adr/ADR-021-wopi-access-token-ttl-timestamp-absolu.md) | WOPI : durée de session en timestamp Unix absolu |
| [ADR-022](04-adr/ADR-022-collabora-integre-ged-pas-module-separe.md) | Collabora intégré à la GED, pas de module séparé |
| [ADR-023](04-adr/ADR-023-wopi-locks.md) | Gestion des verrous WOPI — édition simultanée |
| [ADR-024](04-adr/ADR-024-collabora-settings-par-tenant.md) | Configuration Collabora par organisation |
| [ADR-025](04-adr/ADR-025-recherche-ged-like.md) | Recherche GED par LIKE (pas FULLTEXT) |
| [ADR-026](04-adr/ADR-026-deploiement-production-vps.md) | Déploiement production sur VPS OVH |
| [ADR-027](04-adr/ADR-027-super-admin-restriction-ip.md) | Super Admin : restriction d'accès par adresse réseau |
| [ADR-028](04-adr/ADR-028-script-installation-automatique.md) | Script d'installation automatique `install.sh` |
| [ADR-029](04-adr/ADR-029-wizard-installation-web.md) | Wizard d'installation web PHP standalone |
| [ADR-030](04-adr/ADR-030-collabora-installation-optionnelle.md) | Collabora Online : installation optionnelle via wizard |
| [ADR-031](04-adr/ADR-031-install-collabora-sudo.md) | Script `install-collabora.sh` via sudoers en root |
| [ADR-032](04-adr/ADR-032-pas-de-rotation-cles-aes.md) | Rotation des clés AES : hors périmètre |
| [ADR-033](04-adr/ADR-033-rapatriement-ressources-externes-csp.md) | Rapatriement des ressources externes et mise en place de la CSP |
| [ADR-034](04-adr/ADR-034-mise-a-jour-super-admin.md) | Mécanisme de mise à jour depuis le Super Admin |
| [ADR-035](04-adr/ADR-035-audit-cross-tenant-hors-perimetre.md) | Audit cross-tenant : hors périmètre |
| [ADR-036](04-adr/ADR-036-datagrid-datapilote-droits-hierarchiques.md) | Modules DataGrid, DataPilote et modèle de droits hiérarchiques |
| [ADR-037](04-adr/ADR-037-gouvernance-donnees-personnelles-rgpd.md) | Gouvernance des données personnelles et conformité RGPD |
| [ADR-038](04-adr/ADR-038-source-verite-documentaire.md) | Source de vérité documentaire (niveau 2) |
| [ADR-039](04-adr/ADR-039-datagrid-datapilote-niveau2-3-consolide.md) | DataGrid et DataPilote : feuille de route consolidée niveaux 2 et 3 |
| [ADR-040](04-adr/ADR-040-datagrid-relations-entre-tables.md) | DataGrid relationnel : relations entre tables |
| [ADR-041](04-adr/ADR-041-securite-donnees-restauration.md) | Sécurité des données au repos, sauvegardes chiffrées et restauration |
| [ADR-042](04-adr/ADR-042-datagrid-export-pdf-excel-ods.md) | DataGrid : export PDF, Excel et ODS |
| [ADR-043](04-adr/ADR-043-ged-datagrid-source-verite.md) | GED vs DataGrid : unicité de source de vérité pour les tableurs |

---

## Documents de communication et d'accompagnement

Ces documents sont à la racine du projet et s'adressent aux collectivités, centres de gestion et partenaires potentiels.

| Document | Contenu |
|----------|---------|
| [ARGUMENTAIRE.md](../ARGUMENTAIRE.md) | Présentation de l'auteur, contexte du projet, 8 argumentaires thématiques pour les échanges avec les collectivités |
| [OBJECTIONS.md](../OBJECTIONS.md) | Questions fréquentes posées par les collectivités et leurs réponses honnêtes |
| [ROADMAP.md](../ROADMAP.md) | Avancement du projet et prochaines étapes (document canonique) |
| [public/calculateur-roi-pladigit.html](../public/calculateur-roi-pladigit.html) | Calculateur interactif pour comparer le coût de Microsoft 365 et Pladigit |
