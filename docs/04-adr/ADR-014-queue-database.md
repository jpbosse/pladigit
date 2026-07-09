# ADR-014 — File de travail asynchrone

**Date :** Mars 2026
**Statut :** Révisé (juillet 2026 — driver `database` → `redis`)

## Contexte

Le traitement des uploads médias (génération de miniature, extraction EXIF, calcul SHA-256) est asynchrone — il ne doit pas bloquer la réponse HTTP. Deux options principales existent : Redis (via Laravel Horizon) ou la table `jobs` MySQL.

Redis est déjà utilisé pour les sessions. Ajouter Horizon pour les queues complexifierait le déploiement (daemon PHP supplémentaire, supervision Supervisor, configuration Horizon) pour un volume de jobs qui reste modeste (quelques centaines de médias par jour au maximum).

## Décision initiale (mars 2026) — driver `database`

> Cette décision a été **révisée en juillet 2026** (voir plus bas). Elle est conservée ici pour l'historique.

Utiliser le driver `database` pour les queues. Les jobs sont stockés dans la table `jobs` de la base tenant. Les jobs échoués atterrissent dans `failed_jobs`. Configuration : `tries=3`, `timeout=120s`, retry after 90s.

## Conséquences de la décision initiale

- **Avantage :** déploiement minimal, pas de dépendance supplémentaire, visibilité directe des jobs via SQL.
- **Contrainte :** débit limité — pas adapté à un volume de milliers de jobs/minute. Acceptable pour le profil d'usage prévu (collectivités de 50 à 500 agents).
- Si le volume venait à dépasser quelques milliers de jobs/heure, la migration vers Redis serait transparente : seul le driver change dans `.env`.

## Révision — Juillet 2026 : passage au driver `redis`

**Décision révisée :** le driver de queue retenu est désormais **`redis`** (`QUEUE_CONNECTION=redis`).

**Réalité du code :** cette révision décrit l'état réellement déployé, pas une intention.
- `.env.example` : `QUEUE_CONNECTION=redis`
- `install.sh`, `INSTALL.md` et le guide des prérequis lancent le worker Supervisor avec `queue:work redis`
- Redis est par ailleurs déjà utilisé pour les sessions (`SESSION_DRIVER=redis`) et le cache (`CACHE_STORE=redis`)

**Justification :** Redis étant déjà présent et supervisé pour les sessions et le cache, faire passer la queue par le même backend évite d'entretenir deux mécanismes distincts (table `jobs` MySQL *et* Redis) et simplifie l'exploitation — un seul service à surveiller pour toute la partie asynchrone. Le point de bascule de la décision initiale (« si le volume dépasse quelques milliers de jobs/heure ») n'a pas eu à être atteint : la mutualisation avec l'infrastructure Redis existante suffit à justifier le choix.

> **Note :** cette justification décrit la logique d'exploitation. Si la motivation réelle du basculement était différente (par exemple un comportement observé de la queue `database`), compléter ce paragraphe en conséquence.

**Conséquences de la révision :**
- **Avantage :** un seul backend asynchrone à opérer et superviser, meilleure latence de prise en charge des jobs qu'avec un polling SQL.
- **Contrainte :** Redis devient une dépendance dure du traitement asynchrone (il l'était déjà pour sessions et cache — pas de dépendance nouvelle sur le plan infrastructure).
- **Sans Horizon :** le worker reste un simple `queue:work redis` géré par Supervisor. Horizon n'est pas introduit — il resterait une évolution possible si un besoin de supervision fine des files apparaissait.
- Le nom de fichier `ADR-014-queue-database.md` est conservé pour ne pas casser les liens existants ; seul le contenu reflète la décision à jour.
