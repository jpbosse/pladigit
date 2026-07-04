# ADR-028 — Script d'installation automatique `install.sh`

**Date :** Avril 2026  
**Statut :** Accepté  
**Auteur :** Jean-Pierre Bossé

---

## Contexte

Pladigit est destiné aux petites collectivités locales (mairies, communautés de communes, associations) dont les agents n'ont pas de compétences techniques en administration système. L'installation manuelle décrite dans `INSTALL.md` nécessite une vingtaine d'étapes et suppose la maîtrise de la ligne de commande Linux, de PHP-FPM, de Nginx et de MySQL — des prérequis hors de portée pour la majorité des utilisateurs cibles.

Par ailleurs, un projet open source qui cible ADULLACT et les collectivités doit pouvoir être évalué et déployé rapidement par des administrateurs bénévoles ou des ESN de petite taille.

---

## Décision

Créer un script bash `install.sh` hébergé sur `https://pladigit.fr/install.sh`, exécutable en une seule commande :

```bash
curl -fsSL https://pladigit.fr/install.sh | sudo bash
```

Le script prend en charge l'intégralité de l'installation de l'environnement serveur :

1. **Attente verrou apt** — `unattended-upgrades` tourne souvent au boot ; le script attend jusqu'à 5 minutes que le verrou `/var/lib/dpkg/lock-frontend` soit libéré avant de lancer `apt-get`.
2. **Vérification système** — OS (Ubuntu 22.04/24.04), RAM (≥ 2 Go), disque (≥ 10 Go), connexion internet, ports 80/443
3. **Extension LVM automatique** — Ubuntu Server alloue ~50 % du volume logique par défaut ; le script étend automatiquement le LVM avant la vérification de l'espace disque
4. **Mise à jour système** — `apt-get update && upgrade`
5. **PHP 8.4** (version de référence, `PHP_VERSION="8.4"`) — installé depuis le dépôt APT Sury, puis forcé comme `php` par défaut via `update-alternatives`. Pladigit est compatible 8.3, 8.4 et 8.5 (minimum 8.2). Voir la section *Révision* pour le choix du dépôt.
6. **MySQL 8** — installation + activation de l'authentification native root
7. **Redis, Nginx, Supervisor, Node.js 20**
8. **Clonage du dépôt** + installation des dépendances PHP (Composer) et JS (npm + Vite build)
9. **Wizard d'installation** inclus dans le dépôt cloné (`install/index.php`)
10. **Déploiement de `install-collabora.sh`** et configuration de la règle sudoers (voir ADR-031)
11. **Configuration Nginx** — vhost avec bloc `install/` pour le wizard

À la fin, le script affiche l'URL du wizard de configuration.

---

## Gestion du serveur existant

Quand `install.sh` détecte que Pladigit est déjà installé (présence de `.env` + `install/.lock`), il propose un menu à 3 choix :

```
1) Mettre à jour  — git pull + migrations + cache (recommandé)
2) Réinstaller    — repart de zéro (réécrit le .env)
3) Annuler        — ne rien faire
```

La mise à jour (option 1) exécute : `git pull` → `composer install` → `npm build` → `migrate` → cache → redémarrage workers.

---

## Compatibilité OS

| OS | Support |
|---|---|
| Ubuntu 22.04 LTS | ✅ PHP 8.4 via dépôt Sury (natif : 8.1) |
| Ubuntu 24.04 LTS | ✅ PHP 8.4 via dépôt Sury (natif : 8.3) |
| Ubuntu 26.04 LTS | ✅ PHP 8.4 via dépôt Sury (natif : 8.5) |
| Debian, CentOS, etc. | ❌ Non supporté |

---

## Alternatives écartées

**PPA Launchpad `ppa:ondrej/php`** — écarté car instable depuis les environnements virtualisés (erreur 418, timeouts Launchpad). ⚠️ À ne pas confondre avec le dépôt APT `packages.sury.org`, finalement retenu (voir *Révision*) : même mainteneur, mais un dépôt APT direct qui ne passe pas par Launchpad et n'a donc pas ces défauts.

**Docker Compose** — plus portable mais ajoute une couche de complexité hors de portée des administrateurs cibles. Envisagé pour une version future.

**Ansible/Terraform** — trop complexe pour un débutant.

**Paquet .deb** — requiert une infrastructure de dépôt APT, hors périmètre pour un projet solo.

---

## Conséquences

- L'installation est accessible à tout administrateur capable de copier-coller une commande dans un terminal SSH.
- Le script est idempotent sur les paquets (vérifie si déjà installés), mais non idempotent sur la configuration Nginx (écrase le vhost existant).
- La maintenance du script est à la charge du projet — toute mise à jour majeure de PHP ou MySQL peut nécessiter une adaptation.
- Le wizard `install/index.php` est versionné dans le dépôt git — plus de dépendance à une URL externe pour son téléchargement.

---

## Révision — Juillet 2026 : PHP 8.4 via le dépôt APT Sury

**Statut :** en vigueur (décrit le comportement réel de `install.sh`).

La décision initiale (« PHP natif Ubuntu, sans dépôt externe ») reposait sur l'idée que le natif d'Ubuntu suffisait. Elle ne tient plus une fois la version de référence fixée à **8.4** : aucune version LTS d'Ubuntu ne fournit 8.4 nativement (22.04 → 8.1, 24.04 → 8.3, 26.04 → 8.5). Le recours à un dépôt externe est donc devenu nécessaire.

**Ce que fait `install.sh` :**

1. `PHP_VERSION="8.4"`.
2. Si `php8.4` est déjà présent, il est réutilisé (aucun dépôt ajouté).
3. Sinon, ajout du dépôt **APT Sury** (`packages.sury.org`), installation des paquets `php8.4-*`, puis `update-alternatives --set php /usr/bin/php8.4` pour que le natif (8.3 ou 8.5 selon l'OS) ne prenne pas la main sur `composer`/`artisan`.

**Pourquoi ce choix reste cohérent avec le rejet initial :** ce qui avait été écarté, c'était le **PPA Launchpad** `ppa:ondrej/php`, à cause des erreurs 418 et timeouts propres à Launchpad. Le dépôt `packages.sury.org` est un dépôt APT direct du même mainteneur qui **ne passe pas par Launchpad** — il évite donc précisément le problème qui motivait le rejet. Le principe de fond (pas de dépendance à une infrastructure instable) est préservé.

> **Note :** si la motivation réelle du passage à Sury différait de celle décrite ici, compléter ce paragraphe.

**Conséquence :** la ligne « toute mise à jour majeure de PHP peut nécessiter une adaptation » (section *Conséquences*) reste vraie — il suffit d'ajuster `PHP_VERSION` dans `install.sh` ; le dépôt Sury fournit toutes les branches 8.x.
