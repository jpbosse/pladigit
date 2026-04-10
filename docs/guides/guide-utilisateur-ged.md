# PLADIGIT
Plateforme de Digitalisation Interne

Guide utilisateur
Module GED — Gestion Électronique de Documents
Pour tous les utilisateurs ayant accès au module GED

---

# Sommaire

1. Introduction — à quoi sert ce module ?
2. Les droits dans la GED
3. Naviguer dans les dossiers
4. Déposer des documents
5. Consulter et télécharger des documents
6. Éditer un document avec Collabora Online
7. Gérer les dossiers
8. Versioning — l'historique des modifications
9. Rechercher des documents
10. Lier un document à un projet
11. Synchronisation NAS
12. Questions fréquentes (FAQ)

---

# 1. Introduction — à quoi sert ce module ?

La GED (Gestion Électronique de Documents) de Pladigit est votre bibliothèque documentaire souveraine. Elle remplace les dossiers partagés SharePoint, les disques réseaux mal organisés et les pièces jointes email qui circulent sans contrôle.

Tous vos documents — délibérations, arrêtés, comptes-rendus, contrats, courriers — sont centralisés dans une arborescence structurée, accessibles aux bonnes personnes, versionnés automatiquement et éditables en ligne.

Ce que vous pouvez faire avec la GED :

- Déposer et consulter des documents depuis n'importe quel navigateur
- Organiser les documents en arborescence de dossiers (comme sur votre ordinateur)
- Contrôler qui peut voir, télécharger, modifier ou administrer chaque dossier
- Éditer les documents Word, Excel, PowerPoint et ODF directement dans le navigateur
- Retrouver l'historique complet de toutes les versions d'un document
- Rechercher un document par son nom ou son contenu
- Lier un document à un projet ou une tâche

---

# 2. Les droits dans la GED

Vos droits sur un dossier dépendent de votre rôle dans l'organisation et des permissions définies par l'administrateur.

| Niveau | Ce que vous pouvez faire |
|--------|--------------------------|
| **Aucun** | Dossier invisible |
| **Visualisation** | Voir la liste des documents, prévisualiser les PDF et images |
| **Téléchargement** | Visualiser + télécharger les documents |
| **Upload** | Visualiser, télécharger + déposer de nouveaux documents et modifier les existants |
| **Admin** | Tout + gérer le dossier (renommer, déplacer, supprimer, gérer les droits) |

> ⚠ Les droits se transmettent automatiquement aux sous-dossiers — un droit défini sur un dossier parent s'applique à tous ses enfants, sauf exception explicite.

**Droits automatiques selon votre rôle :**

- **Admin, Président, DGS** — accès complet à toute la GED
- **Resp. Direction** — accès en lecture à tous les dossiers de sa direction
- **Resp. Service** — accès en lecture aux dossiers de son service
- **Agent** — accès uniquement aux dossiers explicitement partagés avec lui

---

# 3. Naviguer dans les dossiers

## 3.1 — Accéder à la GED

1. Cliquez sur **« GED »** dans le menu de gauche.
2. La page s'ouvre sur l'arborescence des dossiers à gauche et le contenu du dossier sélectionné à droite.

## 3.2 — Parcourir l'arborescence

L'arborescence à gauche affiche tous les dossiers auxquels vous avez accès. Cliquez sur la flèche ▶ pour déplier un dossier et voir ses sous-dossiers. Cliquez sur le nom d'un dossier pour afficher son contenu.

## 3.3 — Fil d'Ariane

En haut de la zone de contenu, le fil d'Ariane indique votre position dans l'arborescence. Cliquez sur n'importe quel niveau pour remonter rapidement.

Ex : `GED › Direction des Services Techniques › Voirie › Travaux 2026`

## 3.4 — Dossiers privés

Un dossier marqué 🔒 est privé — il n'est visible que par son créateur et les rôles Admin et DGS. Si vous voyez ce badge, c'est que vous avez les droits pour y accéder.

