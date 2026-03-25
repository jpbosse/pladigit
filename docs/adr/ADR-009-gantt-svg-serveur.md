# ADR-009 — Gantt généré en SVG côté serveur

**Date :** Décembre 2025
**Statut :** Accepté

## Contexte

Un diagramme de Gantt est indispensable pour la gestion de projet. Les librairies JavaScript spécialisées (dhtmlxGantt, Frappe Gantt, Google Charts) apportent des fonctionnalités riches mais introduisent des dépendances JS lourdes, des licences propriétaires ou des contraintes de rendu incompatibles avec l'architecture Blade/Alpine.js choisie (ADR-001).

## Décision

Le Gantt est généré **côté serveur en SVG pur** par un service PHP dédié. Chaque tâche et jalon est rendu comme un élément `<rect>` ou `<line>` SVG calculé à partir des dates. Le SVG est injecté directement dans la page Blade.

Le drag & drop des barres (déplacement de dates) a été ajouté en Mars 2026 via des listeners Alpine.js qui envoient les nouvelles dates au serveur via `fetch()` — sans rechargement de page.

## Alternatives écartées

- **dhtmlxGantt :** licence commerciale pour les fonctionnalités avancées.
- **Frappe Gantt :** open source mais rendu client uniquement, difficile à intégrer avec le cycle de rendu Blade.
- **Canvas HTML5 :** moins accessible, non sélectionnable, export PDF complexe.

## Conséquences

- **Avantage :** rendu identique pour l'export PDF (DomPDF consomme directement le SVG).
- **Avantage :** aucune dépendance JS supplémentaire.
- **Contrainte :** les interactions complexes (resize des barres, liens de dépendance visuels) sont plus laborieuses à implémenter en SVG+Alpine qu'avec une librairie dédiée.
