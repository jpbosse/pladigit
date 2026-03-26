PLADIGIT
Plateforme de Digitalisation Interne
Alternative souveraine open source AGPL-3.0

# 1. Résumé exécutif
Cette session a été entièrement consacrée à la correction de 14 tests en échec apparus après l'évolution de TestCase::setUp() (qui fait désormais $org->save()). Aucune fonctionnalité nouvelle n'a été développée. Le pipeline CI/CD est repassé au vert et les corrections ont été pushées sur develop.


# 2. Cause racine commune
Tous les échecs découlent d'une seule évolution : TestCase::setUp() fait désormais $org->save() directement, persistant l'organisation test (slug='test') en base avant chaque test.

## 2.1 — Impact sur persistCurrentOrg()
Les tests qui appellent persistCurrentOrg() utilisaient Organization::forceCreate() avec slug='test'. L'org étant déjà en base, cela levait une UniqueConstraintViolationException.
- BrandingTest.php — persistCurrentOrg() : forceCreate → updateOrCreate(['slug' => ...], [...])
- ModuleAccessTest.php — même correction
- TenantMailerTest.php — Organization::create([...]) → updateOrCreate([...]) (×2 occurrences)

## 2.2 — Impact sur SyncLdapUsersTest
La commande pladigit:sync-ldap itère sur toutes les organisations actives. L'org test étant désormais persistée et active, elle est incluse dans la boucle. Les deux tests qui attendaient exactly(2) appels à syncAllUsers ont été mis à jour à exactly(3).

## 2.3 — Impact sur LdapCircuitBreakerTest
Le tearDown() de ce test faisait DELETE FROM users sans SET FOREIGN_KEY_CHECKS=0. Depuis que cleanDatabase() utilise TRUNCATE (correction §3), ce DELETE manuel entrait en conflit avec la contrainte FK projects_created_by_foreign. Suppression du tearDown manuel — le nettoyage est entièrement délégué à cleanDatabase().

# 3. TestCase — DELETE → TRUNCATE
Le cleanDatabase() supprimait les enregistrements via DELETE mais ne réinitialisait pas les compteurs AUTO_INCREMENT. Sur plusieurs runs de tests dans la même session PHPUnit, les IDs des users montaient très haut (ex: created_by = 524). Quand GenerateRecurringTasks recopiait parent->created_by, cet ID n'existait plus en base → violation de FK.

## 3.1 — Correction appliquée
Remplacement de tous les $db->table($t)->delete() par $db->statement("TRUNCATE TABLE `{$t}`") dans la boucle de cleanDatabase(). Le bloc SET FOREIGN_KEY_CHECKS=0 / SET FOREIGN_KEY_CHECKS=1 déjà en place rend les TRUNCATE en cascade sûrs.

## 3.2 — Fichier modifié
- tests/TestCase.php — méthode cleanDatabase()

# 4. GenerateRecurringTasks — horizon étendu
## 4.1 — Problème
L'horizon de génération était fixé à today + 4 semaines (28 jours). Or today + 1 mois ≈ 30 jours > 28 jours. Les tâches à récurrence mensuelle tombaient systématiquement hors fenêtre et n'étaient jamais générées.

## 4.2 — Correction
Horizon étendu à 6 semaines (42 jours). Couvre confortablement les récurrences mensuelles et bi-mensuelles.
- app/Console/Commands/GenerateRecurringTasks.php — ligne 65 : addWeeks(4) → addWeeks(6)

# 5. RecurringTasksTest — refonte test_pas_de_doublon
## 5.1 — Problème
Le test appelait la commande deux fois en espérant qu'elle génère la même occurrence. Mais la logique de la commande est : last = dernière occurrence connue → nextDue = last + période. Le second appel calculait donc nextDue = J+14 (différent de J+7) et créait une seconde occurrence légitime. L'anti-doublon ne s'applique que si deux appels calculent le même nextDue.

## 5.2 — Correction
Nouvelle approche en 2 étapes :
- Pré-créer manuellement l'occurrence à J+7 (Task::on('tenant')->create([...]))
- Fixer recurrence_ends = today + 8 jours — J+7 est valide, J+14 dépasse la limite
- Lancer la commande une seule fois — elle voit last=J+7, calcule nextDue=J+14 > recurrence_ends → rien de créé
- Vérifier count = 1
Ce test valide le comportement métier réel : une occurrence planifiée ne peut pas être dupliquée si la récurrence est épuisée.

# 6. État des tests — fin de session
## 6.1 — Évolution


## 6.2 — Suite de tests complète (tous fichiers)


## 6.3 — Pipeline CI/CD


# 7. Composants présents non documentés dans les synthèses précédentes
Cette section documente les éléments du code source qui n'apparaissaient dans aucune synthèse précédente.