---

# 4. Déposer des documents

## 4.1 — Upload par glisser-déposer

1. Naviguez vers le dossier de destination dans la sidebar.
2. Faites glisser vos fichiers depuis votre explorateur directement dans la zone de contenu.
3. Le fichier apparaît immédiatement dans la liste avec son nom, sa taille et son type.

## 4.2 — Upload par le bouton

1. Naviguez vers le dossier souhaité.
2. Cliquez sur **« ⬆ Déposer un document »** en haut à droite.
3. Sélectionnez un ou plusieurs fichiers. Formats acceptés : PDF, Word (.doc, .docx), Excel (.xls, .xlsx), PowerPoint (.ppt, .pptx), ODF (.odt, .ods, .odp), images, ZIP. Taille max : 100 Mo par fichier.

## 4.3 — Déposer une nouvelle version d'un document existant

Si un document existe déjà et que vous souhaitez le mettre à jour :

1. Cliquez sur les trois points ⋯ à côté du document → **« ⬆ Nouvelle version »**.
2. Sélectionnez le nouveau fichier.
3. L'ancienne version est automatiquement archivée dans l'historique — elle n'est pas perdue.

> ℹ Il n'est pas nécessaire de supprimer l'ancien document. Pladigit gère les versions automatiquement.

---

# 5. Consulter et télécharger des documents

## 5.1 — Prévisualiser un document

Cliquez sur le nom d'un document. Les PDF et les images s'ouvrent directement dans le navigateur en prévisualisation inline. Pour les autres formats, utilisez Collabora Online (voir section 6).

## 5.2 — Télécharger un document

Cliquez sur **« ⬇ Télécharger »** à côté du document, ou depuis les trois points ⋯ → **« Télécharger »**. Le fichier original est téléchargé sur votre ordinateur.

## 5.3 — Informations du document

Cliquez sur les trois points ⋯ → **« ℹ Détails »** pour voir : nom, taille, type MIME, version courante, date de dépôt, déposé par.

---

# 6. Éditer un document avec Collabora Online

Collabora Online est l'éditeur bureautique intégré à Pladigit. Il vous permet d'éditer des documents Word, Excel, PowerPoint et ODF directement dans votre navigateur, sans installer de logiciel.

> ℹ Collabora Online doit être configuré par votre administrateur. Si le bouton n'apparaît pas, contactez-le.

## 6.1 — Ouvrir un document dans Collabora

1. Cliquez sur les trois points ⋯ à côté d'un document compatible → **« ✏ Ouvrir dans Collabora »**.
2. L'éditeur s'ouvre dans un nouvel onglet. L'interface ressemble à Microsoft Office ou LibreOffice.
3. Éditez le document normalement.

## 6.2 — Sauvegarder

Collabora sauvegarde automatiquement vos modifications dans Pladigit toutes les quelques secondes. Vous pouvez aussi déclencher une sauvegarde manuelle avec **Ctrl+S**.

À chaque sauvegarde, Pladigit crée automatiquement une nouvelle version archivée du document. Vous ne pouvez pas perdre vos modifications.

## 6.3 — Formats supportés

| Format | Édition | Lecture |
|--------|---------|---------|
| ODT, ODS, ODP, ODG | ✅ Natif | ✅ |
| DOCX, XLSX, PPTX | ✅ Avec conversion | ✅ |
| DOC, XLS, PPT | ✅ Avec conversion | ✅ |
| PDF | ❌ | ✅ Prévisualisation |

> ℹ Les formats Microsoft Office (.docx, .xlsx, .pptx) sont ouverts et convertis à la volée. La mise en page est généralement bien préservée, mais des différences mineures peuvent apparaître sur les documents très complexes (macros, styles avancés).

## 6.4 — Édition simultanée et verrous

Si un collègue a déjà ouvert le document dans Collabora, un verrou est actif. Vous pouvez :

- **Attendre** que votre collègue ferme le document
- **Ouvrir en lecture seule** — vous voyez le document mais ne pouvez pas le modifier

