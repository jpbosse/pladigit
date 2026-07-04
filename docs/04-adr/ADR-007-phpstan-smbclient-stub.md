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
- **Niveau 6+ (à ce stade) :** requiert des annotations génériques sur toutes les collections Eloquent — overhead jugé prématuré au démarrage du projet pour un développeur seul. Cette position a évolué depuis (voir la section *Révision* ci-dessous).
- **Ignorer le fichier SMB dans phpstan.neon :** exclurait des bugs réels dans le driver.

## Conséquences

- 0 erreur PHPStan est une condition de merge — le badge CI reste vert en permanence.
- Le stub doit être mis à jour si de nouvelles fonctions `smbclient_*` sont utilisées.
- Les autres extensions optionnelles (Imagick, etc.) suivront le même pattern si elles sont introduites.

## Révision — Juillet 2026 : trajectoire vers le niveau 8 par paliers

**Statut de la révision :** planifié.

Le niveau 5 reste la **réalité du dépôt** (`phpstan.neon` : `level: 5`, 0 erreur, condition de merge). Aucune montée n'est effective à ce jour.

En revanche, la position sur les niveaux supérieurs a évolué : plutôt que d'écarter définitivement le niveau 6+, le projet vise désormais une **montée progressive jusqu'au niveau 8**, motivée par la robustesse en production. Le niveau 8 introduit la détection des appels sur des valeurs potentiellement `null` — c'est la classe de bugs qui produit le plus souvent des erreurs 500 chez un utilisateur, et donc la plus critique pour un déploiement en commune sans support technique sur place.

Modalités retenues :

- **Un palier par session de travail** : 5 → 6, puis 6 → 7, puis 7 → 8. Jamais deux paliers d'un coup — le saut génère trop d'erreurs simultanées et devient ingérable pour un développeur solo.
- **Les trois gates verts entre chaque palier** : Pint, PHPStan, PHPUnit. Aucun commit rouge.
- **Faux positifs** traités au cas par cas via des `ignoreErrors` ciblés dans `phpstan.neon` (comme déjà fait pour `smbclient` et les relations Eloquent génériques), jamais par un abaissement global du niveau.
- **Le niveau 9** (élimination quasi totale du type `mixed`) n'est **pas** un objectif : coût très élevé avec Livewire et les tableaux de configuration Laravel, pour un gain marginal. Le niveau 8 est le plafond visé.

Tant que la montée n'est pas réalisée, toute documentation doit annoncer **niveau 5** — pas le niveau cible.
