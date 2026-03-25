# ADR-006 — Hiérarchie organisationnelle : Direction > Service > Agent

**Date :** Novembre 2025
**Statut :** Accepté

## Contexte

Les organisations clientes (mairies, communautés de communes) ont une hiérarchie interne rigide : des Directions qui chapeautent des Services, eux-mêmes composés d'Agents. Cette structure doit se refléter dans l'application pour filtrer les contenus, calculer les droits et afficher des vues managériales adaptées.

## Décision

La hiérarchie est modélisée par deux tables dans la base tenant :

- **`departments`** : table auto-référencée (`parent_id`) permettant de représenter aussi bien une Direction (niveau 0) qu'un Service (niveau 1). La colonne `type` distingue les deux niveaux.
- **`user_department`** (table pivot) : rattache un utilisateur à un département avec le flag `is_manager` pour identifier le responsable.

Un utilisateur peut appartenir à plusieurs départements (multi-appartenance), ce qui couvre les cas d'agents transversaux ou de postes à cheval sur plusieurs services.

## Conséquences

- Les droits sur les projets et documents peuvent être filtrés par direction ou service sans requête supplémentaire.
- Le flag `is_manager` dans le pivot remplace un rôle dédié "Chef de service" — plus flexible.
- La profondeur est limitée à 2 niveaux (Direction > Service) pour correspondre aux structures réelles des collectivités. Une hiérarchie à 3 niveaux ou plus n'est pas supportée.
