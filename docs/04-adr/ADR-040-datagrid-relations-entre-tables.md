# ADR-040 — DataGrid Relationnel : relations entre tables, assistant de normalisation et expérience utilisateur transparente

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

La réalité du terrain est brutale : les collectivités arrivent avec des
fichiers Excel de 60 à 90 colonnes — typiquement des fichiers "tout en un"
accumulés pendant des années. Exemple réel :

```
elus_v3_final_2024.xls — 90 colonnes, 47 lignes
Nom | Prénom | Email | Téléphone | Fonction 1 | Date début 1 | Date fin 1
| Fonction 2 | Date début 2 | Date fin 2 | Commission 1 | Commission 2
| Commission 3 | notes_vieux | copie_jean | temp2024 | ...
```

Sans outil d'aide, le Super Admin — ou la responsable de communication
qui ne connaît rien à l'informatique — doit comprendre seul comment
décomposer ce fichier. C'est inenvisageable.

**Principe fondateur de cet ADR :**
L'utilisateur final ne doit jamais savoir qu'il travaille sur plusieurs
tables. Il voit une réalité métier cohérente. Pladigit gère la complexité
technique en arrière-plan.

L'ADR-039 identifie ce chantier comme priorité Extensions et impose
la rédaction de cet ADR avant tout développement.

---

## Décision

### 1. Modèle conceptuel

Trois types de relations sont supportés :

| Type | Description | Exemple |
|------|-------------|---------|
| N-1 (Many-to-One) | Chaque ligne pointe vers une table de référence | Équipement → Type d'équipement |
| 1-N (One-to-Many) | Une ligne principale a plusieurs lignes détail | Élu → ses mandats |
| N-N (Many-to-Many) | Via table de liaison | Élu ↔ Commissions |

Dans tous les cas, **la structure est définie par le Super Admin**.
Les agents consultent et saisissent — ils ne touchent jamais au schéma.

---

### 2. Nouveau type de colonne : `RELATION`

Une colonne de type `RELATION` est ajoutée à l'enum `DatagridColumnType`.

#### 2.1 Schéma

Nouvelles colonnes ajoutées à `datagrid_columns` (migration additive) :

```sql
ALTER TABLE datagrid_columns
    ADD COLUMN relation_table_id    BIGINT UNSIGNED NULL,  -- FK datagrid_tables
    ADD COLUMN relation_display_col VARCHAR(64)    NULL,   -- colonne à afficher
    ADD COLUMN relation_type        VARCHAR(10)    NULL,   -- 'n1' | '1n' | 'nn'
    ADD COLUMN relation_pivot_table VARCHAR(128)   NULL,   -- table liaison si nn
    ADD COLUMN relation_fk_source   VARCHAR(64)    NULL,   -- colonne FK côté source
    ADD COLUMN relation_fk_target   VARCHAR(64)    NULL,   -- colonne FK côté cible
    ADD COLUMN computed_sql         TEXT           NULL,   -- sous-requête calculée
    ADD COLUMN computed_readonly    BOOLEAN        NOT NULL DEFAULT FALSE,
    ADD COLUMN aggregated_separator VARCHAR(10)    NULL;   -- séparateur agrégation
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

### 3. Assistant de normalisation

L'assistant de normalisation est l'outil qui permet à n'importe qui —
y compris une responsable de communication sans compétence technique —
de décomposer un fichier Excel complexe en tables relationnelles propres.

#### 3.1 Phase 1 — Analyse automatique du fichier

À l'upload d'un fichier avec de nombreuses colonnes, Pladigit analyse
et présente un diagnostic en langage naturel :

```
📊 Fichier analysé : elus_v3_final_2024.xls
   90 colonnes détectées — 47 lignes

