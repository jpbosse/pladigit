# ADR-029 — Wizard d'installation web PHP standalone

**Date :** Avril 2026  
**Statut :** Accepté  
**Auteur :** Jean-Pierre Bossé

---

## Contexte

Après l'exécution de `install.sh`, l'environnement serveur est prêt (PHP, MySQL, Redis, Nginx, Node.js) mais Pladigit n'est pas encore configuré. Il manque :

- Le fichier `.env` (connexion MySQL, APP_KEY, SMTP, Super Admin)
- La création de l'utilisateur MySQL dédié
- Les migrations de base de données
- Le hash bcrypt du mot de passe Super Admin

Ces informations sont propres à chaque installation et ne peuvent pas être devinées par le script. Il faut un formulaire interactif.

---

## Décision

Créer un wizard web PHP standalone `install/index.php`, accessible sur `http://IP-DU-SERVEUR/install/` après l'exécution de `install.sh`.

### Caractéristiques techniques

- **PHP standalone** — aucune dépendance à Laravel, Composer ou aux assets JS. Le wizard fonctionne avant que Pladigit soit configuré.
- **7 étapes** avec barre de progression et navigation visuelle
- **Exécution asynchrone** — le runner `install/runner.php` est lancé en arrière-plan via `shell_exec('php runner.php &')`. Le navigateur polle toutes les 1,5 secondes via Ajax pour lire les logs et détecter la fin.
- **Page de succès statique** — `public/install-success.html` généré par le runner avec les identifiants, accessible même après suppression du dossier `install/`
- **Auto-destruction** — le dossier `install/` est supprimé 600 secondes après la fin de l'installation via un script shell `nohup`

### Étapes du wizard

| Étape | Contenu |
|---|---|
| 1. Bienvenue | Présentation, durée estimée |
| 2. Vérification | PHP version, extensions, permissions storage |
| 3. Base de données | Hôte, port, root MySQL, utilisateur dédié avec mot de passe personnalisé |
| 4. Application | URL, nom organisation, fuseau horaire |
| 5. Email | SMTP optionnel (bouton "Passer") |
| 6. Collabora | Installation Docker locale ou instance externe ou skip |
| 7. Administrateur | Nom, email, mot de passe Super Admin (hashé en bcrypt) |

### Transmission des données

Les données sensibles (mots de passe MySQL, mot de passe Super Admin) sont transmises au runner via un fichier `install/config.json` (chmod 600) plutôt qu'embarquées dans le code PHP généré — évite les problèmes d'échappement avec les caractères spéciaux.

Le `.env` est écrit directement par le wizard (pas par le runner) pour garantir l'intégrité du contenu.

### Sécurité

- Après installation réussie, `install/.lock` est créé. Toute tentative d'accès à `install/index.php` retourne une page 403 avec lien vers `/super-admin`.
- Les appels API Ajax (`api_log`, `api_status`, `api_run`) bypassent le check lock pour permettre le polling en fin d'installation.
- `config.json` est supprimé dès que le runner l'a lu.

---

## Alternatives écartées

**Formulaire Laravel (Livewire)** — impossible : Laravel ne peut pas démarrer sans `.env` configuré.

**CLI interactive (`php artisan install`)** — accessible uniquement via SSH, exclut les utilisateurs non techniques.

**Interface web Node.js** — surcharge technique injustifiée pour un wizard usage unique.

---

## Conséquences

- Le wizard est hébergé sur `pladigit.fr` et téléchargé lors de l'installation — une mise à jour du wizard est disponible immédiatement sans mettre à jour le code de chaque installation.
- Le polling Ajax toutes les 1,5 s génère une charge négligeable (fichier log lu en lecture seule).
- Le dossier `install/` doit être absent sur un serveur en production — sa présence doit déclencher une alerte monitoring.
