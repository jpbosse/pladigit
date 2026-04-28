# Questions fréquentes sur Pladigit

Ce document regroupe les questions que les collectivités, centres de gestion et syndicats informatiques posent le plus souvent au sujet de Pladigit. Les réponses sont honnêtes — y compris sur ce que le projet ne fait pas encore.

---

## Questions sur la pérennité du projet

### Et si le projet s'arrête — que deviennent nos données et notre installation ?

C'est la question la plus légitime, et la réponse est dans la licence.

Pladigit est publié sous **licence AGPL-3.0** (Licence Publique Générale GNU Affero — une licence libre reconnue internationalement). Cela signifie que le code source complet est disponible publiquement sur GitHub, dès aujourd'hui. Si le développeur principal disparaît ou arrête le projet demain, le code ne disparaît pas avec lui. N'importe quelle société de services informatiques, n'importe quel développeur indépendant, n'importe quel syndicat informatique territorial peut reprendre le projet, le maintenir, le faire évoluer — sans demander la permission, sans frais de licence, sans négociation.

Vos données, elles, sont sur votre propre serveur ou votre propre serveur virtuel. Elles ne sont dans aucun nuage géré par le projet Pladigit. Si le projet s'arrête, votre installation continue de fonctionner.

C'est structurellement différent d'un éditeur propriétaire qui ferme boutique et emporte avec lui les formats de fichiers et les données de ses clients.

En complément : la documentation est complète (31 fiches de décision d'architecture, guides d'installation, guides utilisateurs) — une reprise par un tiers est documentée et réalisable.

---

### C'est un projet développé par une seule personne — est-ce suffisamment fiable pour une collectivité ?

C'est une vraie question, et elle mérite une réponse directe.

Ce qui compense partiellement ce risque :
- Le code est public, lisible et vérifiable par n'importe quelle société de services informatiques mandatée
- 759 tests automatisés verts, analyse statique de code sans erreur, intégration continue bloquante — le code est maintenu à un niveau de qualité comparable aux projets d'équipes professionnelles
- La feuille de route est documentée et réaliste — pas de sur-promesse

Ce que propose Pladigit pour les organisations qui veulent tester sans risque : **une installation pilote gratuite, sans engagement**. Tester sur un périmètre limité (5 agents, 3 mois), évaluer, décider. Si ça ne convient pas, les données restent sur votre serveur — pas dans un nuage tiers.

---

### Le développeur est retraité — le projet ne va-t-il pas s'essouffler ?

La retraite a précisément libéré le temps nécessaire pour construire ce projet sérieusement. Sept phases livrées en sept mois, 759 tests automatisés, 31 fiches de décision d'architecture — ce n'est pas un projet de week-end.

L'objectif à moyen terme est de constituer un réseau de partenaires (centres de gestion, syndicats informatiques, sociétés de services locales) qui prennent le relais sur l'accompagnement et le support. Le plan Partenaire (sur devis) est précisément ce mécanisme : il permet de rémunérer l'accompagnement humain sans créer de dépendance indéfinie à une seule personne.

---

## Questions sur le support et l'assistance

### Qui assure le support en cas de problème ?

Réponse honnête selon le plan choisi :

- **Plan Communautaire (gratuit)** : assistance communautaire via les tickets GitHub. Pas d'engagement de délai de réponse. Adapté aux organisations qui disposent d'un syndicat informatique ou d'une compétence technique interne, ou qui s'appuient sur leur centre de gestion.
- **Plan Partenaire (sur devis)** : support direct par courriel, réponse sous 48 heures, accompagnement en visioconférence. C'est une réponse directe de quelqu'un qui connaît le code — pas un centre d'appel, mais une relation de proximité réelle.

Ce n'est pas le niveau de support d'un grand éditeur national. C'est assumé. En contrepartie, vous payez environ 180 €/an de serveur virtuel au lieu de 1 500 € de licences annuelles pour 10 agents.

