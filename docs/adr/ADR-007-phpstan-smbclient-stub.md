# ADR-007 — PHPStan niveau 5 + stub smbclient pour CI

**Date :** Novembre 2025
**Statut :** Accepté

## Contexte

PHPStan analyse statiquement le code PHP à la recherche d'erreurs de type, d'appels incorrects et de code mort. Le driver SMB (`smbclient`) est une extension PHP optionnelle non installée sur les environnements de développement ni sur les agents CI GitHub Actions. PHPStan échoue donc dès qu'il tente d'analyser le fichier `NasSmbConnector.php` qui référence des fonctions `smbclient_*`.

## Décision

Maintenir PHPStan au **niveau 5** (niveau de rigueur élevé, mais sans inférence de types génériques). Créer un fichier stub `stubs/smbclient.php` qui déclare les signatures des fonctions `smbclient_*` sans implémentation. PHPStan est configuré dans `phpstan.neon` pour charger ce stub.

Le CI GitHub Actions est configuré avec `continue-on-error: false` — toute erreur PHPStan bloque le merge.

## Alternatives écartées

- **Niveau 4 ou moins :** trop permissif, laisse passer des erreurs de nullabilité importantes.
- **Niveau 6+ :** requiert des annotations génériques sur toutes les collections Eloquent — overhead trop important pour un développeur seul.
- **Ignorer le fichier SMB dans phpstan.neon :** exclurait des bugs réels dans le driver.

## Conséquences

- 0 erreur PHPStan est une condition de merge — le badge CI reste vert en permanence.
- Le stub doit être mis à jour si de nouvelles fonctions `smbclient_*` sont utilisées.
- Les autres extensions optionnelles (Imagick, etc.) suivront le même pattern si elles sont introduites.
