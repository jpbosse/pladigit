# Guide testeur — DataGrid Pladigit v0.8.3

> Ce guide est destiné aux testeurs de la démo. Il explique comment utiliser le module DataGrid
> disponible sur [demo.pladigit.fr](https://demo.pladigit.fr).

---

## Accès à la démo

**URL :** https://demo.pladigit.fr  
**Mot de passe :** `demo1234`

| Rôle | Email |
|------|-------|
| Administrateur | admin@demo.pladigit.fr |
| Maire | maire@demo.pladigit.fr |
| DGS | dgs@demo.pladigit.fr |
| Agent | agent1@demo.pladigit.fr |

> L'instance est réinitialisée toutes les 2 heures.

---

## Qu'est-ce que DataGrid ?

DataGrid remplace les tableurs Excel éparpillés par des **listes collaboratives** intégrées à Pladigit.
Un agent peut importer un fichier Excel et obtenir une liste structurée, consultable et partagée,
sans aucune compétence technique.

---

## 1. Créer une DataGrid depuis un fichier

1. Connectez-vous en tant qu'**Administrateur**
2. Menu latéral → **DataGrid**
3. Cliquez **Nouvelle grille** → **Importer un fichier**
4. Sélectionnez un fichier `.xlsx`, `.ods` ou `.csv`
5. Suivez le wizard en 4 étapes :
   - **Étape 1** — aperçu du fichier, détection du séparateur (CSV)
   - **Étape 2** — typage des colonnes (texte, date, nombre, booléen, liste)
   - **Étape 3** — visibilité initiale (publique / restreinte / privée)
   - **Étape 4** — confirmation et import

> **Conseil :** testez avec un fichier Excel simple (10–50 lignes). Un fichier de test
> est disponible sur demande.

---

## 2. Naviguer dans une grille

Une fois la grille créée :

- **Recherche globale** — un champ en haut à droite cherche dans toutes les colonnes texte
- **Filtres par colonne** — cliquez sur l'entête d'une colonne pour filtrer
- **Tri** — cliquez sur l'entête pour trier ascendant / descendant
- **Pagination** — sélecteur 10 / 20 / 50 lignes par page en bas à gauche

---

## 3. Ajouter / modifier / supprimer une ligne

- **Ajouter** → bouton **+** en bas de la grille
- **Modifier** → cliquez sur une ligne → popup avec onglets Données / Complémentaires / Historique
- **Supprimer** → icône corbeille dans la popup de modification

> Toutes les modifications sont tracées dans l'**historique** de la ligne (onglet Historique).

---

## 4. Exporter les données

Bouton **Exporter** en haut de la grille :

| Format | Contenu |
|--------|---------|
| Excel (.xlsx) | Données avec filtres actifs |
| ODS | Format LibreOffice |
| PDF liste | 100 premières lignes, mise en page impression |
| PDF fiche | Détail d'une seule ligne |

---

## 5. Organiser en dossiers

- Panneau gauche → **Dossiers**
- **Nouveau dossier** → nommez-le (ex : RH, Urbanisme, Associations)
- Glissez-déposez les grilles d'un dossier à l'autre

---

## 6. Droits d'accès (admin seulement)

Connecté en tant qu'**Administrateur** → DataGrid → icône ⚙️ → **Droits** :

- Droits par **rôle** (ex : DGS voit tout, Agent voit seulement son service)
- Droits par **département**
- Droits par **utilisateur** spécifique
- **Colonnes masquables** — ex : colonne Salaire visible uniquement par le service RH

---

## Ce qu'on attend de vous

Merci de tester et de nous faire un retour sur :

- [ ] L'import fonctionne-t-il avec vos fichiers Excel réels ?
- [ ] La détection automatique des types de colonnes est-elle correcte ?
- [ ] La détection des doublons est-elle pertinente ?
- [ ] La navigation (recherche, filtres, tri) est-elle fluide ?
- [ ] L'export correspond-il à vos besoins ?
- [ ] Les droits d'accès sont-ils suffisamment fins ?
- [ ] Que manque-t-il pour remplacer vos tableurs Excel ?

**Retours :** contact@pladigit.fr ou directement sur GitHub Issues.

---

*Pladigit v0.8.3 — Mai 2026 — AGPL-3.0*