Pour les collectivités qui souhaitent un intermédiaire de confiance : un centre de gestion ou un syndicat informatique territorial peut jouer ce rôle de proximité — c'est précisément le modèle de déploiement mutualisé que Pladigit encourage.

---

### Qu'est-ce qu'un accord de niveau de service (SLA) et est-il possible d'en avoir un ?

Un accord de niveau de service (SLA — Service Level Agreement en anglais) est un contrat qui garantit un délai maximal de réponse et d'intervention en cas de problème. C'est une exigence légitime pour des organisations sans compétence informatique interne.

Pladigit auto-hébergé vous donne un avantage important sur ce point : vous contrôlez votre propre serveur. Un agent technique du syndicat informatique ou du centre de gestion peut redémarrer un service, restaurer une sauvegarde, sans dépendre du développeur du projet. La documentation de maintenance est disponible et détaillée.

Si un accord de niveau de service contractualisé est nécessaire, c'est à discuter dans le cadre du plan Partenaire au cas par cas.

---

## Questions sur la maturité du logiciel

### La version 0.8 — est-ce vraiment utilisable en production ?

La numérotation 0.8 est honnête, pas un signe d'instabilité. Elle signifie que toutes les fonctionnalités prévues ne sont pas encore livrées (messagerie instantanée, agenda partagé) — pas que le code livré est défaillant.

Ce qui est livré est couvert par :
- 759 tests unitaires automatisés — 1 645 vérifications individuelles, toutes au vert
- PHPStan niveau 5 — outil d'analyse statique du code, zéro erreur de typage
- Intégration continue bloquante — aucune mise à jour possible si un seul test échoue
- Audit des dépendances — zéro faille de sécurité connue dans les bibliothèques utilisées

À titre de comparaison : combien de logiciels propriétaires utilisés en collectivité peuvent montrer un tel niveau de couverture de tests, visible et vérifiable par quiconque ?

---

### Pladigit est-il homologué par l'ANSSI ? A-t-il la qualification SecNumCloud ?

Non — et c'est honnête à dire pour un projet en version 0.8.

La qualification SecNumCloud (certification de l'Agence Nationale de la Sécurité des Systèmes d'Information pour les hébergements en nuage) demande un processus long et coûteux, inaccessible à un projet en phase initiale.

En revanche, ce qui est en place :
- Zéro faille connue dans les dépendances (audit automatique à chaque mise à jour du code)
- Fail2ban (blocage automatique des adresses réseau malveillantes), pare-feu UFW, connexion à l'annuaire obligatoirement chiffrée, double authentification TOTP — les recommandations de base de l'ANSSI pour les petites collectivités sont toutes implémentées
- 31 fiches de décision d'architecture publiées — chaque choix de sécurité est tracé et défendable
- Code source public — auditable par n'importe quelle société mandatée

À titre de comparaison : Microsoft 365 n'est pas non plus qualifié SecNumCloud, et son code source n'est pas auditable. La qualification SecNumCloud est un objectif de feuille de route pour Pladigit, pas un prérequis pour une installation pilote.

---

### Il manque la messagerie instantanée et l'agenda — peut-on vraiment remplacer Teams sans ça ?

C'est vrai. Pladigit ne remplace pas encore Teams dans sa totalité.

Deux approches pragmatiques :

1. **Déploiement progressif** : commencer par la gestion électronique de documents et l'éditeur Collabora (qui remplacent SharePoint et la suite Office), et par la gestion de projets (qui remplace le planificateur de tâches Microsoft). Ce sont souvent les besoins les plus urgents. La messagerie et l'agenda arrivent en 2027.

2. **Complémentarité avec les outils de l'État** : Pladigit s'articule très bien avec Tchap (la messagerie instantanée de la Direction Interministérielle du Numérique) pour les échanges en temps réel. Ce n'est pas une logique de remplacement total immédiat, mais de transition progressive et raisonnée.

---

## Questions sur la protection des données personnelles (RGPD)

