
PLADIGIT
Guide Super Administrateur

Version 2.0 — Phase 2 — Février 2026
Les Bézots — Chez moi, France
Document confidentiel — Usage interne

# Sommaire
1. Introduction et rôle du Super Administrateur
2. Accès à l'interface Super Admin
3. Gestion des organisations
4. Création d'une organisation
5. Configuration SMTP d'une organisation
6. Configuration LDAP / Active Directory
7. Création du premier administrateur
8. Gestion du cycle de vie des organisations
9. Sécurité et bonnes pratiques
10. Référence rapide

# 1. Introduction et rôle du Super Administrateur
Le Super Administrateur est le gestionnaire technique de la plateforme Pladigit. Il dispose d'un accès complet à l'ensemble des organisations clientes et est responsable du provisionnement, de la configuration technique et de la supervision globale de la plateforme.
Le Super Admin n'est pas un utilisateur d'une organisation cliente — il opère au niveau de la plateforme elle-même, depuis une interface dédiée et isolée.
## 1.1 Responsabilités principales
- Créer et provisionner les bases de données des nouvelles organisations
- Configurer les paramètres techniques (SMTP, LDAP/Active Directory)
- Créer le premier compte administrateur de chaque organisation
- Activer, suspendre ou archiver des organisations
- Surveiller l'état général de la plateforme
⚠ Note : Le Super Admin ne doit pas être utilisé pour des tâches courantes d'administration d'une organisation. Chaque organisation dispose de son propre administrateur.

# 2. Accès à l'interface Super Admin
## 2.1 URL d'accès
L'interface Super Admin est accessible à l'adresse suivante :
https://pladigit.fr/super-admin
⚠ Note : Cette URL est distincte de l'interface des organisations clientes. Elle ne doit pas être communiquée aux utilisateurs finaux.
## 2.2 Connexion
- Saisir l'email et le mot de passe du compte Super Admin
- Le compte Super Admin est unique et créé lors de l'installation de la plateforme
- En cas de perte de mot de passe, contacter l'administrateur système
## 2.3 Déconnexion
Toujours se déconnecter après utilisation via le menu en haut à droite. La session expire automatiquement après inactivité.
✓ Utiliser un gestionnaire de mots de passe pour sécuriser les identifiants Super Admin.

# 3. Gestion des organisations
## 3.1 Liste des organisations
La page d'accueil du Super Admin affiche la liste de toutes les organisations clientes avec leur statut, plan et nombre d'utilisateurs.

# 4. Création d'une organisation
## 4.1 Étapes de création
Pour créer une nouvelle organisation cliente, suivre les étapes suivantes :
- Cliquer sur "Nouvelle organisation" depuis la liste des organisations
- Remplir le formulaire de création
- Valider — la base de données est provisionnée automatiquement
- Configurer SMTP et LDAP depuis la page de détail
- Créer le premier administrateur de l'organisation
## 4.2 Champs du formulaire

## 4.3 Plans disponibles

⚠ Note : Le slug est définitif une fois l'organisation créée. Il détermine le nom de la base de données. Choisir avec soin.

# 5. Configuration SMTP d'une organisation
La configuration SMTP permet à l'organisation d'envoyer des emails (notifications, réinitialisation de mot de passe, alertes). Elle est accessible depuis la page de détail de chaque organisation.
## 5.1 Accès
- Aller dans la liste des organisations
- Cliquer sur l'organisation souhaitée
- Faire défiler jusqu'à la section "Configuration SMTP"
## 5.2 Paramètres SMTP

✓ Le mot de passe SMTP est chiffré en AES-256 avant stockage. Il n'est jamais stocké en clair.
⚠ Note : Laisser le champ mot de passe vide lors d'une mise à jour si le mot de passe n'a pas changé.

# 6. Configuration LDAP / Active Directory
La configuration LDAP permet aux utilisateurs de l'organisation de se connecter avec leurs identifiants Active Directory. Elle est stockée dans la base dédiée de l'organisation.
## 6.1 Prérequis
- Serveur Active Directory ou OpenLDAP accessible depuis le serveur Pladigit
- Compte de service avec droits de lecture sur l'annuaire
- Certificat SSL si connexion LDAPS (recommandé)
## 6.2 Paramètres LDAP

## 6.3 Synchronisation des rôles
Les groupes LDAP sont automatiquement mappés vers les rôles Pladigit lors de la connexion d'un utilisateur. Le mapping est le suivant :

✓ Si un utilisateur appartient à plusieurs groupes, le rôle du premier groupe trouvé dans l'ordre du tableau est appliqué.
⚠ Note : Un utilisateur LDAP sans groupe correspondant reçoit automatiquement le rôle 'user'.
## 6.4 Sécurité LDAPS
La connexion LDAPS (SSL sur port 636) est fortement recommandée en production. Elle chiffre toutes les communications entre Pladigit et le serveur LDAP, protégeant ainsi les identifiants transmis lors de l'authentification.
- En environnement de test : TLS_REQCERT never (certificat auto-signé accepté)
- En production : certificat signé par une autorité de certification reconnue

# 7. Création du premier administrateur
Après la création de l'organisation et la configuration technique, il est nécessaire de créer le premier compte administrateur. Cet administrateur pourra ensuite gérer son organisation de façon autonome.
## 7.1 Étapes
- Depuis la page de détail de l'organisation, accéder à la section "Créer un administrateur"
- Renseigner le nom, l'email et un mot de passe temporaire
- Valider — l'utilisateur est créé avec le rôle 'admin' dans la base tenant
- Communiquer les identifiants à l'administrateur de l'organisation
⚠ Note : L'administrateur devra changer son mot de passe lors de sa première connexion si l'option force_pwd_change est activée.
✓ Utiliser un mot de passe temporaire fort et le transmettre via un canal sécurisé (jamais par email en clair).

# 8. Gestion du cycle de vie des organisations
## 8.1 Activation
Une organisation nouvellement créée est en statut 'pending'. Pour l'activer, cliquer sur le bouton "Activer" depuis la page de détail. Les utilisateurs peuvent alors se connecter.
## 8.2 Suspension
La suspension bloque immédiatement l'accès à l'organisation sans supprimer les données. Utile en cas de non-paiement ou d'incident de sécurité.
- Cliquer sur "Suspendre" depuis la page de détail
- Confirmer la suspension
- Les utilisateurs voient une page d'erreur lors de tentatives de connexion
## 8.3 Réactivation
Une organisation suspendue peut être réactivée à tout moment via le bouton "Activer".
⚠ Note : La suspension et la réactivation sont immédiates. Aucune donnée n'est perdue lors d'une suspension.

# 9. Sécurité et bonnes pratiques
## 9.1 Accès Super Admin
- Ne jamais partager les identifiants Super Admin
- Utiliser un mot de passe fort (minimum 16 caractères, complexe)
- Se déconnecter systématiquement après chaque session
- Accéder uniquement depuis des postes de confiance
## 9.2 Gestion des mots de passe
- Les mots de passe SMTP et LDAP sont chiffrés en AES-256 en base de données
- Ne jamais stocker les mots de passe en clair dans des fichiers ou emails
- Renouveler régulièrement les mots de passe des comptes de service LDAP
## 9.3 Surveillance
- Consulter régulièrement les logs Apache et Laravel
- Vérifier les tentatives de connexion suspectes
- S'assurer que le certificat SSL est valide et à jour

# 10. Référence rapide

— Fin du document —