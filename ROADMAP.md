# Roadmap — Pladigit

> Une vue simple de l'avancement et des prochaines étapes.  
> Cette roadmap reflète la réalité du projet : un développeur, une vision claire, un rythme soutenu.

---

## Ce qui est livré ✅

| Module | Ce que ça remplace | Depuis |
|--------|-------------------|--------|
| Socle — Auth, 2FA, LDAP, multi-tenant | — | Oct 2025 |
| Gestion de projet — Kanban, Gantt, Budget | Microsoft Planner | Mars 2026 |
| Photothèque NAS — Albums, EXIF, partage | OneDrive Photos | Mars 2026 |
| GED documentaire — Arborescence, versioning | SharePoint | Avr 2026 |
| Collabora Online — Édition ODT/ODS/ODP | Word / Excel / PowerPoint | Avr 2026 |

**En chiffres :** 759 tests verts · PHPStan niveau 5 · CI/CD GitHub Actions

---

## Ce qui vient ensuite 🔜

Ces modules sont planifiés dans l'ordre de priorité. Les dates sont indicatives.

| Module | Ce que ça remplace | Période visée |
|--------|-------------------|---------------|
| Sécurité production — CSP, headers, rate limiting | — | Mai 2026 |
| Chat temps réel — canaux, 1:1, WebSocket | Microsoft Teams | Été 2026 |
| Agenda global — CalDAV, récurrence, export iCal | Outlook Calendrier | Automne 2026 |
| Pladigit comme source de vérité documentaire — modèles, nommage automatique | — | 2026–2027 |
| Sondages & questionnaires | Microsoft Forms | 2027 |
| ERP léger — tables no-code, DataGrid | — | 2027 |

---

## Ce qui est envisagé plus tard 💡

Ces fonctionnalités sont identifiées mais sans date planifiée.  
Elles pourront émerger de la communauté open source.

- **IA locale** — tagging automatique photos (Ollama + LLaVA), recherche sémantique documents
- **Applications mobiles / PWA** — accès terrain pour les agents
- **API REST publique** — connecteurs SIG, SIRH, logiciels métiers collectivités
- **Accessibilité RGAA 4.1** — audit complet et mise en conformité
- **Fil d'actualités RSS** — agrégateur pour les organisations

---

## Ce qui est hors périmètre

Ces éléments ont été explicitement écartés pour des raisons de souveraineté ou de complexité :

- Connecteurs Microsoft 365 / Slack / Trello — contraire à la philosophie souveraine
- Hébergement cloud AWS / Google / Azure — même raison
- ONLYOFFICE — origine et défendabilité dans le secteur public (voir ADR-022)
- Portail citoyen — périmètre différent, peut faire l'objet d'un projet séparé

---

## Comment contribuer à la roadmap

Si vous êtes une collectivité, une association ou un développeur et qu'un module vous manque :

1. Ouvrir une [issue GitHub](https://github.com/jpbosse/pladigit/issues) avec le label `roadmap`
2. Décrire le besoin concret, pas la solution technique
3. Les besoins exprimés par des utilisateurs réels remontent en priorité

---

*Dernière mise à jour : Avril 2026*
