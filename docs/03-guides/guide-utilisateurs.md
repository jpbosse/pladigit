# Guide utilisateur — Pladigit

> Ce guide s'adresse à tous les utilisateurs de Pladigit, quel que soit votre rôle
> dans la collectivité. Aucune connaissance informatique particulière n'est requise.
> Dernière mise à jour : juin 2026.

---

## Qu'est-ce que Pladigit ?

Pladigit est l'espace numérique de travail de votre collectivité. Il regroupe en
un seul endroit des outils que vous utilisez peut-être aujourd'hui de façon
dispersée : dossiers partagés, tableurs, documents Word, échanges par email.

Concrètement, Pladigit vous permet de :

- **retrouver un document** sans fouiller des dizaines de dossiers ;
- **travailler à plusieurs** sur le même fichier sans vous envoyer des versions
  par email ;
- **suivre l'avancement d'un projet** sans réunion de coordination hebdomadaire ;
- **consulter et partager des photos** des événements de la commune ;
- **gérer des listes et des tableaux de suivi** sans Excel qui dérive.

Vos données restent hébergées en France, sur un serveur appartenant à votre
collectivité ou à votre prestataire. Elles ne sont jamais partagées avec d'autres
organisations.

---

## Se connecter

### Adresse de connexion

L'adresse de votre espace Pladigit vous est communiquée par votre administrateur.
Elle ressemble à :

```
https://nom-de-votre-commune.pladigit.fr
```

Ouvrez cette adresse dans votre navigateur habituel (Chrome, Firefox, Edge ou
Safari). Pladigit fonctionne aussi bien sur ordinateur que sur tablette.

### Identifiants

Deux modes de connexion sont possibles selon la configuration de votre collectivité :

- **Connexion Pladigit** — email et mot de passe créés par votre administrateur.
- **Connexion Active Directory** — vos identifiants Windows habituels, si votre
  collectivité utilise un annuaire informatique centralisé.

Si vous ne connaissez pas vos identifiants, contactez votre administrateur
Pladigit — il peut générer un nouveau mot de passe depuis son interface.

### Mot de passe oublié

Cliquer sur **Mot de passe oublié** sur la page de connexion. Si votre adresse
email est connue dans le système, un lien de réinitialisation vous est envoyé.
Sinon, contactez directement votre administrateur.

---

## La double authentification (2FA)

La double authentification est une mesure de sécurité qui protège votre compte
même si quelqu'un découvre votre mot de passe. En plus de votre mot de passe,
vous devez saisir un code à 6 chiffres généré par une application sur votre
téléphone.

### Activer la 2FA

Aller dans **Mon profil → Sécurité → Activer la double authentification**.

Installer une application TOTP sur votre téléphone — par exemple **Authy**,
**Google Authenticator** ou **Microsoft Authenticator**. Scanner le QR code
affiché à l'écran avec l'application, puis saisir le code à 6 chiffres
pour confirmer.

### Se connecter avec la 2FA

Après avoir saisi votre mot de passe, Pladigit vous demande le code affiché
dans votre application. Ce code change toutes les 30 secondes — le saisir
rapidement après l'avoir lu.

### Codes de secours

Lors de l'activation, des codes de secours à usage unique vous sont remis.
Ils permettent d'accéder à votre compte si vous perdez votre téléphone.
Les imprimer ou les noter et les conserver en lieu sûr, séparément de votre
téléphone.

---

## Le tableau de bord

Après connexion, vous arrivez sur votre tableau de bord. C'est votre page
d'accueil personnelle — elle affiche les informations importantes et donne
accès aux modules disponibles.

La barre de navigation à gauche (sur ordinateur) ou en bas (sur mobile) permet
d'accéder aux différents modules : GED, Projets, Photothèque, DataGrid, selon
ce qui est activé dans votre organisation.

---

## Mon profil

Accéder à votre profil depuis votre nom en haut à droite de l'écran.

Vous pouvez y modifier :

- votre nom d'affichage ;
- votre photo de profil ;
- votre mot de passe ;
- vos paramètres de double authentification.

L'email ne peut pas être modifié par l'utilisateur — contactez votre
administrateur si nécessaire.

---

## Votre rôle dans Pladigit

Chaque utilisateur dispose d'un rôle qui détermine ce qu'il peut voir et faire
dans la plateforme. Ce rôle est attribué par l'administrateur de l'organisation.

### Maire ou Président

Vous disposez d'une vision d'ensemble de toute l'activité de la collectivité.
Vous pouvez consulter tous les documents, projets et dossiers, suivre
l'avancement des projets en cours et recevoir les notifications importantes
de tous les services.

Vous n'avez pas accès à l'administration des comptes utilisateurs — c'est
le rôle de l'administrateur organisation.

### Directeur Général des Services (DGS)

Vous coordonnez l'activité de l'ensemble des directions. Vous avez accès
à tous les modules avec les mêmes droits de lecture et d'écriture que le
Maire, plus la possibilité d'assigner des tâches à n'importe quel agent
et de superviser les projets de tous les services.

### Responsable de direction

Vous gérez les ressources et l'activité de votre direction. Vous avez accès
à l'ensemble des documents et projets de votre direction, pouvez créer des
projets, assigner des tâches aux membres de votre équipe et partager des
documents avec d'autres directions.

### Responsable de service

Vous gérez l'activité quotidienne de votre service. Votre accès est limité
à votre périmètre de service, avec la possibilité de coordonner votre équipe,
de créer des tâches et de partager des documents avec d'autres services
si votre responsable de direction l'a autorisé.

### Utilisateur (agent)

Vous accédez aux modules et aux ressources partagées avec vous — documents,
projets, albums photos. Vous pouvez contribuer aux projets dont vous êtes
membre et consulter les actualités de l'organisation.

Si vous avez besoin d'accéder à des ressources supplémentaires, faites-en
la demande à votre responsable de service ou à votre administrateur.

---

## Questions fréquentes

**Je ne peux plus me connecter.**
Vérifier que l'adresse email est correcte et que le verrouillage majuscules
n'est pas activé. Si le problème persiste, contacter votre administrateur
qui peut réinitialiser votre mot de passe.

**J'ai perdu mon téléphone avec l'application 2FA.**
Utiliser un de vos codes de secours pour vous connecter, puis reconfigurer
la double authentification depuis Mon profil → Sécurité. Si vous n'avez plus
vos codes de secours, contactez votre administrateur.

**Je ne vois pas un module qui devrait être disponible.**
Votre accès à un module dépend de votre rôle et des modules activés par
votre organisation. Contactez votre administrateur si vous pensez qu'un
accès vous manque.

**Un document a disparu.**
Les documents supprimés sont conservés dans une corbeille pendant une durée
définie par votre administrateur. Vérifier dans la GED → Corbeille.
Si le document n'y est pas, contacter votre administrateur.

---

*Dernière mise à jour : juin 2026*
