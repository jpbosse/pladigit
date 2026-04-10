
PLADIGIT
Plateforme de Digitalisation Interne
Annexe E — Module Photothèque
Documentation utilisateur et administrateur
Version : Phase 4/5 — Mars 2026
Organisation : Les Bézots — Soullans (85), France
Responsable : Jean-Pierre Bossé
⚠ DOCUMENT CONFIDENTIEL

# 1. Présentation
Le module Photothèque permet à chaque organisation de centraliser, organiser et partager ses médias (photos, vidéos, documents) directement depuis Pladigit, sans passer par un dossier réseau ou un NAS en accès direct.

L'objectif à terme est que Pladigit devienne la source de vérité pour la gestion des fichiers multimédias, en remplaçant l'usage quotidien de l'Explorateur Windows sur le NAS.

## Ce que permet le module

# 2. Accès et droits
## Activation du module
Le module Photothèque doit être activé par le super-administrateur pour chaque organisation, via le champ enabled_modules (valeur : media).

## Rôles et permissions

## Visibilité des albums
- Public — visible par tous les utilisateurs de l'organisation
- Restreint — visible uniquement par les utilisateurs explicitement autorisés
- Privé — visible uniquement par l'administrateur et le propriétaire

# 3. Navigation
## Arbre des albums (barre latérale)
La barre latérale gauche affiche l'arborescence complète des albums. Elle est conçue pour gérer des milliers de dossiers sans ralentissement.
- › à gauche d'un dossier : cliquez pour voir ses sous-dossiers (chargement à la demande)
- ▾ : sous-dossiers déjà dépliés — cliquez pour replier
- L'état ouvert/fermé est mémorisé entre les visites (stockage local navigateur)
- L'album en cours est surligné automatiquement, ses ancêtres sont dépliés à l'ouverture

## Recherche dans l'arbre
La barre de recherche en haut de la sidebar cherche dans les noms d'albums et les chemins NAS :
- Résultats instantanés dès 2 caractères
- Le chemin NAS est affiché en monospace sous le nom de l'album
- Cliquez sur un résultat pour aller directement dans l'album

# 4. Albums
## Créer un album
- Cliquez sur + Nouvel album (sidebar ou bouton en haut à droite)
- Renseignez le nom, la description optionnelle, le dossier parent et la visibilité
- Validez — l'album apparaît immédiatement dans l'arbre

## Modifier un album
Depuis l'album : bouton ✏️ en haut à droite → modifier le nom, la visibilité, les droits.

## Image de couverture
- Par défaut : première image de l'album
- Pour choisir une autre image : survolez la photo → bouton ⭐ dans le panneau de détail
- Pour réinitialiser : bouton ⭐↺ dans la barre de l'album

## Sous-dossiers
Un album peut contenir des sous-albums de profondeur illimitée. Ils apparaissent en haut de l'album parent sous forme de cartes cliquables.

# 5. Médias — upload et visualisation
## Uploader des fichiers
- Glisser-déposer : déposez des fichiers directement dans la zone de contenu de l'album.
- Sélection manuelle : bouton Téléverser dans la barre latérale.
- Import ZIP : bouton Importer un ZIP — les fichiers sont extraits et ajoutés automatiquement.

Formats acceptés : images (JPEG, PNG, WebP, GIF, HEIC…), vidéos (MP4, MOV…), documents (PDF, Office…).
Taille maximale par fichier : 200 Mo.
ℹ  Les fichiers identiques (même SHA-256) ne sont pas dupliqués — un badge rouge « DOUBLON » est affiché sur les doublons détectés.

## Vues disponibles
- Grille (⊞) : affichage en mosaïque, 3 à 6 colonnes au choix
- Liste (☰) : tableau avec nom, taille, type, date, propriétaire

## Tri et filtres
Dans la barre de l'album, vous pouvez trier par : Nom, Taille, Date de prise de vue (EXIF), Date d'import, Type.

## Sélection multiple
Cliquez sur les cases à cocher en haut à droite des cartes pour sélectionner plusieurs médias. Actions disponibles : supprimer en lot.

## Panneau de détail
Cliquez sur un média pour ouvrir le panneau de détail à droite :
- Métadonnées : nom, taille, dimensions, type, date de prise de vue, GPS, appareil, ouverture/exposition/ISO, SHA-256
- Légende : cliquez sur le crayon ✏ pour ajouter ou modifier la description
- Propriétaire : nom de la personne qui a uploadé le fichier
- Actions : Télécharger, Plein écran, Couverture ⭐, Rotation ↺↻, Recadrer ✂, Supprimer 🗑

# 6. Visionneuse et diaporama
## Ouvrir la visionneuse (Lightbox)
- Double-clic sur une photo dans la grille
- Bouton ⤢ Plein écran dans le panneau de détail
- Vue Liste : clic simple sur une ligne

