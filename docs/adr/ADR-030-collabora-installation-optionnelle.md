# ADR-030 — Collabora Online : installation optionnelle via wizard

**Date :** Avril 2026  
**Statut :** Accepté  
**Auteur :** Jean-Pierre Bossé

---

## Contexte

Collabora Online est un composant critique de Pladigit — il permet l'édition collaborative de documents ODF et Microsoft Office directement dans le navigateur. Sans Collabora, le module GED est limité au stockage et au téléchargement des fichiers.

Cependant, Collabora est gourmand en ressources (~2 Go RAM, ~2 Go disque pour l'image Docker) et complexe à installer. Toutes les collectivités ne disposeront pas d'un serveur suffisamment dimensionné, notamment pour une installation initiale de découverte.

---

## Décision

Intégrer une étape **Collabora** dans le wizard d'installation avec trois options :

### Option 1 — Installer sur ce serveur (Docker)

Collabora Online est installé via Docker sur le même serveur via le script `install-collabora.sh` (voir ADR-031). Le wizard :
1. Vérifie l'espace disque disponible (`disk_free_space('/')`)
2. Affiche une recommandation selon l'espace disponible
3. Délègue l'installation à `install-collabora.sh` via `sudo` (règle sudoers configurée par `install.sh`)

L'installation Docker peut durer **10 à 20 minutes** selon la connexion (téléchargement de l'image ~1.5 Go).

### Option 2 — Utiliser une instance existante

L'administrateur renseigne l'URL d'un serveur Collabora existant. Le wizard écrit `COLLABORA_URL` dans le `.env`.

### Option 3 — Passer (configurer plus tard)

Collabora n'est pas configuré. L'édition de documents est désactivée. L'administrateur peut activer Collabora depuis les paramètres de l'organisation.

---

## État d'implémentation (Avril 2026)

| Fonctionnalité | État |
|---|---|
| Étape wizard avec 3 options | ✅ Implémenté |
| Détection espace disque | ✅ Implémenté |
| Installation Docker automatique via `install-collabora.sh` | ✅ Implémenté |
| Configuration `COLLABORA_URL` | ✅ Implémenté |
| Skip / configuration ultérieure | ✅ Implémenté |
| Progression temps réel dans le wizard | ✅ Implémenté |

---

## Prérequis Collabora

- Port 9980 accessible ou reverse proxy Nginx
- Certificat SSL (Collabora requiert HTTPS en production)
- Minimum 2 Go RAM supplémentaires
- ~2 Go d'espace disque pour l'image Docker

---

## Alternatives écartées

**Installation native (paquet .deb)** — complexe, dépend de la distribution.

**Instance mutualisée Pladigit** — intéressante à terme pour les très petites structures, mais nécessite une infrastructure supplémentaire.

---

## Conséquences

- Un administrateur peut déployer Pladigit sans Collabora et l'ajouter ultérieurement.
- Les organisations avec Collabora configuré bénéficient de l'édition collaborative ; les autres ont accès uniquement au téléchargement/upload de fichiers.
