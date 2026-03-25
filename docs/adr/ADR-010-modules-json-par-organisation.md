# ADR-010 — Modules activés par organisation (colonne JSON)

**Date :** Octobre 2025
**Statut :** Accepté

## Contexte

Pladigit est conçu pour être déployé sur plusieurs organisations avec des besoins différents. Certaines auront besoin de la photothèque, d'autres uniquement de la GED ou des projets. Facturer et exposer uniquement les modules pertinents est un impératif commercial.

## Décision

La table `organizations` (base plateforme) contient une colonne JSON `enabled_modules`. Le middleware `RequireModule` (alias `module`) vérifie la présence du module dans cette liste avant de laisser passer la requête.

Les modules disponibles sont définis dans l'enum `Module` : `MEDIA`, `PROJECTS`, `GED`, `COLLABORA`, `CHAT`, `CALENDAR`, `SURVEY`, `ERP`, `RSS`.

L'activation se fait depuis le super-admin ou via la console d'administration de l'organisation.

## Alternatives écartées

- **Table pivot `organization_modules` :** plus normalisée mais plus verbeux pour des listes courtes et stables. Le JSON est suffisant et lisible directement en base.
- **Feature flags par utilisateur :** trop granulaire pour ce besoin — l'activation est toujours au niveau de l'organisation entière.

## Conséquences

- Les routes non activées retournent `403` immédiatement via le middleware.
- L'ajout d'un nouveau module ne nécessite aucune migration — juste l'ajout d'une valeur dans l'enum et des routes correspondantes.
- La liste des modules activés est mise en cache avec l'objet `Organization` — pas de requête supplémentaire par requête HTTP.
