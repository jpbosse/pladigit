# Gestion des secrets Pladigit

> Document destiné à l'opérateur qui a accès au serveur.
> Aucune connaissance Unix avancée n'est requise pour les opérations courantes.

---

## La passphrase GPG — ce que c'est, pourquoi c'est critique

Pladigit chiffre automatiquement deux choses avec une seule passphrase :
- **Toutes les archives de sauvegarde** (fichiers `.tar.gz.gpg`)
- **Une copie de secours du fichier `.env`** (fichier `/root/.pladigit_env_backup.gpg`)

Cette passphrase a été générée et affichée **une seule fois** lors de
l'installation, sur l'écran "Sécurité" du wizard. Elle a été stockée
dans la base de données de Pladigit pour les sauvegardes automatiques.

**Si vous ne l'avez plus, personne ne peut vous aider à récupérer vos données.**
Ni votre prestataire, ni l'équipe Pladigit. Il n'existe pas de
procédure de récupération — c'est la nature même du chiffrement.

---

## Où conserver la passphrase

Deux emplacements distincts sont obligatoires :

1. **Un gestionnaire de mots de passe** (Bitwarden, KeePass, 1Password)
   — installé sur le poste du prestataire ou de la mairie, protégé
   par son propre mot de passe maître

2. **Une seconde personne de confiance à la mairie** (DGS, responsable
   informatique, secrétaire de mairie) — transmise **en main propre**
   ou via un gestionnaire de mots de passe partagé

> **⚠ Ne jamais transmettre la passphrase par email, SMS ou messagerie.**
> Ces canaux ne sont pas chiffrés de bout en bout et laissent
> une copie de la passphrase sur des serveurs que vous ne contrôlez pas.
> La remettre en main propre ou la saisir directement dans un
> gestionnaire de mots de passe partagé (Bitwarden Teams, KeePass
> avec fichier sur le NAS de la mairie) sont les seules options sûres.

Un seul emplacement n'est pas suffisant. Si le prestataire disparaît,
si le gestionnaire de mots de passe est inaccessible, si la personne
de contact change de poste — il faut toujours pouvoir retrouver
la passphrase par une autre voie.

---

## Opérations courantes

### Vérifier que le chiffrement GPG est actif

Depuis l'interface Super Admin → Tableau de bord sécurité :
la ligne "Chiffrement des sauvegardes" doit afficher **Activé ✓**.

### Régénérer la passphrase GPG

À faire uniquement en cas de suspicion de compromission ou de
départ du prestataire qui la connaissait.

1. Aller dans Super Admin → Paramètres → Sécurité
2. Cliquer sur "Régénérer la passphrase GPG"
3. Noter la nouvelle passphrase immédiatement
4. La transmettre aux personnes de confiance mentionnées ci-dessus
5. Les anciennes archives ne seront plus déchiffrables avec la nouvelle
   passphrase — conserver l'ancienne pour les archives existantes
   ou relancer une sauvegarde complète immédiatement

### Recréer la copie chiffrée du `.env`

À faire après toute modification du `.env` (changement de mot de passe
MySQL, SMTP, rotation de clés, etc.).

**Option 1 — depuis l'interface (recommandée) :**
Super Admin → Paramètres → Sécurité → "Recréer la copie chiffrée du .env"

**Option 2 — en ligne de commande :**
```bash
gpg --batch --yes --symmetric --cipher-algo AES256 \
    --passphrase "VOTRE_PASSPHRASE_GPG" \
    --output /root/.pladigit_env_backup.gpg \
    /var/www/pladigit/.env
```

### Copier la sauvegarde chiffrée du `.env` hors du serveur

La copie `/root/.pladigit_env_backup.gpg` doit être téléchargée
et conservée hors du serveur (clé USB, NAS de la mairie, email
à soi-même, etc.).

```bash
# Depuis votre poste (remplacer IP et chemin selon votre configuration)
scp ubuntu@IP_DU_SERVEUR:/root/.pladigit_env_backup.gpg ~/Bureau/
```

---

## Procédure de déchiffrement du `.env` (urgence)

**Scénario :** le serveur est compromis ou le `.env` a été perdu.
Vous disposez de la copie chiffrée et de la passphrase.

```bash
gpg --decrypt /root/.pladigit_env_backup.gpg > /var/www/pladigit/.env
# → Saisir la passphrase GPG quand elle est demandée

# Corriger les droits
sudo chown ubuntu:www-data /var/www/pladigit/.env
sudo chmod 640 /var/www/pladigit/.env

# Vider les caches Laravel
php artisan config:clear && php artisan cache:clear
```

---

## TDE MySQL (optionnel — administrateur qualifié uniquement)

Le chiffrement des fichiers MySQL sur disque (TDE) est une protection
supplémentaire contre le vol physique du serveur. Il n'est **pas
activé automatiquement** car sa mise en œuvre nécessite des
compétences Unix/Linux avancées.

Si vous disposez d'un administrateur système qualifié, la configuration
de référence est dans l'ADR-041 §1.1.

La communauté open source est invitée à contribuer un script
d'automatisation. Voir `CONTRIBUTING.md` — label `help wanted / security`.

---

## En cas de perte de la passphrase GPG

Il n'existe pas de procédure de récupération. Les options sont :

1. **Les sauvegardes sont perdues** si aucune copie non chiffrée
   n'existe (ce qui est le cas en fonctionnement normal)
2. **La copie du `.env`** peut être reconstruite manuellement si
   vous avez accès au serveur et pouvez relire les variables
   directement depuis la base de données et la mémoire de votre
   prestataire

La meilleure prévention est de stocker la passphrase à deux endroits
dès l'installation, comme décrit ci-dessus.

---

*Voir aussi : ADR-041 — Sécurité des données au repos, sauvegardes chiffrées et plan de restauration*
