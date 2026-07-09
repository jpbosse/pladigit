# Pladigit — Architecture modulaire

> Ce document s'adresse aux développeurs et analystes.
> Il explique comment Pladigit est découpé en modules, pourquoi, et ce que cela implique
> concrètement pour contribuer ou faire évoluer la plateforme.

---

## Pourquoi une architecture modulaire

Pladigit est destiné à des collectivités de tailles et de besoins très différents. Une
commune de 500 habitants n'a pas les mêmes priorités qu'une communauté de communes de
15 000 habitants. Imposer à toutes le même ensemble de fonctionnalités serait inutile,
coûteux en ressources serveur, et source de complexité inutile pour les utilisateurs.

L'architecture modulaire répond à ce problème : chaque organisation active uniquement
les modules dont elle a besoin. Le reste n'est pas installé, pas chargé, pas visible.

---

## Le cœur et les modules

Pladigit est divisé en deux parties distinctes.

**Le cœur** gère ce qui est commun à toutes les organisations : authentification,
gestion des utilisateurs et des droits, multi-tenant, tableau de bord, notifications,
audit, sauvegarde. Il est toujours présent, toujours actif. On n'y touche pas pour
ajouter une fonctionnalité métier.

**Les modules** apportent les fonctionnalités spécifiques. Chaque module est autonome :
il peut être activé ou désactivé par organisation, sans affecter les autres modules ni
le cœur. Un module ne modifie jamais directement le comportement interne du cœur.

---

## Les types de modules

**Modules métier** — les fonctionnalités visibles par les utilisateurs finaux :
gestion de projets, photothèque, GED, DataGrid, messagerie, agenda, formulaires.
Ce sont les modules qu'une secrétaire de mairie ou un chef de service utilisera
au quotidien.

**Modules de services** — des composants techniques qui enrichissent les modules
métier sans être directement visibles : Collabora Online pour l'édition de documents,
un moteur OCR pour la reconnaissance de texte dans les PDF, un moteur de recherche
avancée. Ils s'installent séparément, souvent dans un conteneur Docker dédié.

**Connecteurs** — des ponts vers des systèmes externes déjà en place dans la
collectivité : annuaire LDAP ou Active Directory, serveur de messagerie SMTP,
FranceConnect pour l'authentification des citoyens. Un connecteur ne stocke pas
de données — il fait le lien.

**Modules IA** — des assistants documentaires ou analytiques basés sur des modèles
de langage. Ils sont toujours optionnels et doivent pouvoir fonctionner avec un
modèle auto-hébergé (Mistral, Llama) pour ne pas créer de dépendance vers un
service cloud externe.

---

## Ce qu'un module doit fournir

Tout module intégré à Pladigit doit respecter un contrat minimal :

- un identifiant unique et stable (utilisé dans la base de données et les routes) ;
- une version sémantique (majeure.mineure.correctif) ;
- une déclaration de ses dépendances — autres modules ou services requis ;
- une migration de base de données propre, réversible ;
- une procédure de désactivation qui ne laisse pas de données orphelines ;
- une documentation utilisateur, même minimale.

Un module qui ne peut pas être désactivé proprement n'est pas un module — c'est
une fonctionnalité mal isolée du cœur.

---

## Activation par organisation

Les modules actifs sont stockés dans un champ JSON sur l'enregistrement de chaque
organisation en base de données. Le Super Admin active ou désactive un module pour
une organisation donnée depuis l'interface d'administration centrale.

L'activation d'un module déclenche automatiquement ses migrations de base de données
sur la base tenant concernée. La désactivation masque les fonctionnalités dans
l'interface mais ne supprime pas les données — une réactivation ultérieure retrouve
l'état précédent.

---

## Services lourds — isolation obligatoire

Certains modules de services consomment beaucoup de ressources ou exposent des
surfaces d'attaque importantes (Collabora, moteur OCR, IA). Ces services sont
systématiquement isolés dans des conteneurs Docker dédiés, avec des règles réseau
strictes. Ils ne s'installent jamais dans le même processus que l'application PHP.

Collabora Online est l'exemple de référence : il s'installe via `install.sh --collabora-only`,
tourne dans son propre conteneur, communique avec Pladigit via le protocole WOPI,
et peut être arrêté sans affecter le reste de la plateforme.

---

## Ajouter un module — par où commencer

Avant d'écrire une ligne de code, trois questions :

1. **Est-ce vraiment un module ?** Si la fonctionnalité modifie le cœur (authentification,
   multi-tenant, routing principal), ce n'est pas un module — c'est une évolution du cœur,
   qui suit un processus de décision différent (ADR obligatoire).

2. **Peut-il être désactivé proprement ?** Si la désactivation casse autre chose, la
   conception est à revoir avant de commencer l'implémentation.

3. **Y a-t-il une alternative libre et auto-hébergeable ?** Pour tout service externe
   intégré, une alternative doit exister qui ne crée pas de dépendance obligatoire vers
   un fournisseur unique.

Les décisions d'architecture significatives sont documentées sous forme d'ADR
(Architecture Decision Record) dans `docs/02-architecture/adr/`. Consultez-les avant
de prendre une décision qui pourrait contredire un choix déjà acté.

---

*Dernière mise à jour : juin 2026*