# ADR-003 — TDD Partiel dès la Phase 1

**Date :** Octobre 2025  
**Statut :** Accepté

## Décision

TDD partiel : les tests sont écrits AVANT le code pour les modules critiques. Couverture cible ≥ 90 % sur authentification et isolation multi-tenant. Pipeline CI/CD avec GitHub Actions déclenché à chaque push — aucun merge sans tests verts.

## Conséquences

Les tests ralentissent le développement de ~20 % en Phase 1 mais réduisent le temps de débogage de 60 %+ dans les phases suivantes.
