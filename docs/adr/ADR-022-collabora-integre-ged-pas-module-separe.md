# ADR-022 — Collabora Online intégré à GED, pas de module séparé

**Date :** Avril 2026
**Statut :** Accepté

---

## Contexte

`ModuleKey::COLLABORA` existe dans l'enum mais l'interface super-admin ne propose pas d'activer/désactiver COLLABORA indépendamment de GED. La question s'est posée de savoir si Collabora devait être un module autonome ou une fonctionnalité du module GED.

## Décision

Collabora Online est **une fonctionnalité du module GED**, pas un module indépendant. La règle est :

> Si GED est activé ET `config('collabora.url')` est non vide → Collabora est disponible.

Le bouton "Ouvrir dans Collabora" est donc conditionné par `GedDocument::isCollaboraSupported()` (vérifie l'URL + le type MIME), pas par un module séparé. L'URL peut être configurée par tenant via la page Admin > GED > Collabora.

## Raisons

1. **Pas de super-admin** pour activer COLLABORA tenant par tenant — c'est l'URL qui fait office d'interrupteur.
2. **Cohérence** — un utilisateur qui a GED mais pas Collabora voit simplement que le bouton n'apparaît pas, sans erreur.
3. **Simplicité opérationnelle** — une seule instance Collabora par déploiement Pladigit est le cas le plus courant.

## Conséquences

- La route `ged/documents/{id}/editor` est protégée par `middleware('module:ged')` — pas besoin de `module:collabora`.
- WOPI routes (`/wopi/files/*`) sont publiques par design : Collabora les appelle depuis son serveur, le token WOPI est le mécanisme de sécurité.
- Si un tenant ne doit pas accéder à Collabora : laisser `collabora_url` vide dans ses settings.
- `ModuleKey::COLLABORA` reste dans l'enum pour usage futur éventuel (facturation différenciée, activation super-admin, etc.).
