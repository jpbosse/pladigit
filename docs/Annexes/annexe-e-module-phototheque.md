# Annexe E — Module Photothèque

> Documentation utilisateur et administrateur du module **Photothèque** de Pladigit.
> Version : Phase 4 — Mars 2026

---

## Table des matières

1. [Présentation](#1-présentation)
2. [Accès et droits](#2-accès-et-droits)
3. [Navigation et arbre des albums](#3-navigation-et-arbre-des-albums)
4. [Albums — création et gestion](#4-albums--création-et-gestion)
5. [Médias — upload et visualisation](#5-médias--upload-et-visualisation)
6. [Tags manuels](#6-tags-manuels)
7. [Visionneuse et diaporama](#7-visionneuse-et-diaporama)
8. [Édition des médias](#8-édition-des-médias)
9. [Réorganisation par glisser-déposer](#9-réorganisation-par-glisser-déposer)
10. [Partage par lien temporaire](#10-partage-par-lien-temporaire)
11. [Export ZIP](#11-export-zip)
12. [Recherche globale](#12-recherche-globale)
13. [Synchronisation NAS](#13-synchronisation-nas)
14. [Espace de stockage et quota](#14-espace-de-stockage-et-quota)

---

## 1. Présentation

Le module **Photothèque** permet à chaque organisation de centraliser, organiser et partager ses médias (photos, vidéos, documents) directement depuis Pladigit, sans passer par un dossier réseau ou un NAS en accès direct.

L'objectif stratégique est que **Pladigit devienne la source de vérité** pour la gestion des fichiers multimédias, en remplaçant l'usage quotidien de l'Explorateur Windows sur le NAS. Les réorganisations d'albums se font depuis Pladigit, qui répercute automatiquement les changements sur le NAS.

### Ce que permet le module

| Fonctionnalité | Description |
|---|---|
| Albums hiérarchiques | Organisation en dossiers et sous-dossiers, profondeur illimitée |
| Upload direct | Glisser-déposer ou sélection de fichiers jusqu'à 200 Mo |
| Import ZIP | Dépôt d'une archive entière en une fois |
| Synchronisation NAS | Ingestion automatique des fichiers déposés sur le NAS |
| Tags manuels | Mots-clés libres sur chaque photo, avec autocomplete |
| Visionneuse | Lightbox plein écran avec navigation clavier |
| Diaporama | Défilement automatique réglable (2 s / 3 s / 5 s) |
| Rotation / Recadrage | Édition directement dans le navigateur |
| Légende | Description textuelle sur chaque photo |
| Déplacement par glisser-déposer | Déplacer des photos et des albums par drag-and-drop |
| Partage | Lien temporaire sécurisé, avec ou sans mot de passe |
| Export ZIP | Téléchargement de tout un album en une archive |
| Recherche | Recherche plein texte sur nom, légende, tag, type, date |
| Droits fins | Visibilité et actions par rôle et par album |
| Quota de stockage | Plafond par organisation, alertes à 80 / 90 / 95 % |

---

## 2. Accès et droits

### Activation du module

Le module Photothèque doit être activé par le super-administrateur pour chaque organisation, via le champ `enabled_modules` (valeur `media`).

### Rôles et permissions

| Action | Admin | Président / DGS | Resp. Direction / Service | Agent |
|---|:---:|:---:|:---:|:---:|
| Créer un album | ✅ | ✅ | ✅ | — |
| Modifier / supprimer un album | ✅ | ✅ | selon droits | — |
| Réorganiser la hiérarchie (DnD albums) | ✅ | ✅ | — | — |
| Uploader des médias | ✅ | ✅ | ✅ | ✅ |
| Déplacer des médias vers un autre album | ✅ | ✅ | selon droits | — |
| Voir les médias (album public) | ✅ | ✅ | ✅ | ✅ |
| Voir les médias (album restreint) | selon droits | selon droits | selon droits | selon droits |
| Supprimer un média | ✅ | ✅ | selon droits | — |
| Ajouter / retirer un tag | ✅ | ✅ | selon droits | — |
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

La visibilité est héritée par les sous-albums : un sous-album d'un album restreint est au moins aussi restrictif que son parent.

---

## 3. Navigation et arbre des albums

### Arbre des albums (barre latérale)

La barre latérale gauche affiche l'arborescence complète des albums. Elle est conçue pour gérer des milliers de dossiers sans ralentissement (chargement des enfants à la demande).

- **›** à gauche d'un dossier : cliquez pour voir ses sous-dossiers
- **▾** : sous-dossiers déjà dépliés — cliquez pour replier
- L'état ouvert/fermé est mémorisé entre les visites (stockage local navigateur)
- L'album en cours est surligné automatiquement, ses ancêtres sont dépliés à l'ouverture
- Le **+** qui apparaît au survol d'un album crée directement un sous-album

### Recherche dans l'arbre

La barre de recherche en haut de la sidebar cherche dans les **noms d'albums** :

- Résultats instantanés dès 2 caractères (délai de 300 ms)
- Cliquez sur un résultat pour aller directement dans l'album

---

## 4. Albums — création et gestion

### Créer un album

1. Cliquez sur **+ Nouvel album** (sidebar ou bouton en haut à droite)
2. Renseignez le nom, la description optionnelle, le dossier parent et la visibilité
3. Validez — l'album apparaît immédiatement dans l'arbre

### Modifier un album

Depuis l'album : bouton ✏️ en haut à droite → modifier le nom, la visibilité, les droits.

### Image de couverture

L'image de couverture est affichée dans la grille de la page d'accueil et dans la sidebar :

- Par défaut : première image de l'album (ou première image d'un sous-album)
- Pour choisir une autre image : survolez la photo dans l'album → bouton ⭐ dans le panneau de détail
- Pour réinitialiser : bouton ⭐↺ dans la barre de l'album

### Sous-dossiers

Un album peut contenir des sous-albums de profondeur illimitée. Ils apparaissent en haut de l'album parent sous forme de cartes cliquables. Pour réorganiser la hiérarchie par glisser-déposer, voir la [section 9](#9-réorganisation-par-glisser-déposer).

### Droits d'accès par album

Pour les albums en visibilité **Restreinte**, l'onglet **Droits** du formulaire de modification permet de désigner les utilisateurs ou groupes autorisés.

---

## 5. Médias — upload et visualisation

### Uploader des fichiers

**Glisser-déposer** : déposez des fichiers directement dans la zone de contenu de l'album. Une zone de dépôt s'affiche au survol.

**Sélection manuelle** : bouton **Téléverser** dans la barre latérale ou dans la barre de l'album.

**Import ZIP** : bouton **Importer un ZIP** — déposez une archive, les fichiers sont extraits et ajoutés à l'album automatiquement. Utile pour importer un lot de photos d'un seul coup.

Formats acceptés : images (JPEG, PNG, WebP, GIF, HEIC…), vidéos (MP4, MOV, AVI…), documents (PDF).
Taille maximale par fichier : **200 Mo**.

> Les fichiers identiques (même empreinte SHA-256) ne sont pas dupliqués. Un badge rouge **DOUBLON** est affiché sur les fichiers déjà présents dans la photothèque.

### Vues disponibles

- **Grille** (⊞) : affichage en mosaïque, colonnes configurables
- **Liste** (☰) : tableau avec nom, taille, type, date, propriétaire

### Pagination

Le nombre de photos affichées par page se règle dans la barre de l'album :

| Option | Usage recommandé |
|--------|------------------|
| **5** | Aperçu rapide, connexion lente |
| **10** | Utilisation courante |
| **20** | Albums denses |
| **Tout** | Albums de moins de 100 médias |

### Tri et filtres

Dans la barre de l'album, vous pouvez trier par :
- Nom, taille, date de prise de vue (EXIF), date d'import, type

### Sélection multiple

Cliquez sur les cases à cocher en haut à droite des cartes pour sélectionner plusieurs médias. La barre de sélection apparaît avec les actions disponibles :

| Action | Description |
|--------|-------------|
| **Supprimer** | Supprime les médias sélectionnés (confirmation requise) |
| **↗ Déplacer vers…** | Déplace les médias sélectionnés vers un autre album (voir [section 9](#9-réorganisation-par-glisser-déposer)) |

### Panneau de détail

Cliquez sur un média pour ouvrir le panneau de détail à droite :

- **Métadonnées** : nom, taille, dimensions, type, date de prise de vue, coordonnées GPS, appareil photo, ouverture/exposition/ISO, SHA-256
- **Tags** : mots-clés libres associés à ce média (voir [section 6](#6-tags-manuels))
- **Légende** : cliquez sur le crayon ✏ pour ajouter ou modifier la description
- **Propriétaire** : nom de la personne qui a uploadé le fichier
- **Actions** : Télécharger, Plein écran, Couverture ⭐, Rotation ↺↻, Recadrer ✂, Supprimer 🗑

---

## 6. Tags manuels

Les tags permettent d'associer des **mots-clés libres** à chaque photo pour faciliter la recherche et le regroupement thématique.

### Ajouter un tag

Depuis le **panneau de détail** (ou la page de détail d'un média) :

1. La zone **Tags** affiche les tags déjà associés, suivis d'un champ de saisie
2. Tapez le nom du tag — des suggestions apparaissent à partir du premier caractère (tags déjà utilisés dans la photothèque)
3. Sélectionnez une suggestion ou appuyez sur **Entrée** pour créer le tag
4. Le tag s'affiche immédiatement sous forme de badge

Les tags sont normalisés en minuscules. Un même tag ne peut être associé qu'une seule fois à un même média.

### Retirer un tag

Cliquez sur le **×** à droite d'un badge de tag pour le retirer de ce média. Cela ne supprime pas le tag de la photothèque — il reste disponible pour d'autres médias.

### Filtrer par tag

Dans le panneau de détail, cliquer sur un tag (badge) filtre l'album courant pour n'afficher que les médias portant ce tag.

> **Droits** : l'ajout et la suppression de tags nécessitent le droit **manage** sur l'album.

---

## 7. Visionneuse et diaporama

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

## 8. Édition des médias

> Ces fonctionnalités sont réservées aux utilisateurs ayant le droit **manage** sur l'album.

Les modifications sont appliquées **directement sur le fichier NAS** et la miniature est régénérée automatiquement. L'opération est irréversible — conservez une copie si nécessaire.

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

## 9. Réorganisation par glisser-déposer

Pladigit permet de réorganiser la photothèque entièrement par glisser-déposer, sans toucher au NAS. Toutes les opérations de déplacement sont **répercutées physiquement sur le NAS** — Pladigit reste la source de vérité.

### Déplacer des photos vers un autre album

**Par glisser-déposer sur un sous-album :**

1. Dans la grille d'un album, maintenez le clic sur une photo et faites-la glisser
2. Déposez-la sur une carte de **sous-album** (en haut de la grille) — elle devient bleue à l'approche
3. La photo est déplacée immédiatement et la page se recharge

**Par glisser-déposer vers la sidebar :**

1. Faites glisser une photo depuis la grille vers **n'importe quel album de la barre latérale**
2. L'album cible est surligné à l'approche
3. Relâchez pour déplacer la photo

**Par le modal de recherche (sélection multiple) :**

1. Sélectionnez une ou plusieurs photos (cases à cocher)
2. Cliquez sur **↗ Déplacer vers…** dans la barre de sélection
3. Un modal s'ouvre avec un champ de recherche — tapez le nom de l'album cible
4. Cliquez sur l'album dans la liste des résultats pour confirmer le déplacement

> Le déplacement nécessite le droit **manage** sur l'album source et le droit **upload** sur l'album cible.

### Déplacer un album dans la hiérarchie

> Fonctionnalité réservée aux rôles **Admin**, **Président** et **DGS**.

**Pour déplacer un album sous un autre :**

1. Dans la barre latérale, maintenez le clic sur le nom d'un album
2. Faites-le glisser vers l'album parent cible dans l'arbre
3. L'album cible est surligné en bleu à l'approche
4. Relâchez — l'album et tous ses descendants sont déplacés

**Pour remonter un album à la racine :**

1. Commencez à faire glisser l'album — une zone **"Déposer ici pour mettre à la racine"** apparaît en haut de la sidebar
2. Déposez l'album sur cette zone

**Ce qui se passe lors d'un déplacement d'album :**

- Le dossier est renommé / déplacé **physiquement sur le NAS**
- Les chemins (`nas_path`) de l'album et de tous ses sous-albums sont mis à jour en base
- Les chemins (`file_path`, `thumb_path`) de tous les médias concernés sont mis à jour
- La page se recharge pour refléter la nouvelle organisation

> Un album ne peut pas être déposé sur lui-même ni sur l'un de ses propres descendants (protection contre les boucles circulaires).

---

## 10. Partage par lien temporaire

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

## 11. Export ZIP

Téléchargez tous les fichiers d'un album en une seule archive compressée.

- Bouton **↓ ZIP** dans la barre de l'album
- L'archive est générée à la volée (sans surcharge mémoire) et téléchargée directement
- Limite : albums jusqu'à **500 Mo** de contenu
- Disponible aussi depuis les liens de partage (si l'option a été activée lors de la création du lien)

---

## 12. Recherche globale

La recherche globale permet de retrouver un fichier dans **toute la photothèque**, quel que soit l'album dans lequel il se trouve.

### Accès

- Barre de recherche en haut de la page d'accueil de la photothèque
- Bouton 🔍 dans la barre de tout album

### Critères disponibles

| Critère | Description |
|---|---|
| **Mot-clé** | Recherche dans le nom de fichier, la légende et les tags |
| **Type** | Tous / Images / Vidéos / Documents |
| **Du / Au** | Date de prise de vue (EXIF) — ou date d'import si pas d'EXIF |

> **À propos des dates** : si la photo contient des métadonnées EXIF, la date utilisée est celle enregistrée par l'appareil au moment de la prise de vue. Pour les fichiers sans EXIF (captures d'écran, documents, vidéos…), c'est la date d'import dans Pladigit.

### Résultats

Les résultats s'affichent sous forme de grille (48 par page) avec :
- La miniature du fichier
- Le nom ou la légende
- Les tags associés
- L'album d'appartenance (avec le dossier parent)
- La date (prise de vue ou import)

Cliquez sur un résultat pour aller directement dans l'album correspondant.

---

## 13. Synchronisation NAS

> Voir l'**Annexe F — Politique de synchronisation NAS** pour la documentation technique complète.

### Principe

Les fichiers déposés directement sur le NAS (via l'Explorateur Windows ou autre outil réseau) peuvent être **ingérés automatiquement** dans la photothèque via la synchronisation.

### Déclencher une synchronisation

- **Manuellement** : bouton 🔄 dans la barre de la photothèque (admins uniquement)
- **Automatiquement** : tâche planifiée toutes les heures en jours ouvrés, et profonde (SHA-256) chaque nuit à 2 h

### Ce que fait la synchronisation

1. Parcourt les dossiers NAS associés aux albums
2. Détecte les nouveaux fichiers (par chemin ou SHA-256)
3. Génère les miniatures et extrait les métadonnées EXIF
4. Ne crée pas de doublons (vérification SHA-256)
5. Supprime les entrées des fichiers qui ont disparu du NAS

### Flux recommandé

```
Agent dépose des photos sur le NAS
         ↓
Synchronisation automatique (cron horaire)
         ↓
Photos apparaissent dans Pladigit
         ↓
Administrateur réorganise depuis Pladigit (drag-and-drop)
         ↓
NAS reflète la nouvelle organisation
```

---

## 14. Espace de stockage et quota

### Affichage de l'utilisation

L'espace utilisé est visible en permanence dans **la barre latérale** (barre de progression en bas) et sur la **page Paramètres → Photothèque** (admins uniquement).

La barre change de couleur selon le niveau d'utilisation :

| Utilisation | Couleur | Signification |
|------------|---------|---------------|
| < 80 % | Vert | Situation normale |
| 80 – 94 % | Orange | Surveiller et planifier un nettoyage |
| ≥ 95 % | Rouge | Agir rapidement — uploads bientôt bloqués |

### Notifications de seuil

Pladigit envoie une **notification interne** aux administrateurs à chaque seuil franchi :

| Seuil | Message |
|-------|---------|
| **80 %** | Première alerte — espace à surveiller |
| **90 %** | Alerte importante — planifier une action |
| **95 %** | Seuil critique — libérer de l'espace en urgence |

Une seule notification est émise par seuil (pas de répétition tant que le niveau reste au-dessus du seuil).

### Quota et blocage des uploads

Lorsque le quota est atteint, tout nouvel upload est **refusé** avec un message indiquant l'espace utilisé, le quota total et l'espace libre. La synchronisation NAS ignore également les nouveaux fichiers tant que le quota n'est pas libéré.

### Augmenter le quota

Le quota est défini par organisation (défaut : **10 Go**). Pour l'augmenter, contactez le super-administrateur. La demande peut être formulée dès réception de l'alerte à 80 % pour anticiper le blocage.

> Voir l'**Annexe O — Politique de quotas** pour le détail technique du mécanisme d'enforcement.
