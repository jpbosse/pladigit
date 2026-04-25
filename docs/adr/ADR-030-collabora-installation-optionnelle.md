# ADR-030 — Collabora Online : installation optionnelle via wizard

**Date :** Avril 2026  
**Statut :** Accepté — implémentation partielle  
**Auteur :** Jean-Pierre Bossé

---

## Contexte

Collabora Online est un composant critique de Pladigit — il permet l'édition collaborative de documents ODF et Microsoft Office directement dans le navigateur. Sans Collabora, le module GED est limité au stockage et au téléchargement des fichiers.

Cependant, Collabora est gourmand en ressources (~2 Go RAM, ~2 Go disque pour l'image Docker) et complexe à installer. Toutes les collectivités ne disposeront pas d'un serveur suffisamment dimensionné, notamment pour une installation initiale de découverte.

---

## Décision

Intégrer une étape **Collabora** dans le wizard d'installation avec trois options :

### Option 1 — Installer sur ce serveur (Docker)
Collabora Online est installé via Docker sur le même serveur. Le wizard :
1. Vérifie l'espace disque disponible (`disk_free_space('/')`)
2. Affiche une recommandation selon l'espace :
   - ≥ 4 Go libres → "Suffisant — recommandé"
   - 2–4 Go → "Juste — possible mais surveillez l'espace"
   - < 2 Go → "Insuffisant — utilisez une instance externe"
3. Installe Docker et lance Collabora Online via `docker run`

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
| Installation Docker automatique | 🔄 À implémenter |
| Configuration `COLLABORA_URL` | ✅ Implémenté |
| Skip / configuration ultérieure | ✅ Implémenté |

L'installation Docker automatique (Option 1) n'est pas encore implémentée dans le runner. Sélectionner "Installer sur ce serveur" passe l'étape sans installer Collabora — un avertissement sera ajouté.

---

## Prérequis Collabora

- Docker installé (`apt-get install docker.io`)
- Port 9980 accessible ou reverse proxy Nginx
- Certificat SSL (Collabora requiert HTTPS en production)
- Minimum 2 Go RAM supplémentaires

---

## Alternatives écartées

**Installation native (paquet .deb)** — complexe, dépend de la distribution, peu documenté pour Ubuntu 24.04.

**Instance mutualisée Pladigit** — intéressante à terme pour les très petites structures, mais nécessite une infrastructure supplémentaire.

---

## Conséquences

- Un administrateur peut déployer Pladigit sans Collabora et l'ajouter ultérieurement.
- L'installation Docker complète sera disponible dans une version future du wizard.
- Les organisations avec Collabora configuré bénéficient de l'édition collaborative ; les autres ont accès uniquement au téléchargement/upload de fichiers.
