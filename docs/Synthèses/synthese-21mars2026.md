PLADIGIT
Plateforme de Digitalisation Interne
Alternative souveraine open source AGPL-3.0


# 1. Résumé exécutif
Cette session a couvert cinq livraisons majeures sur le module Gestion de projet (Phase 8) : les dépendances Fin→Fin entre tâches, l'export iCal des jalons pour les élus, la correction du délai dans le tableau de bord, l'historique d'activité du projet, et le branchement de tous les contrôleurs sur le format de log attendu par l'historique.



# 2. Dépendances Fin→Fin entre tâches
Implémentation des dépendances de type Fin→Fin : une tâche B ne peut être marquée "Terminée" tant qu'une tâche A dont elle dépend n'est pas terminée. La table task_dependencies et les relations BelongsToMany étaient déjà en place depuis la Phase 8 initiale.

## 2.1 — Fichiers créés / modifiés

## 2.2 — Comportement
• Slideover tâche → section 🔗 Dépendances : liste des prédécesseurs (✅/⏳), bouton + Ajouter avec sélecteur pleine largeur, suppression par ✕
• PATCH /tasks/{task} avec status=done → 422 JSON si un prédécesseur n'est pas done, message nommant la tâche bloquante
• Gantt : barres bloquées en gris clair avec tirets, libellé ⏳ Bloquée, icône 🔒 dans la colonne titre
• Détection de cycle (BFS) et auto-dépendance bloquées côté serveur
• Dépendance inter-projet refusée (firstOrFail sur project_id)

## 2.3 — Routes ajoutées


# 3. Export iCal jalons
Nouvelle route GET /{project}/export/milestones.ics générant un fichier .ics contenant uniquement les jalons du projet, destiné aux élus pour import dans Outlook / Google Calendar.

## 3.1 — Contenu du fichier .ics
• Jalons normaux → 🏁, STATUS:IN-PROCESS, CATEGORIES:Jalon
• Jalons atteints → ✅, STATUS:COMPLETED + timestamp COMPLETED:, CATEGORIES:Jalon Atteint
• Jalons en retard → ⚠, CATEGORIES:Jalon En retard
• Phases → 📌, CATEGORIES:Phase
• PERCENT-COMPLETE branché sur progressionPercent()
• Plage de dates si start_date renseignée, sinon all-day sur due_date
• X-WR-CALNAME pour nom de calendrier propre dans Outlook/Google

## 3.2 — Fichiers


# 4. Historique d'activité projet
Fil chronologique des actions sur un projet, chargé en AJAX depuis la sidebar. Visible par tous les membres (leurs propres actions) et par les owners/managers (tout l'historique).

## 4.1 — Fichiers créés / modifiés

## 4.2 — Comportement
• Chargement AJAX au premier clic sur Historique — pas de rechargement de page
• Chargement automatique si URL contient ?section=historique au démarrage
• Filtre par type d'action : select Alpine → événement load-history → rechargement AJAX
• Groupement par jour avec séparateurs, icône colorée par type, auteur de l'action
• Pagination 25/page avec liens ‹ / › inline dans le HTML retourné
• Membres voient leurs propres actions — owners, DGS, resp. direction voient tout

## 4.3 — 15 types d'actions affichés


# 5. Branchement audit — format new_values
L'AuditService stocke new_values uniquement si $extra['new'] est présent. Les contrôleurs passaient les données directement dans $extra sans la clé 'new', rendant new_values null et les logs invisibles dans l'historique. Correction appliquée sur 12 appels dans 5 contrôleurs.


Format corrigé : $this->audit->log('action', $user, ['new' => ['project_id' => $project->id, ...]]);


# 6. Bonnes pratiques consolidées


# 7. État CI/CD — fin de session
Note : les tests TaskDependencyTest et ProjectMilestonesIcalTest ont été produits dans cette session mais pas encore intégrés dans le pipeline CI — à ajouter lors de la prochaine session.


# 8. Prochaines étapes
## Phase 8 — Gestion de projet (éléments restants)
• Notifications événements projet — modèle, listeners, UI cloche (~3h)
• Conduite du changement — refonte visuelle : matrice impact/adhésion, timeline comm, quadrants risques
• Tests manquants : ProjectBudgetTest, ProjectStakeholderTest, ProjectChangeTest, ProjectObservationTest, WorkloadViewTest
• Intégrer TaskDependencyTest et ProjectMilestonesIcalTest dans le pipeline CI

## Phase 3 — Photothèque (reprises)
• Image de couverture albums
• Partage par lien temporaire
• Export ZIP d'un album

## Infrastructure
• Index BDD sur department_id + tenant
• Purge sessions expirées — Artisan planifié
• Cache thumbnails — Cache-Control renforcé