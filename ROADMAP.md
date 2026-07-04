# Roadmap — Pladigit

> Une vue simple de l'avancement et des prochaines étapes.
> Cette roadmap reflète la réalité du projet : un développeur, une vision claire, un rythme soutenu.

---

## Ce qui est livré ✅

| Module | Ce que ça remplace | Depuis |
|--------|-------------------|--------|
| Socle — Auth, double authentification, LDAP, multi-organisation | — | Oct 2025 |
| Gestion de projet — Kanban, Gantt, Budget, Risques | Microsoft Planner | Mars 2026 |
| Photothèque NAS — Albums, EXIF, partage, filigrane | OneDrive Photos | Mars 2026 |
| GED documentaire — Arborescence, versioning, droits fins | SharePoint | Avr 2026 |
| Collabora Online — Édition ODT/ODS/ODP et formats Office | Word / Excel / PowerPoint | Avr 2026 |
| Assistant d'installation — script automatique + wizard web 8 étapes | — | Avr 2026 |
| Sécurité production — CSP, HSTS, X-Frame-Options, headers Nginx, ressources locales | — | Mai 2026 |
| Mise à jour Super Admin — `update.sh` sans accès SSH, log temps réel, `pladigit:update-status` | — | Mai 2026 |
| **DataGrid — Socle et niveau 2** — listes collaboratives, droits hiérarchiques, import CSV/XLSX/ODS, export Excel/PDF/ODS, recherche floue multi-colonnes, détection doublons, organisation en dossiers, audit log | Tableurs Excel éparpillés | Mai 2026 |
| **Sauvegardes chiffrées GPG** — AES-256, vérification SHA-256, architecture centralisée Super Admin, procédure de restauration documentée | — | Juin 2026 |
| **Migration Laravel 12** — 886 tests verts, dépendances à jour (`composer audit` propre) | — | Juin 2026 |

**En chiffres :** 886 tests verts · PHPStan niveau 5 · 43 décisions architecturales documentées · CI/CD GitHub Actions

**Qualité du code :** l'analyse statique est aujourd'hui au niveau 5 de PHPStan. Une montée progressive vers le niveau 8 est planifiée, par paliers (5 → 6 → 7 → 8) : un palier par session de travail, avec les trois gates (Pint, PHPStan, PHPUnit) verts à chaque étape. Le niveau 8 — détection des appels sur valeurs potentiellement nulles — est visé car il attrape la classe de bugs la plus fréquente en production.

---

## Ce qui vient ensuite 🔜

Les modules sont dans l'ordre de priorité révisé. Les dates sont indicatives.

| Module | Ce que ça remplace | Période visée |
|--------|-------------------|---------------|
| **Sécurité — Bloc 1 (suite)** — tableau de bord sécurité Super Admin, page test sauvegarde, pentest OWASP Top 10 (isolation inter-tenants, injection SQL, XSS, CSRF, upload malveillant) | — | Été 2026 |
| **Qualité — Montée PHPStan par paliers** — niveau 5 → 6 → 7 → 8, une session par palier | — | Été–Automne 2026 |
| **Source de vérité documentaire** — classification des actes officiels (délibérations, arrêtés, PV), nommage automatique, modèles Collabora — socle architectural posé (ADR-038), UI à construire | SharePoint / registres papier | Été–Automne 2026 |
| **DataGrid — Extensions** — relations entre tables (N-1 / 1-N / N-N), vues métier transparentes, colonnes calculées et agrégées, workflow statuts, commentaires, pièces jointes GED | Airtable / NocoDB propriétaires | Automne 2026 |
| **DataPilote** — tableaux croisés dynamiques par drag & drop, graphiques Apache ECharts, export Excel/PDF | Synthèses Excel manuelles | Automne–Hiver 2026 |
| Chat temps réel — canaux par service/projet, 1:1, WebSocket | Microsoft Teams | Fin 2026 |
| Agenda global — CalDAV, récurrence, synchronisation Thunderbird | Outlook Calendrier | Fin 2026 – 2027 |
| Signature électronique — RGS \*\* (Yousign/Docaposte) pour les élus | — | 2027 |
| Sondages et questionnaires | Microsoft Forms | 2027 |

### Priorité immédiate : finaliser la sécurité avant tout déploiement réel

Avant de présenter Pladigit à des collectivités en production, plusieurs points doivent être finalisés :

- **Pentest complet** — isolation inter-tenants (priorité absolue), OWASP Top 10, brute force, injection SQL, XSS, CSRF, upload malveillant, accès cross-tenant
- **Tableau de bord sécurité Super Admin** — état des sauvegardes, accès `/install/` verrouillé, statut GPG
- **Guide de restauration testé** — déchiffrement GPG, restauration base de données, vérification intégrité en conditions réelles

Une sauvegarde sans procédure de restauration testée ne vaut rien : c'est le filet de sécurité indispensable avant tout déploiement chez des collectivités réelles.