### Notre délégué à la protection des données va nous demander une analyse d'impact — vous pouvez nous aider ?

Oui, et c'est l'un des points forts de Pladigit.

Les éléments disponibles pour une analyse d'impact relative à la protection des données (aussi appelée AIPD ou DPIA) :

- **Journal d'audit complet** : chaque accès, modification ou suppression est enregistré, horodaté, attribué à un utilisateur identifié — exportable en format tableur (CSV) ou fichier structuré (JSON). La durée de conservation est paramétrable à 12 mois par défaut, et peut être étendue jusqu'à 36 mois selon les besoins réglementaires.
- **Versioning documentaire** : toute modification d'un document dans la gestion électronique de documents est traçable et réversible
- **Hébergement maîtrisé** : les données sont hébergées soit sur un serveur interne à la collectivité, soit sur un serveur virtuel situé physiquement en France (OVH, Scaleway, Infomaniak), soit au minimum chez un hébergeur européen. Dans tous les cas, la localisation des données ne fait aucun doute et le droit applicable est clairement identifié.
- **Code source public** : le délégué à la protection des données ou toute société mandatée peut lire et vérifier le code source lui-même, sans avoir à faire confiance à l'éditeur sur parole
- **Isolation entre organisations** : chaque collectivité dispose de sa propre base de données — aucun mélange de données entre organisations distinctes n'est possible

---

### La licence AGPL-3.0 — est-ce que ça pose un problème pour les données de nos agents ?

Non. La licence AGPL-3.0 porte sur le **code source du logiciel**, pas sur les données qui transitent dans ce logiciel.

Vos données (agents, délibérations, documents) restent les vôtres, hébergées sur votre propre serveur ou serveur virtuel, sous votre contrôle exclusif. Ni le développeur du projet, ni GitHub, ni aucun tiers n'y a accès. La licence AGPL-3.0 oblige simplement quiconque redistribue une version modifiée du logiciel à rendre publiques ses modifications — ce qui ne concerne en rien vos données.

---

## Questions sur le coût

### "Gratuit", c'est souvent le piège — on finit toujours par payer quelque chose.

C'est une méfiance légitime. Voici ce qu'il en est réellement :

- Le **plan Communautaire est gratuit sans limite de fonctionnalités ni de durée** — ce n'est pas une version d'essai ni un modèle freemium. Toutes les fonctionnalités sont disponibles, sans condition.
- Le **plan Partenaire est payant** — il rémunère le temps d'accompagnement humain (installation, formation, support), pas l'accès aux fonctionnalités.
- La licence AGPL-3.0 **interdit juridiquement de rendre le code source propriétaire** — toute version dérivée doit rester libre. Il n'est pas possible de "fermer" le logiciel dans une future version payante.

Ce qui a un coût réel : le serveur virtuel (entre 15 et 50 €/mois selon la configuration choisie), et l'accompagnement si votre organisation en a besoin.

---

### Nous avons déjà payé nos licences Microsoft pour 3 ans — ce n'est pas le bon moment.

Tout à fait compréhensible. Ce n'est effectivement pas le bon moment pour un basculement complet.

