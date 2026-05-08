# ADR-039 — DataGrid et DataPilote : feuille de route consolidée niveaux 2 et 3

## Statut
Proposé — 2026-05-08
Remplace [ADR-036](ADR-036-datagrid-datapilote-droits-hierarchiques.md)

## Contexte

L'ADR-036 a posé les fondations architecturales de DataGrid et DataPilote
(modèle de droits hiérarchiques, choix de Tabulator, DataPilote sur mesure).
La phase de développement niveau 1 du DataGrid est désormais achevée :
import wizard, affichage grille, filtres, tri, pagination, édition en popup,
suppression, types de colonnes, audit log, sélecteur de colonnes visibles,
vues sauvegardées, super admin liste et import.

Les retours des démarches de communication (ADULLACT, collectivités contactées)
confirment que les collectivités n'évaluent pas un logiciel perçu comme
incomplet. Pour décrocher l'attention d'une DSI ou d'un directeur général
des services, le DataGrid doit atteindre un niveau fonctionnel suffisant
pour gérer des cas d'usage réels : annuaire des élus, registre des associations,
suivi d'équipements.

La décision est prise de ne pas découper niveaux 2 et 3 en deux livraisons
séparées, mais de poser une **architecture cible consolidée** dès maintenant,
en distinguant ce qui est indispensable avant toute démo (socle) de ce qui
enrichit progressivement le produit (extensions).

Ce document remplace et complète l'ADR-036 pour la partie feuille de route.
Les décisions architecturales fondamentales de l'ADR-036 (Tabulator, modèle
de droits, DataPilote sur mesure) restent en vigueur.

---

## Décision

### 1. Stratégie de développement

Le développement suit trois blocs séquentiels :

| Bloc | Contenu | Condition de passage |
|------|---------|----------------------|
| **Socle** | Fonctionnalités indispensables à une démo réelle | Toutes les fonctionnalités du bloc livrées et testées |
| **Extensions** | Fonctionnalités avancées qui enrichissent le produit | Socle stable, au moins une collectivité en test |
| **DataPilote** | Module analytique complet | DataGrid Extensions stable |

---

### 2. Bloc Socle — priorité absolue

#### 2.1 Qualité des données et recherche

**Nouveau type de colonne : `NOM_PERSONNE`**
Sous-type de `TEXT`, déclaré dans l'enum `DatagridColumnType` comme valeur
distincte. Se comporte comme un champ texte standard pour la saisie et
l'affichage, mais active des comportements spécifiques :
- Recherche floue (distance de Levenshtein) activée automatiquement
- Détection de doublons à l'import activée automatiquement
- Normalisation à la saisie : suppression des espaces multiples, trim

Stocké en base comme `VARCHAR` standard. La distinction est portée
uniquement par les métadonnées de la colonne (`datagrid_columns.type`).

Exemple d'usage : colonne `nom` d'une table Élus, colonne `contact`
d'une table Associations.

**Recherche globale multicolonne**
Un seul champ "Rechercher…" en haut de la grille effectue une recherche
simultanée sur toutes les colonnes de type texte visibles. Résultats
surlignés dans la grille. Complément des filtres par colonne (qui restent
disponibles pour les recherches précises).

**Recherche floue sur les noms**
Pour les colonnes déclarées de type `NOM_PERSONNE` (sous-type de `TEXT`),
la recherche tolère les variations orthographiques courantes :
- Casse insensible et accents normalisés
- Distance de Levenshtein ≤ 2 (Dupond / Dupont, Dupont / Dupond)
- `SOUNDS LIKE` MySQL comme fallback pour les homophones

Ce mécanisme est activé uniquement sur les colonnes explicitement marquées
`fuzzy_search = true` par le Super Admin (performance préservée).

**Détection de doublons à l'import**
Avant validation d'un import, Pladigit compare les valeurs des colonnes
`fuzzy_search = true` avec les fiches existantes. Les correspondances
probables (seuil configurable, défaut : distance ≤ 2) sont présentées
à l'opérateur avec trois choix par ligne suspecte :
- Ignorer cette ligne (ne pas importer)
- Fusionner avec la fiche existante identifiée
- Importer quand même (doublon assumé)

