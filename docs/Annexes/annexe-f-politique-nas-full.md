
PLADIGIT
Plateforme de Digitalisation Interne
Annexe F — Politique de synchronisation NAS
Documentation technique pour les administrateurs système
Version : Phase 4/5 — Mars 2026
Organisation : Les Bézots — Soullans (85), France
Responsable : Jean-Pierre Bossé
⚠ DOCUMENT CONFIDENTIEL

# 1. Principe de fonctionnement
La synchronisation NAS est le mécanisme par lequel Pladigit ingère les fichiers déposés sur un partage réseau (NAS, serveur de fichiers) et les rend accessibles dans le module Photothèque.


ℹ  La synchronisation est non-destructive : elle n'efface jamais de fichiers sur le NAS. Elle peut en revanche supprimer des entrées en base de données si un fichier a disparu du NAS.

# 2. Drivers NAS supportés
Pladigit supporte trois drivers via l'abstraction NasConnectorInterface :


Le driver est sélectionné dans config/nas.php via la clé driver. Un seul driver est actif par organisation.

# 3. Configuration
## config/nas.php

## Variables d'environnement .env

# 4. Algorithme de synchronisation
La synchronisation suit ces étapes pour chaque dossier NAS associé à un album :


## Mode standard vs mode profond

Le mode standard est rapide (pas de lecture de chaque fichier). Le mode profond est plus lent mais détecte les fichiers modifiés.

# 5. Déduplication par SHA-256
Pladigit calcule l'empreinte SHA-256 de chaque fichier ingéré. Si un fichier identique existe déjà en base de données (même hash), il est marqué comme doublon sans être dupliqué sur le NAS.

- Les doublons sont signalés par un badge rouge DOUBLON dans la grille de l'album
- Ils restent accessibles et téléchargeables
- L'administrateur peut les supprimer manuellement ou les conserver

ℹ  La déduplication est basée sur le contenu, pas sur le nom. Deux photos de noms différents mais de contenu identique seront détectées comme doublons.

# 6. Commandes artisan
## Synchronisation standard
Synchronise tous les albums de toutes les organisations ayant un nas_path défini.

## Synchronisation profonde
Recalcule le SHA-256 de chaque fichier. À utiliser après des modifications manuelles de fichiers sur le NAS.

## Synchronisation d'une organisation spécifique

## Re-extraction EXIF
Ré-extrait les métadonnées EXIF de tous les médias images. Utile après une migration ou si l'extraction a échoué lors de la sync initiale.

# 7. Planification automatique (cron)
La synchronisation est planifiée dans routes/console.php (scheduler Laravel) :


## Prérequis cron système
Sur le serveur, la tâche cron Laravel doit être active :

# 8. Politique de nommage recommandée
Pour que la synchronisation produise une arborescence d'albums propre dans Pladigit, il est recommandé d'adopter une convention de nommage des dossiers NAS.

## Convention recommandée

## À éviter

## Attribution des fichiers importés via sync
- Album créé manuellement : attribué à l'utilisateur ayant créé l'album
- Album créé automatiquement par la sync : attribué au premier administrateur de l'organisation

# 9. Réorganisation depuis Pladigit
## Principe
L'objectif stratégique de Pladigit est de remplacer l'usage direct du NAS comme outil de travail quotidien. Quand un administrateur réorganise un album dans Pladigit :

- Pladigit déplace le dossier correspondant sur le NAS (opération physique)
- Met à jour le parent_id et le nas_path de l'album et de tous ses descendants en base
- La prochaine synchronisation trouve les dossiers à leur nouvel emplacement — aucun doublon n'est créé

## Flux recommandé pour les agents

ℹ  Ce flux garantit que le NAS et Pladigit sont toujours cohérents, sans divergence entre ce qu'on voit dans l'Explorateur Windows et ce qu'on voit dans Pladigit.

# 10. Limites et précautions
## Taille maximale par fichier
Les fichiers de plus de 200 Mo sont ignorés lors de la synchronisation (configurable via NAS_MAX_FILE_MB).

## Formats non supportés
Seuls les MIME types listés dans config/nas.php (allowed_mimes) sont ingérés. Les autres fichiers sont ignorés silencieusement.

## Verrou de synchronisation
Une synchronisation en cours pose un verrou pour éviter les exécutions parallèles. Si une sync reste bloquée (crash serveur), le verrou peut être levé manuellement :

## Modification directe sur le NAS pendant une sync
⚠  Il est déconseillé de déplacer ou renommer des dossiers sur le NAS pendant une synchronisation en cours. Cela peut créer des entrées orphelines temporaires, qui seront nettoyées à la prochaine sync.

## Quota de stockage
La sync ne vérifie pas le quota de stockage de l'organisation avant d'ingérer des fichiers. Le quota est informatif (affiché dans la sidebar). Si le quota est dépassé, un avertissement est affiché mais les fichiers continuent d'être indexés.

## Pas de sauvegarde automatique
⚠  Pladigit ne sauvegarde pas les fichiers NAS — il les indexe. La politique de sauvegarde du NAS (snapshots, RAID, sauvegardes externes) reste de la responsabilité de l'organisation.