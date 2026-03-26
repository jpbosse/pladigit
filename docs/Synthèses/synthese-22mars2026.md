PLADIGIT
Plateforme de Digitalisation Interne
Alternative souveraine open source AGPL-3.0

Synthèse Technique — Session du 22 Mars 2026
Gestion de projet, Gantt drag & drop, sécurité, CDC v2.1, documents

# 1. Résumé exécutif
Cette session a été extrêmement productive. Elle a couvert la finalisation de la Phase 8 Gestion de projet (tris, drag & drop Gantt, jalons), la gestion documentaire liée aux projets, l’amélioration du dashboard, la politique de mots de passe configurable, et la refonte complète du CDC en version 2.1. 21 nouveaux tests ont été intégrés au pipeline CI.


# 2. Tri Kanban / Gantt / Liste
## 2.1 — Barre de tri commune (_planif.blade.php)
Un sélecteur de tri (Nom, Date d’échéance, Priorité, Assigné) + bouton ↑3/↑3 ajouté au-dessus des onglets. Dispatche l’événement sort-tasks vers toutes les vues.
## 2.2 — En-têtes cliquables (_list_table.blade.php)
Les colonnes Tâche, Priorité, Assigné, Échéance ont des flèches ↔3/↔3 cliquables. Premier clic : croissant, deuxième clic : décroissant. En-tête actif en bleu navy. Tri via tri DOM Alpine.js côté client sur les attributs data-sort-*.
## 2.3 — Gantt
Le Gantt reste ordonné par dates (cohérence sémantique). Un bandeau jaune informe que le tri s’applique à Liste et Kanban uniquement.

# 3. Intégration CI — 21 nouveaux tests

# 4. Gestion documentaire liée aux projets
## 4.1 — Architecture
Table project_documents polymorphique (documentable_type + documentable_id) couvrant Project et Task. Deux types : file (upload vers storage/app/private/project-docs/{slug}/{project_id}/) et link (URL externe). Driver local par défaut, extensible NAS Phase 5.
## 4.2 — Livrables
Note : relation documents() ajoutée sur Project, Task et ProjectMilestone. Interface jalons prévue lors de la GED Phase 5.

# 5. Gantt interactif — Drag & Drop
Les barres SVG des tâches dans le Gantt sont maintenant déplaçables horizontalement. Un glisser-déposer décale automatiquement start_date et due_date.
## 5.1 — Fonctionnement
- Barres enrichies avec data-task-id, data-project-id, data-start, data-due, data-x1, data-bw, data-day-width, data-view-start, data-can-edit
- mousedown → mémorise position de départ
- mousemove → déplace la barre en temps réel + tooltip flottant avec nouvelles dates
- mouseup → si déplacement ≥ 0,5 jour : PATCH /projects/{id}/tasks/{id}/dates
- Clic simple sans drag → ouvre le slideover tâche normalement
- Zoom pris en compte dans le calcul pixels → jours
- Échec PATCH → barre remise à sa position d’origine
## 5.2 — Prérequis
La tâche doit avoir start_date ET due_date renseignées pour apparaître dans le Gantt. Le drag est désactivé (curseur pointer) si can-edit = 0.

# 6. Dashboard — Widgets projets

# 7. Politique de mots de passe — Interface admin
La page Admin → Paramètres → Sécurité a été entièrement refaite. Elle couvre désormais 4 sections :
- 🔑 Politique des mots de passe — longueur minimale, historique, expiration (jours), 3 cases de complexité (Majuscules, Chiffres, Caractères spéciaux)
- ⏱ Sessions — durée d’inactivité avec conversion automatique heure/jour
- 🔒 Verrouillage de compte — tentatives max + durée
- 📱 2FA obligatoire — toggle avec explication

Un bandeau récapitulatif en bas de page affiche la politique active avec des badges colorés. Le contrôleur updateSecurity() a été enrichi pour valider les 10 paramètres (checkboxes forcées à false si non cochées, pwd_validity_days=0 converti en NULL).

# 8. Jalons — Bouton Atteint / Annuler
Deux boutons ajoutés sur chaque jalon (enfant et autonome) dans la vue But & description :
- ✅ Atteint (fond vert) — visible si jalon non atteint, confirmation requise → pose reached_at = now()
- ↩ Annuler (fond jaune) — visible si jalon atteint, confirmation requise → remet reached_at = null

Le contrôleur ProjectMilestoneController::update() gère désormais les deux cas : reached=1 (atteinte) et reached=0 (annulation).

# 9. Productions documentaires

# 10. État CI/CD — fin de session

# 11. Prochaines étapes
## Phase 4 — Photothèque NAS (Avr–Mai 2026)
- Image de couverture sur les albums
- Partage par lien temporaire
- Export ZIP d’un album
- Tri par date EXIF
- Détection doublons inter-albums
- Recherche dans la photothèque
## Gestion de projet — éléments restants
- Tests manquants à intégrer au CI (ProjectBudgetTest, ProjectChangeTest...)
- Conduite du changement — refonte visuelle matrice impact/adhésion
## Infrastructure
- Index BDD sur department_id + tenant
- Purge sessions expirées — Artisan planifié
- Cache thumbnails — Cache-Control renforcé
## À planifier — Fonctionnalités IA (post Phase 6)
- Assistant IA contextuel — avatar bas-droite, guide utilisateur pas à pas (ex: « comment mettre des photos »)
- Recherche sémantique — retrouver photos par description visuelle (LLaVA) et documents par contenu (Mistral)
- Version gratuite : Ollama + Mistral 7B + LLaVA self-hosted — version payante : API Anthropic par tenant

# 12. Bonnes pratiques consolidées


Les Bézots — Soullans (85) — github.com/jpbosse/pladigit — AGPL-3.0