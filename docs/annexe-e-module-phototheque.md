# Annexe E — Module Photothèque

> Documentation utilisateur et administrateur du module **Photothèque** de Pladigit.
> Version : Phase 4/5 — Mars 2026

---

## Table des matières

1. [Présentation](#1-présentation)
2. [Accès et droits](#2-accès-et-droits)
3. [Navigation](#3-navigation)
4. [Albums](#4-albums)
5. [Médias — upload et visualisation](#5-médias--upload-et-visualisation)
6. [Visionneuse et diaporama](#6-visionneuse-et-diaporama)
7. [Édition des médias](#7-édition-des-médias)
8. [Partage par lien temporaire](#8-partage-par-lien-temporaire)
9. [Export ZIP](#9-export-zip)
10. [Recherche globale](#10-recherche-globale)
11. [Synchronisation NAS](#11-synchronisation-nas)
12. [Espace de stockage](#12-espace-de-stockage)

---

## 1. Présentation

Le module **Photothèque** permet à chaque organisation de centraliser, organiser et partager ses médias (photos, vidéos, documents) directement depuis Pladigit, sans passer par un dossier réseau ou un NAS en accès direct.

L'objectif à terme est que **Pladigit devienne la source de vérité** pour la gestion des fichiers multimédias, en remplaçant l'usage quotidien de l'Explorateur Windows sur le NAS.

### Ce que permet le module

| Fonctionnalité | Description |
|---|---|
| Albums hiérarchiques | Organisation en dossiers et sous-dossiers, profondeur illimitée |
| Upload direct | Glisser-déposer ou sélection de fichiers jusqu'à 200 Mo |
| Import ZIP | Dépôt d'une archive entière en une fois |
| Synchronisation NAS | Ingestion automatique des fichiers déposés sur le NAS |
| Visionneuse | Lightbox plein écran avec navigation clavier |
| Diaporama | Défilement automatique réglable (2 s / 3 s / 5 s) |
| Rotation / Recadrage | Édition non-destructive directement dans le navigateur |
| Légende | Ajout d'une description sur chaque photo |
| Partage | Lien temporaire sécurisé, avec ou sans mot de passe |
| Export ZIP | Téléchargement de tout un album en une archive |
| Recherche | Recherche plein texte sur nom, légende, type, date |
| Droits fins | Visibilité et actions par rôle utilisateur |

---

## 2. Accès et droits

### Activation du module

Le module Photothèque doit être activé par le super-administrateur pour chaque organisation, via le champ `enabled_modules` (valeur `media`).

### Rôles et permissions

| Action | Admin | Président / DGS | Resp. Direction / Service | Agent |
|---|:---:|:---:|:---:|:---:|
| Créer un album | ✅ | ✅ | ✅ | — |
| Modifier / supprimer un album | ✅ | ✅ | selon droits | — |
| Uploader des médias | ✅ | ✅ | ✅ | ✅ |
| Voir les médias (album public) | ✅ | ✅ | ✅ | ✅ |
| Voir les médias (album restreint) | selon droits | selon droits | selon droits | selon droits |
| Supprimer un média | ✅ | ✅ | selon droits | — |
| Rotation / recadrage | ✅ | ✅ | selon droits | — |
| Créer un lien de partage | ✅ | ✅ | selon droits | — |
| Gérer les droits d'un album | ✅ | ✅ | — | — |
| Synchroniser le NAS | ✅ | — | — | — |
| Paramètres (quota, watermark…) | ✅ | — | — | — |

### Visibilité des albums

Chaque album possède un niveau de visibilité :

- **Public** — visible par tous les utilisateurs de l'organisation
- **Restreint** — visible uniquement par les utilisateurs explicitement autorisés
- **Privé** — visible uniquement par l'administrateur et le propriétaire

---

## 3. Navigation

### Arbre des albums (barre latérale)

La barre latérale gauche affiche l'arborescence complète des albums. Elle est conçue pour gérer des milliers de dossiers sans ralentissement.

- **›** à gauche d'un dossier : cliquez pour voir ses sous-dossiers (chargement à la demande)
- **▾** : sous-dossiers déjà dépliés — cliquez pour replier
- L'état ouvert/fermé est mémorisé entre les visites (stockage local navigateur)
- L'album en cours est surligné automatiquement, ses ancêtres sont dépliés à l'ouverture

### Recherche dans l'arbre

La barre de recherche en haut de la sidebar cherche dans les **noms d'albums et les chemins NAS** :

- Résultats instantanés dès 2 caractères
- Le chemin NAS est affiché en monospace sous le nom de l'album
- Cliquez sur un résultat pour aller directement dans l'album

---

## 4. Albums

### Créer un album

1. Cliquez sur **+ Nouvel album** (sidebar ou bouton en haut à droite)
2. Renseignez le nom, la description optionnelle, le dossier parent et la visibilité
3. Validez — l'album apparaît immédiatement dans l'arbre

### Modifier un album

Depuis l'album : bouton ✏️ en haut à droite → modifier le nom, la visibilité, les droits.

### Image de couverture

L'image de couverture est affichée dans la grille de la page d'accueil :

- Par défaut : première image de l'album
- Pour choisir une autre image : survolez la photo dans l'album → bouton ⭐ dans le panneau de détail
- Pour réinitialiser : bouton ⭐↺ dans la barre de l'album

### Sous-dossiers

Un album peut contenir des sous-albums de profondeur illimitée. Ils apparaissent en haut de l'album parent sous forme de cartes cliquables.

---

## 5. Médias — upload et visualisation

### Uploader des fichiers

**Glisser-déposer** : déposez des fichiers directement dans la zone de contenu de l'album. Une zone de dépôt s'affiche.

**Sélection manuelle** : bouton **Téléverser** dans la barre latérale.

**Import ZIP** : bouton **Importer un ZIP** — déposez une archive, les fichiers sont extraits et ajoutés à l'album automatiquement.

Formats acceptés : images (JPEG, PNG, WebP, GIF, HEIC…), vidéos (MP4, MOV…), documents (PDF, Office…).
Taille maximale par fichier : **200 Mo**.

> Les fichiers identiques (même SHA-256) ne sont pas dupliqués — un badge rouge « DOUBLON » est affiché sur les doublons détectés.

### Vues disponibles

- **Grille** (⊞) : affichage en mosaïque, 3 à 6 colonnes au choix
- **Liste** (☰) : tableau avec nom, taille, type, date, propriétaire

### Tri et filtres

Dans la barre de l'album, vous pouvez trier par :
- Nom, taille, date de prise de vue (EXIF), date d'import, type

### Sélection multiple

Cliquez sur les cases à cocher en haut à droite des cartes pour sélectionner plusieurs médias. Actions disponibles sur la sélection : supprimer en lot.

### Panneau de détail

Cliquez sur un média pour ouvrir le panneau de détail à droite :

- **Métadonnées** : nom, taille, dimensions, type, date de prise de vue, coordonnées GPS, appareil photo, ouverture/exposition/ISO, sha-256
- **Légende** : cliquez sur le crayon ✏ pour ajouter ou modifier la description
- **Propriétaire** : nom de la personne qui a uploadé le fichier
- **Actions** : Télécharger, Plein écran, Couverture ⭐, Rotation ↺↻, Recadrer ✂, Supprimer 🗑

---

## 6. Visionneuse et diaporama

### Ouvrir la visionneuse (Lightbox)

- **Double-clic** sur une photo dans la grille
- Bouton **⤢ Plein écran** dans le panneau de détail
- Vue Liste : clic simple sur une ligne

### Navigation dans la visionneuse

| Action | Résultat |
|---|---|
| ‹ / › (boutons) | Photo précédente / suivante |
| ← → (clavier) | Photo précédente / suivante |
| Échap | Fermer la visionneuse |
| Espace | Démarrer / Mettre en pause le diaporama |

### Diaporama automatique

La barre en bas de la visionneuse contient les contrôles du diaporama :

- **▶** : démarrer — **⏸** : mettre en pause
- **2s / 3s / 5s** : vitesse de défilement (défaut : 3 secondes)
- Le diaporama s'arrête automatiquement à la dernière photo

---

## 7. Édition des médias

> Ces fonctionnalités sont réservées aux utilisateurs ayant le droit **manage** sur l'album.

Les modifications sont appliquées **directement sur le fichier NAS** et la miniature est régénérée automatiquement. L'opération est irréversible — pensez à conserver une copie si nécessaire.

### Rotation

Depuis le **panneau de détail** ou la **barre de la visionneuse** :

- **↺ Gauche** : rotation 90° dans le sens anti-horaire
- **↻ Droite** : rotation 90° dans le sens horaire
- La miniature et les dimensions sont mises à jour immédiatement

### Recadrage

1. Cliquez sur **✂ Recadrer** dans le panneau de détail ou la visionneuse
2. Une fenêtre s'ouvre avec l'image et une zone de sélection
3. Déplacez et redimensionnez la zone de recadrage
4. Cliquez sur **✓ Recadrer** pour valider — la zone sélectionnée remplace l'image originale

### Légende (caption)

1. Survolez le champ de légende dans le panneau de détail → cliquez sur **✏**
2. Saisissez la légende (500 caractères max)
3. **Entrée** ou clic sur ✓ pour sauvegarder — **Échap** pour annuler

---

## 8. Partage par lien temporaire

Partagez un album avec des personnes extérieures à Pladigit, sans qu'elles aient besoin d'un compte.

### Créer un lien de partage

1. Ouvrez l'album → bouton **🔗 Partager** dans la barre d'outils
2. Renseignez :
   - **Expiration** : date limite de validité du lien (obligatoire)
   - **Mot de passe** : optionnel — le destinataire devra le saisir
   - **Autoriser le téléchargement** : si coché, un bouton "Tout télécharger (ZIP)" est affiché
3. Copiez le lien généré et transmettez-le par email ou autre canal

### Ce que voit le destinataire

- Une page publique avec la galerie de l'album
- Lightbox avec navigation clavier
- Bouton de téléchargement individuel (si autorisé)
- Bouton "Tout télécharger (ZIP)" (si autorisé)

### Supprimer un lien

Depuis le gestionnaire de liens (bouton 🔗) → icône 🗑 à côté du lien concerné. Le lien devient immédiatement invalide.

---

## 9. Export ZIP

Téléchargez tous les fichiers d'un album en une seule archive compressée.

- Bouton **↓ ZIP** dans la barre de l'album
- L'archive est générée à la volée (sans surcharge mémoire) et téléchargée directement
- Limite : albums jusqu'à **500 Mo** de contenu
- Disponible aussi depuis les liens de partage (si l'option a été activée)

---

## 10. Recherche globale

La recherche globale permet de retrouver un fichier dans **toute la photothèque**, quel que soit l'album dans lequel il se trouve.

### Accès

- Barre de recherche en haut de la page d'accueil de la photothèque
- Bouton 🔍 dans la barre de tout album
- Lien **Recherche** dans la barre latérale

### Critères disponibles

| Critère | Description |
|---|---|
| **Mot-clé** | Recherche dans le nom de fichier et la légende |
| **Type** | Tous / Images / Vidéos / Documents |
| **Du / Au** | Date de prise de vue (EXIF) — ou date d'import si pas d'EXIF |

> **À propos des dates** : si la photo contient des métadonnées EXIF, la date utilisée est celle enregistrée par l'appareil photo au moment de la prise de vue. Pour les fichiers sans EXIF (captures d'écran, documents, vidéos…), c'est la date d'import dans Pladigit qui est utilisée.

### Résultats

Les résultats s'affichent sous forme de grille (48 par page) avec :
- La miniature du fichier
- Le nom ou la légende
- L'album d'appartenance (avec le dossier parent)
- La date (prise de vue ou import)

Cliquez sur un résultat pour aller directement dans l'album correspondant.

---

## 11. Synchronisation NAS

> Voir l'**Annexe F — Politique de synchronisation NAS** pour la documentation technique complète.

### Principe

Les fichiers déposés directement sur le NAS (via l'Explorateur Windows ou autre outil réseau) peuvent être **ingérés automatiquement** dans la photothèque via la synchronisation.

### Déclencher une synchronisation

- **Manuellement** : bouton 🔄 dans la barre de la photothèque (admins uniquement)
- **Automatiquement** : tâche planifiée (cron) — fréquence configurable par l'organisation

### Ce que fait la synchronisation

1. Parcourt les dossiers NAS associés aux albums
2. Détecte les nouveaux fichiers (par chemin ou SHA-256)
3. Génère les miniatures et extrait les métadonnées EXIF
4. Ne crée pas de doublons (vérification SHA-256)
5. Supprime les entrées des fichiers qui ont disparu du NAS

### Réorganisation depuis Pladigit

Il est possible de réorganiser les albums directement dans Pladigit (drag-and-drop) — cette opération déplace également les dossiers sur le NAS. Ainsi, **le NAS reflète toujours l'organisation choisie dans Pladigit**.

---

## 12. Espace de stockage

L'espace utilisé est visible dans :

- La **barre latérale** de la photothèque (barre de progression en bas)
- L'**écran de paramètres** du module (Paramètres → Photothèque), pour les administrateurs

Le quota est défini par organisation. Lorsque 90 % du quota est atteint, un avertissement s'affiche.

> L'espace affiché correspond aux fichiers **indexés dans Pladigit** via la synchronisation ou l'upload direct. Les fichiers présents sur le NAS mais non encore synchronisés ne sont pas comptabilisés.
