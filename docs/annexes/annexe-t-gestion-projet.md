PLADIGIT
Plateforme de Digitalisation Interne
Alternative souveraine open source AGPL-3.0

Annexe T — Module Gestion de projet
Architecture complète, fonctionnalités livrées, droits et tests — Mars 2026

# 1. Présentation du module
Le module Gestion de projet de Pladigit est une alternative complète à Microsoft Planner et Project, conçue spécifiquement pour les collectivités territoriales et associations. Il permet de piloter des projets municipaux de bout en bout : cahier des charges, jalonnement, suivi des tâches, gestion budgétaire, conduite du changement et reporting aux élus.
Bien que prévu au CDC v2.0 pour la Phase 8 (Juillet–Septembre 2027), l’ensemble du module a été livré en avance lors des sessions de développement de mars 2026, complètement intégré au pipeline CI/CD avec 166 tests dédiés.


# 2. Architecture et décisions techniques
## 2.1 — ADR-008 à ADR-011

## 2.2 — Migrations MySQL (base tenant)

## 2.3 — Contrôleurs (15)
Tous dans App\Http\Controllers\Projects\ — protégés par middleware module:projects :

# 3. Modèle de données
## 3.1 — Table projects

## 3.2 — Table project_milestones (jalons + phases)
Un jalon avec parent_id = null EST une phase (isPhase() = true) dès lors qu’il a des enfants. Un jalon avec parent_id non null est un jalon enfant d’une phase. Un jalon sans enfants et sans parent est un jalon autonome.

## 3.3 — Table tasks

# 4. Droits et sécurité
## 4.1 — Trois couches cumulatives (ADR-010 + ADR-011)

## 4.2 — Enum ProjectRole

## 4.3 — Projets privés (is_private)
Un projet marqué is_private = true bloque la couche hiérarchique (couche 2). Seuls les membres explicitement nommés y ont accès. Admin/DGS voient tout, privé ou non. Le badge 🔒 Privé est affiché dans l’index et le détail du projet.

# 5. Vues de planification
Les cinq vues sont accessibles depuis l’onglet « Tâches & planning » via une barre d’onglets (Liste → Kanban → Gantt → Agenda → Charge). Un sélecteur de tri commun (Nom, Date d’échéance, Priorité, Assigné) s’applique à la Liste et au Kanban. Les en-têtes de colonne de la Liste sont cliquables (↑3/↑3 avec indicateur visuel).

## 5.1 — Liste
Tableau groupé par jalon, affichage en cours par défaut. Colonnes : Tâche, Statut, Priorité, Assigné, Échéance, Heures. Filtre texte instant. Toggle « Toutes les tâches ». En-têtes Tâche, Priorité, Assigné, Échéance cliquables pour tri.
## 5.2 — Kanban
4 colonnes fixes (À faire / En cours / En revue / Terminé), groupées par jalon. Sections rétractables (jalon actif déplié automatiquement). Cartes avec barre d’heures et avatar. Drag & drop intra-colonne et inter-colonnes via PATCH /kanban/move.
## 5.3 — Gantt
SVG généré côté serveur (Blade/PHP). Mini-timeline sur les jalons repliés. Corps SVG avec barres colorées par priorité, ligne rouge aujourd’hui, losange jalon. Zoom Alpine.js stocké en localStorage. Tâches bloquées par dépendance affichées en gris avec icône 🔒.
## 5.4 — Agenda
Vue liste chronologique des événements du projet. Bande colorée, bloc date, titre, heure, lieu, badge visibilité. Slideover de consultation. CRUD via modal. Export iCal agenda + jalons (RFC 5545).
## 5.5 — Charge (Workload)
Vue hebdomadaire par membre, centrée sur les tâches actives (±2 semaines / +6 semaines). Cellules colorées par seuil (Normal bleu / Chargé 3+ jaune / Surchargé 5+ rouge). Bandeau total heures réalisées / estimées avec barre de consommation.

