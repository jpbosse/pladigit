# Guide Super Administrateur — Pladigit

> Ce guide s'adresse au Super Administrateur de la plateforme Pladigit.
> Il couvre la gestion des organisations, la configuration technique, les sauvegardes
> et les procédures d'urgence.
> Dernière mise à jour : juin 2026.

---

## Rôle et périmètre

Le Super Administrateur est le responsable technique de la plateforme dans son ensemble.
Il n'appartient à aucune organisation cliente — il opère au niveau de l'infrastructure,
depuis une interface dédiée et isolée.

Ses responsabilités couvrent :

- la création et le provisionnement des nouvelles organisations ;
- la configuration technique partagée (SMTP, LDAP, modules) ;
- la gestion du cycle de vie des organisations (activation, suspension, suppression) ;
- la configuration et le suivi des sauvegardes ;
- les interventions d'urgence (restauration, perte de mot de passe, incident de sécurité).

Le Super Admin ne doit pas être utilisé pour des tâches courantes d'administration
d'une organisation. Chaque organisation dispose de son propre administrateur pour cela.

---

## Accès à l'interface

L'interface Super Admin est accessible à l'adresse :

```
https://pladigit.fr/super-admin
```

L'accès est restreint par adresse IP. Seules les adresses déclarées dans le fichier
`.env` (`SUPER_ADMIN_ALLOWED_IPS`) peuvent atteindre cette interface. Toute tentative
depuis une adresse non autorisée reçoit une erreur 403.

Le compte Super Admin est unique, créé lors de l'installation. En cas de perte du
mot de passe, la réinitialisation se fait directement sur le serveur via la commande :

```bash
cd /var/www/pladigit
sudo -u www-data php artisan pladigit:reset-super-admin-password
```

Toujours se déconnecter après chaque session. La session expire automatiquement
après inactivité prolongée.

---

## Créer une nouvelle organisation

Depuis la liste des organisations, cliquer sur **Nouvelle organisation** et remplir
le formulaire :

| Champ | Obligatoire | Notes |
|-------|-------------|-------|
| Nom | Oui | Nom complet affiché dans l'interface (ex : Commune de Soullans) |
| Slug | Oui | Identifiant URL unique, lettres minuscules et tirets (ex : soullans). **Définitif — impossible à modifier.** |
| Plan | Oui | Communautaire (gratuit) ou Partenaire (sur devis) |
| Quota de stockage | Oui | En Mo. Minimum 512 Mo. Par défaut 10 240 Mo (10 Go) |

Le slug détermine l'URL de l'organisation (`soullans.pladigit.fr`) et le nom de sa
base de données (`pladigit_soullans`). Le choisir avec soin — une erreur impose
de supprimer et recréer l'organisation.

Après création, la base de données est provisionnée automatiquement et l'organisation
passe en statut `pending`. Elle doit être activée manuellement avant que les
utilisateurs puissent se connecter.

---

## Configurer le SMTP d'une organisation

La configuration SMTP permet à l'organisation d'envoyer des emails — notifications,
réinitialisation de mot de passe, alertes. Elle se configure depuis la page de détail
de chaque organisation.

Le mot de passe SMTP est chiffré avant stockage. Laisser le champ vide lors d'une
mise à jour si le mot de passe n'a pas changé — le laisser vide efface la valeur
existante.

Un bouton **Tester la connexion SMTP** permet de vérifier la configuration avant
de la valider.

---

## Configurer LDAP / Active Directory

La configuration LDAP permet aux utilisateurs de se connecter avec leurs identifiants
Windows habituels. Elle est stockée dans la base dédiée de l'organisation.

Prérequis :

- serveur Active Directory ou OpenLDAP accessible depuis le serveur Pladigit ;
- compte de service avec droits de lecture sur l'annuaire ;
- certificat SSL si connexion LDAPS (recommandé en production).

La connexion LDAPS (port 636, chiffrée) est fortement recommandée. En environnement
de test, un certificat auto-signé peut être accepté en configurant `TLS_REQCERT never`.

Les groupes LDAP sont automatiquement mappés vers les rôles Pladigit lors de chaque
connexion. Un utilisateur sans groupe correspondant reçoit automatiquement le rôle
`user`.

---

## Créer le premier administrateur d'une organisation

Après création de l'organisation et configuration technique, créer le premier compte
administrateur depuis la page de détail de l'organisation, section **Créer un
administrateur**.

Renseigner le nom, l'email et un mot de passe temporaire fort. L'utilisateur est
créé avec le rôle `admin` dans la base tenant de l'organisation.

Communiquer les identifiants par téléphone ou en face à face — jamais par email
non chiffré. L'administrateur devra changer son mot de passe à la première connexion.

---

## Gérer le cycle de vie d'une organisation

**Activation** — une organisation nouvellement créée est en statut `pending`.
Cliquer sur **Activer** depuis la page de détail pour permettre aux utilisateurs
de se connecter.

**Suspension** — bloque immédiatement l'accès sans supprimer les données. Utile
en cas d'incident ou de non-renouvellement. Les utilisateurs voient une page d'erreur.
La réactivation est immédiate via le bouton **Activer**.

**Suppression** — définitive et irréversible. Supprime la base de données et tous
les fichiers de l'organisation. Toujours effectuer une sauvegarde manuelle avant
de supprimer une organisation.

---

## Sauvegardes

### Configuration

La configuration des sauvegardes est centralisée dans l'interface Super Admin,
section **Sauvegarde**. Une seule destination commune pour toutes les organisations.

