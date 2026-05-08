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
| Mise à jour Super Admin — `update.sh`, log temps réel, `pladigit:update-status` | — | Mai 2026 |

**En chiffres :** 828 tests verts · PHPStan niveau 8 · 41 décisions architecturales documentées · CI/CD GitHub Actions

---

## Ce qui vient ensuite 🔜

Les modules sont dans l'ordre de priorité révisé. Les dates sont indicatives.

| Module | Ce que ça remplace | Période visée |
|--------|-------------------|---------------|
| **DataGrid — Socle** — listes collaboratives sans programmation, droits hiérarchiques, export Excel/PDF, recherche multicolonne et floue, détection et fusion des doublons, assistant de normalisation des fichiers complexes | Tableurs Excel éparpillés | Été 2026 |
| **Sécurité renforcée** — chiffrement MySQL TDE au repos, sauvegardes GPG chiffrées, procédures de restauration testées (complète / partielle / fichier GED), plan de réponse à incident, rotation des secrets | — | Été 2026 |
| **Source de vérité documentaire** — classification des actes officiels (délibérations DEL-2026-042, arrêtés, PV), nommage automatique, modèles Collabora — socle architectural posé (ADR-038), UI à construire | SharePoint / registres papier | Été–Automne 2026 |
| **DataGrid — Extensions** — relations entre tables (N-1 / 1-N / N-N), vues métier transparentes (l'utilisateur ne voit pas les tables techniques), colonnes calculées et agrégées, workflow statuts, commentaires, pièces jointes GED | Airtable / NocoDB propriétaires | Automne 2026 |
| **DataPilote** — tableaux croisés dynamiques par drag & drop, graphiques Apache ECharts, export Excel/PDF, lecture seule | Synthèses Excel manuelles | Automne–Hiver 2026 |
| Chat temps réel — canaux par service/projet, 1:1, WebSocket | Microsoft Teams | Fin 2026 |
| Agenda global — CalDAV, récurrence, synchronisation Thunderbird | Outlook Calendrier | Fin 2026 – 2027 |
| Signature électronique — RGS \*\* (Yousign/Docaposte) pour les élus | — | 2027 |
| Sondages et questionnaires | Microsoft Forms | 2027 |

### Pourquoi DataGrid avant le Chat et l'Agenda

Le Chat et l'Agenda sont des modules visibles et attendus, mais ils ne résolvent pas le problème le plus fréquent constaté dans les petites collectivités : les dizaines de tableurs Excel éparpillés — listes d'élus, registres d'associations, suivi d'équipements, tableaux de bord — chacun dans son coin, sans lien entre eux, sans traçabilité.

DataGrid remplace ces tableurs par des listes collaboratives intégrées à Pladigit, accessibles selon les droits de chaque agent. Le module est conçu pour que même une responsable de communication sans compétence informatique puisse migrer ses fichiers Excel complexes — y compris les fichiers "tout en un" avec 60 à 90 colonnes — vers des données propres, relationnelles et cohérentes, sans jamais voir les tables techniques. DataPilote permet ensuite d'en extraire des synthèses croisées à la demande.

Ces modules constituent un argument commercial fort et différenciant, notamment face à Nextcloud, et ils sont réalisables par un développeur solo dans un délai raisonnable.

### Pourquoi la sécurité renforcée en priorité haute

Les sauvegardes automatiques sont en place mais les archives ne sont pas chiffrées. Si un attaquant accède au serveur ou vole une sauvegarde, il obtient l'intégralité des données de toutes les collectivités hébergées. Par ailleurs, une sauvegarde sans procédure de restauration testée ne vaut rien. Le chiffrement MySQL TDE, le chiffrement GPG des archives et les procédures de restauration documentées et testées constituent le filet de sécurité indispensable avant tout déploiement chez des collectivités réelles.

---

## Ce qui est envisagé plus tard 💡

Ces fonctionnalités sont identifiées mais sans date planifiée. Elles pourront émerger des retours terrain ou de contributions de la communauté.

- **Intelligence artificielle locale** — catégorisation automatique des photos (Ollama + LLaVA), recherche sémantique dans les documents. Prévu sur la configuration AMD 3800x / 32 Go RAM avec modèle Mistral 7B Q4.

- **DataGrid Assistant IA** — interface en langage naturel pour configurer et utiliser le DataGrid sans compétence technique. Un agent tape "j'ai un fichier Excel de suivi des associations, je veux une présentation avec recherche et filtres" — l'IA analyse le fichier, propose la structure de la grille, les colonnes, les types et génère une vue personnalisée. S'appuie sur la même infrastructure Ollama/Mistral et sur le `DatagridNormalizationService` posé dans le socle DataGrid. Pensé dès la conception pour que l'architecture soit compatible — API de configuration des grilles introspectable, génération de vues paramétrables. Répond à l'évolution des usages : aujourd'hui n'importe quel agent peut demander à une IA de lui construire un outil sur mesure — Pladigit doit être ce terrain d'accueil natif et souverain.

- **Recherche cross-tables** — un seul champ de recherche global qui cherche simultanément dans toutes les grilles du tenant, avec résultats groupés par table. Retrouver "Jean Dupont" qu'il soit dans Élus, Contacts ou Prestataires en une seule recherche, avec tolérance aux variations orthographiques.

- **Applications mobiles / PWA** — accès terrain pour les agents de voirie, culture, technique.

- **API REST publique** — connecteurs SIG (Système d'Information Géographique), SIRH, logiciels métiers collectivités.

- **Accessibilité RGAA 4.1** — référentiel général d'amélioration de l'accessibilité — audit complet et mise en conformité.

- **Fil d'actualités RSS** — agrégateur de veille informationnelle pour les organisations.

- **Audit de sécurité externe** — audit par un prestataire spécialisé avant communication officielle vers les collectivités. Indispensable pour la crédibilité auprès des DSI et des CDG.

---

## Architecture et décisions documentées 📐

Pladigit documente ses choix techniques sous forme d'ADR (Architecture Decision Records) dans `docs/adr/`. Chaque ADR explique pourquoi une décision a été prise, les alternatives considérées et les conséquences. Cette documentation est publique et auditables par toute collectivité ou contributeur.

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
| ADR-040 | DataGrid Relationnel — relations entre tables, assistant de normalisation, expérience utilisateur transparente |
| ADR-041 | Sécurité des données au repos, sauvegardes chiffrées GPG, plan de restauration et réponse à incident |

---

## Ce qui est hors périmètre

Ces éléments ont été explicitement écartés pour des raisons de souveraineté ou de cohérence :

- Connecteurs Microsoft 365 / Slack / Trello — contraire à la philosophie souveraine du projet
- Hébergement cloud AWS / Google / Azure — même raison
- ONLYOFFICE — origine et défendabilité dans le secteur public écartées (voir ADR-022)
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

*Dernière mise à jour : Mai 2026*
