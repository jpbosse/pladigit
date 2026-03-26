PLADIGIT
Synthèse Technique — Session du 19 Mars 2026 (suite)
Phase 8 — Gestion de projet | CI/CD : 471 tests / 997 assertions ✓

# 1. Résumé exécutif
Cette session (suite de la journée du 19 mars) a été consacrée à la correction de bugs critiques sur le module Gestion de projet, notamment un crash Alpine global causé par un div orphelin dans l'onglet Agenda. Plusieurs fonctionnalités ont également été finalisées.


# 2. Corrections critiques
## 2.1 — Crash Alpine global — div orphelin dans _agenda.blade.php
Symptôme : toutes les sections du projet (Risques, Plan de comm, Charge, etc.) disparaissaient. Les erreurs Alpine indiquaient showAll, tab, showEventForm, editEvent non définis.

Cause racine : un </div> en double dans la section événements à venir (lignes 123-124) fermait prématurément le x-data racine de l'agenda à la ligne 126. Tout le contenu suivant (modals, onglet Charge, workload) se retrouvait hors scope Alpine.

Méthode de diagnostic : trace ligne par ligne des profondeurs d'imbrication <div> via script Python — depth tombant à 0 avant la fin du fichier, puis à -1 en fin de fichier.

Correction : suppression du </div> orphelin ligne 123 + ajout du </div> fermant le x-data racine en fin de fichier.


## 2.2 — JS orphelin dans _kanban.blade.php
Un fragment de code JS (let orderedIds = []; ... this.patchMove(...)) était positionné entre les méthodes onDrop et patchMove de l'objet Alpine kanban(), hors de toute fonction. Ce code syntaxiquement invalide en position d'objet littéral causait l'erreur Unexpected identifier 'orderedIds' bloquant tout Alpine.

## 2.3 — Conflit guillemets dans fetch()
Le second bloc <script> natif utilisait fetch('{{ route(...) }}') — les guillemets simples de la directive Blade s'imbriquaient dans les guillemets simples JS. Corrigé en utilisant des backticks : fetch(`{{ route(...) }}`).

## 2.4 — x-model avant x-data dans _workload.blade.php
Le checkbox x-model='showAll' était à la ligne 58 mais le x-data='{ showAll: false }' seulement à la ligne 78. Alpine ne pouvait pas résoudre la variable. Tout le partial workload a été enveloppé dans un seul x-data dès la première ligne.

# 3. Nouvelles fonctionnalités
## 3.1 — Slideover événement Agenda
Le slideover de visualisation d'événement a été extrait de _agenda.blade.php vers un partial indépendant _event_slideover.blade.php, inclus directement dans show.blade.php hors des onglets — même pattern que _task_slideover.blade.php.

Comportement : clic sur un événement → slideover s'ouvre depuis la droite avec bandeau coloré (couleur de l'événement), horaires, lieu, description, visibilité, créateur. Bouton Modifier visible uniquement pour le créateur.


## 3.2 — Modal édition phases/jalons
Ajout d'une modal d'édition (titre, dates début/fin, couleur) sur chaque phase, jalon enfant et jalon autonome. La couleur utilise des pastilles 38px avec sélection en JS natif (pas Alpine) pour éviter les conflits de scope.
Validation côté serveur : si la nouvelle due_date est antérieure à la date de la tâche la plus tardive du jalon, le serveur refuse avec un message explicite nommant la tâche en conflit.

## 3.3 — Gestion des onglets planification
Onglet par défaut changé de Kanban vers Liste. Ordre des onglets : Liste → Kanban → Gantt → Agenda → Charge. Méthode switchTab() qui dispatche close-event-slideover à chaque changement d'onglet pour fermer automatiquement le slideover agenda.

## 3.4 — Module projects
Correction du doublon require routes/projects.php dans web.php (était inclus deux fois : une fois dans le groupe module:media et une fois correctement). ModuleKey::isAvailable() mis à jour pour déclarer explicitement MEDIA et PROJECTS comme disponibles.

# 4. Bonnes pratiques consolidées


# 5. Prochaines étapes
## 5.1 — Phase 8 restante
Notifications événements projet — modèle, listeners, UI cloche (~3h)
Conduite du changement — refonte visuelle : matrice impact/adhésion, timeline comm, quadrants risques
Rapport PDF — vue tableau de bord élus finalisée (DomPDF installé)

## 5.2 — Tests manquants
ProjectBudgetTest, ProjectStakeholderTest, ProjectChangeTest, ProjectObservationTest, WorkloadViewTest, ProjectPolicyTest (3 nouvelles méthodes)

## 5.3 — Infrastructure
Index BDD sur department_id + tenant
Purge sessions expirées — Artisan planifié
Cache thumbnails — Cache-Control renforcé

Pladigit — Les Bézots, Soullans (85) — github.com/jpbosse/pladigit — AGPL-3.0