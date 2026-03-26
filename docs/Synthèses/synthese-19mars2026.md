

# 1. Résumé exécutif
Cette session a couvert la finalisation des fonctionnalités de la Phase 8 (Gestion de projet) de Pladigit, avec 8 livraisons majeures, plusieurs corrections, et une mise en conformité complète du pipeline CI/CD (tests, PHPStan, Pint).


# 2. ADR-011 — Droits hiérarchiques projets
Implémentation d'une troisième couche de droits sur les projets, calquée sur le modèle AlbumPermissionService de la Photothèque (Phase 3).

## 2.1 — Trois couches cumulatives

## 2.2 — Projet privé
Un projet marqué is_private = true bloque la couche hiérarchique. Seuls les membres explicitement nommés y ont accès. Admin/DGS voient tout, privé ou non.

• Migration : 2026_10_01_000033_add_is_private_to_projects.php
• ProjectPolicy::view() — vérification is_private avant couche hiérarchique
• Project::scopeVisibleFor() — exclut les projets privés du scope hiérarchique
• Interface : toggle dans Créer/Modifier avec explication Normal vs Privé
• Badge 🔒 Privé dans l'index et "But & description" avec tooltip

## 2.3 — Fichiers modifiés

# 3. Export PDF + ZIP élus
Génération du tableau de bord élus en PDF (DomPDF) et en archive ZIP contenant le PDF + fichiers CSV. Accessible depuis l'onglet "Tableau de bord élus".

## 3.1 — Prérequis
• composer require barryvdh/laravel-dompdf

## 3.2 — Contenu du ZIP

## 3.3 — Routes ajoutées
• GET /projects/{project}/export/pdf → ProjectController::exportPdf()
• GET /projects/{project}/export/zip → ProjectController::exportZip()

# 4. ProjectTemplate + Duplication de projet

## 4.1 — Modèles de projets (ProjectTemplate)
Capture la structure type d'un projet (phases, jalons, tâches) pour la réutiliser. Les dates sont calculées par offset en jours depuis la date de démarrage choisie.

• Migration : 2026_10_01_000031_create_project_templates_table.php (déjà en place)
• app/Models/Tenant/ProjectTemplate.php — applyTo(), milestoneCount(), taskCount()
• app/Http/Controllers/Projects/ProjectTemplateController.php — CRUD + apply() + fromProject()
• Builder visuel Alpine.js : ajouter phases/jalons/tâches avec offset J+N
• Accès : Resp. Direction et au-dessus

## 4.2 — Duplication de projet
Copie la structure d'un projet existant (phases, jalons, tâches) vers un nouveau projet. Les dates sont décalées depuis la nouvelle date de démarrage. Budget, risques et observations ne sont pas copiés.

• ProjectController::duplicate() — deux passes (parents puis enfants)
• Modal dans Modifier le projet → onglet Actions
• "Sauvegarder comme modèle" — capture la structure en template depuis l'onglet Actions

## 4.3 — Commandes Artisan

# 5. Refonte interface

## 5.1 — Page Modifier le projet — 4 onglets

## 5.2 — Modals avec bandeau coloré
Toutes les modals du module projet ont été refactorées avec un en-tête coloré (titre blanc) sans dépendance au build Vite — styles inline uniquement.


## 5.3 — Autres corrections UI
• pd-card — background: var(--pd-navy-dark) → var(--pd-surface) (toutes les cartes étaient sur fond sombre)
• Observations — x-data restructuré (div flex ne contenait plus la liste)
• Dates Modifier projet — grid inline 1fr 1fr au lieu de pd-form-row-2 (classe non compilée)
• Formulaire ajout membre — gap:12px inline au lieu de pd-form-row-3
• Tableau des rôles dans l'onglet Membres — label() + description() depuis l'enum

# 6. État des tests — fin de session

## 6.1 — Évolution

## 6.2 — Pipeline CI/CD

# 7. Bonnes pratiques consolidées


# 8. Prochaines étapes — backlog Phase 8

## 🔴 Priorité haute
• Notifications événements projet — modèle, listeners, UI cloche (~3h)
• Conduite du changement — refonte visuelle matrice impact/adhésion, timeline comm, quadrants risques

## 🟡 Tests manquants
• ProjectBudgetTest
• ProjectStakeholderTest
• ProjectChangeTest
• ProjectObservationTest
• WorkloadViewTest

## 🔵 Phase 12 — avant production
• Rapport PDF — vue tableau de bord élus finalisée (DomPDF installé ✓)
• Rotation audit_logs > 12 mois
• CSP + headers sécurité complets
• Rate limiting sous-domaines
• Rotation clés AES TOTP/LDAP

Pladigit — Les Bézots, Soullans (85) — github.com/jpbosse/pladigit — AGPL-3.0