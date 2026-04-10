# PLADIGIT
Plateforme de Digitalisation Interne

Guide utilisateur
Module Photothèque
Pour tous les utilisateurs ayant accès au module MEDIA

---

# Sommaire

1. Introduction — à quoi sert ce module ?
2. Les droits dans la photothèque
3. Naviguer dans les albums
4. Uploader des photos
5. Consulter et visualiser les photos
6. Gérer les albums
7. Rechercher des photos
8. Partager des photos
9. Exporter et télécharger
10. Tags
11. Synchronisation NAS
12. Quotas de stockage
13. Questions fréquentes (FAQ)

---

# 1. Introduction — à quoi sert ce module ?

Le module Photothèque de Pladigit est votre médiathèque souveraine. Il remplace les dossiers partagés OneDrive ou les clés USB qui circulent entre les services. Toutes vos photos — événements municipaux, travaux, portraits d'élus, vie associative — sont centralisées, organisées et accessibles aux bonnes personnes.

Ce que vous pouvez faire avec la Photothèque :

- Déposer et consulter des photos depuis n'importe quel navigateur
- Organiser les photos en albums hiérarchiques (ex : Événements > Fête communale 2026)
- Contrôler qui peut voir, télécharger ou gérer chaque album
- Partager un album ou une photo par lien sécurisé temporaire
- Retrouver une photo par son nom, sa date de prise de vue ou ses tags
- Synchroniser automatiquement les photos déposées sur votre NAS

---

# 2. Les droits dans la photothèque

Vos droits sur un album dépendent de votre rôle dans l'organisation et des permissions définies par l'administrateur.

| Niveau | Ce que vous pouvez faire |
|--------|--------------------------|
| **Aucun** | Album invisible |
| **Visualisation** | Voir les photos en miniature et en plein écran |
| **Téléchargement** | Visualiser + télécharger les photos |
| **Upload** | Visualiser, télécharger + déposer de nouvelles photos |
| **Admin** | Tout + gérer l'album (renommer, déplacer, supprimer, gérer les droits) |

> ⚠ Les droits se transmettent automatiquement aux sous-albums — un droit défini sur un album parent s'applique à tous ses enfants, sauf exception explicite.

---

# 3. Naviguer dans les albums

## 3.1 — Accéder à la photothèque

1. Cliquez sur **« Photothèque »** dans le menu de gauche.
2. La page s'ouvre sur l'arborescence de vos albums à gauche et la galerie à droite.

## 3.2 — Parcourir l'arborescence

L'arborescence à gauche affiche tous les albums auxquels vous avez accès. Cliquez sur la flèche ▶ d'un album pour déplier ses sous-albums. Cliquez sur le nom d'un album pour afficher son contenu dans la galerie.

## 3.3 — Vue mosaïque

Les photos s'affichent en grille de miniatures. Par défaut, les photos sont triées par date de prise de vue (EXIF). Vous pouvez modifier le tri depuis le sélecteur en haut à droite : date d'upload, nom de fichier, taille.

## 3.4 — Barre de progression de quota

En bas de la sidebar gauche, une barre indique l'espace utilisé par votre organisation. Elle devient orange à 80 %, rouge à 90 %. Contactez votre administrateur si vous approchez de la limite.

---

# 4. Uploader des photos

## 4.1 — Upload par glisser-déposer

1. Naviguez vers l'album de destination dans la sidebar.
2. Faites glisser vos fichiers depuis votre explorateur de fichiers directement dans la zone de galerie.
3. Une barre de progression s'affiche pendant le traitement. Les miniatures apparaissent automatiquement une fois le traitement terminé.

## 4.2 — Upload par le bouton

1. Naviguez vers l'album souhaité.
2. Cliquez sur **« ⬆ Uploader des photos »** en haut à droite.
3. Sélectionnez un ou plusieurs fichiers. Formats acceptés : JPG, JPEG, PNG, GIF, WebP, MP4, MOV, AVI. Taille max par fichier : 200 Mo.
4. Le traitement se fait en arrière-plan — vous pouvez continuer à naviguer.

## 4.3 — Import ZIP

Pour importer un grand nombre de photos d'un coup :

1. Constituez un fichier ZIP contenant vos photos (dossiers autorisés dans le ZIP).
2. Dans l'album de destination, cliquez sur **« 📦 Importer un ZIP »**.
3. Sélectionnez votre fichier ZIP. Le traitement est asynchrone — vous recevrez une notification quand il sera terminé.

## 4.4 — Doublons

Pladigit détecte automatiquement les fichiers identiques (même contenu, quelle que soit le nom). Si une photo existe déjà dans la photothèque, elle ne sera pas importée en double. Vous en serez informé dans le rapport d'import.

