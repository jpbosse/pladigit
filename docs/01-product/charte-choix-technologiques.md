# Pladigit — Les choix technologiques et pourquoi ils ont été faits

> Ce document s'adresse aux développeurs, analystes et prestataires qui veulent comprendre
> les principes qui guident chaque choix technique dans Pladigit.
> Il explique le pourquoi, pas seulement le quoi.

---

## Open source — pas par idéologie, par pragmatisme

Pladigit utilise exclusivement des logiciels dont le code source est librement accessible.
Ce n'est pas une posture militante. C'est une décision pratique.

Un logiciel open source peut être audité — par la collectivité, par son prestataire, par
l'État. On peut vérifier ce qu'il fait réellement avec les données. On peut le corriger
si un problème est découvert, sans attendre qu'un éditeur daigne publier un correctif.
On peut le faire évoluer si les besoins changent, sans négocier un avenant à un contrat.

Pour une collectivité qui gère des données d'agents, des délibérations et des dossiers
d'administrés, cette transparence n'est pas un luxe — c'est une exigence.

---

## Réversibilité — sortir doit toujours être possible

Toutes les données stockées dans Pladigit doivent pouvoir en sortir, dans des formats
standards, sans l'aide de l'éditeur.

Cette règle s'applique à tout : les documents (formats ODF, PDF), les bases de données
(exports SQL standards), les fichiers (arborescence classique sur disque). Aucune donnée
ne doit être enfermée dans un format propriétaire illisible sans le logiciel qui l'a créée.

En pratique, cela signifie qu'une collectivité qui décide un jour d'abandonner Pladigit
peut récupérer l'intégralité de ses données et les confier à un autre outil ou prestataire.
C'est une garantie contractuelle que peu d'éditeurs commerciaux sont capables d'offrir.

---

## Auto-hébergement — la collectivité choisit où vivent ses données

Pladigit peut être installé sur n'importe quel serveur — un serveur physique dans les
locaux de la mairie, un VPS chez un hébergeur français, une infrastructure mutualisée
portée par un centre de gestion ou un syndicat informatique.

Aucune fonctionnalité ne dépend d'un service externe obligatoire. Pas de compte chez
un tiers, pas de clé API vers un cloud américain, pas de données qui transitent par des
serveurs dont on ignore la localisation.

Ce principe a des implications concrètes sur chaque décision technique : si une
fonctionnalité ne peut pas fonctionner sans un service cloud externe, elle n'est pas
intégrée au cœur de Pladigit. Elle peut être proposée comme module optionnel, avec une
alternative locale.

---

## Modularité — remplacer sans tout casser

Pladigit est construit pour que chaque composant puisse être remplacé indépendamment
des autres. L'éditeur de documents collaboratif est aujourd'hui Collabora Online —
demain ce pourrait être EuroOffice ou n'importe quelle autre solution compatible.
Le moteur de recherche, le système d'authentification, le stockage de fichiers : chacun
peut évoluer sans que le reste de la plateforme s'effondre.

Cette architecture évite l'un des pièges les plus courants des projets informatiques
publics : être contraint de tout réécrire parce qu'un composant central n'est plus
maintenu ou devient trop cher.

---

## Préférence européenne — un choix assumé

À qualité et coût équivalents, Pladigit choisit systématiquement les solutions
européennes — logiciels, hébergeurs, fournisseurs de services.

Ce n'est pas du protectionnisme. C'est une cohérence avec les valeurs du projet et
avec les exigences croissantes du droit européen sur la localisation des données
publiques. Un hébergeur soumis au droit américain (Cloud Act) n'offre pas les mêmes
garanties qu'un hébergeur soumis au droit français ou européen — même si ses serveurs
sont physiquement en France.

---

## Pérennité — le projet doit survivre à son créateur

Chaque décision technique est prise en pensant à celui ou celle qui devra maintenir
le code dans cinq ans sans avoir participé à son écriture.

Cela se traduit concrètement par plusieurs règles non négociables : les dépendances
externes doivent être largement adoptées et activement maintenues par leur communauté ;
le code doit être couvert par des tests automatisés ; chaque décision d'architecture
importante est documentée avec son contexte et ses alternatives écartées.

Un projet dont seul le créateur comprend les entrailles n'est pas un projet pérenne —
c'est une bombe à retardement pour la collectivité qui en dépend.

---

## Ce que ces principes excluent

Ces principes ont des conséquences directes sur ce que Pladigit ne fera pas :

- Pas d'intégration native avec des services dont les conditions d'utilisation sont
  hors du contrôle de la collectivité (Google Drive, Microsoft OneDrive, Dropbox).
- Pas de dépendance à un service d'IA externe obligatoire — si l'IA est intégrée,
  elle doit pouvoir fonctionner avec un modèle auto-hébergé.
- Pas de composant dont le code source est inaccessible ou la licence restrictive.

Ces exclusions ne sont pas définitives — elles peuvent évoluer si les conditions
changent. Mais elles s'appliquent tant que des alternatives libres et souveraines
existent.

---

*Dernière mise à jour : juin 2026*