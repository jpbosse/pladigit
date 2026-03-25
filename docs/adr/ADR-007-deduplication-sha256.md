# ADR-007 — Déduplication des médias par SHA-256

**Date :** Mars 2026
**Statut :** Accepté

## Contexte

Les agents téléversent fréquemment les mêmes photos depuis plusieurs appareils, ou ré-importent des albums déjà présents sur le NAS. Sans contrôle, la base de données se remplit de doublons qui occupent de l'espace disque et polluent les albums.

## Décision

À chaque upload, Pladigit calcule le SHA-256 du fichier et le compare aux hashs existants dans la base tenant. Si une correspondance est trouvée, l'upload est bloqué (exception `DuplicateMediaException`) sauf si le flag `force=true` est passé explicitement.

Le hash est stocké dans `media_items.sha256_hash` (colonne indexée, contrainte unique partielle excluant les soft-deleted). La synchronisation NAS utilise le même mécanisme lors du scan SHA-256 nocturne (`nas:sync --deep`).

## Alternatives écartées

- **Comparaison par nom de fichier :** trop fragile — deux photos différentes peuvent avoir le même nom.
- **Comparaison perceptuelle (pHash) :** utile pour les photos quasi-identiques, mais trop coûteuse à calculer en temps réel et hors scope Phase 4.

## Conséquences

- L'upload d'un duplicata échoue proprement avec un message clair indiquant l'album et la date d'import de l'original.
- Le scan SHA-256 nocturne est plus lent que le scan mtime (qui compare uniquement la taille et la date) mais garantit la cohérence en cas de modification externe sur le NAS.
- Le champ `is_duplicate` (colonne booléenne ajoutée en Phase 5) permet de marquer les doublons détectés a posteriori sans les supprimer.
