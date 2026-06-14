# ADR-006 — Hiérarchie organisationnelle libre et récursive

**Date :** Novembre 2025 — Mis à jour Mars 2026  
**Statut :** Accepté et livré

## Contexte

Les organisations clientes ont des structures hiérarchiques variées : Directions, Services, Pôles, Comités de pilotage, Bureaux, Cellules, Délégations... La structure ne peut pas être figée à deux niveaux : un comité de pilotage peut être rattaché au Président, un service peut se situer dans une direction qui est elle-même sous une autre direction.

## Décision

Table auto-référencée `departments` avec `parent_id` récursif, sans contrainte de profondeur. La colonne `label` libre permet à l'Admin Organisation de nommer chaque nœud librement : Pôle, Direction, Commission, Comité, Cellule, Bureau, Service, Délégation...

**Colonnes clés :**
- `parent_id` — auto-référence nullable (null = nœud racine)
- `type` — enum direction/service (compatibilité ascendante)
- `label` — string libre
- `color` — couleur hex optionnelle
- `is_transversal` — entité transversale (DSI, communication...)
- `sort_order` — ordre d'affichage

**Modèle Eloquent :**
- `children()` → HasMany récursif
- `allChildren()` → eager-load récursif avec membres et managers
- `ancestors()` → remonte l'arbre jusqu'à la racine
- `parentDept()` → BelongsTo vers le parent direct

**Interface Admin :**
- Vue liste avec arborescence visuelle
- Vue organigramme dédiée (`/admin/departments/organigramme`)
- Labels suggérés depuis les valeurs déjà utilisées dans le tenant
- Sélecteur "Rattaché à" sans contrainte de niveau

## Conséquences

- Profondeur illimitée — n'importe quelle structure réelle est modélisable.
- Multi-appartenance via pivot `user_department` avec flag `is_manager`.
- `is_transversal` couvre les entités hors hiérarchie principale.
- Les droits projets/documents utilisent `department_id` au niveau du nœud direct — pas de remontée récursive automatique dans les droits.
