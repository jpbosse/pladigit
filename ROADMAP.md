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

**En chiffres :** 781 tests verts · PHPStan niveau 5 · 35 décisions architecturales documentées · CI/CD GitHub Actions

---

## Ce qui vient ensuite 🔜

Les modules sont dans l'ordre de priorité révisé. Les dates sont indicatives.

| Module | Ce que ça remplace | Période visée |
|--------|-------------------|---------------|
| **DataGrid** — listes collaboratives sans programmation | Tableurs Excel éparpillés | Été 2026 |
| **DataPilot** — tableaux croisés dynamiques sur DataGrid | Synthèses Excel manuelles | Été–Automne 2026 |
| Chat temps réel — canaux par service/projet, 1:1, WebSocket | Microsoft Teams | Automne 2026 |
| Agenda global — CalDAV, récurrence, synchronisation Thunderbird | Outlook Calendrier | Fin 2026 |
| **Source de vérité documentaire** — classification des actes officiels (délibérations, arrêtés, PV), référence normalisée DEL-2026-042, nommage automatique, modèles Collabora — socle posé (ADR-038 accepté, migrations créées), UI à construire en parallèle du DataGrid | SharePoint / registres papier | Été–Automne 2026 |
| Signature électronique — RGS ** (Yousign/Docaposte) pour les élus | — | 2027 |
| Sondages et questionnaires | Microsoft Forms | 2027 |

### Pourquoi DataGrid et DataPilot avant le Chat et l'Agenda

Le Chat et l'Agenda sont des modules visibles et attendus, mais ils ne résolvent pas le problème le plus fréquent constaté dans les petites collectivités : les dizaines de tableurs Excel éparpillés — listes d'élus, registres d'associations, suivi d'équipements, tableaux de bord — chacun dans son coin, sans lien entre eux, sans traçabilité.

DataGrid remplace ces tableurs par des listes collaboratives intégrées à Pladigit, accessibles selon les droits de chaque agent. DataPilot permet d'en extraire des synthèses croisées à la demande. Ces deux modules constituent un argument commercial fort et différenciant, notamment face à Nextcloud, et ils sont réalisables par un développeur solo dans un délai raisonnable.

---

## Ce qui est envisagé plus tard 💡

Ces fonctionnalités sont identifiées mais sans date planifiée. Elles pourront émerger des retours terrain ou de contributions de la communauté.

- **Intelligence artificielle locale** — catégorisation automatique des photos (Ollama + LLaVA), recherche sémantique dans les documents. Prévu sur la configuration AMD 3800x / 32 Go RAM avec modèle Mistral 7B Q4.

- **DataGrid Assistant IA** — interface en langage naturel pour configurer et utiliser le DataGrid sans compétence technique. Un agent tape "j'ai un fichier Excel de suivi des associations, je veux une présentation avec recherche et filtres" — l'IA analyse le fichier, propose la structure de la grille, les colonnes, les types et génère une vue personnalisée. S'appuie sur la même infrastructure Ollama/Mistral. Pensé dès la conception du socle DataGrid pour que l'architecture soit compatible (API de configuration des grilles, schéma de colonnes introspectable, génération de vues paramétrables). Répond à l'évolution des usages : aujourd'hui n'importe quel agent peut demander à une IA de lui construire un outil sur mesure — Pladigit doit être ce terrain d'accueil natif et souverain.

- **Applications mobiles / PWA** — accès terrain pour les agents de voirie, culture, technique.

- **API REST publique** — connecteurs SIG (Système d'Information Géographique), SIRH, logiciels métiers collectivités.

- **Accessibilité RGAA 4.1** — référentiel général d'amélioration de l'accessibilité — audit complet et mise en conformité.

- **Fil d'actualités RSS** — agrégateur de veille informationnelle pour les organisations.

---

## Ce qui est hors périmètre

Ces éléments ont été explicitement écartés pour des raisons de souveraineté ou de cohérence :

- Connecteurs Microsoft 365 / Slack / Trello — contraire à la philosophie souveraine du projet
- Hébergement cloud AWS / Google / Azure — même raison
- ONLYOFFICE — origine et défendabilité dans le secteur public écartées (voir ADR-022)
- Portail citoyen — périmètre différent, peut faire l'objet d'un projet distinct

---

## Comment contribuer à la roadmap

Si vous êtes une collectivité, une association, un centre de gestion ou un développeur et qu'un module vous manque :

1. Ouvrir une [issue GitHub](https://github.com/jpbosse/pladigit/issues) avec le label `roadmap`
2. Décrire le besoin concret et le contexte collectivité, pas la solution technique
3. Les besoins exprimés par des utilisateurs réels remontent en priorité

---

*Dernière mise à jour : Mai 2026*