Quand le document est verrouillé, un badge 🔒 apparaît à côté de son nom dans la liste.

---

# 7. Gérer les dossiers

> ⚠ Ces actions nécessitent le droit **Admin** sur le dossier concerné.

## 7.1 — Créer un dossier

1. Dans la sidebar, cliquez sur **« + Nouveau dossier »** à côté du dossier parent (ou à la racine).
2. Renseignez le nom du dossier.
3. Cochez **« 🔒 Dossier privé »** si ce dossier ne doit être visible que par vous, les Admins et les DGS.
4. Cliquez sur **« Créer »**.

## 7.2 — Renommer un dossier

Cliquez sur les trois points ⋯ à côté du dossier → **« ✏ Renommer »**.

## 7.3 — Déplacer un dossier

Glissez-déposez le dossier dans la sidebar vers son nouvel emplacement. Une confirmation est demandée si le déplacement modifie les droits hérités.

## 7.4 — Gérer les droits d'un dossier

1. Cliquez sur les trois points ⋯ → **« 🔒 Droits »**.
2. Définissez les permissions par rôle (tous les Agents, tous les Resp. Service...), par direction, par service, ou par utilisateur individuel.
3. Cliquez sur **« Enregistrer »**.

> ℹ Les sous-dossiers héritent automatiquement des droits du dossier parent. Vous pouvez surcharger ces droits sur un sous-dossier spécifique si besoin.

## 7.5 — Déplacer un document

Cliquez sur les trois points ⋯ à côté du document → **« 📁 Déplacer »**. Choisissez le dossier de destination dans l'arborescence.

## 7.6 — Supprimer un document

Cliquez sur les trois points ⋯ → **« 🗑 Supprimer »**. Le document est placé en corbeille. Il est définitivement supprimé après le délai configuré par votre administrateur (30 jours par défaut).

## 7.7 — Supprimer un dossier

Cliquez sur les trois points ⋯ → **« 🗑 Supprimer »**. Tous les documents et sous-dossiers contenus sont également supprimés.

> ⚠ La suppression d'un dossier est récursive — vérifiez bien son contenu avant de confirmer.

---

# 8. Versioning — l'historique des modifications

Chaque modification d'un document (nouvelle version uploadée ou sauvegarde depuis Collabora) crée automatiquement une version archivée.

## 8.1 — Consulter l'historique des versions

1. Cliquez sur les trois points ⋯ à côté d'un document → **« 🕒 Historique des versions »**.
2. La liste des versions s'affiche : numéro de version, date, auteur, taille.
3. Cliquez sur **« ⬇ Télécharger »** à côté d'une version pour récupérer l'ancienne version.

## 8.2 — Restaurer une version antérieure

1. Dans l'historique des versions, cliquez sur **« ↩ Restaurer »** à côté de la version souhaitée.
2. Confirmez. La version choisie devient la version courante. L'ancienne version courante est archivée.

> ℹ Aucune version n'est jamais définitivement perdue, même après une restauration.

---

# 9. Rechercher des documents

## 9.1 — Recherche globale

1. Cliquez sur la barre de recherche 🔍 en haut de la GED.
2. Saisissez un mot-clé (nom du fichier ou partie du nom).
3. Les résultats s'affichent avec le nom du document, son dossier d'appartenance et sa date de dépôt.

## 9.2 — Filtrer par type de fichier

Dans les résultats de recherche, utilisez le filtre **« Type »** pour limiter aux PDF, aux documents Word, aux feuilles Excel, etc.

## 9.3 — Filtrer par dossier

La recherche s'effectue dans toute la GED par défaut. Pour limiter la recherche à un dossier spécifique, naviguez d'abord vers ce dossier, puis lancez la recherche.

---

# 10. Lier un document à un projet

Vous pouvez associer un document GED à un projet ou à une tâche pour centraliser tous les documents liés à un projet.

