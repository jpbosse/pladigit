
PLADIGIT
Guide Administrateur Organisation

Version 2.0 — Phase 2 — Février 2026
Les Bézots — Chez moi, France
Document confidentiel — Usage interne

# Sommaire
1. Rôle de l'Administrateur Organisation
2. Première connexion
3. Tableau de bord
4. Gestion des utilisateurs
5. Personnalisation de l'espace (Branding)
6. Gestion des rôles et permissions
7. Réinitialisation de mot de passe
8. Sécurité et bonnes pratiques
9. Référence rapide

# 1. Rôle de l'Administrateur Organisation
L'Administrateur Organisation est le responsable technique et fonctionnel de l'espace Pladigit de son organisation. Il gère les utilisateurs, personnalise l'interface et s'assure du bon fonctionnement de la plateforme pour son organisation.
Contrairement au Super Administrateur (qui gère la plateforme globale), l'Administrateur Organisation n'a accès qu'à son propre espace et ne peut pas voir ni modifier les données des autres organisations.
## 1.1 Responsabilités
- Créer, modifier et désactiver les comptes utilisateurs
- Attribuer les rôles appropriés à chaque utilisateur
- Personnaliser l'apparence de l'espace (logo, couleurs)
- Réinitialiser les mots de passe en cas de besoin
- Veiller au respect des règles de sécurité
⚠ Note : L'Administrateur Organisation ne configure pas le SMTP ni le LDAP — ces paramètres sont gérés par le Super Administrateur de la plateforme.

# 2. Première connexion
## 2.1 Accès à la plateforme
L'URL de connexion est communiquée par le Super Administrateur lors de la mise en service. Elle est de la forme :
https://[votre-organisation].pladigit.fr/login
## 2.2 Identifiants initiaux
Les identifiants initiaux (email + mot de passe temporaire) sont fournis par le Super Administrateur lors de la création du compte.
- Se rendre sur l'URL de connexion
- Saisir l'email et le mot de passe temporaire
- Changer le mot de passe dès la première connexion si demandé
- Accéder au tableau de bord administrateur
✓ Choisir un mot de passe fort : au moins 12 caractères, mêlant majuscules, chiffres et caractères spéciaux.

# 3. Tableau de bord
Le tableau de bord est la page d'accueil de l'Administrateur Organisation. Il affiche un résumé de l'état de l'organisation : nombre d'utilisateurs, modules actifs et dernières activités.
## 3.1 Navigation
Le menu de navigation permet d'accéder aux différentes sections de l'administration :
- Utilisateurs — gestion des comptes
- Paramètres — personnalisation et configuration
- Tableau de bord — vue d'ensemble
## 3.2 Accès à l'espace admin
L'espace administration est accessible uniquement aux utilisateurs ayant le rôle 'admin'. L'URL est :
https://[votre-organisation].pladigit.fr/admin

# 4. Gestion des utilisateurs
## 4.1 Liste des utilisateurs
La liste des utilisateurs est accessible depuis Admin → Utilisateurs. Elle affiche tous les comptes de l'organisation avec leur rôle, statut et date de dernière connexion.
## 4.2 Créer un utilisateur
- Aller dans Admin → Utilisateurs → Créer un utilisateur
- Remplir le formulaire
- Attribuer un rôle
- Valider — l'utilisateur peut se connecter immédiatement


## 4.3 Modifier un utilisateur
Depuis la liste des utilisateurs, cliquer sur l'icône de modification à droite du compte. Il est possible de modifier le nom, le rôle, le statut et le département.
⚠ Note : L'email ne peut pas être modifié après création — c'est l'identifiant unique de l'utilisateur.
## 4.4 Désactiver un utilisateur
La désactivation d'un compte bloque l'accès à la plateforme sans supprimer les données. L'utilisateur voit un message d'erreur lors de tentatives de connexion.
- Cliquer sur le bouton "Désactiver" depuis la liste ou la page d'édition
- Confirmer l'action
- Le compte passe en statut 'inactive'
✓ Préférer la désactivation à la suppression pour conserver l'historique des actions de l'utilisateur.
## 4.5 Statuts des utilisateurs

# 5. Personnalisation de l'espace (Branding)
La section Personnalisation permet d'adapter l'apparence de la plateforme aux couleurs et à l'identité visuelle de l'organisation.
## 5.1 Accès
- Aller dans Admin → Paramètres → Personnalisation
## 5.2 Éléments personnalisables

## 5.3 Appliquer les modifications
- Choisir la couleur via le sélecteur de couleur ou saisir le code hexadécimal
- Téléverser le logo et/ou l'image de fond si nécessaire
- Cliquer sur "Sauvegarder"
- Rafraîchir la page pour voir les changements appliqués
✓ Utiliser un logo en format SVG pour une meilleure qualité d'affichage sur tous les écrans.

# 6. Gestion des rôles et permissions
La plateforme Pladigit dispose de 6 niveaux de rôles hiérarchiques. Chaque rôle définit les fonctionnalités accessibles à l'utilisateur.

## 6.1 Attribution des rôles
Le rôle est attribué lors de la création du compte et peut être modifié à tout moment depuis la page d'édition de l'utilisateur.
⚠ Note : Il ne peut y avoir qu'un seul administrateur principal par organisation. Créer plusieurs admins doit rester exceptionnel.
## 6.2 Connexion via Active Directory
Si l'organisation utilise Active Directory, les rôles sont synchronisés automatiquement depuis les groupes LDAP lors de chaque connexion. Le tableau de correspondance est défini par le Super Administrateur.

# 7. Réinitialisation de mot de passe
En tant qu'Administrateur Organisation, vous pouvez réinitialiser le mot de passe d'un utilisateur qui ne peut plus se connecter.
## 7.1 Procédure
- Aller dans Admin → Utilisateurs
- Trouver l'utilisateur concerné
- Cliquer sur "Réinitialiser le mot de passe"
- Un nouveau mot de passe aléatoire est généré et affiché à l'écran
- Communiquer ce mot de passe à l'utilisateur via un canal sécurisé
- L'utilisateur devra changer son mot de passe à la prochaine connexion
⚠ Note : Le nouveau mot de passe n'est affiché qu'une seule fois. Le noter immédiatement avant de fermer la page.
✓ Communiquer le mot de passe temporaire par téléphone ou en face à face, jamais par email non chiffré.

# 8. Sécurité et bonnes pratiques
## 8.1 Gestion des comptes
- Désactiver immédiatement les comptes des collaborateurs quittant l'organisation
- Ne pas partager les identifiants entre plusieurs personnes
- Revoir régulièrement la liste des utilisateurs actifs
- Attribuer uniquement les droits nécessaires à chaque utilisateur (principe du moindre privilège)
## 8.2 Mots de passe
- Exiger des mots de passe forts (configuré par le Super Admin dans les paramètres de sécurité)
- Ne jamais communiquer les mots de passe par email non chiffré
- Encourager l'utilisation d'un gestionnaire de mots de passe
## 8.3 Double authentification (2FA)
La double authentification (2FA) via application TOTP (Google Authenticator, Authy...) est disponible pour tous les utilisateurs. Elle ajoute une couche de sécurité supplémentaire en demandant un code à 6 chiffres en plus du mot de passe.
- Fortement recommandée pour le compte administrateur
- Peut être rendue obligatoire par le Super Admin via le paramètre force_2fa

# 9. Référence rapide

— Fin du document —