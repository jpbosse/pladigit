# Pladigit — Cahier des Charges v2.3

> Plateforme de Digitalisation Interne  
> Alternative souveraine open source AGPL-3.0  
> Pour les collectivités locales, associations et structures du secteur parapublic français

| | |
|---|---|
| **Responsable** | Jean-Pierre Bossé |
| **Contact** | contact@pladigit.fr |
| **Dépôt** | github.com/jpbosse/pladigit |
| **Domaine** | pladigit.fr (avril 2026 → avril 2029) |
| **Licence** | AGPL-3.0 (code) / CC BY-SA 4.0 (documentation) |
| **Version** | 2.3 — Avril 2026 |

---

## Historique des versions

| Version | Date | Changements |
|---------|------|-------------|
| v2.0 | Mars 2026 | Refonte structurelle CDC. Phases 1–2 livrées. |
| v2.1 | Mars 2026 | Phase 3 Gestion de projet livrée. |
| v2.2 | Mars 2026 | Phases 4–5 Photothèque livrées. |
| v2.3 | Avril 2026 | Phases 6–7 GED + Collabora livrées. Refonte complète du planning en 3 niveaux. Ajout workflows, signature électronique, DataGrid, DataPilot. |

---

## 1. Présentation du projet

Pladigit est une plateforme SaaS multi-tenant de digitalisation interne, développée en open source (AGPL-3.0) par Jean-Pierre Bossé, retraité de la fonction publique territoriale, depuis Soullans (Vendée, France).

Elle est conçue comme une alternative souveraine aux outils Microsoft (Teams, SharePoint, OneDrive, Word, Excel, Planner) pour les collectivités locales de moins de 20 000 habitants, les associations et les structures du secteur parapublic français.

### 1.1 — Philosophie

**Souveraineté numérique.** Aucune donnée ne quitte le territoire français. Aucune dépendance à un cloud propriétaire. Hébergement sur les serveurs de l'organisation ou chez un hébergeur français.

**Zéro friction.** Les agents territoriaux n'ont pas toujours une forte culture numérique. L'interface doit être simple, intuitive, sans formation longue. Chaque fonctionnalité répond à un besoin concret du terrain.

**Défendable.** Chaque choix technique doit pouvoir être justifié devant un élu, un DGS ou un audit. Collabora plutôt qu'ONLYOFFICE, Jitsi souverain plutôt que Zoom, formats ODF natifs — pas de compromis sur la souveraineté.

**Progressif.** Les collectivités ne migrent pas en un jour. Pladigit coexiste avec les NAS existants, les fichiers Excel historiques, les habitudes en place. La migration est douce, jamais forcée.

### 1.2 — Contexte de développement

Pladigit est un projet solo, porté par un développeur unique à titre personnel, sans financement externe ni pression commerciale. L'infrastructure de démonstration (domaine pladigit.fr, VPS OVH) est financée personnellement. Les contributions financières via GitHub Sponsors permettent de couvrir ces frais.

Le projet est développé à un rythme soutenu — 7 phases livrées en 6 mois (octobre 2025 → avril 2026) — avec l'appui d'outils d'assistance au développement.

---

## 2. Stack technique

| Technologie | Version | Rôle |
|-------------|---------|------|
| PHP | 8.4 | Langage backend |
| Laravel | 11.x | Framework MVC |
| Livewire | 4.2 | Composants réactifs |
| Alpine.js | 3.x | Interactivité frontend |
| MySQL | 8.0+ | SGBD multi-tenant |
| Redis | 7.x | Cache, queues, sessions |
| Tailwind CSS | 3.x | Framework CSS |
| Collabora Online | CODE 24.x | Éditeur bureautique WOPI |
| Docker | 24+ | Conteneurisation Collabora |
| PHPUnit | 11.x | Tests |
| PHPStan | 1.x | Analyse statique niveau 5 |

**Architecture :** multi-tenant custom — base MySQL dédiée par organisation, middleware `ResolveTenant`, isolation totale entre organisations.

