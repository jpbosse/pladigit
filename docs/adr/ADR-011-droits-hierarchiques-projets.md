# ADR-011 — Droits hiérarchiques : Resp. en lecture seule sur projets non-privés

**Date :** Décembre 2025
**Statut :** Accepté

## Contexte

Un Responsable de Direction ou de Service doit pouvoir suivre l'avancement de tous les projets rattachés à son périmètre, même s'il n'y est pas explicitement membre. Lui imposer d'être ajouté manuellement à chaque projet crée une charge administrative et un risque d'oubli.

## Décision

Les utilisateurs avec le rôle `RespDirection` ou `RespService` obtiennent automatiquement un accès **lecture seule** (`Viewer` implicite) sur tous les projets non marqués `is_private` qui appartiennent à leur direction ou service.

Les projets marqués `is_private` sont visibles uniquement par leurs membres explicites et les Admins — aucune exception hiérarchique.

Cette règle est implémentée dans `ProjectPolicy::view()` et dans le scope `scopeVisibleFor()` du modèle `Project`.

## Conséquences

- Un Resp. Service voit immédiatement tous les projets de son service sans configuration manuelle.
- La confidentialité des projets sensibles est garantie par le flag `is_private`.
- Si un Resp. a besoin d'agir (créer des tâches, commenter), il doit être ajouté explicitement comme `Member` ou `Manager` par le Manager du projet.
