# ADR-001 — Stack Frontend : Livewire 3 + Alpine.js

**Date :** Octobre 2025  
**Statut :** Accepté

## Contexte

Le projet est développé par un unique développeur. Le frontend doit être réactif et moderne sans introduire la complexité d'un framework SPA (React, Vue, Angular) qui nécessite une expertise JS avancée et un pipeline de build séparé.

## Décision

Utiliser **Livewire 3** pour les composants réactifs côté serveur et **Alpine.js** pour les interactions légères côté client. Les templates Blade de Laravel sont utilisés pour le rendu. Laravel Echo + Soketi gèrent le temps réel pour le chat (Phase 9).

## Conséquences

- **Avantage :** stack homogène Laravel, courbe d'apprentissage faible, déploiement simple.
- **Contrainte :** les interfaces très riches (éditeur type Notion) nécessiteront Alpine.js avancé ou des composants Livewire optimisés.
- Pas de framework mobile natif — la responsivité repose entièrement sur le CSS.
