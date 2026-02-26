# ADR-002 — Multi-tenant : base MySQL dédiée par organisation

**Date :** Octobre 2025  
**Statut :** Accepté

## Contexte

Plusieurs modèles de multi-tenancy existent : colonne `tenant_id` partagée (moins sûr), schéma séparé, ou base dédiée. La plateforme vise des organisations du secteur public qui exigent la garantie absolue que leurs données ne sont jamais mélangées.

## Décision

Chaque organisation cliente dispose d'une base MySQL physiquement distincte (`pladigit_{slug}`). Un `TenantManager` interne, développé sans librairie tierce, résout le tenant depuis le sous-domaine et bascule la connexion Eloquent à chaque requête.

## Conséquences

- **Isolation maximale** — une bug dans une requête ne peut pas affecter les données d'un autre tenant.
- **Inconvénient :** chaque nouvelle organisation provisionne une base complète, ce qui demande quelques secondes.
- La gestion de plusieurs centaines de tenants nécessitera une revue de l'orchestration en Phase 10.
