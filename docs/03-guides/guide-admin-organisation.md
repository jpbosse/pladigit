# Guide Administrateur d'organisation — Pladigit

> Ce guide s'adresse à l'administrateur de votre espace Pladigit.
> Il couvre la gestion des utilisateurs, la personnalisation et les paramètres
> de sécurité de votre organisation.
> Dernière mise à jour : juin 2026.

---

## Votre rôle

L'administrateur d'organisation est le responsable de l'espace Pladigit de sa
collectivité. Il gère les comptes utilisateurs, personnalise l'interface aux
couleurs de la commune et s'assure que chacun dispose des droits adaptés à
ses fonctions.

Il n'a accès qu'à son propre espace — il ne peut pas voir ni modifier les
données des autres organisations hébergées sur la même plateforme.

Ce que l'administrateur d'organisation **ne fait pas** : la configuration du
serveur de messagerie (SMTP), de l'annuaire Active Directory (LDAP) et des
sauvegardes relève du Super Administrateur de la plateforme.

---

## Première connexion

L'URL de connexion vous est communiquée par le Super Administrateur lors de
la mise en service. Elle est de la forme :

```
https://votre-commune.pladigit.fr/login
```

Vos identifiants initiaux (email + mot de passe temporaire) vous sont transmis
par téléphone ou en face à face. Changez votre mot de passe dès la première
connexion.

L'interface d'administration est accessible depuis le menu en haut à droite,
rubrique **Administration**, ou directement via :

```
https://votre-commune.pladigit.fr/admin
```

---

## Gérer les utilisateurs

### Créer un compte

Aller dans **Administration → Utilisateurs → Créer un utilisateur**.

Renseigner le nom, l'email et attribuer un rôle. L'utilisateur peut se connecter
immédiatement avec un mot de passe temporaire que vous lui communiquez — là
encore, par téléphone ou en face à face, jamais par email.

L'email est l'identifiant unique de l'utilisateur dans Pladigit. Il ne peut
pas être modifié après création.

### Les rôles disponibles

Pladigit distingue six niveaux d'accès, du plus large au plus restreint :

| Rôle | Profil type | Accès |
|------|-------------|-------|
| Administrateur | Responsable informatique | Gestion complète de l'organisation |
| Maire / Président | Élu | Lecture de toute l'organisation, sans accès admin |
| DGS | Directeur Général des Services | Coordination opérationnelle, accès tous services |
| Responsable de direction | Chef de pôle | Accès limité à sa direction |
| Responsable de service | Chef de service | Accès limité à son service |
| Utilisateur | Agent | Accès aux ressources partagées avec lui |

Attribuer toujours le rôle le plus restreint compatible avec les besoins réels
de l'utilisateur. Un agent n'a pas besoin de voir les dossiers de toute
la direction.

### Modifier ou désactiver un compte

Depuis la liste des utilisateurs, cliquer sur le compte concerné.

La **désactivation** bloque l'accès immédiatement sans supprimer les données.
C'est l'action à effectuer dès qu'un agent quitte l'organisation ou change
de poste. Préférer la désactivation à la suppression — l'historique des
actions de l'utilisateur est ainsi conservé.

### Réinitialiser un mot de passe

Si un utilisateur ne peut plus se connecter, aller dans **Administration →
Utilisateurs**, trouver le compte et cliquer sur **Réinitialiser le mot de
passe**. Un mot de passe temporaire est généré et affiché une seule fois —
le noter immédiatement avant de fermer la page.

---

## Personnaliser l'interface

Aller dans **Administration → Paramètres → Personnalisation**.

Trois éléments sont personnalisables :

- **Couleur principale** — teinte utilisée pour les boutons et les éléments
  d'interface. Saisir le code couleur hexadécimal ou utiliser le sélecteur.
- **Logo** — affiché en haut à gauche de l'interface. Format PNG, JPG ou SVG,
  taille recommandée 200 × 60 pixels.
- **Image de fond de la page de connexion** — personnalise l'écran d'accueil.
  Format JPG ou PNG, résolution minimale 1280 × 720 pixels.

Après modification, rafraîchir la page pour voir les changements appliqués.

---

## Paramètres de sécurité

Aller dans **Administration → Paramètres → Sécurité**.

Les paramètres configurables couvrent :

- **Durée de session** — délai d'inactivité avant déconnexion automatique
  (défaut : 120 minutes).
- **Tentatives de connexion** — nombre d'échecs avant verrouillage du compte
  (défaut : 10 tentatives, verrouillage 15 minutes).
- **Politique de mots de passe** — longueur minimale, exigence de majuscules,
  chiffres, caractères spéciaux, durée de validité.
- **Double authentification obligatoire** — force l'activation du 2FA pour
  tous les utilisateurs de l'organisation.

---

## Consulter l'état des sauvegardes

Aller dans **Administration → Paramètres → Sauvegarde**.

Cette page affiche le statut de la dernière sauvegarde et permet de vérifier
que l'archive n'a pas été altérée (vérification SHA-256).

Les sauvegardes sont configurées et gérées par le Super Administrateur.
En cas de problème ou de besoin de restauration, contacter votre prestataire
Pladigit.

---

## Bonnes pratiques

**Comptes utilisateurs**
- Désactiver immédiatement les comptes des agents quittant l'organisation.
- Ne pas créer de compte générique partagé entre plusieurs personnes.
- Revoir la liste des utilisateurs actifs au moins une fois par trimestre.

**Mots de passe**
- Ne jamais transmettre un mot de passe par email.
- Encourager l'utilisation d'un gestionnaire de mots de passe.
- Activer la double authentification sur votre propre compte en priorité.

**Double authentification**
La double authentification (2FA) ajoute une couche de sécurité importante.
Elle demande, en plus du mot de passe, un code à 6 chiffres généré par une
application sur votre téléphone (Google Authenticator, Authy, etc.).
Fortement recommandée pour le compte administrateur, elle peut être rendue
obligatoire pour tous les utilisateurs via les paramètres de sécurité.

---

*Dernière mise à jour : juin 2026*
