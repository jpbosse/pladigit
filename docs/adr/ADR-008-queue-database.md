# ADR-008 — File de travail sur MySQL (pas Redis queues)

**Date :** Mars 2026
**Statut :** Accepté

## Contexte

Le traitement des uploads médias (génération de miniature, extraction EXIF, calcul SHA-256) est asynchrone — il ne doit pas bloquer la réponse HTTP. Deux options principales existent : Redis (via Laravel Horizon) ou la table `jobs` MySQL.

Redis est déjà utilisé pour les sessions. Ajouter Horizon pour les queues complexifierait le déploiement (daemon PHP supplémentaire, supervision Supervisor, configuration Horizon) pour un volume de jobs qui reste modeste (quelques centaines de médias par jour au maximum).

## Décision

Utiliser le driver `database` pour les queues. Les jobs sont stockés dans la table `jobs` de la base tenant. Les jobs échoués atterrissent dans `failed_jobs`. Le worker est démarré par le cron système (`php artisan queue:work --stop-when-empty`) ou un service Systemd minimal.

Configuration : `tries=3`, `timeout=120s`, retry after 90s.

## Conséquences

- **Avantage :** déploiement minimal, pas de dépendance supplémentaire, visibilité directe des jobs via SQL.
- **Contrainte :** débit limité — pas adapté à un volume de milliers de jobs/minute. Acceptable pour le profil d'usage prévu (collectivités de 50 à 500 agents).
- Si le volume venait à dépasser quelques milliers de jobs/heure, la migration vers Horizon serait transparente : seul le driver change dans `.env`.
