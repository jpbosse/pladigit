# ADR-011 — Double couche d'autorisation : UserRole + ProjectRole

**Date :** Novembre 2025
**Statut :** Accepté

## Contexte

Une organisation publique a une hiérarchie rigide (DGS > Resp. Direction > Resp. Service > Agent) mais les projets peuvent impliquer des membres de services différents avec des niveaux d'accès spécifiques au projet. Un modèle de rôle unique ne peut pas couvrir les deux dimensions.

## Décision

Deux couches d'autorisation coexistent :

**1. `UserRole` (global, par tenant) :** `Admin`, `President`, `DGS`, `RespDirection`, `RespService`, `Agent`. Attribué à l'utilisateur lors de son invitation. Contrôle l'accès aux fonctionnalités d'administration et aux ressources globales (albums photos, documents GED).

**2. `ProjectRole` (par projet) :** `Manager`, `Member`, `Viewer`. Attribué lors de l'ajout d'un membre au projet via `project_members`. Contrôle les actions sur les tâches, commentaires, documents du projet.

Les policies Laravel (`ProjectPolicy`, `TaskPolicy`, `MediaAlbumPolicy`, `MediaItemPolicy`) orchestrent ces deux couches : un `Admin` peut toujours tout faire ; un `Agent` ne peut agir sur un projet que si son `ProjectRole` le permet.

## Conséquences

- **Avantage :** flexibilité — un agent peut être Manager d'un projet sans avoir de rôle global élevé.
- **Contrainte :** deux vérifications sont nécessaires dans les policies, ce qui les rend légèrement plus complexes.
- L'héritage de droits pour les sous-albums (photothèque) est géré séparément via `album_permissions` et `album_user_permissions` (Phase 5).