# 6. Modules fonctionnels
## 6.1 — Budget
Lignes budgétaires typisées : prévision, engagement, mandat, cofinancement. Méthodes : variance() (dépassement), budgetSummary() (agrégat). Alertes visuelles si variance > 0. Accessible depuis l’onglet Finances. Droits : manageBudget = Owner uniquement.
## 6.2 — Parties prenantes
Matrice adhésion : champion | supporter | neutral | resistant | opponent. Chaque partie prenante liée à un utilisateur du tenant (optionnel). Droits : manageStakeholders = Owner uniquement.
## 6.3 — Conduite du changement
Deux sous-modules :
- Freins & risques — Matrice quadrants probabilité × impact. Chaque risque : probabilité (low/medium/high), impact (low/medium/high/critical), catégorie, statut, plan de mitigation. Criticité calculée automatiquement (score = prob × impact). Droits : manageChange = Owner + Member.
- Plan de communication — Timeline Gantt-style. Actions avec canal, responsable, date planifiée, date réalisée. Canaux : email, réunion, affichage, presse, réseaux sociaux, courrier. Fenêtre temporelle adaptative.
## 6.4 — Observations élus
Zone de saisie d’observations structurées destinées aux élus. Chaque observation : type (alerte/information/décision/validation), corps texte, auteur, date. Visible dans le tableau de bord élus.
## 6.5 — Tableau de bord élus
Vue synthèse exportable en PDF (DomPDF) et en archive ZIP (PDF + CSV tâches + CSV budget + CSV risques). Contenu : KPIs, alertes, budget, jalons, risques actifs, observations, équipe. Route GET /{project}/export/pdf et GET /{project}/export/zip.
## 6.6 — Documents (pièces jointes)
Pièces jointes et liens URL sur le projet et les tâches (table project_documents polymorphique). Deux types : file (upload vers storage/app/private/project-docs/{slug}/{id}/) et link (URL externe). Pilote local par défaut, extensible vers NAS dédié (Phase 5 GED). Extensions acceptées : PDF, Word, Excel, PowerPoint, ODF, images, ZIP (max 50 Mo). Interface : onglet Documents dans le projet, panneau rétractable dans le slideover tâche.
## 6.7 — Notifications in-app
NotificationService déclenche des notifications pour 6 événements : événement créé/modifié/supprimé (membres), tâche assignée (assigné), tâche terminée (owners), jalon atteint (membres). Cloche AJAX dans la topbar, badge numérique, marquer lu/tout lire/supprimer.
## 6.8 — Historique d’activité
Fil chronologique chargé en AJAX. 15 types d’actions (projet.créé, tâche.statut, jalon.atteint, budget.ajouté...). Filtre par type. Pagination 25/page. Membres voient leurs propres actions, owners/DGS voient tout. AuditService stocke les new_values JSON.

# 7. Tâches avancées
## 7.1 — Dépendances Fin→Fin
Une tâche B ne peut pas être marquée « Terminée » tant qu’une tâche A dont elle dépend n’est pas terminée. La table task_dependencies stocke les relations. Détection de cycle (BFS) et auto-dépendance bloquées côté serveur. Dépendances inter-projets refusées. Affichage dans le Gantt (barre grise + icône 🔒).
## 7.2 — Récurrence
Types : daily, weekly, monthly. Fréquence configurable (tous les N). Date de fin optionnelle. La commande artisan pladigit:generate-recurring-tasks génère les occurrences futures (horizon 6 semaines) — planifiée quotidiennement à 06h00 via scheduler. Anti-doublon intégré. Option --tenant pour cibler un seul tenant.
## 7.3 — Sous-tâches
Référencées via parent_task_id (auto-référence sur tasks). Compteur sous-tâches affiché sur les cartes Kanban. Progression incluse dans les calculs de jalon.

# 8. Modèles et duplication
## 8.1 — ProjectTemplate
Capture la structure type d’un projet (phases, jalons, tâches types) pour réutilisation. Les dates sont calculées par offset en jours depuis la date de démarrage choisie. Builder visuel Alpine.js : ajouter phases / jalons / tâches avec offset J+N. Accès : Resp. Direction et au-dessus. Actions : Appliquer un modèle à un nouveau projet, Sauvegarder un projet existant comme modèle.
## 8.2 — Duplication de projet
Copie la structure complète (phases, jalons, tâches) avec décalage des dates depuis une nouvelle date de démarrage. Budget, risques et observations non copiés. Deux passes : parents puis enfants pour préserver les parent_id. Accessible depuis l’onglet Actions de la page Modifier.
## 8.3 — Commandes Artisan

# 9. Routes (66 au total)
Toutes les routes sont dans routes/projects.php, protégées par middleware auth + module:projects. Préfixe /projects, namespace projects.

# 10. Intégration dashboard
## 10.1 — Widgets dashboard principal

## 10.2 — Page d’index multi-projets (/projects)
Tableau de bord de supervision pour Admin/DGS. 4 métriques (total, actifs, en alerte, progression moyenne). Jalons 30 jours (tous projets). Projets en alerte. Grille projets avec barre de progression, prochain jalon, membres. Filtres par statut. Badge rouge dans la sidebar si projets en alerte.

# 11. Couverture de tests
166 tests dédiés au module Gestion de projet, intégrés au pipeline CI/CD GitHub Actions (--exclude-group ldap,integration) :

# 12. Évolutions prévues
- Gantt interactif — drag & drop des barres pour modifier les dates (différé post Phase 8)
- Module Agenda global — agenda inter-projets tenant-wide avec vue calendrier (Annexe J, Phase ultérieure)
- Documents — extension vers NAS dédié et GED Phase 5 (ged_document_id sur project_documents)
- Documents — interface sur les jalons (prévu lors de la GED Phase 5)
- Notifications — exclusion des événements privés (visibility = private)
- Tests manquants à intégrer au CI : TaskDependencyTest et ProjectMilestonesIcalTest (créés, à pousser)
- Rapport PDF élus — finalisation de la vue DomPDF (installé, vue en place)


Les Bézots — Soullans (85) — github.com/jpbosse/pladigit — AGPL-3.0