# ADR-040 — DataGrid Relationnel : relations entre tables

## Statut
Proposé — 2026-05-08

## Contexte

Le DataGrid niveau 1 gère des tables indépendantes. Un besoin récurrent
dans les collectivités dépasse ce modèle : une même entité (une personne,
un équipement, un lieu) possède plusieurs ensembles de données liés.

Exemples concrets :
- Un élu a plusieurs mandats et appartient à plusieurs commissions
- Un agent a suivi plusieurs formations avec leurs dates et résultats
- Un équipement a un historique d'interventions et de prestataires
- Une association a plusieurs contacts et plusieurs subventions reçues

Sans relations, la collectivité doit soit dénormaliser (60 colonnes pour
un élu avec tous ses mandats), soit maintenir des tables séparées sans lien.
Les deux solutions dégradent la qualité des données et l'ergonomie.

L'ADR-039 identifie ce chantier comme priorité Extensions et impose
la rédaction de cet ADR avant tout développement.

---

## Décision

### 1. Modèle conceptuel

Trois types de relations sont supportés :

| Type | Description | Exemple |
|------|-------------|---------|
| N-1 (Many-to-One) | Chaque ligne pointe vers une ligne d'une table de référence | Équipement → Type d'équipement |
| 1-N (One-to-Many) | Une ligne principale a plusieurs lignes détail | Élu → ses mandats |
| N-N (Many-to-Many) | Via table de liaison | Élu ↔ Commissions |

Dans tous les cas, **la structure est définie par le Super Admin**.
Les agents consultent et saisissent — ils ne touchent jamais au schéma.

---

### 2. Nouveau type de colonne : `RELATION`

Une colonne de type `RELATION` est ajoutée à l'enum `DatagridColumnType`.

#### 2.1 Schéma

Nouvelles colonnes ajoutées à `datagrid_columns` :

```sql
ALTER TABLE datagrid_columns
    ADD COLUMN relation_table_id    BIGINT UNSIGNED NULL,  -- FK datagrid_tables
    ADD COLUMN relation_display_col VARCHAR(64)    NULL,   -- colonne à afficher
    ADD COLUMN relation_type        VARCHAR(10)    NULL,   -- 'n1' | '1n' | 'nn'
    ADD COLUMN relation_pivot_table VARCHAR(128)   NULL,   -- table liaison si nn
    ADD COLUMN relation_fk_source   VARCHAR(64)    NULL,   -- colonne FK côté source
    ADD COLUMN relation_fk_target   VARCHAR(64)    NULL;   -- colonne FK côté cible
```

#### 2.2 Comportement selon le type de relation

**N-1 (liste de référence)**
La colonne stocke un entier (`id` de la ligne cible).
Dans la grille : affiche `relation_display_col` de la ligne cible.
Dans la popup : dropdown avec les valeurs de la table cible.

Exemple : colonne `type_equipement_id` dans la table Équipements,
qui pointe vers la table TypesEquipements et affiche le libellé.

**1-N (détail)**
Pas de colonne FK dans la table source — c'est la table détail
qui contient le FK vers la source.
Dans la grille source : ligne dépliable (Master/Détail) affichant
les lignes liées.
Dans la popup : onglet dédié avec mini-grille des lignes liées,
bouton "Ajouter une ligne détail".

Exemple : table Élus (source) + table Mandats (détail avec `elu_id`).

**N-N (via table de liaison)**
Le Super Admin désigne la table de liaison existante (`relation_pivot_table`)
et les deux colonnes FK (`relation_fk_source`, `relation_fk_target`).
Dans la popup : liste de cases à cocher des valeurs disponibles.

Exemple : table Élus + table Commissions + table liaison `elu_commission`
(colonnes `elu_id`, `commission_id`).

---

### 3. Interface Super Admin

Le Super Admin configure les relations dans la page de définition
d'une table DataGrid :

1. Sélectionner une colonne existante ou en créer une nouvelle
2. Choisir le type : `RELATION`
3. Sélectionner la table cible (parmi les tables du même tenant)
4. Choisir le type de relation (N-1 / 1-N / N-N)
5. Sélectionner la colonne d'affichage dans la table cible
6. Pour N-N : sélectionner la table de liaison et les FK

Validation avant enregistrement :
- La table cible doit exister dans le même tenant
- La colonne d'affichage doit exister dans la table cible
- Pour N-N : les colonnes FK doivent exister dans la table de liaison

---

### 4. Vue Master / Détail

Pour les relations 1-N, la grille principale affiche un indicateur
de dépliage sur chaque ligne. Au clic :

