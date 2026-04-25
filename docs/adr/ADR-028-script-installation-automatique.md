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

Créer un script bash `install.sh` hébergé sur `https://pladigit.fr/get-install`, exécutable en une seule commande :

```bash
curl -fsSL https://pladigit.fr/get-install | sudo bash
```

Le script prend en charge l'intégralité de l'installation de l'environnement serveur :

1. **Vérification système** — OS (Ubuntu 22.04/24.04), RAM (≥ 2 Go), disque (≥ 10 Go), connexion internet, ports 80/443
2. **Extension LVM automatique** — Ubuntu Server alloue ~50 % du volume logique par défaut ; le script étend automatiquement le LVM avant la vérification de l'espace disque
3. **Mise à jour système** — `apt-get update && upgrade`
4. **PHP 8.4** — via PPA Ondrej (Ubuntu 22.04/24.04) avec toutes les extensions requises : fpm, cli, mysql, redis, xml, curl, mbstring, zip, gd, intl, bcmath, opcache, imagick, ldap
5. **MySQL 8** — installation + activation de l'authentification native root (incompatible avec PDO par défaut sur Ubuntu)
6. **Redis, Nginx, Supervisor, Node.js 20**
7. **Clonage du dépôt** + installation des dépendances PHP (Composer) et JS (npm + Vite build)
8. **Téléchargement du wizard** depuis `https://pladigit.fr/get-wizard`
9. **Configuration Nginx** — vhost avec bloc `install/` pour le wizard

À la fin, le script affiche l'URL du wizard de configuration.

---

## Protection contre la réinstallation

Le script détecte si Pladigit est déjà installé (présence de `.env` + `install/.lock`) et demande une confirmation explicite avant de continuer :

```
Pour continuer, tapez exactement : je confirme la réinstallation
```

---

## Compatibilité OS

| OS | Support |
|---|---|
| Ubuntu 22.04 LTS | ✅ PHP 8.4 via PPA Ondrej |
| Ubuntu 24.04 LTS | ✅ PHP 8.4 via PPA Ondrej |
| Ubuntu 26.04 LTS | ❌ Non supporté (PPA Ondrej indisponible au moment de l'écriture) |
| Debian, CentOS, etc. | ❌ Non supporté |

---

## Alternatives écartées

**Docker Compose** — plus portable mais ajoute une couche de complexité (gestion des volumes, réseau, logs) qui dépasse les compétences des administrateurs cibles. Envisagé pour une version future.

**Ansible/Terraform** — trop complexe pour un débutant.

**Paquet .deb** — requiert une infrastructure de dépôt APT, hors périmètre pour un projet solo.

---

## Conséquences

- L'installation est accessible à tout administrateur capable de copier-coller une commande dans un terminal SSH.
- Le script est idempotent sur les paquets (vérifie si déjà installés), mais non idempotent sur la configuration Nginx (écrase le vhost existant).
- La maintenance du script est à la charge du projet — toute mise à jour majeure de PHP ou MySQL peut nécessiter une adaptation.
