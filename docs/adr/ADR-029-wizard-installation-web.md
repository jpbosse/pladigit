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
- **8 étapes** avec barre de progression et navigation visuelle
- **Persistance des choix via `config.json`** — chaque clic "Continuer" écrit/met à jour `install/config.json`. L'utilisateur peut naviguer librement entre les étapes et modifier ses choix. Le fichier est supprimé après utilisation.
- **`write_runner()` appelé au clic "Lancer"** — le runner PHP est généré au dernier moment, avec l'état définitif de `config.json`. Cela garantit que tous les choix (y compris Collabora) sont pris en compte même si l'utilisateur est revenu en arrière.
- **Exécution asynchrone** — le runner `install/runner.php` est lancé en arrière-plan via `shell_exec('php runner.php &')`. Le navigateur polle toutes les 1,5 secondes via Ajax pour lire les logs et détecter la fin.
- **Page de succès statique** — `public/install-success.html` généré par le runner avec les identifiants
- **Auto-destruction** — le dossier `install/` est supprimé 600 secondes après la fin de l'installation

### Étapes du wizard

| Étape | Contenu |
|---|---|
| 1. Bienvenue | Présentation, durée estimée (5-10 min, 30 min avec Collabora Docker) |
| 2. Vérification | PHP version, extensions, permissions storage |
| 3. Base de données | Hôte, port, root MySQL, utilisateur dédié |
| 4. Application | URL, nom organisation, fuseau horaire |
| 5. Email | SMTP optionnel |
| 6. Collabora | Installation Docker locale, instance externe, ou skip |
| 7. Administrateur | Nom, email, mot de passe Super Admin |
| 8. Installation | Récapitulatif + lancement automatique avec barre de progression |

### Transmission des données

Les données saisies dans les formulaires sont stockées dans `install/config.json` (chmod 600) à chaque étape. `write_runner()` lit ce fichier au moment du clic "Lancer l'installation" pour générer `runner.php` avec toutes les valeurs interpolées directement dans le code PHP (pas de variables à résoudre à l'exécution du runner).

Le `.env` est écrit directement par le wizard (pas par le runner) pour garantir l'intégrité du contenu.

### Sécurité

- Après installation réussie, `install/.lock` est créé. Toute tentative d'accès à `install/index.php` retourne une page 403.
- `config.json` est supprimé dès que le runner démarre.
- `runner.php` est supprimé après exécution.

---

## Alternatives écartées

**Formulaire Laravel (Livewire)** — impossible : Laravel ne peut pas démarrer sans `.env` configuré.

**CLI interactive (`php artisan install`)** — accessible uniquement via SSH, exclut les utilisateurs non techniques.

---

## Conséquences

- Le wizard est hébergé sur `pladigit.fr/install-wizard.php` et téléchargé lors de l'installation — une mise à jour du wizard est disponible immédiatement sans mettre à jour le code de chaque installation.
- Le polling Ajax toutes les 1,5 s génère une charge négligeable.
- Le dossier `install/` doit être absent sur un serveur en production.