Ce qui reste possible et utile dès maintenant : **installer Pladigit en parallèle sur un périmètre limité** (photothèque communale, suivi d'un projet de travaux), sans toucher à Microsoft 365. Vous évaluez sereinement pendant 18 mois. Quand le contrat Microsoft arrive à renouvellement, vous avez une alternative opérationnelle, des agents qui connaissent l'outil, et une décision éclairée à prendre.

---

## Questions sur l'installation et la maintenance

### Personne chez nous n'a les compétences pour installer un logiciel sur un serveur.

C'est exactement le problème que l'assistant d'installation de Pladigit a été conçu pour résoudre.

Une seule commande à saisir sur le serveur installe automatiquement tous les composants nécessaires (PHP 8.4, base de données MySQL 8, Redis, serveur web Nginx, et Pladigit lui-même) sur un serveur Ubuntu 22.04 ou 24.04. Un assistant web en 8 étapes guide ensuite la configuration (base de données, adresse web, courriel, compte administrateur) — sans aucune ligne de commande supplémentaire.

L'installation complète, éditeur Collabora inclus, prend environ **30 minutes**. Elle est documentée et testée.

Pour les organisations sans compétence technique interne, le plan Partenaire couvre l'installation et la configuration initiale. Un centre de gestion ou un syndicat informatique territorial peut également jouer ce rôle pour les communes de son périmètre.

---

### Les mises à jour — comment ça fonctionne ?

Actuellement, les mises à jour se font manuellement (récupération du nouveau code + application des migrations de base de données). La procédure est documentée dans le guide de maintenance.

Un système de mises à jour assisté est prévu dans la prochaine phase. En attendant, les alertes de sécurité sont publiées via GitHub et signalées aux organisations qui le souhaitent.

C'est un point en cours d'amélioration — pas une fonctionnalité acquise.

---

## Questions sur les alternatives existantes

### Nextcloud fait à peu près la même chose et il est plus mature — pourquoi choisir Pladigit ?

Nextcloud est mature et très bien pour un usage généraliste. Il n'a pas été conçu pour les collectivités françaises, et certaines différences sont importantes en pratique.

Différences concrètes :

- **Hébergement multi-organisations** : Nextcloud nécessite une installation séparée par organisation. Pladigit gère plusieurs communes sur une seule installation, avec des espaces totalement isolés. Pour un centre de gestion ou un syndicat informatique, c'est une différence opérationnelle majeure.
- **Structure hiérarchique** : la structure Direction > Service > Agent, qui correspond à l'organigramme réel d'une collectivité, est native dans Pladigit. Dans Nextcloud, elle doit être émulée avec des groupes, sans la même cohérence sur les droits d'accès.
- **Gestion de projets** : Nextcloud ne dispose pas de diagramme de Gantt, de suivi budgétaire par ligne, ni d'export de rapport pour les élus.
- **Installation** : 30 minutes avec l'assistant Pladigit. Nextcloud demande une expertise nettement plus poussée pour un résultat correct — c'est la raison principale d'abandon constatée dans les collectivités qui ont essayé.

---

### La Suite Numérique de l'État (DINUM) existe déjà — pourquoi un outil de plus ?

Parce que la Suite Numérique de la Direction Interministérielle du Numérique (DINUM) ne couvre pas les besoins documentaires et de gestion de projets locaux.

Tchap est une messagerie. Resana est une plateforme de travail collaboratif national. Webconf est un outil de visioconférence. Aucun de ces outils ne gère une base documentaire communale avec versioning, un suivi de chantier avec diagramme de Gantt, ou une photothèque d'événements locaux avec synchronisation depuis le serveur de fichiers.

Pladigit est **complémentaire** à la Suite DINUM, pas concurrent. Un agent peut utiliser Tchap pour communiquer avec ses collègues et Pladigit pour gérer les documents et les projets de sa commune.

---

## La vraie question derrière toutes les autres

Derrière la plupart des questions techniques se cache souvent une interrogation plus fondamentale : *"Est-ce que je peux m'appuyer sur ce projet pour mon organisation ?"*

La réponse honnête : **pour une dépendance critique totale sans aucune compétence technique de relais, Pladigit n'est pas encore la solution idéale**. En revanche, pour une installation pilote, avec un centre de gestion ou un syndicat informatique comme point d'appui, sur un périmètre limité — oui. Avec la certitude que si le projet évolue mal, vos données restent chez vous et le code reste public.

C'est le contrat proposé. Pas plus, pas moins.

---

## Contact

**Jean-Pierre Bossé** — contact@pladigit.fr
Code source : [github.com/jpbosse/pladigit](https://github.com/jpbosse/pladigit)
Démonstration : [pladigit.fr](https://pladigit.fr)

*Licence AGPL-3.0 (code) · CC BY-SA 4.0 (documentation) · Mai 2026*