Cette étape s'intègre dans l'assistant d'import RGPD défini dans l'ADR-037
comme étape 3 (analyse automatique du fichier).

**Fusion de doublons**
Interface dédiée accessible à l'admin tenant : sélection de deux fiches,
comparaison côte à côte champ par champ, choix de la valeur à conserver
pour chaque champ, fusion en une seule fiche avec historique de la fusion
dans l'audit log.

#### 2.2 Gestion des droits — UI admin tenant

L'ADR-036 §4 définit le modèle de droits hiérarchiques. L'UI manque.

**Interface de gestion des permissions**
L'admin tenant accède à une page "Permissions" par grille. Il peut :
- Attribuer des droits (`can_read`, `can_write`, `can_delete`, `can_export`)
  à un département (hérités par tous ses sous-départements)
- Attribuer des droits à un utilisateur individuel
  (prime sur les droits départementaux)
- Poser une exception explicite `denied = true` sur un nœud ou utilisateur
- Visualiser l'arbre hiérarchique avec les droits hérités en lecture

**Cache Redis des droits résolus**
Les droits effectifs sont mis en cache Redis avec la clé
`datagrid_perm:{tenant_id}:{user_id}:{table_id}`.
Invalidation automatique à chaque modification de permission.
TTL : 1 heure (configurable).

**Droits au niveau colonne**
Une colonne peut être masquée pour un département donné.
Exemple : colonne `Salaire` visible uniquement pour le service RH.
L'admin tenant configure cela dans les paramètres de la colonne.

#### 2.3 Export

**Export Excel**
Export de la grille filtrée courante au format `.xlsx` (PhpSpreadsheet).
Les types de colonnes sont respectés (dates au format date, nombres sans
apostrophe Excel). Si la grille contient des données personnelles
(`has_rgpd = true`), un avertissement RGPD est affiché avant téléchargement
(conforme ADR-037 §4.2).

