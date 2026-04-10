# Documentation — Pladigit

Bienvenue dans la documentation technique de Pladigit.

---

## Démarrage rapide

| Je veux... | Document |
|-----------|----------|
| Installer Pladigit sur un serveur | [INSTALL.md](../INSTALL.md) |
| Lire le Cahier des Charges complet | [CDC_Pladigit_v2.3.md](CDC_Pladigit_v2.3.md) |
| Trouver la bonne annexe | [index-annexes.md](index-annexes.md) |
| Comprendre un terme technique | [glossaire.md](glossaire.md) |
| Comprendre l'architecture | [Annexe B — Multi-tenant](annexes/annexe-b-multitenant.md) |
| Configurer les droits et rôles | [Annexe C — Matrice des droits](annexes/annexe-c-matrice-droits.md) |
| Comprendre pourquoi telle décision technique | [docs/adr/](adr/) |
| Mettre en production | [divers/checklist-mise-en-prod.md](divers/checklist-mise-en-prod.md) |
| Former les utilisateurs | [guides/](guides/) |
| Guide Photothèque | [guides/guide-utilisateur-phototheque.md](guides/guide-utilisateur-phototheque.md) |
| Guide GED + Collabora | [guides/guide-utilisateur-ged.md](guides/guide-utilisateur-ged.md) |

---

## Structure

```
docs/
├── README.md                   ← ce fichier
├── CDC_Pladigit_v2.3.md        ← cahier des charges complet
├── index-annexes.md            ← index et rôles de toutes les annexes
├── glossaire.md                ← glossaire des termes techniques et métier
│
├── adr/                        ← Décisions architecturales (ADR-001 à ADR-025)
│   ├── ADR-001-stack-frontend.md
│   ├── ADR-002-multi-tenant-base-dedier.md
│   └── ...
│
├── annexes/                    ← Documentation technique par module
│   ├── annexe-a-personas.md
│   ├── annexe-b-multitenant.md
│   ├── annexe-c-matrice-droits.md
│   ├── annexe-d-org-structure.md
│   ├── annexe-e-module-phototheque.md
│   ├── annexe-f-politique-nas.md
│   ├── annexe-g-module-ged-collabora.md
│   ├── annexe-k-cicd.md
│   ├── annexe-m-pra.md
│   ├── annexe-o-politique-quotas.md
│   ├── annexe-q-succession.md
│   └── annexe-t-gestion-projet.md
│
├── guides/                     ← Guides utilisateurs
│   ├── guide-utilisateurs.md
│   ├── guide-admin-organisation.md
│   ├── guide-super-admin.md
│   ├── guide-utilisateur-gestion-projet.md
│   ├── guide-utilisateur-phototheque.md
│   └── guide-utilisateur-ged.md
│
└── divers/                     ← Exploitation et maintenance
    ├── checklist-mise-en-prod.md
    ├── guide-installation-prerequis.md
    └── guide-maintenance.md
```

---

## Décisions architecturales (ADR)

Les ADR documentent les choix techniques importants — pourquoi telle décision a été prise, quelles alternatives ont été considérées, quelles en sont les conséquences.

| ADR | Sujet |
|-----|-------|
| [ADR-001](adr/ADR-001-stack-frontend.md) | Stack frontend : Livewire + Alpine.js |
| [ADR-002](adr/ADR-002-multi-tenant-base-dedier.md) | Multi-tenant : base MySQL dédiée par organisation |
| [ADR-003](adr/ADR-003-tdd-partiel.md) | TDD partiel : tests feature sur vraies bases |
| [ADR-004](adr/ADR-004-auth-locale-bcrypt.md) | Authentification locale bcrypt |
| [ADR-005](adr/ADR-005-ldap-ldaps-obligatoire.md) | LDAP : LDAPS obligatoire |
| [ADR-006](adr/ADR-006-hierarchie-direction-service-agent.md) | Hiérarchie Direction > Service > Agent |
| [ADR-007](adr/ADR-007-phpstan-smbclient-stub.md) | PHPStan : stub smbclient |
| [ADR-008](adr/ADR-008-kanban-par-jalon.md) | Kanban par jalon |
| [ADR-009](adr/ADR-009-gantt-svg-serveur.md) | Gantt SVG côté serveur |
| [ADR-010](adr/ADR-010-double-couche-droits-projets.md) | Double couche de droits projets |
| [ADR-011](adr/ADR-011-droits-hierarchiques-projets.md) | Droits hiérarchiques projets |
| [ADR-012](adr/ADR-012-stockage-nas-pas-cloud.md) | Stockage NAS, pas cloud |
| [ADR-013](adr/ADR-013-deduplication-sha256.md) | Déduplication SHA-256 |
| [ADR-014](adr/ADR-014-queue-database.md) | Queue database |
| [ADR-015](adr/ADR-015-streaming-range-http.md) | Streaming range HTTP |
| [ADR-016](adr/ADR-016-modules-json-par-organisation.md) | Modules JSON par organisation |
| [ADR-017](adr/ADR-017-2fa-totp-pas-sms.md) | 2FA TOTP, pas SMS |
| [ADR-018](adr/ADR-018-watermark-gd-natif.md) | Watermark GD natif |
| [ADR-019](adr/ADR-019-enforcement-quota-strict.md) | Enforcement quota strict |
| [ADR-020](adr/ADR-020-ged-storage-interface.md) | GED : abstraction stockage |
| [ADR-021](adr/ADR-021-wopi-access-token-ttl-timestamp-absolu.md) | WOPI : access_token_ttl timestamp absolu |
| [ADR-022](adr/ADR-022-collabora-integre-ged-pas-module-separe.md) | Collabora intégré à GED |
| [ADR-023](adr/ADR-023-wopi-locks.md) | Verrous WOPI — édition simultanée |
| [ADR-024](adr/ADR-024-collabora-settings-par-tenant.md) | Configuration Collabora par tenant |
| [ADR-025](adr/ADR-025-recherche-ged-like.md) | Recherche GED par LIKE |