**CI/CD :** GitHub Actions — PHPUnit, Pint PSR-12, PHPStan niveau 5, Composer audit — 4 checks verts obligatoires avant merge sur `main`.

---

## 3. Remplacement des outils Microsoft

| Outil Microsoft | Alternative Pladigit | Statut |
|-----------------|---------------------|--------|
| Microsoft Planner | Gestion de projet | ✅ Livré |
| OneDrive / Photos | Photothèque NAS | ✅ Livré |
| SharePoint | GED documentaire | ✅ Livré |
| Word / Excel / PowerPoint | Collabora Online | ✅ Livré |
| Teams | Chat temps réel | 🔜 Niveau 2 |
| Outlook Calendrier | Agenda global CalDAV | 🔜 Niveau 2 |
| Forms | Sondages & questionnaires | 💡 Niveau 3 |
| Excel listes/stats | DataGrid + DataPilot | 🔜 Niveau 2 |

---

## 4. Planning — 3 niveaux

Le planning v2.3 abandonne la numérotation séquentielle des phases au profit de trois niveaux reflétant la réalité du projet.

---

### Niveau 1 — Livré et stable ✅

> Ce qui existe aujourd'hui. Documenté, testé (759 tests / 1645 assertions), déployable.

#### Socle technique (octobre–décembre 2025)
- Architecture multi-tenant — base MySQL dédiée par organisation
- Authentification locale — bcrypt coût 12, verrouillage compte, politique MDP configurable
- Double authentification TOTP (Google Authenticator, Aegis) — codes secours AES-256
- Authentification LDAP/AD — LDAPS obligatoire, circuit breaker, sync automatique
- Journalisation complète — audit trail, export CSV/JSON, rétention RGPD configurable
- CI/CD GitHub Actions — PHPStan niveau 5, Pint PSR-12, 0 vulnérabilité

#### Organisations & utilisateurs (janvier–mars 2026)
- Gestion des rôles hiérarchiques — Admin, Président, DGS, Resp. Direction, Resp. Service, Agent
- Structure organisationnelle — Directions > Services > Agents
- Invitation par email, personnalisation visuelle (branding) par organisation
- Super Admin isolé — provisionnement, configuration technique, supervision

#### Gestion de projet (mars 2026) — *remplace Microsoft Planner*
- Vues : Kanban par jalon, Gantt SVG drag & drop, Liste, Charge, Agenda projet
- Tâches : récurrence, dépendances Fin→Fin, commentaires, assignation
- Budget, risques, parties prenantes, conduite du changement
- Export PDF élus, export iCal jalons, modèles de projet, duplication
- Droits : UserRole global + ProjectRole par projet (ADR-010, ADR-011)
- Intégration Jitsi Meet souverain (meet.numerique.gouv.fr)

#### Photothèque NAS (mars–avril 2026) — *remplace OneDrive Photos*
- Albums hiérarchiques, upload drag & drop, traitement asynchrone
- Déduplication SHA-256 cross-album, extraction EXIF, watermark configurable
- Partage par lien sécurisé temporaire, export ZIP, streaming range HTTP
- Synchronisation NAS planifiée (local, SFTP, SMB)
- Droits par album, quotas de stockage stricts par organisation

#### GED documentaire (avril 2026) — *remplace SharePoint*
- Arborescence de dossiers, permissions fines héritées (rôle, direction, service, utilisateur)
- Upload drag & drop, prévisualisation inline, versioning complet
- Synchronisation NAS → GED silencieuse (mtime, 5 min, job async)
- Recherche plein texte MySQL FULLTEXT
- Intégration GED ↔ Projets (ProjectGedLink)
- Gouvernance admin : transfert propriété, purge, intégrité fichiers

#### Collabora Online (avril 2026) — *remplace Microsoft Office*
- Édition collaborative ODF (ODT, ODS, ODP) et formats Microsoft Office
- Protocole WOPI complet : CheckFileInfo, GetFile, PutFile, Lock/Unlock/RefreshLock/GetLock
- Token multi-tenant sécurisé — un seul aliasgroup Collabora pour tous les tenants
- Versioning automatique à chaque sauvegarde
- Administration URL, TTL session, test connexion depuis l'interface admin