---

# 5. Consulter et visualiser les photos

## 5.1 — Ouvrir une photo en plein écran (lightbox)

Cliquez sur une miniature pour ouvrir la visionneuse en plein écran. Naviguez entre les photos avec les flèches ← → ou les touches du clavier.

## 5.2 — Diaporama

1. Dans un album, cliquez sur **« ▶ Diaporama »** en haut de la galerie.
2. Les photos défilent automatiquement toutes les 4 secondes.
3. Appuyez sur Espace ou Échap pour arrêter.

## 5.3 — Informations EXIF

Dans la lightbox, cliquez sur l'icône ℹ pour afficher les métadonnées EXIF de la photo : appareil, date et heure de prise de vue, lieu (si GPS disponible), dimensions, taille.

## 5.4 — Watermark

Selon la configuration de votre organisation, les photos peuvent être affichées avec un filigrane (logo ou texte). Le fichier original sur le NAS n'est jamais modifié — le watermark est appliqué uniquement à l'affichage.

---

# 6. Gérer les albums

> ⚠ Ces actions nécessitent le droit **Admin** sur l'album concerné.

## 6.1 — Créer un album

1. Dans la sidebar, cliquez sur **« + Nouvel album »** à côté de l'album parent (ou à la racine).
2. Renseignez le nom de l'album et une description optionnelle.
3. Définissez les droits d'accès (voir section 6.4).
4. Cliquez sur **« Créer »**.

## 6.2 — Renommer un album

1. Dans la sidebar, faites un clic droit sur l'album → **« ✏ Renommer »**.
2. Ou cliquez sur les trois points ⋯ à côté de l'album → **« Renommer »**.

## 6.3 — Déplacer un album

Glissez-déposez l'album dans la sidebar vers son nouvel emplacement parent. Une confirmation est demandée si le déplacement modifie les droits hérités.

## 6.4 — Gérer les droits d'un album

1. Cliquez sur les trois points ⋯ à côté de l'album → **« 🔒 Droits »**.
2. La page des permissions s'ouvre.
3. Vous pouvez définir des droits par rôle (tous les Agents, tous les Resp. Service...), par direction, par service, ou par utilisateur individuel.
4. Le niveau le plus élevé entre les droits collectifs et individuels est toujours appliqué.

## 6.5 — Déplacer des photos entre albums

1. Sélectionnez une ou plusieurs photos (clic long sur mobile, case à cocher sur desktop).
2. Cliquez sur **« 📁 Déplacer »** dans la barre d'actions.
3. Choisissez l'album de destination dans l'arborescence.

## 6.6 — Supprimer un album

1. Cliquez sur les trois points ⋯ → **« 🗑 Supprimer »**.
2. Confirmez. Toutes les photos de l'album et de ses sous-albums sont supprimées.

> ⚠ La suppression d'un album est définitive. Les fichiers sur le NAS ne sont pas supprimés — uniquement les entrées dans Pladigit.

---

# 7. Rechercher des photos

## 7.1 — Recherche globale

1. Cliquez sur la barre de recherche 🔍 en haut de la photothèque.
2. Saisissez un mot-clé (nom de fichier, tag, description).
3. Les résultats s'affichent en mosaïque avec l'album d'appartenance.

## 7.2 — Filtrer par date

Dans la barre de recherche, utilisez les champs **« Du »** et **« Au »** pour filtrer les photos par date de prise de vue (EXIF) ou par date d'upload.

## 7.3 — Filtrer par album

La recherche s'effectue par défaut dans tous les albums auxquels vous avez accès. Pour limiter la recherche à un album spécifique, naviguez d'abord vers cet album puis lancez la recherche.

---

# 8. Partager des photos

## 8.1 — Partager un lien temporaire vers une photo

1. Cliquez sur les trois points ⋯ sous une photo → **« 🔗 Partager »**.
2. Choisissez une durée de validité : 1 jour, 7 jours, 30 jours ou durée personnalisée.
3. Copiez le lien généré et transmettez-le. Le destinataire peut visualiser et télécharger la photo sans être connecté à Pladigit.
4. Le lien expire automatiquement à la date choisie.

## 8.2 — Partager un album complet

1. Dans l'album, cliquez sur **« 🔗 Partager cet album »**.
2. Choisissez une durée de validité et optionnellement un mot de passe.
3. Le destinataire accède à une galerie en lecture seule sans connexion requise.

## 8.3 — Révoquer un lien de partage

1. Dans l'album partagé, cliquez sur **« 🔗 Gérer les partages »**.
2. Cliquez sur **« ✕ Révoquer »** à côté du lien à supprimer. Le lien devient immédiatement invalide.