- Requête lazy vers l'API Laravel pour charger les lignes liées
- Affichage d'une mini-grille inline sous la ligne principale
- La mini-grille supporte le tri et le filtrage basiques
- Bouton "Ajouter" ouvre la popup d'édition de la table détail
  avec le FK pré-rempli

Performance : les lignes détail sont chargées à la demande (lazy),
pas au chargement initial de la grille.

---

### 5. Recherche cross-tables

Un champ de recherche global accessible depuis le tableau de bord
(et depuis chaque grille) cherche simultanément dans toutes les grilles
du tenant.

Architecture :
- Endpoint API `GET /tenant/datagrid/search?q=dupont`
- Recherche sur toutes les tables actives du tenant
- Pour chaque table : recherche sur les colonnes `TEXT` et `NOM_PERSONNE`
  avec la recherche floue si applicable
- Résultats groupés par table, limités à 5 résultats par table
- Lien direct vers la fiche (ouvre la popup d'édition de la ligne)

---

### 6. Contraintes fondamentales

Ces règles ne souffrent aucune exception :

- **Pas de DDL depuis les relations** — la création de tables de liaison
  est effectuée manuellement par le Super Admin via MySQL.
  Pladigit ne génère jamais de `CREATE TABLE` automatiquement.
- **Relations intra-tenant uniquement** — une colonne `RELATION` ne peut
  pointer que vers une table du même tenant. Les relations inter-tenants
  sont hors périmètre.
- **Pas de cascade automatique** — la suppression d'une ligne source
  ne supprime pas les lignes liées. Un avertissement est affiché
  si des lignes liées existent, avec choix de l'opérateur.
- **Intégrité référentielle gérée par l'application** — pas de
  contraintes FK MySQL (incompatibles avec la création dynamique
  de tables tenant). La cohérence est assurée par les validations
  Laravel avant chaque INSERT/UPDATE/DELETE.

---

### 7. Impact sur le schéma existant

La migration est **strictement additive** — aucune table existante
n'est modifiée de façon destructive :

```
database/migrations/tenant/
    2026_XX_XX_000001_add_relation_columns_to_datagrid_columns.php
```

Les colonnes `datagrid_columns` existantes conservent `relation_*` à `NULL`
et se comportent exactement comme avant.

---

### 8. Ordre de développement

```
1. Migration additive — colonnes relation_* sur datagrid_columns
2. Enum DatagridColumnType::RELATION
3. Interface Super Admin — configuration N-1 (le plus simple)
4. Rendu N-1 dans la grille (dropdown → affichage libellé)
5. Interface Super Admin — configuration 1-N
6. Vue Master/Détail dans la grille
7. Interface Super Admin — configuration N-N
8. Rendu N-N dans la popup (cases à cocher)
9. Recherche cross-tables (endpoint + UI)
10. Avertissement suppression avec lignes liées
```

---

## Conséquences

### Positives
- Couvre les cas d'usage les plus complexes des collectivités
  (élus/mandats/commissions, agents/formations, équipements/interventions)
- Fondation pour l'annuaire des personnalités (ADR-037 §2.3)
- La migration additive protège les données existantes
- La recherche cross-tables répond au problème "Jean Dupont dans
  quelle table ?" sans dupliquer les données

### Points de vigilance
- L'intégrité référentielle sans contraintes FK MySQL est
  entièrement sous la responsabilité de l'application — tests
  exhaustifs obligatoires sur les cas de suppression
- La vue Master/Détail avec lazy loading nécessite une API JSON
  distincte de la pagination Livewire — anticiper ce refactoring
  dès le début du développement
- Les tables de liaison N-N sont créées manuellement par le Super Admin
  — documenter clairement cette procédure
- Performance : une grille avec plusieurs colonnes RELATION peut
  générer N+1 requêtes — utiliser des eager loads Laravel

### Alternatives écartées
- **FK MySQL natives** : incompatibles avec la création dynamique
  de tables tenant (les migrations tenant sont exécutées à la volée,
  pas à l'installation)
- **Relations inter-tenants** : risque de fuite de données entre
  organisations — écarté définitivement
- **Génération automatique des tables de liaison** : DDL automatique
  écarté (ADR-036 §2.12 et ADR-039 §5)

---

## Références
- ADR-036 : DataGrid, DataPilote et modèle de droits hiérarchiques
- ADR-037 : Gouvernance des données personnelles — annuaire des personnalités
- ADR-039 : DataGrid/DataPilote feuille de route consolidée niveaux 2 et 3
- Migration `create_datagrid_tables_table.php`
- Migration `create_datagrid_columns_table.php`
