# ADR-019 — Enforcement quota strict (upload + sync NAS)

**Date :** Mars 2026
**Statut :** Accepté

## Contexte

Le module Photothèque permet aux agents de téléverser des médias et au cron d'ingérer des fichiers depuis le NAS. Sans plafonnement effectif, une organisation peut consommer l'intégralité de l'espace disque du serveur mutualisé, impactant les autres organisations hébergées.

La Phase 4 introduit un champ `storage_quota_mb` sur `organizations` (base plateforme). La question est : ce quota doit-il être **bloquant** (refuse l'ajout si dépassé) ou **indicatif** (affiche une alerte mais laisse passer) ?

## Décision

Le quota est **strictement bloquant** dans tous les chemins d'ingestion :

1. **Upload direct** (`MediaService::assertQuota()`) : lève une `RuntimeException` avant toute écriture NAS si `usedBytes + incomingBytes > quotaBytes`. L'upload échoue avec un message d'erreur précis (Mo utilisés / quota / espace libre / taille du fichier).

2. **Synchronisation NAS** (`MediaService::ingestNasFile()`) : vérifie le quota avant d'ingérer chaque fichier. Si dépassé, le fichier est ignoré (log warning) et la sync continue sur les autres fichiers.

En complément, des **notifications de seuil** sont envoyées aux admins locaux à 80 %, 90 % et 95 % d'utilisation, avec anti-spam (une seule notification par seuil franchi).

## Alternatives écartées

- **Quota indicatif uniquement :** simple à implémenter, mais expose l'hébergeur à une saturation disque silencieuse. Inacceptable sur un serveur mutualisé.

- **Quota bloquant uniquement sur upload, pas sur sync :** la sync NAS représente le chemin principal d'ingestion pour beaucoup d'organisations. L'exclure du contrôle viderait le quota de son sens.

- **Purge automatique des vieux fichiers à la saturation :** risque de suppression involontaire de données patrimoniales. Les collectivités locales archivent des photos ayant une valeur historique — toute suppression automatique est exclue.

- **Quota par album :** trop granulaire pour une gestion mutualisée. Le quota par organisation est le niveau pertinent pour l'hébergeur.

## Conséquences

- Les organisations ne peuvent pas dépasser leur quota alloué, quelle que soit la voie d'ingestion.
- Les administrateurs locaux sont alertés à temps (80/90/95 %) pour prendre des mesures avant le blocage.
- Un fichier ignoré lors d'une sync NAS (quota plein) sera automatiquement re-tenté à la prochaine exécution si de l'espace a été libéré.
- Le super-administrateur reste le seul à pouvoir augmenter le quota (`organizations.storage_quota_mb`, minimum 512 Mo, défaut 10 240 Mo).
- Les miniatures ne sont pas comptées dans le quota (espace NAS supplémentaire non contrôlé — acceptable car les miniatures sont ~2–5 % de la taille des originaux).