⚠ Ce fichier semble complexe. Voici ce que j'ai détecté :

   👤 Informations personnelles (12 colonnes)
      Nom, Prénom, Email, Téléphone, Adresse, Code postal…

   🏛 Mandats et fonctions (18 colonnes)
      Fonction, Date début mandat, Date fin mandat,
      Fonction 2, Date début 2, Date fin 2…
      → Ces colonnes se répètent — un élu peut avoir
        plusieurs mandats.

   🗂 Commissions (15 colonnes)
      Commission 1, Commission 2, Commission 3…
      → Ces colonnes se répètent aussi.

   ❓ Colonnes non identifiées (45 colonnes)
      A_traiter_1, notes_vieux, copie_jean, temp2024…
      → À trier manuellement.

💡 Recommandation : décomposer en 3 tables plutôt qu'une seule.
   Cela facilitera la recherche, l'édition et l'évolution des données.

[ Importer tout en une seule table ]  [ M'aider à décomposer → ]
```

**Algorithme de détection des colonnes répétées :**
Pladigit détecte les patterns `col_1/col_2/col_3`, `col1/col2/col3`,
`col (1)/col (2)`. Toute série de 3 colonnes ou plus avec le même
préfixe et un suffixe numérique est signalée comme candidate à la
normalisation en table séparée.

**Algorithme de détection des colonnes inutiles :**
Une colonne dont plus de 80 % des valeurs sont vides, ou dont le nom
contient des mots-clés courants de fichiers de travail (`temp`, `copie`,
`old`, `vieux`, `backup`, `test`, `archive`) est signalée comme
probablement inutile.

#### 3.2 Phase 2 — Groupage des colonnes par entité

Interface drag & drop en étapes guidées, une entité à la fois.

**Étape 1 — La table principale**

```
Quelle est la table principale ?
Glissez ici les colonnes qui décrivent UNE PERSONNE
(une ligne = un élu)

┌──────────────────┐    ┌──────────────────────────────┐
│ Colonnes dispo   │    │ 👤 Table principale : Élus   │
│                  │    │                              │
│ Nom           →→ │    │ Nom ✓                        │
│ Prénom        →→ │    │ Prénom ✓                     │
│ Email         →→ │    │ Email ✓                      │
│ Téléphone        │    │                              │
│ Fonction 1       │    │                              │
│ Date début 1     │    │                              │
│ Commission 1     │    │                              │
│ ...              │    │                              │
└──────────────────┘    └──────────────────────────────┘

💡 Conseil : mettez ici uniquement ce qui ne change
   pas souvent (nom, prénom, contact).
```

**Étape 2 — Les colonnes répétées**

```
J'ai remarqué que Fonction, Date début, Date fin
apparaissent plusieurs fois (Fonction 1, Fonction 2…).

Cela signifie qu'un élu peut avoir PLUSIEURS mandats.

Voulez-vous créer une table "Mandats" séparée ?
  ○ Oui — chaque mandat sera une ligne (recommandé)
  ○ Non — garder ces colonnes dans la table Élus
```

**Étape 3 — Les listes de valeurs répétées**

```
J'ai remarqué que Commission 1, Commission 2, Commission 3
contiennent des valeurs répétées sur plusieurs élus
("Finances", "Culture", "Voirie"…).

Voulez-vous créer une table "Commissions" ?
Cela permettra de cocher/décocher les commissions d'un élu
sans risque de faute de frappe.
  ○ Oui — liste partagée entre tous les élus (recommandé)
  ○ Non — garder comme texte libre
```

#### 3.3 Phase 3 — Récapitulatif et validation

```
✅ Plan de migration

   Table 1 : elus (table principale)
   ├── 12 colonnes
   └── 47 lignes à importer

   Table 2 : mandats
   ├── 4 colonnes : elu_id, fonction, date_debut, date_fin
   ├── Liée à : elus (une ligne elus → plusieurs mandats)
   └── ~94 lignes estimées (47 élus × ~2 mandats en moyenne)

   Table 3 : commissions
   ├── 2 colonnes : id, nom
   ├── ~12 valeurs distinctes détectées
   └── Table de liaison elu_commission créée

   🗑 45 colonnes ignorées (vides ou non identifiées)
      Vous pourrez les récupérer plus tard si besoin.

[ ← Modifier ]   [ Valider et créer les tables → ]
```

Le Super Admin valide explicitement — aucun DDL n'est exécuté sans
cette validation (principe ADR-036 §2.12).

#### 3.4 Phase 4 — Migration automatique des données

Après validation, Pladigit exécute en job asynchrone avec indicateur
de progression :

1. DDL : création des tables MySQL (Super Admin uniquement)
2. Import des colonnes de la table principale dans `elus`
3. **Pivot** des colonnes répétées en lignes dans `mandats`
   (Fonction 1 + Date début 1 + Date fin 1 → ligne 1, etc.)
4. Déduplication des listes (`commissions`) et création des liaisons
5. Détection fuzzy des doublons probables sur colonnes `NOM_PERSONNE`
6. Rapport final :

```
✅ Migration terminée

   47 élus importés
   94 mandats créés
   12 commissions créées
   141 liaisons élu ↔ commission créées
   8 doublons potentiels détectés → à vérifier

[ Voir les doublons ]   [ Aller à la grille Élus ]
```

#### 3.5 Service Laravel : `DatagridNormalizationService`

Posé dans le Socle même si l'interface wizard n'est livrée qu'en
Extensions — les méthodes sont réutilisables par l'assistant IA
(ADR-039 — DataGrid Assistant IA).

Responsabilités :
- `detectRepeatingGroups(array $headers): array`
- `detectUselessColumns(array $headers, array $data): array`
- `buildMigrationPlan(array $groups): MigrationPlan`
- `pivotColumnsToRows(array $data, array $repeatingGroup): array`
- `deduplicateListValues(array $data, string $column): array`

---

### 4. Vue métier — transparence pour l'utilisateur

L'utilisateur final ne voit jamais plusieurs tables techniques.
Il voit une **vue métier** — une configuration qui regroupe plusieurs
tables sous un nom unique avec colonnes calculées et agrégées.

#### 4.1 Nouvelle table : `datagrid_views`

```sql
datagrid_views
    id
    label               -- "Élus et mandats" (affiché à l'utilisateur)
    primary_table_id    -- FK datagrid_tables (table principale)
    folder_id           -- FK datagrid_folders (rangement sidebar)
    description
    tenant_id
    created_by
    timestamps
```

La sidebar affiche les `datagrid_views`, pas les `datagrid_tables` brutes.
Une table sans vue associée reste accessible au Super Admin uniquement.

#### 4.2 Ce que voit l'utilisateur dans la grille

```
┌──────────────┬───────────┬──────────────────┬───────────────┬──────┐
│ Nom          │ Prénom    │ Fonction actuelle │ Commissions   │      │
├──────────────┼───────────┼──────────────────┼───────────────┼──────┤
│ ▶ Dupont     │ Jean      │ Maire            │ Finances, RH  │ ✏   │
│ ▶ Martin     │ Marie     │ Adjointe culture │ Culture       │ ✏   │
│ ▶ Bernard    │ Paul      │ Conseiller       │ Voirie        │ ✏   │
└──────────────┴───────────┴──────────────────┴───────────────┴──────┘
```

- "Fonction actuelle" → colonne calculée (§5.1)
- "Commissions" → colonne agrégée (§5.2)
- ▶ → lignes liées dépliables (Master/Détail)

L'utilisateur ne sait pas que ces données viennent de 3 tables.

#### 4.3 Vue Master / Détail

La ligne se déplie au clic sur ▶ :

```
┌──────────────┬───────────┬──────────────────┬───────────────┬──────┐
│ ▼ Dupont     │ Jean      │ Maire            │ Finances, RH  │ ✏   │
├─────────────────────────────────────────────────────────────────────┤
│  📋 Mandats                                         [+ Ajouter]    │
│  ┌─────────────────┬──────────────┬──────────────┬──────────────┐  │
│  │ Fonction        │ Début        │ Fin          │              │  │
│  │ Maire           │ 01/04/2020   │ —            │ ✏ 🗑         │  │
│  │ Adjoint voirie  │ 15/03/2014   │ 31/03/2020   │ ✏ 🗑         │  │
│  └─────────────────┴──────────────┴──────────────┴──────────────┘  │
│                                                                     │
│  🏛 Commissions                                     [+ Ajouter]    │
│  ┌──────────────────────────────────────────────────────────────┐  │
│  │ ✓ Finances  ✓ Ressources humaines  ☐ Culture  ☐ Voirie      │  │
│  └──────────────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────────────┘
```

Les lignes détail sont chargées **lazy** via endpoint JSON dédié,
pas au chargement initial de la grille.

#### 4.4 Popup d'édition avec onglets

```
┌──────────────────────────────────────────────────────────────────┐
│  Jean Dupont — Maire                                         ✕   │
├────────────┬────────────┬────────────────┬──────────────────────┤
│ Identité   │ Mandats    │ Commissions    │ Historique           │
├────────────┴────────────┴────────────────┴──────────────────────┤
│                                                                  │
│  Onglet Identité    : champs de la table principale (elus)       │
│  Onglet Mandats     : mini-grille éditable (table mandats)       │
│  Onglet Commissions : cases à cocher (table elu_commission)      │
│  Onglet Historique  : journal toutes tables confondues           │
│                                                                  │
│              [ Annuler ]        [ Enregistrer ]                  │
└──────────────────────────────────────────────────────────────────┘
```

"Enregistrer" écrit dans les tables concernées en une seule transaction.
L'utilisateur ne voit qu'un bouton, qu'un résultat.

#### 4.5 Filtres transparents

Les filtres sur colonnes calculées et agrégées fonctionnent
comme sur n'importe quelle colonne. Pladigit génère les jointures
nécessaires en arrière-plan — l'utilisateur ne les voit pas.

---

### 5. Colonnes calculées et agrégées

#### 5.1 Colonnes calculées (lecture seule)

Stockées dans `computed_sql`. Exécutées côté serveur.
Le `:id` est remplacé par l'`id` de la ligne courante.

Exemples :

```sql
-- Fonction actuelle (mandat en cours)
SELECT fonction FROM mandats
WHERE elu_id = :id AND date_fin IS NULL
ORDER BY date_debut DESC LIMIT 1

-- Âge depuis date de naissance
TIMESTAMPDIFF(YEAR, date_naissance, CURDATE())

-- Nombre de mandats
SELECT COUNT(*) FROM mandats WHERE elu_id = :id
```

Le Super Admin saisit la sous-requête dans l'interface.
Validation syntaxique avant enregistrement.
Exécution sur connexion en lecture seule dédiée (`SELECT` uniquement).

#### 5.2 Colonnes agrégées

Concatènent les valeurs d'une relation en une seule chaîne.
Exemple : `"Finances, RH, Culture"` pour les commissions d'un élu.
Implémentation : `GROUP_CONCAT` MySQL. Séparateur configurable.

---

### 6. Interface Super Admin

#### 6.1 Configuration des relations

Dans la page de définition d'une table :
1. Créer une colonne de type `RELATION`
2. Choisir N-1 / 1-N / N-N
3. Sélectionner la table cible (même tenant)
4. Sélectionner la colonne d'affichage
5. Pour N-N : sélectionner table de liaison et FK

#### 6.2 Configuration des vues métier

Interface dédiée "Vues métier" :
1. Créer une vue — nom métier ("Élus et mandats")
2. Sélectionner la table principale
3. Sélectionner les tables liées à inclure
4. Configurer les onglets de la popup
5. Configurer les colonnes calculées et agrégées
6. Ranger dans un dossier (sidebar)

---

### 7. Recherche cross-tables

Endpoint `GET /tenant/datagrid/search?q=dupont` :
- Recherche sur toutes les tables actives du tenant
- Colonnes `TEXT` et `NOM_PERSONNE` avec fuzzy si applicable
- Résultats groupés par vue métier, 5 résultats max par vue
- Lien direct vers la fiche

---

### 8. Contraintes fondamentales

- **Pas de DDL automatique** — le plan de migration est présenté,
  le Super Admin valide explicitement avant toute exécution.
- **Relations intra-tenant uniquement** — pas de relations inter-tenants.
- **Pas de cascade automatique** — avertissement si lignes liées
  existent avant toute suppression.
- **Intégrité référentielle applicative** — pas de FK MySQL.
- **Sous-requêtes calculées en lecture seule** — `SELECT` uniquement,
  liste blanche des instructions autorisées.
- **Transparence non obligatoire** — une table peut exister sans vue
  métier. Elle reste accessible au Super Admin directement.

---

### 9. Impact sur le schéma existant

Migrations **strictement additives** :

```
database/migrations/tenant/
    2026_XX_XX_000001_add_relation_columns_to_datagrid_columns.php
    2026_XX_XX_000002_create_datagrid_views_table.php
```

---

### 10. Ordre de développement

```
Fondations (dans le Socle — avant tout développement Extensions)
├── 1. Migration additive — colonnes relation_* + computed_* sur datagrid_columns
├── 2. Migration — table datagrid_views
├── 3. Enum DatagridColumnType::RELATION
└── 4. DatagridNormalizationService (détection colonnes répétées/inutiles)

Extensions — Relations
├── 5. Interface Super Admin — configuration N-1
├── 6. Rendu N-1 dans la grille
├── 7. Interface Super Admin — configuration 1-N
├── 8. Vue Master/Détail lazy
├── 9. Interface Super Admin — configuration N-N
└── 10. Rendu N-N dans la popup (cases à cocher)

Extensions — Vues métier
├── 11. Interface Super Admin — création de vues métier
├── 12. Colonnes calculées — rendu + validation
├── 13. Colonnes agrégées — rendu
├── 14. Popup onglets multi-tables
└── 15. Sidebar — vues métier à la place des tables brutes

Extensions — Assistant de normalisation
├── 16. Interface wizard Phase 1 — diagnostic du fichier
├── 17. Interface wizard Phase 2 — drag & drop groupage colonnes
├── 18. Interface wizard Phase 3 — récapitulatif et validation
├── 19. Exécution migration — pivot colonnes → lignes (job asynchrone)
└── 20. Rapport post-migration avec détection doublons

Extensions — Recherche et sécurité
├── 21. Recherche cross-tables (endpoint + UI tableau de bord)
└── 22. Avertissement suppression avec lignes liées
```

---

## Conséquences

### Positives
- L'utilisateur final ne sait pas qu'il travaille sur plusieurs tables
- L'assistant de normalisation rend accessible la migration depuis Excel
  à une personne sans compétence informatique
- Les colonnes calculées et agrégées évitent la redondance de données
- Le `DatagridNormalizationService` posé dans le Socle est réutilisable
  par le DataGrid Assistant IA (ADR-039)
- Couvre les cas d'usage les plus complexes des collectivités

### Points de vigilance
- Intégrité référentielle sans FK MySQL — tests exhaustifs obligatoires
- Les sous-requêtes calculées sont une surface d'attaque potentielle
  (injection SQL) — liste blanche stricte, connexion lecture seule dédiée
- Vue Master/Détail lazy nécessite API JSON distincte de Livewire —
  anticiper ce refactoring dès le début
- Le pivot colonnes → lignes peut être long sur grands fichiers —
  job asynchrone avec indicateur de progression obligatoire
- Cache Redis sur les valeurs calculées avec invalidation à chaque
  modification de la ligne source

### Alternatives écartées
- **FK MySQL natives** : incompatibles avec la création dynamique de tables tenant
- **Relations inter-tenants** : risque de fuite de données — écarté définitivement
- **DDL automatique sans validation** : écarté (ADR-036 §2.12, ADR-039 §5)
- **Interface purement technique** : écarté — l'assistant guidé en langage
  naturel est le seul chemin acceptable pour les collectivités cibles

---

## Références
- ADR-036 : DataGrid, DataPilote et modèle de droits hiérarchiques
- ADR-037 : Gouvernance des données personnelles — annuaire des personnalités
- ADR-039 : DataGrid/DataPilote feuille de route consolidée niveaux 2 et 3
- Migration `create_datagrid_tables_table.php`
- Migration `create_datagrid_columns_table.php`
