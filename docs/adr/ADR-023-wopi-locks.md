# ADR-023 — Verrous WOPI pour l'édition simultanée (GedWopiLock)

**Date :** Avril 2026  
**Statut :** Accepté et livré

## Contexte

Sans mécanisme de verrou, deux utilisateurs ouvrant le même document dans Collabora Online simultanément peuvent s'écraser mutuellement silencieusement. La spec WOPI définit un protocole de verrouillage via les opérations LOCK, UNLOCK, REFRESH_LOCK et GET_LOCK dispatché sur l'header `X-WOPI-Override`.

## Décision

Implémenter les verrous WOPI via une table dédiée `ged_wopi_locks` et un service `WopiLockService`.

**Table `ged_wopi_locks` :**
- `document_id` — clé unique (un seul verrou actif par document)
- `lock_id` — identifiant fourni par Collabora (string jusqu'à 1024 chars)
- `expires_at` — expiration automatique (TTL configurable, défaut 30 min)
- `locked_by` — utilisateur détenteur du verrou

**Sémantique implémentée :**
- `LOCK` — crée le verrou si absent, rafraîchit le TTL si même `lock_id`, retourne 409 si `lock_id` différent
- `UNLOCK` — libère le verrou si `lock_id` correspond
- `REFRESH_LOCK` — prolonge l'expiration
- `GET_LOCK` — retourne le `lock_id` courant via header `X-WOPI-Lock`

Les verrous expirés sont purgés automatiquement avant chaque opération WOPI sur le document.

`checkFileInfo` retourne `"SupportsLocks": true` — Collabora active son comportement collaboratif.

## Conséquences

- Deux utilisateurs ne peuvent plus écraser leur travail mutuellement — Collabora affiche une alerte de conflit.
- Un verrou abandonné (session fermée sans UNLOCK) expire après le TTL et libère automatiquement le document.
- Le TTL de 30 min est distinct du TTL du token WOPI (4h) — configurable séparément via `collabora.lock_ttl`.
