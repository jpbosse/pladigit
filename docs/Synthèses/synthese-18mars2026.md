PLADIGIT
Plateforme de Digitalisation Interne
Alternative souveraine open source AGPL-3.0
Synthèse Technique — Session du 19 Mars 2026



# 1. Résumé exécutif
Cette session a couvert la correction de la cause racine du CSS (Tailwind Preflight), la refonte visuelle complète des formulaires et vues de planification, et la mise en service de la récurrence des tâches de bout en bout.



# 2. Correction CSS — cause racine identifiée
## 2.1 — Problème
Toutes les classes pd-input, pd-btn, pd-modal-* étaient définies dans pladigit.css mais visuellement sans effet. Les formulaires s'affichaient sans style — inputs sans bordure, boutons sans fond, selects sans flèche.

## 2.2 — Cause racine
Dans app.css, l'instruction @import 'pladigit.css' était placée avant @tailwind base. Tailwind Preflight (le reset CSS global intégré à @tailwind base) s'exécutait ensuite et écrasait l'intégralité des styles des inputs, selects et boutons définis dans pladigit.css.

## 2.3 — Solution
pladigit.css déclaré comme entry point Vite indépendant dans vite.config.js (input séparé de app.css). Le layout app.blade.php charge les deux fichiers séparément via @vite. pladigit.css est compilé sans passer par le pipeline Tailwind, ses styles s'appliquent en dernier et ne peuvent plus être écrasés.

Fichiers modifiés :
- vite.config.js — ajout de resources/css/pladigit.css dans input[]
- resources/css/app.css — suppression du @import pladigit.css
- resources/views/layouts/app.blade.php — @vite avec les deux fichiers
- resources/css/pladigit.css — 350 lignes ajoutées (formulaires, modals, slide-overs)


# 3. Refonte visuelle des formulaires
## 3.1 — Classes unifiées dans pladigit.css
Source unique de vérité pour tous les éléments de saisie :
- pd-form-group, pd-form-row-2/3/4 — structure et grilles
- pd-label, pd-label-req — labels avec indicateur obligatoire (*)
- pd-input — inputs, selects, textareas avec hover/focus/invalid animés
- pd-error, pd-hint, pd-form-section, pd-form-actions — feedback et séparateurs
- pd-modal-overlay/modal/modal-header/modal-body/modal-footer — modals
- pd-slideover-overlay/panel/header/body/footer — panneaux latéraux
- pd-btn, pd-btn-primary/secondary/danger, pd-btn-sm/xs — boutons

## 3.2 — Fichiers refactorisés
- create.blade.php — structure en cartes avec titres de section, grilles 2 colonnes
- edit.blade.php — même structure + zone danger distincte + membres
- _task_slideover.blade.php — entièrement réécrit, champs récurrence ajoutés
- _finances.blade.php — modal corrigée avec pd-modal-header/body/footer
- _stakeholders.blade.php, _comcom.blade.php, _risques.blade.php — idem


# 4. Refonte des vues de planification
## 4.1 — Principe commun
Toutes les vues (Kanban, Gantt, Liste, Charge) appliquent désormais la même logique de groupement :
- Jalons atteints — repliés, fond vert, coche ✓
- Jalons en retard — repliés, fond rouge, indicateur !
- Jalon actif (prochain non atteint, due_date future) — déplié automatiquement
- Tâches en cours (in_progress) affichées par défaut — toggle Toutes les tâches

## 4.2 — Kanban
- Groupé par jalon — sections rétractables indépendantes Alpine.js
- 4 colonnes À faire / En cours / En revue / Terminé toujours visibles
- Cartes enrichies : mini barre heures estimées/réalisées, avatar assigné
- Bouton + Ajouter pré-remplit le milestone_id dans le slideover

## 4.3 — Gantt v3
- Mini timeline sur les jalons repliés : barre de durée, progression done, points bleus en cours, losange positionné, ligne rouge aujourd'hui
- Corps déplié : SVG par groupe, tâches en cours uniquement par défaut
- Losange jalon avec ligne pointillée verticale sur toutes les tâches du groupe
- Zoom Alpine.js conservé — stocké en localStorage

## 4.4 — Liste
- Groupée par jalon avec même logique repli/déplié que le Kanban
- Tâches in_progress par défaut, filtre texte force l'affichage
- Point bleu devant chaque tâche en cours

