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
5. **PHP 8.3+** — depuis les dépôts Ubuntu natifs (universe), sans dépôt externe. Compatible PHP 8.3 et 8.4.
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
| Ubuntu 22.04 LTS | ✅ PHP 8.3+ natif (universe) |
| Ubuntu 24.04 LTS | ✅ PHP 8.3+ natif (universe) |
| Debian, CentOS, etc. | ❌ Non supporté |

---

## Alternatives écartées

**Dépôt PPA Ondrej / packages.sury.org** — ces dépôts externes sont instables depuis les environnements virtualisés (erreur 418, timeouts Launchpad). PHP 8.3 étant disponible nativement dans Ubuntu 24.04 `universe`, l'utilisation d'un dépôt externe n'est pas justifiée.

**Docker Compose** — plus portable mais ajoute une couche de complexité hors de portée des administrateurs cibles. Envisagé pour une version future.

**Ansible/Terraform** — trop complexe pour un débutant.

**Paquet .deb** — requiert une infrastructure de dépôt APT, hors périmètre pour un projet solo.

---

## Conséquences

- L'installation est accessible à tout administrateur capable de copier-coller une commande dans un terminal SSH.
- Le script est idempotent sur les paquets (vérifie si déjà installés), mais non idempotent sur la configuration Nginx (écrase le vhost existant).
- La maintenance du script est à la charge du projet — toute mise à jour majeure de PHP ou MySQL peut nécessiter une adaptation.
- Le wizard `install/index.php` est versionné dans le dépôt git — plus de dépendance à une URL externe pour son téléchargement.