---

### Niveau 2 — Prochaines priorités 🔜

> Ce qui sera développé avant et juste après la publication GitHub publique.  
> Ordre indicatif — certains modules peuvent être parallélisés.

#### Sécurité production
- CSP (Content Security Policy) et headers HTTP sécurité complets
- Rate limiting sur les sous-domaines et les endpoints d'authentification
- Rotation des clés AES TOTP/LDAP
- Audit cross-tenant TenantManager
- Protection Nginx — maintenance, disponibilité, logs

#### "Pladigit source de vérité documentaire"
- Modèles de documents par type — délibération, arrêté, compte-rendu, courrier
- Nommage automatique — date + type + service
- Création dans le bon dossier avec droits hérités
- Migration douce depuis NAS existant — coexistence NAS + Pladigit sur le long terme

#### Workflows documentaires
- Statuts de document — Brouillon → En révision → Validé → Archivé
- Circuits de validation — assignation d'un valideur, notifications, relances
- Historique des validations — qui a approuvé, quand

#### Signature électronique
Intégrée dans la GED. Le type de signature dépend du **rôle de la personne qui signe**, pas du niveau d'offre. Les deux types sont disponibles dès la version Communautaire.

| Rôle | Type de signature | Valeur |
|------|------------------|--------|
| Président, Maire, Élu signataire | RGS \*\* via prestataire souverain (Yousign, Docaposte) | Légale — opposable en préfecture |
| DGS, Resp. Direction | RGS \*\* ou PKI interne selon l'acte | Selon le besoin |
| Agents, Resp. Service | PKI interne auto-hébergée | Usage interne uniquement |

Ce qui distingue les offres est le niveau d'accompagnement, pas l'accès à la fonctionnalité :

| Offre | Accompagnement |
|-------|----------------|
| Communautaire | Tout disponible — configuration autonome |
| Assistance | Accompagnement configuration prestataire RGS \*\* et PKI interne |
| Enterprise | Déploiement PKI sur mesure + SLA chaîne de signature |

#### DataGrid — listes structurées no-code
- Définition des structures de tables par le Super Admin (aucun accès MySQL direct pour les utilisateurs)
- CRUD no-code sur listes — élus, anciens élus, associations, prestataires, équipements, bénévoles...
- Droits fins par table et par colonne — lecture, modification, administration
- Certaines colonnes masquables selon le rôle (ex: indemnités élus visibles DGS uniquement)
- Export CSV compatible publipostage, export PDF
- Remplacement des fichiers Excel éparpillés dans les services