## Navigation dans la visionneuse

## Diaporama automatique
- ▶ : démarrer — ⏸ : mettre en pause
- 2s / 3s / 5s : vitesse de défilement (défaut : 3 secondes)
- Le diaporama s'arrête automatiquement à la dernière photo

# 7. Édition des médias
ℹ  Ces fonctionnalités sont réservées aux utilisateurs ayant le droit manage sur l'album. Les modifications sont appliquées directement sur le fichier NAS et la miniature est régénérée automatiquement. L'opération est irréversible.

## Rotation
- ↺ Gauche : rotation 90° dans le sens anti-horaire
- ↻ Droite : rotation 90° dans le sens horaire
- La miniature et les dimensions sont mises à jour immédiatement

## Recadrage
- Cliquez sur ✂ Recadrer dans le panneau de détail ou la visionneuse
- Une fenêtre s'ouvre avec l'image et une zone de sélection
- Déplacez et redimensionnez la zone de recadrage
- Cliquez sur ✓ Recadrer pour valider — la zone sélectionnée remplace l'image originale

## Légende (caption)
- Survolez le champ de légende dans le panneau de détail → cliquez sur ✏
- Saisissez la légende (500 caractères max)
- Entrée ou clic sur ✓ pour sauvegarder — Échap pour annuler

# 8. Partage par lien temporaire
Partagez un album avec des personnes extérieures à Pladigit, sans qu'elles aient besoin d'un compte.

## Créer un lien de partage
- Ouvrez l'album → bouton 🔗 Partager dans la barre d'outils
- Renseignez l'expiration (obligatoire), le mot de passe (optionnel), et l'autorisation de téléchargement
- Copiez le lien généré et transmettez-le par email ou autre canal

## Ce que voit le destinataire
- Une page publique avec la galerie de l'album
- Lightbox avec navigation clavier
- Bouton de téléchargement individuel (si autorisé)
- Bouton « Tout télécharger (ZIP) » (si autorisé)

## Supprimer un lien
Depuis le gestionnaire de liens (bouton 🔗) → icône 🗑 à côté du lien concerné. Le lien devient immédiatement invalide.

# 9. Export ZIP
- Bouton ↓ ZIP dans la barre de l'album
- L'archive est générée à la volée (sans surcharge mémoire) et téléchargée directement
- Limite : albums jusqu'à 500 Mo de contenu
- Disponible aussi depuis les liens de partage (si l'option a été activée)

# 10. Recherche globale
La recherche globale permet de retrouver un fichier dans toute la photothèque, quel que soit l'album dans lequel il se trouve.

## Accès
- Barre de recherche en haut de la page d'accueil de la photothèque
- Bouton 🔍 dans la barre de tout album
- Lien Recherche dans la barre latérale

## Critères disponibles

ℹ  Si la photo contient des métadonnées EXIF, la date utilisée est celle enregistrée par l'appareil au moment de la prise de vue. Pour les fichiers sans EXIF (captures d'écran, documents, vidéos…), c'est la date d'import dans Pladigit qui est utilisée.

## Résultats
Les résultats s'affichent sous forme de grille (48 par page) avec la miniature, le nom ou la légende, l'album d'appartenance et la date.

# 11. Synchronisation NAS
ℹ  Voir l'Annexe F — Politique de synchronisation NAS pour la documentation technique complète.

## Principe
Les fichiers déposés directement sur le NAS (via l'Explorateur Windows ou autre outil réseau) peuvent être ingérés automatiquement dans la photothèque via la synchronisation.

## Déclencher une synchronisation
- Manuellement : bouton 🔄 dans la barre de la photothèque (admins uniquement)
- Automatiquement : tâche planifiée (cron) — fréquence configurable par l'organisation

## Ce que fait la synchronisation
- Parcourt les dossiers NAS associés aux albums
- Détecte les nouveaux fichiers (par chemin ou SHA-256)
- Génère les miniatures et extrait les métadonnées EXIF
- Ne crée pas de doublons (vérification SHA-256)
- Supprime les entrées des fichiers qui ont disparu du NAS

## Réorganisation depuis Pladigit
Il est possible de réorganiser les albums directement dans Pladigit (drag-and-drop) — cette opération déplace également les dossiers sur le NAS. Ainsi, le NAS reflète toujours l'organisation choisie dans Pladigit.

# 12. Espace de stockage
L'espace utilisé est visible dans :
- La barre latérale de la photothèque (barre de progression en bas)
- L'écran de paramètres du module (Paramètres → Photothèque), pour les administrateurs

Le quota est défini par organisation. Lorsque 90 % du quota est atteint, un avertissement s'affiche.

ℹ  L'espace affiché correspond aux fichiers indexés dans Pladigit via la synchronisation ou l'upload direct. Les fichiers présents sur le NAS mais non encore synchronisés ne sont pas comptabilisés.