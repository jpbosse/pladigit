# ADR-025 — Recherche GED par LIKE (pas FULLTEXT)

**Date :** Avril 2026  
**Statut :** Accepté

## Contexte

La recherche dans la GED doit permettre de retrouver un document par son nom ou le nom de son dossier. Deux approches ont été considérées : MySQL FULLTEXT ou LIKE avec wildcard.

## Décision

Utiliser des requêtes `LIKE '%terme%'` sur les colonnes `name` de `ged_documents` et `ged_folders`. La recherche filtre ensuite les résultats selon les droits de l'utilisateur (`GedPermissionService::canView()`).

## Raisons

- **Volume modeste** — les collectivités cibles ont rarement plus de quelques milliers de documents. `LIKE` est suffisant à ce volume.
- **Pas d'index FULLTEXT nécessaire** — évite une migration supplémentaire et une contrainte sur le moteur MySQL (InnoDB FULLTEXT requiert utf8mb4 et une syntaxe particulière).
- **Recherche par sous-chaîne** — `LIKE '%terme%'` trouve "Rapport_final_2026" avec le mot "rapport", ce que FULLTEXT en mode BOOLEAN ne fait pas nativement.
- **Simplicité** — le code est lisible, maintenable, et ne dépend d'aucune configuration moteur.

## Conséquences

- La recherche couvre les noms de fichiers et de dossiers — pas le contenu des documents (texte dans un ODT ou PDF).
- La recherche dans le contenu des documents est prévue en Phase IA (Ollama + Mistral) — hors périmètre actuel.
- Si le volume de documents venait à dépasser quelques dizaines de milliers, l'ajout d'un index FULLTEXT serait transparent : seule la requête SQL change.

> Note : l'ADR-014 (queue database) est sans lien avec la recherche GED malgré la numérotation voisine.