**Export PDF**
Export de la grille filtrée en PDF via une vue Blade dédiée et
`dompdf` ou `wkhtmltopdf`. Mise en page automatique (colonnes adaptées
à la largeur A4, pagination, en-tête avec nom de la grille et date
d'export).

**Impression d'une fiche**
Depuis la popup d'édition : bouton "Imprimer" qui génère une vue HTML
propre de la fiche, optimisée pour l'impression (`@media print`).

#### 2.4 Gestion des données — fonctionnalités manquantes

**Organisation des grilles en dossiers**
Une collectivité peut rapidement atteindre 20 à 30 grilles. Sans organisation,
la barre latérale devient illisible. Un système de dossiers hiérarchiques
est introduit :

```
📁 Ressources humaines
   └── Agents
   └── Formations
📁 Vie municipale
   └── Élus
   └── Commissions
   └── Arrêtés
📁 Associations
   └── Registre
   └── Subventions
```

Table dédiée avec autoréférence (même principe que `departments`) :

```sql
datagrid_folders
    id
    label
    parent_id        -- NULL = dossier racine
    sort_order
    tenant_id
    timestamps
```

`datagrid_tables.folder_id` est ajouté en FK nullable.
La barre latérale affiche les dossiers avec collapse/expand.
Les dossiers imbriqués (sous-dossiers) sont supportés sans limite de profondeur.
Une grille peut exister hors dossier (niveau racine).
L'admin tenant gère les dossiers ; le Super Admin peut en créer depuis son interface.

**Nouveau type de colonne : `CHEMIN_FICHIER`**
Cas fréquent dans les collectivités : une table d'arrêtés ou de délibérations
avec une colonne contenant un chemin vers un PDF stocké sur le NAS
(`\\NAS\Arretes\2026\ARR-2026-015.pdf` ou `/mnt/archives/2026/del-042.pdf`).

Comportement dans la grille :
- Si le chemin se termine par `.pdf` → icône PDF cliquable (📄)
- Si le chemin se termine par `.jpg`, `.png`, `.jpeg` → icône image cliquable (🖼)
- Autres extensions → icône fichier générique cliquable (📎)
- Le clic tente d'ouvrir le fichier via le navigateur (lien `href` direct)
  ou via la GED Pladigit si le chemin correspond à un document GED

Dans la popup d'édition : champ texte libre pour saisir le chemin,
avec bouton "Parcourir la GED" pour sélectionner un document Pladigit
(qui renseigne automatiquement le chemin GED interne).

Note : Pladigit ne gère pas l'accès au NAS réseau de la collectivité —
il affiche le chemin et tente l'ouverture. L'accessibilité du fichier
dépend de la configuration réseau de la collectivité.

**Ajout manuel d'une ligne**
Bouton "Ajouter une ligne" dans la barre d'outils.
Ouvre la popup d'édition avec tous les champs vides.
Valeurs par défaut configurables par colonne (stockées dans
`datagrid_columns.default_value`).

**Création et modification de structure par l'admin orga**
L'admin tenant peut, pour ses propres grilles :
- Renommer une colonne (libellé affiché uniquement — le nom technique
  MySQL n'est jamais modifiable après création)
- Modifier le type d'une colonne (avec avertissement si des données
  existantes pourraient être affectées)
- Réordonner les colonnes (drag & drop sur `sort_order`)
- Activer / désactiver une colonne (`visible_by_default`)
- Ajouter une colonne (DDL exécuté via Super Admin uniquement —
  l'admin tenant soumet une demande, le Super Admin valide)

Note : le DDL reste la prérogative exclusive du Super Admin (ADR-036 §2.12).

**Persistance des préférences utilisateur**
- Colonnes visibles : persistées par `user_id + table_id` en base
- Nombre de lignes par page : persisté par `user_id`
- Dernière vue sauvegardée active : persistée par `user_id + table_id`

Stockage dans une table `datagrid_user_preferences` :

```sql
datagrid_user_preferences
    id
    user_id
    table_id          -- NULL pour les prefs globales (ex: perPage)
    key               -- 'visible_columns' | 'per_page' | 'active_view'
    value             -- JSON
    timestamps
```

**Compteur contextuel**
Au-dessus de la grille : "247 enregistrements trouvés (sur 1 843 au total)".
Le premier chiffre reflète les filtres actifs ; le second est le total
sans filtre, calculé en cache.

**Gestion des dates Excel**
À l'import, les colonnes déclarées de type `DATE` reçoivent un traitement
automatique : si la valeur est un entier entre 1 et 2 958 465, elle est
interprétée comme un numéro sériel Excel (base : 1900-01-01, avec
correction du bug leap year Excel 1900) et convertie en date ISO.

#### 2.5 Popup d'édition — onglets

La popup passe à une structure tabulée (configurable par le Super Admin) :

| Onglet | Contenu | Toujours présent |
|--------|---------|-----------------|
| Données principales | Colonnes marquées `tab = 'main'` | Oui |
| Informations complémentaires | Colonnes marquées `tab = 'extra'` | Si au moins une colonne |
| Documents | Pièces jointes GED (voir §3.3) | Si module GED actif |
| Historique | Journal des modifications de la fiche | Oui |
| Onglets métier | Configurables par le Super Admin | Non |

Par défaut, toutes les colonnes sont dans l'onglet "Données principales".
Le Super Admin peut les répartir entre les onglets.

**Copier / dupliquer une fiche**
Bouton "Dupliquer" dans la popup : crée une nouvelle fiche avec toutes
les valeurs copiées. L'utilisateur peut modifier avant d'enregistrer.
La duplication est tracée dans l'audit log.

**Historique de la fiche**
Onglet "Historique" dans la popup : liste chronologique des modifications
avec date, heure, utilisateur, champ modifié, ancienne valeur, nouvelle valeur.
Données issues de `datagrid_audit_logs`.

---

### 3. Bloc Extensions

#### 3.1 Relations entre tables (DataGrid Relationnel)

C'est le chantier architectural le plus important. Un ADR dédié (ADR-040)
sera rédigé avant tout développement. Les principes posés ici :

**Nouveau type de colonne : `RELATION`**
Une colonne de type `RELATION` pointe vers une table cible (`target_table_id`)
et une colonne d'affichage (`display_column`). En base, elle stocke l'`id`
de la ligne cible. Dans l'UI, elle affiche la valeur de `display_column`.

```sql
datagrid_columns
    ...
    relation_table_id    -- FK datagrid_tables (NULL si pas de relation)
    relation_display_col -- nom de la colonne à afficher dans la source
    relation_allow_multi -- boolean : relation 1-N ou N-N
```

**Cas d'usage collectivités :**
- Élus → Commissions (via table de liaison)
- Agents → Services (via `RELATION` vers la table Services)
- Équipements → Type d'équipement (liste de référence)

**Vue Master / Détail**
Dans la grille, une ligne peut être dépliée pour afficher ses
enregistrements liés (relations 1-N). Configurable par le Super Admin.

**Recherche cross-tables**
Un champ de recherche global (accessible depuis le tableau de bord)
cherche simultanément dans toutes les grilles du tenant et regroupe
les résultats par table. Utile pour retrouver "Dupont" qu'il soit
dans Élus, Contacts ou Prestataires.

#### 3.2 Champs conditionnels

Une colonne peut être configurée comme conditionnellement visible :

```
Afficher la colonne [B] si la colonne [A] = [valeur]
```

Niveau simple : une condition (colonne = valeur).
Niveau avancé : règles combinées ET/OU, configurées par le Super Admin
dans l'interface de définition des colonnes.

La visibilité conditionnelle s'applique dans la popup d'édition.
Dans la grille, la colonne reste visible mais peut afficher "N/A"
si la condition n'est pas remplie (comportement configurable).

#### 3.3 Pièces jointes — intégration GED

Depuis l'onglet "Documents" de la popup d'édition :
- Joindre un document existant de la GED Pladigit (navigateur GED intégré)
- Uploader un nouveau document (stocké dans la GED, lié à la fiche)
- Prévisualisation PDF et images directement dans la popup

Table de liaison :

```sql
datagrid_row_documents
    id
    table_name        -- nom MySQL de la table tenant
    row_id            -- id de la ligne dans la table
    ged_document_id   -- FK ged_documents
    added_by
    timestamps
```

#### 3.4 Workflow et statuts

**Colonne statut avec transitions**
Une colonne de type `STATUS` (sous-type de `SELECT`) permet de définir
des transitions autorisées entre valeurs :

```
Brouillon → En cours → Validé → Archivé
(retour arrière : Validé → En cours si refus)
```

Chaque transition peut nécessiter un rôle ou une permission spécifique.
La couleur de la ligne dans la grille suit le statut (configurable).

**Validation par un responsable**
Une transition marquée `requires_approval = true` génère une notification
à l'utilisateur désigné comme valideur. Il approuve ou refuse depuis
la popup de la fiche.

#### 3.5 Collaboration

**Commentaires internes sur une fiche**
Onglet ou section dans la popup : fil de commentaires horodatés,
non modifiables après envoi, visibles selon les droits de lecture.

**Assignation d'une fiche**
Champ `assigned_to` (utilisateur du tenant) sur chaque ligne.
Filtrage rapide "Mes fiches" dans la barre d'outils.
Notification à l'utilisateur assigné (via le système de notifications
du Chat temps réel — dépendance au module Chat).

**Notifications de modification**
Un utilisateur peut "suivre" une fiche. Il reçoit une notification
lorsqu'elle est modifiée. Dépendance au module Chat/notifications.

#### 3.6 Widgets et tableaux de bord

Widgets configurables affichés au-dessus de la grille :

| Widget | Description |
|--------|-------------|
| Compteur total | Nombre total de lignes (avec/sans filtre) |
| Somme | Somme d'une colonne numérique |
| Moyenne | Moyenne d'une colonne numérique |
| Répartition | Camembert ou barres pour une colonne SELECT/BOOLEAN |
| Alertes | Lignes remplissant une condition configurée |

Configurable par l'admin tenant. Jusqu'à 4 widgets par grille.

#### 3.7 Accessibilité et ergonomie avancée

**Virtualisation pour grands volumes**
Mode client-side jusqu'à 2 000 lignes.
Au-delà : server-side pagination via API JSON Laravel
(Tabulator supporte les deux modes nativement).

**Tri multi-colonnes**
Shift+clic sur un en-tête pour ajouter une colonne au tri.
Indicateur visuel (numéro de priorité sur les en-têtes triés).

**En-têtes de colonnes groupés**
Possibilité de regrouper visuellement des colonnes sous un en-tête commun.
Exemple : "Coordonnées" regroupe Adresse, Code postal, Ville, Téléphone.

**Verrouillage optimiste**
Avertissement non bloquant si deux agents ouvrent la même fiche
simultanément. À la sauvegarde, détection de conflit et proposition
de fusion manuelle si les valeurs divergent.

**QR code par fiche**
Bouton dans la popup : génère un QR code contenant l'URL directe de la fiche
(accès mobile rapide). Utile pour les agents terrain (équipements, visites).

**Champs calculés en lecture seule**
Une colonne peut être définie comme calculée :
`age` = année courante − `annee_naissance`, affiché en lecture seule.
Calcul effectué côté serveur au moment de l'affichage.

---

### 4. DataPilote — module analytique

Positionné après stabilisation complète du DataGrid (Socle + Extensions).
Les décisions architecturales de l'ADR-036 §3 restent en vigueur.

#### 4.1 Accès

Bouton "Analyser" dans la barre d'outils du DataGrid.
Bascule fluide DataGrid ↔ DataPilote sans changer de contexte.
L'URL change (`/datagrid/{id}/pilote`) pour permettre le partage de liens.

#### 4.2 Fonctionnalités

**Tableau croisé dynamique**
- Axes ligne / colonne configurables par drag & drop
- Agrégations : somme, moyenne, compte, min, max, valeur unique
- Filtres applicables indépendamment du DataGrid
- Sous-totaux et totaux configurables

**Graphiques associés**
Via Apache ECharts (MIT) :
- Barres (verticales et horizontales)
- Courbes (avec zones)
- Secteurs (camembert)
- Graphique combiné (barres + courbe)

Le graphique suit la configuration du tableau croisé.
Changement de type de graphique en un clic.

**Export DataPilote**
- Export du tableau croisé en Excel (PhpSpreadsheet)
- Export du graphique en image PNG (ECharts server-side rendering)
- Export en PDF (tableau + graphique)

#### 4.3 Architecture

Configuration stockée en base (sauvegarde de l'état du DataPilote) :

```sql
datagrid_pilote_configs
    id
    table_id
    user_id
    name             -- nom de la configuration sauvegardée
    row_axis         -- JSON : colonnes en axe ligne
    col_axis         -- JSON : colonnes en axe colonne
    aggregation      -- JSON : agrégations configurées
    chart_type       -- enum: bar | line | pie | combo
    filters          -- JSON : filtres spécifiques au DataPilote
    timestamps
```

Calculs d'agrégation exécutés côté serveur (Laravel).
Rendu du tableau croisé côté client (Alpine.js + JS vanilla).
Rendu des graphiques côté client (ECharts).

---

### 5. Fonctionnalités explicitement hors périmètre

Ces éléments ont été évalués et écartés pour cette feuille de route :

| Fonctionnalité | Raison de l'exclusion |
|----------------|----------------------|
| Formules entre colonnes (type Excel) | Complexité excessive, risque de régression, hors usage collectivités |
| Import depuis URL / API externe | Niveau 4 réaliste — dépend d'un scheduler robuste |
| Droits au niveau cellule individuelle | ADR-036 §4.4 : complexité excessive |
| Relations inter-tenants | Hors périmètre multi-tenant |
| Éditeur de colonnes SQL direct | DDL réservé au Super Admin (ADR-036 §2.12) |

---

### 6. Ordre de développement recommandé

```
Socle
├── 1. Recherche globale multicolonne (impact immédiat, facile)
├── 2. Compteur contextuel X/Y (trivial)
├── 3. Gestion des dates Excel à l'import
├── 4. Ajout manuel d'une ligne
├── 5. Organisation des grilles en dossiers (table + barre latérale)
├── 6. Type de colonne CHEMIN_FICHIER avec icône cliquable
├── 7. Export Excel (PhpSpreadsheet)
├── 8. Persistance préférences utilisateur (table + mount)
├── 9. Popup onglets + onglet Historique UI
├── 10. Droits UI admin tenant (droits par département et utilisateur)
├── 11. Cache Redis droits résolus
├── 12. Droits au niveau colonne
├── 13. Export PDF + impression fiche
├── 14. Recherche floue sur les noms (fuzzy)
├── 15. Détection doublons à l'import
└── 16. Fusion de doublons UI

Extensions
├── 17. ADR-040 — Relations entre tables (rédaction avant code)
├── 18. Type de colonne RELATION + UI
├── 19. Master / Détail
├── 20. Recherche cross-tables
├── 21. Champs conditionnels (niveau simple)
├── 22. Pièces jointes — intégration GED
├── 23. Workflow statuts + transitions
├── 24. Commentaires internes
├── 25. Widgets tableaux de bord
├── 26. Virtualisation grands volumes (server-side Tabulator)
├── 27. Tri multi-colonnes UI
├── 28. En-têtes groupés
├── 29. Verrouillage optimiste
├── 30. Champs calculés
├── 31. Assignation + notifications (dépend du module Chat)
└── 32. QR code par fiche

DataPilote
├── 33. Tableau croisé dynamique (MVP)
├── 34. Graphiques ECharts
├── 35. Export DataPilote
└── 36. Sauvegarde configurations DataPilote
```

---

## Conséquences

### Positives
- Feuille de route claire et priorisée — pas de développement à l'aveugle
- L'architecture relationnelle est posée dès le Socle (migration `RELATION`)
  sans en construire toute l'UI immédiatement
- La recherche floue répond à un vrai problème métier des collectivités
  (données Excel historiques hétérogènes)
- Le Socle seul suffit pour une démo convaincante auprès d'une DSI
- Le DataPilote sur mesure garantit la cohérence UI et l'indépendance

### Points de vigilance
- La fusion de doublons est un mécanisme destructif — interface de confirmation
  stricte et audit log détaillé obligatoires
- Le cache Redis des droits doit être invalidé sur toute modification
  de la hiérarchie départementale (pas seulement des permissions)
- La virtualisation Tabulator server-side nécessite une API JSON Laravel
  distincte de la pagination Livewire actuelle — refactoring à anticiper
- Les notifications (assignation, suivi de fiche) dépendent du module Chat
  temps réel — les développer sans ce module implique un système de
  notifications interne simplifié à refactorer ensuite
- ADR-040 (Relations entre tables) doit être rédigé et validé avant
  tout développement du type `RELATION` pour éviter un schéma de données
  à refactorer

### Alternatives écartées
- **Découpage strict niveau 2 / niveau 3** : livraison intermédiaire trop
  pauvre pour décrocher l'attention des collectivités cibles
- **Adoption de Tabulator immédiate** : l'implémentation actuelle (Livewire
  natif) est fonctionnelle et les migrations vers Tabulator seront décidées
  colonne par colonne selon les besoins (virtualisation, tri multi-colonnes)
- **DataPilote basé sur une librairie pivot existante** : ADR-036 §3 —
  aucune librairie open source n'atteint le niveau requis

---

## Références
- ADR-036 : DataGrid, DataPilote et modèle de droits hiérarchiques (fondations)
- ADR-037 : Gouvernance des données personnelles et conformité RGPD
- ADR-038 : Source de vérité documentaire
- ADR-040 : Relations entre tables DataGrid (à rédiger)
- Tabulator 6.x : https://tabulator.info
- Apache ECharts : https://echarts.apache.org
- PhpSpreadsheet : https://phpspreadsheet.readthedocs.io
- CGCT art. L.2122-22 — délégations du Maire