## 4.5 — Agenda
- Séparation À venir / Passés — passés masqués par défaut
- Modal création convertie en pd-modal-* propre

## 4.6 — Charge
- Fenêtre temporelle centrée sur les tâches actives (±2 semaines / +6 semaines)
- Cellules charge hebdomadaire : Normal (bleu) / Chargé 3+ (jaune) / Surchargé 5+ (rouge)
- Barres de tâches en cours en bleu plein, toggle Toutes les tâches pour le reste
- Bug corrigé : calcul diffInDays incorrect qui donnait position 0 pour toutes les barres

## 4.7 — Bandeau heures total
Affiché en haut de la section Planification, sur toutes les vues :
- Xh réalisées / Yh estimées en grand (police 28px)
- Barre de progression avec pourcentage de consommation
- Tout passe en rouge si dépassement, avec mention +Xh dépassement
- Message discret si aucune heure estimée


# 5. Autres corrections et améliorations
## 5.1 — ProjectPolicy complétée
3 méthodes manquantes ajoutées dans app/Policies/ProjectPolicy.php :
- manageBudget — owner uniquement (engagement financier)
- manageStakeholders — owner uniquement (vision stratégique)
- manageChange — owner + member (tout contributeur peut identifier un risque)
Sans ces méthodes, tous les formulaires budget/parties prenantes/risques retournaient HTTP 403 silencieusement.

## 5.2 — Éditeur Trix sur la description projet
- Chargé via CDN jsdelivr (trix@2.0.8) — aucune dépendance Vite
- Barre d'outils : gras, italique, listes, titres, citations
- Contenu stocké en HTML dans la colonne description existante — pas de migration
- Affichage dans _but.blade.php avec {!! !!} et styles .trix-content
- Bug rôle corrigé : \App\Enums\ProjectRole::tryFrom() affiché en texte brut au lieu d'être exécuté


# 6. Récurrence des tâches — mise en service complète
## 6.1 — Composants livrés
- Migration 2026_10_01_000030_add_recurrence_to_tasks — colonnes recurrence_type, recurrence_every, recurrence_ends, recurrence_parent_id
- Task.php — $fillable et $casts mis à jour
- TaskController.php — validation recurrence_type/every/ends dans store() et update()
- GenerateRecurringTasks.php — TenantManager injecté via constructeur (correction appel statique)
- routes/console.php — tâche planifiée quotidiennement à 06h00 avec withoutOverlapping()
- RecurringTasksTest.php — 5 tests : création, hebdo, mensuel, date de fin, anti-doublon

## 6.2 — Bugs corrigés pendant le déploiement
- TenantManager::connectTo() appelé statiquement → injecté via constructeur
- Migration non appliquée sur les tenants → php artisan migrate --database=tenant --path=database/migrations/tenant --force
- Tâche test créée dans pladigit_tenant_template (base par défaut) au lieu de pladigit_demo → connectTo() requis avant create()
- project_id=4 inexistant dans pladigit_demo → id=1 dans cette base

## 6.3 — Validation finale
Commande testée manuellement avec une tâche récurrente weekly créée en tinker dans pladigit_demo — résultat : Occurrences générées : 1, occurrence avec due_date = 25 mars 2026 (aujourd'hui + 7 jours).


# 7. Reste à faire
## 7.1 — Priorité haute
- Conduite du changement — refonte visuelle : matrice impact/adhésion interactive, timeline comm, matrice risques quadrants
- Rapport PDF — DomPDF, vue dédiée tableau de bord élus, route export
- Modèles de projets — ProjectTemplate model + contrôleur + vues

## 7.2 — Tests PHPUnit manquants
- ProjectBudgetTest, ProjectStakeholderTest, ProjectChangeTest, ProjectObservationTest
- ProjectPolicyTest mis à jour avec les 3 nouvelles méthodes
- WorkloadViewTest

## 7.3 — Refonte visuelle restante
- Modals admin : invitation utilisateur, paramètres LDAP, SMTP, branding
- Formulaires photothèque : upload, création album
- CSS à confirmer sur les vues admin

## 7.4 — Documentation
- Synthèse de session Phase 8 complète à produire une fois les tests verts
- CDC v2.0 à mettre à jour avec toutes les fonctionnalités Phase 8


# 8. État CI/CD — fin de session



# 9. Bonnes pratiques consolidées