#### DataPilot — tableaux croisés dynamiques
- Agrégation et analyse de données issues des DataGrids
- Dimensions configurables (axe de croisement — service, mois, type)
- Mesures configurables (ce qu'on agrège — coût, volume, quantité)
- Cas d'usage : consommation téléphonique par service/mois, coûts photocopieurs par direction, carburant par véhicule
- Interface simple — aucune connaissance SQL requise

#### Chat temps réel — *remplace Microsoft Teams*
- Canaux par service/projet, messagerie 1:1
- WebSocket (Soketi)
- Partage de fichiers depuis la GED

#### Agenda global CalDAV — *remplace Outlook Calendrier*
- Agenda inter-projets, récurrence, export iCal
- Synchronisation CalDAV avec les agendas externes (Thunderbird, etc.)

---

### Niveau 3 — Roadmap communautaire 💡

> Ce que la communauté open source pourra reprendre et développer.  
> Sans date planifiée — dépend des contributions.

- **IA locale** — tagging automatique photos (Ollama + LLaVA), recherche sémantique documents (Ollama + Mistral)
- **Sondages & questionnaires** — remplace Microsoft Forms
- **Fil d'actualités RSS** — agrégateur interne
- **Open Data** — publication contrôlée de listes DataGrid (colonnes marquées publiques uniquement, avec avertissement RGPD)
- **Applications mobiles / PWA** — accès terrain pour les agents
- **API REST publique** — connecteurs SIG, SIRH, logiciels métiers collectivités
- **Accessibilité RGAA 4.1** — audit complet et mise en conformité

---

## 5. Modules et activation

Les modules sont activables par organisation via la colonne JSON `enabled_modules` (ADR-016). Le Super Admin contrôle quels modules sont disponibles. L'Admin Organisation active ceux dont son organisation a besoin.

| Module | Clé | Statut |
|--------|-----|--------|
| Photothèque NAS | `MEDIA` | ✅ Livré |
| Gestion de projet | `PROJECTS` | ✅ Livré |
| GED documentaire | `GED` | ✅ Livré |
| Collabora Online | Intégré à GED | ✅ Livré |
| Chat | `CHAT` | 🔜 Niveau 2 |
| Agenda global | `CALENDAR` | 🔜 Niveau 2 |
| DataGrid | `ERP` | 🔜 Niveau 2 |
| Sondages | `SURVEY` | 💡 Niveau 3 |
| Fil RSS | `RSS` | 💡 Niveau 3 |

---

## 6. Sécurité et conformité

- **Chiffrement** — AES-256 pour les secrets sensibles (LDAP, TOTP, SMTP), TLS obligatoire
- **Authentification** — bcrypt coût 12, 2FA TOTP, LDAPS obligatoire, circuit breaker
- **Autorisation** — UserRole global + ProjectRole par projet + GedPermissionLevel + AlbumPermissionLevel
- **RGPD** — audit trail complet, rétention configurable (3/6/12/24/36 mois), export des données
- **Isolation** — base MySQL dédiée par organisation, aucune fuite cross-tenant possible
- **Qualité** — PHPStan niveau 5, 0 vulnérabilité dépendances (Composer audit), CI/CD vert permanent

---

## 7. Infrastructure recommandée

```
VPS Ubuntu 24 LTS — hébergeur français (OVH, Scaleway, Infomaniak)
├── Nginx + SSL Let's Encrypt wildcard (*.pladigit.fr)
├── PHP 8.4-FPM
├── MySQL 8 — bases dédiées par tenant
├── Redis 7 — cache, queues, sessions
├── Supervisor — 2 queue workers
└── Docker → Collabora Online CODE (optionnel)

Recommandé avec Collabora : 8 vCPU / 16 Go RAM / 200 Go SSD
Minimum sans Collabora : 2 vCPU / 4 Go RAM / 40 Go SSD
```

**Sauvegardes :** rsync vers stockage distant — 6 horaires, 5 quotidiennes, 3 hebdomadaires, 2 mensuelles.

---

## 8. Décisions architecturales (ADR)

22 ADR documentés dans `docs/adr/` — de ADR-001 (stack frontend) à ADR-022 (Collabora intégré à GED). Chaque décision technique importante est tracée avec son contexte, ses alternatives considérées et ses conséquences.

---

## 9. Documentation

| Document | Audience | Localisation |
|----------|----------|-------------|
| README.md | Tout public | Racine |
| INSTALL.md | Déployeurs | Racine |
| CONTRIBUTING.md | Contributeurs | Racine |
| SECURITY.md | Chercheurs sécurité | Racine |
| ROADMAP.md | Élus, contributeurs | Racine |
| docs/adr/ | Développeurs | 22 ADR |
| docs/annexes/ | Développeurs | 12 annexes techniques |
| docs/guides/ | Utilisateurs finaux | 4 guides par rôle |
| docs/divers/ | Opérateurs | Installation, maintenance, checklist prod |

---

## 10. Contribution et financement

Pladigit est un bien commun numérique. Les contributions sont bienvenues — code, documentation, retours d'usage terrain.

L'infrastructure (domaine pladigit.fr, VPS) est financée personnellement. Les contributions financières via GitHub Sponsors permettent de maintenir une instance de démonstration publique.

> **Instance de démonstration :** pladigit.fr  
> ⚠ Disponibilité non garantie — infrastructure personnelle.

---

*Pladigit — Reprendre le contrôle de votre numérique.*  
*contact@pladigit.fr — github.com/jpbosse/pladigit — AGPL-3.0*