---

# 9. Exporter et télécharger

## 9.1 — Télécharger une photo

Dans la lightbox ou depuis la mosaïque, cliquez sur **« ⬇ Télécharger »**. Le fichier original (sans watermark) est téléchargé si vous avez le droit de téléchargement.

## 9.2 — Télécharger un album en ZIP

1. Dans l'album souhaité, cliquez sur **« 🗃 Exporter en ZIP »**.
2. Le ZIP est préparé en arrière-plan. Une notification vous avertit quand il est prêt.
3. Cliquez sur le lien de téléchargement dans la notification.

> ℹ Le ZIP contient les fichiers originaux. Les sous-albums sont organisés en sous-dossiers.

---

# 10. Tags

Les tags permettent de retrouver rapidement des photos par thème ou par sujet.

## 10.1 — Ajouter un tag à une photo

1. Cliquez sur une photo pour ouvrir la lightbox.
2. Dans le panneau latéral, cliquez sur **« + Ajouter un tag »**.
3. Saisissez le tag (ex : "inauguration", "travaux", "conseil municipal") et appuyez sur Entrée.
4. Commencez à taper pour voir les tags existants suggérés — réutilisez-les pour une cohérence de la recherche.

## 10.2 — Supprimer un tag

Dans le panneau de la photo, cliquez sur le ✕ à côté du tag à supprimer.

## 10.3 — Rechercher par tag

Dans la barre de recherche, tapez le nom d'un tag. Les photos ayant ce tag s'affichent dans les résultats.

---

# 11. Synchronisation NAS

Si votre organisation utilise un NAS (serveur de stockage), Pladigit peut y lire automatiquement les nouvelles photos.

## 11.1 — Comment ça fonctionne

La synchronisation NAS tourne en arrière-plan toutes les heures. Elle détecte les photos déposées directement sur le NAS (depuis Windows Explorer, macOS Finder, etc.) et les indexe dans Pladigit sans action de votre part.

> ℹ Vous n'avez rien à faire — la synchro est automatique.

## 11.2 — Déclencher une synchronisation manuelle

Si vous venez de déposer des fichiers sur le NAS et ne voulez pas attendre la prochaine synchro automatique :

1. Cliquez sur les trois points ⋯ à côté de l'album synchronisé → **« 🔄 Synchroniser maintenant »**.
2. La synchro se lance en arrière-plan. Une notification confirme la fin du traitement.

> ⚠ Seul l'administrateur peut déclencher une synchro manuelle globale depuis le panneau d'administration.

---

# 12. Quotas de stockage

Votre organisation dispose d'un espace de stockage limité défini par l'administrateur.

| Seuil | Indicateur |
|-------|-----------|
| < 80 % | Barre bleue — normal |
| ≥ 80 % | Barre orange — alerte envoyée à l'admin |
| ≥ 90 % | Barre rouge — alerte critique |
| 100 % | Upload bloqué jusqu'à libération d'espace |

Si vous atteignez la limite, contactez votre administrateur organisation pour augmenter le quota ou libérer de l'espace.

---

# 13. Questions fréquentes (FAQ)

**Ma photo n'apparaît pas après l'upload — que faire ?**
Le traitement des photos (miniatures, EXIF) se fait en arrière-plan. Attendez quelques secondes et rafraîchissez la page. Si le problème persiste après 2 minutes, contactez votre administrateur.

**Je ne vois pas un album que mon collègue voit — pourquoi ?**
Vos droits d'accès sont différents. Demandez à votre administrateur organisation de vérifier les permissions de cet album pour votre rôle ou votre service.

**Le lien de partage que j'ai envoyé ne fonctionne plus.**
Le lien a probablement expiré. Créez un nouveau lien de partage avec une durée de validité plus longue.

**Puis-je uploader des vidéos ?**
Oui. Les formats MP4, MOV et AVI sont acceptés. Les vidéos sont streamées directement depuis le NAS sans recodage. Attention à la taille — la limite est de 200 Mo par fichier.

**Le watermark apparaît sur mes photos — le fichier original est-il modifié ?**
Non. Le fichier original sur le NAS n'est jamais touché. Le watermark est appliqué uniquement à l'affichage dans Pladigit et lors du téléchargement si votre administrateur l'a configuré ainsi.

**Comment supprimer définitivement une photo ?**
Supprimez la photo depuis l'interface (clic droit → Supprimer). Cela retire la photo de Pladigit. Le fichier physique sur le NAS n'est pas supprimé — contactez votre administrateur si vous souhaitez également supprimer le fichier source.

---

*Pladigit — contact@pladigit.fr — github.com/jpbosse/pladigit — AGPL-3.0*