## 10.1 — Lier depuis la GED

1. Cliquez sur les trois points ⋯ à côté d'un document → **« 🔗 Lier à un projet »**.
2. Sélectionnez le projet ou la tâche dans la liste déroulante.
3. Cliquez sur **« Lier »**.

## 10.2 — Consulter les documents liés depuis un projet

Dans un projet, l'onglet **« Documents »** affiche tous les documents GED liés à ce projet. Cliquez sur un document pour l'ouvrir directement dans la GED.

## 10.3 — Supprimer un lien

Dans l'onglet Documents du projet, cliquez sur **« ✕ Détacher »** à côté du document. Le document reste dans la GED — seul le lien avec le projet est supprimé.

---

# 11. Synchronisation NAS

Si votre organisation stocke des documents sur un NAS (serveur de stockage), Pladigit peut les indexer automatiquement.

## 11.1 — Comment ça fonctionne

La synchronisation NAS tourne en arrière-plan toutes les heures. Elle détecte les documents déposés directement sur le NAS (depuis Windows Explorer, macOS Finder, un logiciel métier...) et les indexe dans Pladigit sans action de votre part.

Les dossiers créés sur le NAS sont automatiquement créés dans Pladigit. Les documents supprimés du NAS sont retirés de Pladigit.

> ℹ Vous n'avez rien à faire — la synchro est silencieuse et automatique.

## 11.2 — Déclencher une synchronisation manuelle

Si vous venez de déposer des fichiers sur le NAS et souhaitez qu'ils apparaissent immédiatement :

1. Cliquez sur les trois points ⋯ à côté du dossier synchronisé → **« 🔄 Synchroniser maintenant »**.
2. La synchro se lance en arrière-plan.

---

# 12. Questions fréquentes (FAQ)

**Je ne trouve pas un document que mon collègue a déposé.**
Vérifiez que vous avez bien les droits sur le dossier concerné. Demandez à votre administrateur organisation de contrôler les permissions.

**Le bouton "Ouvrir dans Collabora" n'apparaît pas.**
Soit Collabora n'est pas configuré sur votre instance, soit le format du fichier n'est pas supporté (ex : PDF). Contactez votre administrateur.

**Mon document est verrouillé — je ne peux pas le modifier.**
Un collègue l'a ouvert dans Collabora. Attendez qu'il ferme l'éditeur ou contactez-le directement. Le verrou expire automatiquement si la session est abandonnée (durée configurée par l'administrateur, par défaut 4 heures).

**J'ai fait une erreur dans un document — comment revenir en arrière ?**
Consultez l'historique des versions (trois points ⋯ → Historique des versions) et restaurez la version antérieure souhaitée.

**Puis-je supprimer définitivement un document ?**
Oui — supprimez le document et il sera placé en corbeille. Après le délai configuré par votre administrateur (30 jours par défaut), il sera supprimé définitivement. Votre administrateur peut aussi forcer la purge depuis le panneau d'administration.

**La mise en page de mon fichier Word est différente dans Collabora.**
Collabora ouvre les formats Microsoft Office avec une très bonne compatibilité, mais des différences mineures peuvent apparaître sur des documents complexes (macros, polices non standard, styles avancés). Pour un rendu parfait, privilégiez le format ODF (.odt, .ods, .odp) qui est le format natif de Collabora.

**Puis-je déposer des fichiers depuis mon téléphone ?**
Oui. Pladigit est accessible depuis n'importe quel navigateur mobile. L'upload depuis un mobile fonctionne via le bouton "Déposer un document" — le glisser-déposer n'est pas disponible sur mobile.

**Quelle est la différence entre supprimer un document et supprimer le lien avec un projet ?**
Supprimer le document le retire définitivement de la GED (après délai de corbeille). Supprimer le lien avec un projet ne fait que dissocier le document du projet — le document reste intact dans la GED.

---

*Pladigit — contact@pladigit.fr — github.com/jpbosse/pladigit — AGPL-3.0*
