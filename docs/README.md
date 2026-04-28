# Documentation — Pladigit

Bienvenue dans la documentation technique et fonctionnelle de Pladigit.

---

## Démarrage rapide

| Je veux… | Document |
|----------|----------|
| Installer Pladigit (non-informaticien) | [Guide illustré avec captures d'écran](https://jpbosse.github.io/pladigit/GUIDE-INSTALLATION.html) |
| Installer Pladigit sur un serveur existant | [INSTALL.md](../INSTALL.md) |
| Calculer ce que je vais économiser | [Calculateur ROI](../public/calculateur-roi-pladigit.html) |
| Comprendre le projet et son contexte | [ARGUMENTAIRE.md](../ARGUMENTAIRE.md) |
| Répondre aux questions fréquentes | [OBJECTIONS.md](../OBJECTIONS.md) |
| Lire le cahier des charges complet | [CDC_Pladigit_v2.3.md](CDC_Pladigit_v2.3.md) |
| Trouver la bonne annexe technique | [index-annexes.md](index-annexes.md) |
| Comprendre un terme technique ou métier | [glossaire.md](glossaire.md) |
| Comprendre l'architecture multi-organisation | [Annexe B — Multi-tenant](annexes/annexe-b-multitenant.md) |
| Configurer les droits et rôles | [Annexe C — Matrice des droits](annexes/annexe-c-matrice-droits.md) |
| Comprendre pourquoi telle décision technique | [docs/adr/](adr/) |
| Mettre en production | [divers/checklist-mise-en-prod.md](divers/checklist-mise-en-prod.md) |
| Former les utilisateurs | [guides/](guides/) |

---

## Structure complète

```
docs/
├── README.md                        ← ce fichier
├── CDC_Pladigit_v2.3.md             ← cahier des charges complet v2.3
├── index-annexes.md                 ← index et rôles de toutes les annexes
├── glossaire.md                     ← glossaire des termes techniques et métier
│
├── GUIDE-INSTALLATION.html          ← guide illustré pas-à-pas (s'ouvre dans le navigateur)
│
├── adr/                             ← Décisions architecturales — ADR-001 à ADR-035
│   ├── ADR-001 à ADR-035            ← voir tableau complet ci-dessous
│
├── annexes/                         ← Documentation technique par module
│   ├── annexe-a-personas.md         ← Personas et parcours utilisateurs
│   ├── annexe-b-multitenant.md      ← Architecture multi-organisation
│   ├── annexe-c-matrice-droits.md   ← Matrice complète des droits et rôles
│   ├── annexe-d-org-structure.md    ← Structure Direction > Service > Agent
│   ├── annexe-e-module-phototheque.md ← Module photothèque NAS
│   ├── annexe-f-politique-nas.md    ← Politique de stockage NAS
│   ├── annexe-g-module-ged-collabora.md ← Module GED et Collabora Online
│   ├── annexe-k-cicd.md             ← Pipeline CI/CD GitHub Actions
│   ├── annexe-m-pra.md              ← Plan de reprise d'activité
│   ├── annexe-o-politique-quotas.md ← Politique de quotas de stockage
│   ├── annexe-q-succession.md       ← Continuité du projet (succession)
│   └── annexe-t-gestion-projet.md   ← Module gestion de projet
│
├── guides/                          ← Guides utilisateurs par profil
│   ├── guide-utilisateurs.md        ← Guide général tous agents
│   ├── guide-admin-organisation.md  ← Guide administrateur organisation (SGM, DGS)
│   ├── guide-super-admin.md         ← Guide super administrateur plateforme
│   ├── guide-utilisateur-gestion-projet.md ← Guide module projets
│   ├── guide-utilisateur-phototheque.md    ← Guide module photothèque
│   └── guide-utilisateur-ged.md     ← Guide module GED + Collabora
│
└── divers/                          ← Exploitation et maintenance
    ├── checklist-mise-en-prod.md    ← Checklist avant mise en production
    ├── guide-installation-prerequis.md ← Prérequis détaillés
    └── guide-maintenance.md         ← Guide de maintenance courante
```

---

## Décisions architecturales (ADR)

Les ADR (fiches de décision architecturale) documentent les choix techniques : pourquoi telle décision a été prise, quelles alternatives ont été considérées, quelles en sont les conséquences. Ils permettent à tout contributeur ou prestataire de comprendre non seulement *quoi*, mais *pourquoi*.

| ADR | Sujet |
|-----|-------|
| [ADR-001](adr/ADR-001-stack-frontend.md) | Stack frontend : Livewire + Alpine.js |
| [ADR-002](adr/ADR-002-multi-tenant-base-dedier.md) | Multi-organisation : base MySQL dédiée par organisation |
| [ADR-003](adr/ADR-003-tdd-partiel.md) | Tests : TDD partiel sur vraies bases MySQL |
| [ADR-004](adr/ADR-004-auth-locale-bcrypt.md) | Authentification locale bcrypt coût 12 |
| [ADR-005](adr/ADR-005-ldap-ldaps-obligatoire.md) | Annuaire : LDAPS obligatoire (connexion chiffrée) |
| [ADR-006](adr/ADR-006-hierarchie-direction-service-agent.md) | Hiérarchie Direction > Service > Agent |
| [ADR-007](adr/ADR-007-phpstan-smbclient-stub.md) | PHPStan : stub smbclient |
| [ADR-008](adr/ADR-008-kanban-par-jalon.md) | Kanban organisé par jalon de projet |
| [ADR-009](adr/ADR-009-gantt-svg-serveur.md) | Diagramme de Gantt SVG généré côté serveur |
| [ADR-010](adr/ADR-010-double-couche-droits-projets.md) | Double couche de droits sur les projets |
| [ADR-011](adr/ADR-011-droits-hierarchiques-projets.md) | Droits hiérarchiques sur les projets |
| [ADR-012](adr/ADR-012-stockage-nas-pas-cloud.md) | Stockage NAS local, pas cloud |
| [ADR-013](adr/ADR-013-deduplication-sha256.md) | Déduplication des fichiers par empreinte SHA-256 |
| [ADR-014](adr/ADR-014-queue-database.md) | File de tâches asynchrones (queue database) |
| [ADR-015](adr/ADR-015-streaming-range-http.md) | Streaming HTTP adaptatif (Range requests) |
| [ADR-016](adr/ADR-016-modules-json-par-organisation.md) | Modules activables par organisation via JSON |
| [ADR-017](adr/ADR-017-2fa-totp-pas-sms.md) | Double authentification TOTP, pas SMS |
| [ADR-018](adr/ADR-018-watermark-gd-natif.md) | Filigrane (watermark) via GD natif PHP |
| [ADR-019](adr/ADR-019-enforcement-quota-strict.md) | Enforcement strict des quotas de stockage |
| [ADR-020](adr/ADR-020-ged-storage-interface.md) | GED : abstraction du stockage via interface |
| [ADR-021](adr/ADR-021-wopi-access-token-ttl-timestamp-absolu.md) | WOPI : durée de session en timestamp Unix absolu |
| [ADR-022](adr/ADR-022-collabora-integre-ged-pas-module-separe.md) | Collabora intégré à la GED, pas de module séparé |
| [ADR-023](adr/ADR-023-wopi-locks.md) | Gestion des verrous WOPI — édition simultanée |
| [ADR-024](adr/ADR-024-collabora-settings-par-tenant.md) | Configuration Collabora par organisation |
| [ADR-025](adr/ADR-025-recherche-ged-like.md) | Recherche GED par MySQL FULLTEXT |
| [ADR-026](adr/ADR-026-deploiement-production-vps.md) | Déploiement production sur VPS OVH |
| [ADR-027](adr/ADR-027-super-admin-restriction-ip.md) | Super Admin : restriction d'accès par adresse réseau |
| [ADR-028](adr/ADR-028-script-installation-automatique.md) | Script d'installation automatique `install.sh` |
| [ADR-029](adr/ADR-029-wizard-installation-web.md) | Wizard d'installation web PHP standalone |
| [ADR-030](adr/ADR-030-collabora-installation-optionnelle.md) | Collabora Online : installation optionnelle via wizard |
| [ADR-031](adr/ADR-031-install-collabora-sudo.md) | Script `install-collabora.sh` via sudoers en root |
| [ADR-032](adr/ADR-032-pas-de-rotation-cles-aes.md) | Rotation des clés AES : hors périmètre |
| [ADR-033](adr/ADR-033-rapatriement-ressources-externes-csp.md) | Rapatriement des ressources externes et mise en place de la CSP |
| [ADR-034](adr/ADR-034-mise-a-jour-super-admin.md) | Mécanisme de mise à jour depuis le Super Admin |
| [ADR-035](adr/ADR-035-audit-cross-tenant-hors-perimetre.md) | Audit cross-tenant : hors périmètre |

---

## Documents de communication et d'accompagnement

Ces documents sont à la racine du projet et s'adressent aux collectivités, centres de gestion et partenaires potentiels.

| Document | Contenu |
|----------|---------|
| [ARGUMENTAIRE.md](../ARGUMENTAIRE.md) | Présentation de l'auteur, contexte du projet, 8 argumentaires thématiques pour les échanges avec les collectivités |
| [OBJECTIONS.md](../OBJECTIONS.md) | Questions fréquentes posées par les collectivités et leurs réponses honnêtes |
| [public/calculateur-roi-pladigit.html](../public/calculateur-roi-pladigit.html) | Calculateur interactif pour comparer le coût de Microsoft 365 et Pladigit |
