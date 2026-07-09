# ADR-008 — Kanban organisé par jalon (pas par statut global)

**Date :** Décembre 2025
**Statut :** Accepté

## Contexte

La vue Kanban classique présente des colonnes par statut (À faire / En cours / Terminé). Dans le contexte des projets de collectivités (travaux, marchés publics, événements), les projets sont découpés en jalons correspondant à des phases métier distinctes (ex. : "Préparation", "Exécution", "Clôture"). Afficher toutes les tâches dans les mêmes colonnes de statut noie l'information.

## Décision

Le Kanban de Pladigit est organisé **par jalon** : chaque jalon est une colonne, et à l'intérieur de chaque colonne les tâches sont regroupées par statut. Chaque jalon constitue un sprint autonome avec ses propres dates de début et de fin.

## Alternatives écartées

- **Kanban global par statut :** adapté aux équipes produit (Scrum/Kanban pur), mais illisible dès qu'un projet dépasse 20 tâches réparties sur plusieurs phases.
- **Vue par assignataire :** plus pertinente pour la vue "Charge de travail" (disponible séparément).

## Conséquences

- La vue Kanban est plus naturelle pour les chefs de projet des collectivités qui raisonnent par phases.
- Un projet sans jalons affiche un seul "jalon virtuel" pour rester utilisable.
- Le drag & drop entre jalons déplace la tâche vers un autre sprint, ce qui a une signification métier forte — une confirmation est demandée.