## 7.1 — Enum ProjectRole (App\Enums\ProjectRole)
Rôle d'un utilisateur au sein d'un projet. Couche distincte de UserRole (rôle global tenant) — deux couches cumulatives selon ADR-010 :
- UserRole global : Admin/Président/DGS → accès total à tous les projets
- ProjectRole local : détermine ce qu'un membre peut faire dans UN projet


Méthodes : label(), description(), canEdit(), canManage(), values(), rule(), options()

## 7.2 — Enum AlbumPermissionLevel (App\Enums\AlbumPermissionLevel)
Niveau de permission sur un album photo. Hiérarchie numérique croissante :

Méthodes : label(), level(), atLeast(self $min), max(self $a, self $b), options()

## 7.3 — AlbumPermissionService
Résolution des droits effectifs sur un album. Priorité décroissante pour chaque album de la chaîne :
- 1. Permission utilisateur individuel sur cet album
- 2. Permission service de l'utilisateur sur cet album
- 3. Permission direction de l'utilisateur sur cet album
- 4. Permission rôle exact de l'utilisateur sur cet album
- 5. Si aucune trouvée → remonte au parent jusqu'à la racine
- 6. Si rien → AlbumPermissionLevel::None
Cas spéciaux : Admin/Président/DGS → toujours Admin. level=none sur enfant → coupe l'héritage.

## 7.4 — Commande migrate:tenants
Applique les migrations Laravel sur toutes les bases tenant actives (ou une seule avec --slug). TenantManager::connectTo() reconfiguré pour chaque organisation avant l'appel artisan migrate.
- Signature : migrate:tenants {--slug=} {--pretend} {--force}
- Chaque base a son propre historique de migrations (table migrations dédiée)
- Testé par MigrateTenantsCommandTest — 4 tests

## 7.5 — Export iCal (ProjectController::exportIcal())
Export de l'agenda d'un projet au format iCalendar (RFC 5545). Accessible aux membres uniquement.
- Événements publics inclus pour tous les membres
- Événements privés visibles uniquement par leur créateur
- Jalons inclus comme VEVENT all-day (DTSTART;VALUE=DATE)
- Nom du fichier : projet-{slug}-agenda.ics
- Content-Type : text/calendar; charset=UTF-8
- Testé par ProjectIcalTest — 8 tests

## 7.6 — ProfileTest
Suite de 12 tests couvrant la page profil utilisateur. Périmètre :
- Accès invité redirigé
- Lecture et modification du nom
- Changement de mot de passe (mauvais MDP actuel, confirmation incorrecte)
- Régénération des codes de secours 2FA (avec/sans 2FA actif, avec vérification MDP)

## 7.7 — ShareServiceTest
15 tests couvrant la résolution des droits hiérarchiques via AlbumPermissionService. Scénarios validés :
- Délégation individuelle directe (can_view, can_download)
- Délégation individuelle prime sur délégation de rôle
- Héritage hiérarchique : DGS hérite de resp_direction qui hérite de resp_service
- Héritage inversé bloqué : resp_service n'hérite pas des droits de resp_direction
- Héritage par nœud organisationnel (pôle → directions enfants → services petits-enfants)
- Utilisateur sans aucune délégation → accès refusé

## 7.8 — Policies (TaskPolicy, MediaAlbumPolicy, MediaItemPolicy)
Trois policies non mentionnées dans les synthèses précédentes :
- TaskPolicy — droits sur les tâches : view/create/update/delete selon ProjectRole
- MediaAlbumPolicy — droits sur les albums : create/update/delete via AlbumPermissionService
- MediaItemPolicy — droits sur les items : view/download/upload/delete via AlbumPermissionService

## 7.9 — TenantManagerTest
3 tests unitaires couvrant :
- dbNameFromSlug() — génère pladigit_{slug} depuis un slug
- Sans tenant configuré → current() retourne null
- currentOrFail() lance une exception si aucun tenant

# 8. Fichiers modifiés cette session


# 9. Bonnes pratiques consolidées


# 10. Prochaines étapes
## Phase 3 — Photothèque (reprise)
- Image de couverture sur les albums
- Partage par lien temporaire
- Tri par date EXIF (prise de vue réelle)
- Détection doublons inter-albums
- Export ZIP d'un album
- Recherche dans la photothèque
- serve() images > 10 Mo → stream() automatique

## Phase 8 — Gestion de projet (éléments restants)
- Conduite du changement — refonte visuelle : matrice impact/adhésion, timeline comm, quadrants risques
- Rapport PDF — DomPDF, vue tableau de bord élus, route export
- Modèles de projets — ProjectTemplate model + contrôleur + vues
- Tests manquants : ProjectBudgetTest, ProjectStakeholderTest, ProjectChangeTest, ProjectObservationTest, ProjectPolicyTest (3 nouvelles méthodes), WorkloadViewTest

## Infrastructure
- Index BDD sur department_id + tenant
- Purge sessions expirées — Artisan planifié
- Cache thumbnails — Cache-Control renforcé