Paramètres à configurer :

- **Chemin local** — répertoire sur le serveur où sont stockées les archives
  (ex : `/var/www/pladigit/storage/app/private/backup`). Le bouton **Vérifier
  le chemin** crée le répertoire s'il n'existe pas et vérifie les droits d'écriture.
- **Planification** — quotidienne (minuit) recommandée.
- **Rétention** — nombre d'archives conservées par organisation (défaut : 7).
- **Chiffrement GPG** — activé lors de l'installation via le wizard. La passphrase
  est générée automatiquement et stockée chiffrée.

Chaque organisation génère sa propre archive dans un sous-répertoire dédié :
`{chemin}/{slug}/backup_YYYY-MM-DD_HHmmss_{slug}.tar.gz.gpg`

### Lancer une sauvegarde manuelle

Depuis l'interface Super Admin → Sauvegarde, cliquer sur **Lancer la sauvegarde**.
Toutes les organisations actives sont sauvegardées en séquence. Le statut se met
à jour automatiquement.

Un délai minimum de 10 minutes est imposé entre deux sauvegardes manuelles.

### Ce que contient une archive

Chaque archive contient :

- le dump complet de la base de données plateforme ;
- le dump complet de la base de données tenant ;
- les fichiers GED (`storage/app/private/ged/`) ;
- les médias NAS si configurés en local ;
- le fichier `.env` du serveur.

Un fichier `.sha256` est généré à côté de chaque archive pour vérification d'intégrité.

### Chiffrement GPG — point critique

> ⚠ La passphrase GPG est la clé de déchiffrement de toutes les archives.
> Sans elle, les archives `.gpg` sont irrécupérables — aucune procédure technique
> ne permet de les déchiffrer.

**Trois obligations dès l'activation du chiffrement :**

- [ ] Conserver la passphrase dans un gestionnaire de mots de passe (Bitwarden, KeePass)
- [ ] En faire une copie sur un support physique sécurisé, séparé du serveur
- [ ] S'assurer qu'un successeur désigné y a accès en cas d'indisponibilité

La passphrase est consultable dans l'interface Super Admin → Sauvegarde → Chiffrement GPG,
à condition d'être connecté depuis une adresse IP autorisée.

---

## Restauration

La restauration est une opération manuelle sur le serveur. Elle est réservée au
Super Administrateur. Les administrateurs d'organisation ne peuvent pas restaurer
leurs données — en cas de sinistre, ils contactent leur prestataire Pladigit.

### Étape 1 — Déchiffrer l'archive

```bash
GNUPGHOME=/var/www/pladigit/storage/.gnupg gpg \
  --batch --decrypt \
  --passphrase "VOTRE_PASSPHRASE" \
  --output /tmp/backup_restore.tar.gz \
  /var/www/pladigit/storage/app/private/backup/demo/backup_2026-06-13_101917_demo.tar.gz.gpg
```

### Étape 2 — Vérifier l'intégrité

```bash
sha256sum -c /var/www/pladigit/storage/app/private/backup/demo/backup_2026-06-13_101917_demo.tar.gz.gpg.sha256
```

### Étape 3 — Extraire l'archive

```bash
mkdir /tmp/restore
tar -xzf /tmp/backup_restore.tar.gz -C /tmp/restore
ls /tmp/restore
```

### Étape 4 — Restaurer les bases de données

```bash
zcat /tmp/restore/db_platform.sql.gz | sudo mysql -u root pladigit
zcat /tmp/restore/db_demo.sql.gz | sudo mysql -u root pladigit_demo
```

### Étape 5 — Restaurer les fichiers si nécessaire

Copier les fichiers GED et médias depuis `/tmp/restore/` vers leurs emplacements
d'origine. Vérifier les droits après copie :

```bash
sudo chown -R www-data:www-data /var/www/pladigit/storage/app/private/ged/
```

### Étape 6 — Nettoyer

```bash
rm -rf /tmp/restore /tmp/backup_restore.tar.gz
```

---

## Installer Collabora Online

Collabora Online est le module d'édition de documents collaboratif. Il est optionnel
et ne s'installe jamais automatiquement lors de l'installation standard.

Pour l'activer sur le serveur :

```bash
sudo bash /var/www/pladigit/install.sh --collabora-only https://pladigit.fr /var/www/pladigit
```

Cette commande installe Docker, télécharge et configure le conteneur Collabora,
et met à jour la configuration Nginx. L'opération prend environ 5 à 10 minutes.

Vérifier l'installation :

```bash
docker ps | grep collabora
```

---

## Sécurité — bonnes pratiques

**Accès Super Admin**
- Ne jamais partager les identifiants Super Admin.
- Accéder uniquement depuis des postes de confiance et des adresses IP déclarées.
- Se déconnecter systématiquement après chaque session.
- Utiliser un mot de passe d'au moins 16 caractères, géré par un gestionnaire de mots de passe.

**Surveillance**
- Consulter régulièrement les logs : `sudo tail -f /var/log/pladigit-worker.log`
- Vérifier la validité du certificat SSL avant son expiration.
- Contrôler l'intégrité des archives depuis l'interface Super Admin → Sauvegarde → Tester.

**Vérifier que `/install/` est verrouillé**

Après toute réinstallation, vérifier que l'accès à l'interface d'installation
est bien bloqué :

```bash
curl -sk -o /dev/null -w "%{http_code}\n" https://pladigit.fr/install/
# Doit retourner 403
```

---

*Dernière mise à jour : juin 2026*
