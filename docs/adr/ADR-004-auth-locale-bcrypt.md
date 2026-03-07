# ADR-004 — Authentification locale : bcrypt coût 12

**Date :** Octobre 2025  
**Statut :** Accepté

## Décision

Utiliser **bcrypt** avec un coût minimum de 12 pour la Phase 1. Argon2id sera évalué en Phase 10 lors de l'audit de sécurité. La migration est transparente dans Laravel.

## Conséquences

Bcrypt coût 12 prend ~250ms par vérification — acceptable pour un login mais pas pour un brute-force. Les tests PHPUnit utilisent `BCRYPT_ROUNDS=4` pour accélérer la suite de ~85 %.