### Pourquoi DataGrid avant le Chat et l'Agenda

Le Chat et l'Agenda sont des modules visibles et attendus, mais ils ne résolvent pas le problème le plus fréquent constaté dans les petites collectivités : les dizaines de tableurs Excel éparpillés — listes d'élus, registres d'associations, suivi d'équipements, tableaux de bord — chacun dans son coin, sans lien entre eux, sans traçabilité.

DataGrid remplace ces tableurs par des listes collaboratives intégrées à Pladigit, accessibles selon les droits de chaque agent. Le module est conçu pour que même une responsable de communication sans compétence informatique puisse migrer ses fichiers Excel complexes — y compris les fichiers « tout en un » avec 60 à 90 colonnes — vers des données propres, relationnelles et cohérentes, sans jamais voir les tables techniques. DataPilote permet ensuite d'en extraire des synthèses croisées à la demande.

Ces deux modules constituent un argument différenciant fort, notamment face à Nextcloud, et ils sont réalisables par un développeur solo dans un délai raisonnable.

---

## Ce qui est envisagé plus tard 💡

Ces fonctionnalités sont identifiées mais sans date planifiée. Elles pourront émerger des retours terrain ou de contributions de la communauté.

- **Intelligence artificielle locale** — catégorisation automatique des photos (Ollama + LLaVA), recherche sémantique dans les documents. Infrastructure auto-hébergée — aucune dépendance vers un cloud IA externe.
- **DataGrid Assistant IA** — interface en langage naturel pour configurer le DataGrid sans compétence technique : un agent décrit son fichier Excel et l'usage attendu, l'IA propose la structure de la grille, les colonnes, les types et génère une vue personnalisée. S'appuie sur la même infrastructure IA locale et sur le `DatagridNormalizationService` posé dans le socle DataGrid.
- **Recherche cross-tables** — un seul champ de recherche global cherchant simultanément dans toutes les grilles du tenant, résultats groupés par table, tolérance aux variations orthographiques.
- **Applications mobiles / PWA** — accès terrain pour les agents de voirie, culture, technique.
- **API REST publique** — connecteurs SIG, SIRH, logiciels métiers collectivités.
- **Fil d'actualités RSS** — agrégateur de veille informationnelle pour les organisations.
- **Accessibilité RGAA 4.1** — audit complet et mise en conformité.
- **Audit de sécurité externe** — par un prestataire spécialisé, avant communication officielle vers les collectivités. Indispensable pour la crédibilité auprès des DSI et des CDG.

---

## Architecture et décisions documentées 📐

Pladigit documente ses choix techniques sous forme d'ADR (Architecture Decision Records) dans `docs/04-adr/`. Chaque ADR explique pourquoi une décision a été prise, les alternatives considérées et les conséquences. Cette documentation est publique et auditable par toute collectivité ou contributeur.

| ADR | Sujet |
|-----|-------|
| ADR-001 à 025 | Socle technique, GED, sécurité, performances |
| ADR-026 | Déploiement production VPS |
| ADR-027 | Restriction IP Super Admin |
| ADR-028 à 031 | Installation automatique et Collabora |
| ADR-032 à 035 | Sécurité applicative et CSP |
| ADR-036 | DataGrid et DataPilote — fondations *(remplacé par ADR-039)* |
| ADR-037 | Gouvernance RGPD et annuaire des personnalités |
| ADR-038 | Source de vérité documentaire |
| ADR-039 | DataGrid et DataPilote — feuille de route consolidée niveaux 2 et 3 |
| ADR-040 | DataGrid relationnel — relations entre tables, assistant de normalisation |
| ADR-041 | Sécurité des données au repos, sauvegardes chiffrées GPG, plan de restauration |
| ADR-042 | DataGrid — export PDF, Excel et ODS |
| ADR-043 | GED vs DataGrid — règle d'unicité de source de vérité |

---

## Ce qui est hors périmètre

Ces éléments ont été explicitement écartés pour des raisons de souveraineté ou de cohérence :

- Connecteurs Microsoft 365 / Slack / Trello — contraire à la philosophie souveraine du projet
- Hébergement cloud AWS / Google / Azure — même raison
- ONLYOFFICE — défendabilité dans le secteur public écartée (voir ADR-022)
- Portail citoyen — périmètre différent, peut faire l'objet d'un projet distinct
- Formules entre colonnes type Excel dans DataGrid — complexité excessive, hors usage collectivités
- Relations entre tenants dans DataGrid — risque de fuite de données entre organisations

---

## Comment contribuer à la roadmap

Si vous êtes une collectivité, une association, un centre de gestion ou un développeur et qu'un module vous manque :

1. Ouvrir une [issue GitHub](https://github.com/jpbosse/pladigit/issues) avec le label `roadmap`
2. Décrire le besoin concret et le contexte collectivité, pas la solution technique
3. Les besoins exprimés par des utilisateurs réels remontent en priorité

---

*Dernière mise à jour : Juillet 2026*
