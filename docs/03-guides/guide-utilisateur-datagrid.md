# Guide utilisateur — Module DataGrid

> Ce guide s'adresse à tous les utilisateurs ayant accès au module DataGrid.
> Dernière mise à jour : juin 2026.

---

## À quoi sert DataGrid ?

DataGrid remplace les tableurs Excel éparpillés sur les postes et les serveurs de
fichiers par des listes collaboratives intégrées à Pladigit. Un agent peut importer
un fichier Excel existant et obtenir une liste structurée, consultable et partagée
par toute l'équipe — sans compétence technique particulière.

Exemples d'usage dans une collectivité :

- liste des associations subventionnées avec suivi des dossiers ;
- registre des demandes de travaux avec statut et responsable ;
- tableau de suivi des marchés publics ;
- liste des agents avec leurs affectations et formations.

---

## Les rôles dans DataGrid

L'accès à une grille dépend de votre rôle dans l'organisation et des droits
définis par l'administrateur sur chaque grille.

| Droit | Ce que vous pouvez faire |
|-------|--------------------------|
| Lecture | Consulter les données, filtrer, rechercher, exporter |
| Écriture | Lecture + ajouter, modifier et supprimer des lignes |
| Admin | Écriture + gérer la structure de la grille et les droits d'accès |

Certaines colonnes peuvent être masquées pour certains rôles — par exemple,
une colonne contenant des données sensibles peut n'être visible que par le service
concerné.

---

## Naviguer dans une grille

Cliquer sur **DataGrid** dans le menu de gauche. Le panneau gauche affiche les
dossiers et les grilles auxquels vous avez accès.

Une fois dans une grille :

- **Recherche globale** — un champ en haut à droite cherche dans toutes les colonnes
  texte simultanément.
- **Filtres par colonne** — cliquer sur l'entête d'une colonne pour filtrer sur
  une valeur précise.
- **Tri** — cliquer sur l'entête d'une colonne pour trier les lignes
  (ascendant / descendant).
- **Pagination** — sélecteur 10 / 20 / 50 lignes par page en bas à gauche.
- **Vues sauvegardées** — vous pouvez sauvegarder votre combinaison de filtres et
  colonnes sous un nom, et la retrouver à chaque connexion. Vos vues sont
  personnelles — elles n'affectent pas l'affichage des autres utilisateurs.

---

## Consulter le détail d'une ligne

Cliquer sur une ligne pour ouvrir le panneau de détail. Il affiche :

- **Données** — toutes les valeurs de la ligne.
- **Complémentaires** — informations additionnelles si configurées.
- **Historique** — journal de toutes les modifications apportées à cette ligne,
  avec la date, l'auteur et la valeur avant/après.

---

## Ajouter, modifier et supprimer des lignes

Ces actions nécessitent le droit **Écriture** sur la grille.

**Ajouter une ligne** — cliquer sur le bouton **+** en bas de la grille. Un
formulaire s'ouvre avec tous les champs à renseigner.

**Modifier une ligne** — cliquer sur la ligne pour ouvrir le panneau de détail,
puis cliquer sur **Modifier**.

**Supprimer une ligne** — depuis le panneau de détail, cliquer sur l'icône
corbeille. Une confirmation est demandée. La suppression est définitive.

Toutes les modifications sont tracées dans l'historique de la ligne.

---

## Exporter les données

Cliquer sur le bouton **Exporter** en haut de la grille. Quatre formats sont
disponibles :

| Format | Contenu |
|--------|---------|
| Excel (.xlsx) | Données avec les filtres actifs |
| ODS | Format LibreOffice / OpenOffice |
| PDF liste | Les 100 premières lignes, mise en page impression |
| PDF fiche | Détail complet d'une seule ligne |

L'export respecte les droits d'accès — seules les colonnes visibles pour votre
rôle sont incluses dans l'export.

---

## Créer une grille (administrateurs)

Cette section s'adresse aux utilisateurs disposant du rôle administrateur.

### Importer depuis un fichier Excel

1. Dans le menu DataGrid, cliquer sur **Nouvelle grille → Importer un fichier**.
2. Sélectionner un fichier `.xlsx`, `.ods` ou `.csv`.
3. Suivre le wizard en 4 étapes :
   - **Étape 1** — aperçu du fichier, détection du séparateur (pour les CSV).
   - **Étape 2** — typage des colonnes : texte, date, nombre, booléen, liste déroulante.
   - **Étape 3** — visibilité initiale : publique (tous les utilisateurs), restreinte
     (selon les droits) ou privée (administrateurs seulement).
   - **Étape 4** — confirmation et import.

Conseil : commencer avec un fichier simple (moins de 50 lignes) pour valider
la configuration avant d'importer les données réelles.

### Organiser en dossiers

Dans le panneau gauche, cliquer sur **Nouveau dossier** et lui donner un nom
(ex : RH, Urbanisme, Associations). Glisser-déposer les grilles d'un dossier
à l'autre pour les organiser.

### Gérer les droits d'accès

Depuis la liste des grilles, cliquer sur l'icône ⚙️ → **Droits**.

Les droits peuvent être définis par rôle, par direction, par service ou par
utilisateur individuel. Il est possible de masquer certaines colonnes pour certains
profils — utile pour les données sensibles.

---

## Questions fréquentes

**Je ne vois pas une grille que mon collègue voit.**
Votre accès dépend des droits définis sur cette grille. Contactez votre
administrateur pour qu'il vérifie vos permissions.

**Une colonne est masquée dans mon export mais visible à l'écran.**
Certaines colonnes sont configurées comme non-exportables pour votre rôle.
C'est une décision de votre administrateur.

**J'ai supprimé une ligne par erreur — puis-je la récupérer ?**
La suppression est définitive. En revanche, si la ligne avait été modifiée
avant suppression, l'historique conserve toutes les valeurs précédentes.
Contactez votre administrateur — une restauration depuis la sauvegarde est
possible en dernier recours.

**Puis-je importer un fichier Excel avec des formules ?**
Seules les valeurs calculées sont importées — pas les formules elles-mêmes.
Vérifier que les cellules affichent bien les valeurs attendues avant d'importer.

**La détection automatique du type de colonne est incorrecte.**
Lors de l'import (étape 2 du wizard), vous pouvez corriger manuellement le type
de chaque colonne avant de valider.

---

*Dernière mise à jour : juin 2026*
