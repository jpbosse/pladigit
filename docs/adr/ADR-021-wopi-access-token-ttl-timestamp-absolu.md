# ADR-021 — WOPI : access_token_ttl est un timestamp Unix absolu en millisecondes

**Date :** Avril 2026
**Statut :** Accepté

---

## Contexte

La spec WOPI définit `access_token_ttl` comme la valeur à passer dans le formulaire POST vers Collabora pour indiquer l'expiration du token. Une implémentation naïve envoie une durée en millisecondes (ex : `1800 * 1000 = 1 800 000`).

## Problème rencontré

Collabora interprète `access_token_ttl` comme un **timestamp Unix absolu en millisecondes**, pas une durée. Envoyer `1 800 000` signifie "expire le 01/01/1970 à 00h30" → session immédiatement invalide dès l'ouverture de l'éditeur.

## Décision

`WopiTokenService::ttlMs()` retourne `now()->addSeconds($ttl)->timestamp * 1000`, soit l'instant d'expiration futur exprimé en ms depuis l'epoch Unix.

```php
public function ttlMs(): int
{
    $ttl = (int) config('collabora.token_ttl', 14400);
    return (int) (now()->addSeconds($ttl)->timestamp * 1000);
}
```

Le TTL par défaut est 14 400 secondes (4 heures), configurable par tenant via `TenantSettings::collabora_token_ttl_minutes`.

## Conséquences

- Sessions Collabora valides pendant 4h par défaut (au lieu de quelques secondes).
- Toute future intégration WOPI doit respecter ce format — à documenter dans les tests et la revue de code.
