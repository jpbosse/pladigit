# ADR-036 — Modules DataGrid, DataPilote et modèle de droits hiérarchiques

## Statut
Proposé — 2026-04-29

## Contexte

Pladigit doit offrir aux agents des collectivités un outil de consultation,
d'édition et d'analyse de données structurées, sans exposer la définition
de la structure (DDL) aux utilisateurs finaux.

La hiérarchie organisationnelle existe déjà dans le modèle tenant :
- Table `departments` avec `parent_id` autoréférentiel (récursif, x niveaux)
- Champ `label` libre (Pôle, Direction, Service, Bureau, Cellule…)
- Pivot `user_department` avec `is_manager` et appartenance multiple

Les droits doivent s'appuyer sur cette structure plutôt que sur un système
de rôles plats (spatie/laravel-permission écarté — non adapté à l'héritage
organisationnel).

---

## Décision

### 1. Séparation des responsabilités

| Acteur         | Périmètre                                                    |
|----------------|--------------------------------------------------------------|
| Super Admin    | Crée les tables MySQL, définit les colonnes et les types     |
| Admin tenant   | Assigne les droits par nœud hiérarchique ou par utilisateur  |
| Utilisateur    | Consulte, édite, exporte selon ses droits hérités            |

La structure (DDL) n'est jamais accessible aux utilisateurs finaux.

---

### 2. Module DataGrid

**Rôle :** présenter, éditer et exporter les données d'une table MySQL tenant.

**Librairie retenue :** Tabulator 6.x (licence MIT, vanilla JS, zéro dépendance jQuery)

**Intégration :** composant Livewire qui expose l'API JSON Laravel ;
Tabulator est initialisé côté client via Alpine.js.

#### 2.1 Affichage et navigation

- Colonnes visibles par défaut configurables par le Super Admin (ex: 5 sur 30)
- Sélecteur de colonnes — panneau latéral pour afficher / masquer les colonnes disponibles
- Persistance de la sélection de colonnes par utilisateur
- Tri multi-colonnes, regroupement de lignes
- En-têtes de colonnes groupés (dossiers / sous-dossiers)
- Virtualisation des lignes et colonnes pour les grands volumes

#### 2.2 Pagination

- Sélecteur du nombre de lignes : 5 / 10 / 20 / 50 / 100 / Tout
- Indicateur contextuel : "247 enregistrements trouvés (sur 1 843 au total)"
- Navigation : première / précédente / numéros / suivante / dernière page
- Retour automatique à la page 1 lors d'un changement de filtre
- Indicateur visuel de filtre actif
- Mode client-side jusqu'à ~2 000 lignes ; server-side au-delà (API Laravel)
- Persistance du nombre de lignes par page par utilisateur

#### 2.3 Filtrage

- Filtres par colonne (texte, date, nombre, liste)
- Constructeur de filtres avancé (ET / OU, opérateurs configurables)
- Vues sauvegardées — l'agent nomme et retrouve ses filtres habituels
- Vues par défaut par service définies par l'admin tenant

#### 2.4 Édition

- Pas d'édition inline — toute modification passe par une popup modale
- Popup d'édition avec onglets configurables :
  - Onglet "Données principales"
  - Onglet "Informations complémentaires"
  - Onglet "Documents" (intégration GED Pladigit)
  - Onglet "Historique / audit"
  - Onglets métier configurables par le Super Admin
- Champs intelligents selon le type : calendrier, carte adresse, téléphone
  formaté, SIRET validé, code postal avec ville auto-complétée
- Champs calculés en lecture seule (ex: âge depuis date de naissance)
- Valeurs par défaut configurables par colonne
- Champs obligatoires avec validation avant enregistrement
- Copier / dupliquer une fiche existante
- Verrouillage optimiste — avertissement si deux agents ouvrent la même fiche

#### 2.5 Master / Détail

- Ligne principale = entité principale (ex: une personne)
- Détail déroulant = enregistrements liés (ex: mandats, rôles, historique)
- Liens entre tables (relations configurées par le Super Admin)

#### 2.6 Collaboration et suivi

- Commentaires internes sur une ligne (non modifiables, horodatés)
- Historique des modifications par ligne — qui a changé quoi et quand
- Notifications de modification entre agents
- Assignation d'une fiche à un agent avec suivi

#### 2.7 Workflow et statuts

- Colonne statut avec transitions configurables
  (ex: Brouillon → En cours → Validé → Archivé)
- Couleur de ligne selon le statut
- Validation par un responsable avant passage au statut suivant

#### 2.8 Export et impression

- Export Excel et PDF en conservant les types de données
- Impression d'une fiche formatée
- Export d'une liste avec avertissement RGPD si données personnelles

#### 2.9 Pièces jointes

- Joindre des documents à une fiche (intégration GED Pladigit existante)
- Prévisualisation PDF et images dans la popup

#### 2.10 Widgets et tableaux de bord

- Widgets résumés au-dessus de la grille (total lignes, sommes, alertes)
- Indicateurs configurables par l'admin tenant

#### 2.11 Accessibilité

- Navigation clavier complète
- Mode sombre (cohérent avec Pladigit)
- QR code sur une fiche pour accès rapide mobile

#### 2.12 Contrainte fondamentale

Le DataGrid ne peut jamais exécuter de DDL (CREATE, ALTER, DROP).
La structure est définie exclusivement par le Super Admin.

---

### 3. Module DataPilote

**Rôle :** analyser et croiser les données — vue analytique de la même source
ou d'une table dédiée définie par le Super Admin.

**Accès :** bouton "Analyser" dans la barre d'outils du DataGrid —
bascule fluide DataGrid ↔ DataPilote sans changer de contexte.

**Fonctionnalités :**
- Tableau croisé dynamique : axes ligne / colonne libres par drag & drop
- Agrégations : somme, moyenne, compte, min, max, personnalisée
- Graphiques associés (barres, courbes, secteurs) via Apache ECharts (MIT)
- Lecture seule — aucune édition depuis le DataPilote

**Architecture :** interface de configuration en Livewire + Alpine.js ;
calculs d'agrégation exécutés côté serveur (Laravel) pour les grands volumes ;
rendu du tableau croisé côté client.

**Note :** aucune librairie pivot open source existante n'atteint le niveau
de présentation requis pour des agents de collectivité. Le DataPilote sera
développé sur mesure — cohérence visuelle avec Pladigit garantie,
aucune dépendance à une librairie abandonnée ou limitée.

**Positionnement :** niveau 3 — après stabilisation complète du DataGrid.

---

### 4. Modèle de droits hiérarchiques

#### 4.1 Principe fondamental

Les droits héritent du parent vers tous les enfants (récursif, x niveaux).
Une exception explicite (`denied = true` ou règle individuelle) prime
toujours sur l'héritage. En cas de conflit entre deux règles,
**la restriction l'emporte toujours** (principe de moindre privilège).

#### 4.2 Structure de données

```sql
-- Tables définies par le Super Admin
datagrid_tables
    id
    name             -- nom technique de la table MySQL tenant
    label            -- nom affiché aux utilisateurs
    description
    tenant_id
    created_by
    timestamps

-- Permissions : départementales ou individuelles
datagrid_permissions
    id
    table_id         -- FK datagrid_tables (NULL = toutes tables)
    column_name      -- NULL = droit sur toute la table
    department_id    -- NULL si règle individuelle (user_id requis)
    user_id          -- NULL si règle départementale (department_id requis)
    can_read         -- boolean
    can_write        -- boolean
    can_delete       -- boolean
    can_export       -- boolean
    denied           -- exception explicite (prime sur tout héritage)
    timestamps
```

#### 4.3 Algorithme de résolution des droits

1. Collecter tous les nœuds hiérarchiques de l'utilisateur
   (ses `departments` + tous leurs ancêtres, récursivement)
2. Rechercher une règle explicite sur chaque nœud
3. Appliquer la règle du nœud le plus proche (plus spécifique)
4. Si `denied = true` sur n'importe quel nœud → accès refusé
5. Une règle individuelle (`user_id`) prime sur une règle départementale

**Performance :** droits résolus mis en cache Redis par `user_id + table_id`
avec invalidation à chaque modification de permission.

#### 4.4 Niveaux de granularité

| Niveau  | Granularité                  | Exemple concret                                    |
|---------|------------------------------|----------------------------------------------------|
| Table   | Visible ou non               | Direction Finances voit la table Budgets           |
| Colonne | Visible / lecture / écriture | Colonne Salaire masquée sauf RH                    |
| Ligne   | Filtre automatique           | Agent ne voit que ses propres dossiers *(phase 2)* |

Droits au niveau cellule individuelle écartés (complexité excessive).

#### 4.5 Visibilité des fiches

Chaque fiche dispose d'un champ `visibilite` :
- `public` — visible sur le site de la mairie
- `interne` — visible aux agents selon droits
- `confidentiel` — visible uniquement au service concerné
- `archive` — anonymisé, conservé pour mémoire historique

#### 4.6 Extension du type department

Le champ `type` enum actuel (`direction`, `service`) sera progressivement
remplacé par le champ `label` libre déjà présent, permettant Pôle, Bureau,
Cellule, Unité… La récursivité via `parent_id` supporte x niveaux
sans modification de schéma.

---

## Conséquences

### Positives
- Modèle de droits intuitif pour les collectivités (suit l'organigramme réel)
- Fondation hiérarchique déjà en place — pas de refactoring du modèle tenant
- Tabulator couvre 100 % des besoins DataGrid sous licence MIT
- DataPilote sur mesure = cohérence UI et indépendance totale
- Cache Redis des droits = performances préservées sur grands annuaires
- Popup onglets = ergonomie adaptée aux agents non techniques

### Points de vigilance
- Tabulator : intégration Livewire à prototyper (échanges JSON, événements)
- DataPilote sur mesure : charge de développement significative — niveau 3
- Droits sur les lignes (filtre automatique) reportés en phase 2
- Résolution récursive : tester les performances sur annuaires > 500 agents
- Verrouillage optimiste : gérer les conflits de concurrence en base

### Alternatives écartées
- **spatie/laravel-permission** : rôles plats, héritage organisationnel non natif
- **AG Grid Community** : Master/Détail et pivot réservés Enterprise ($999/dev)
- **DevExtreme** : licence commerciale obligatoire, incompatible open source
- **PivotTable.js / WebDataRocks** : UI datée ou limitations (1 000 lignes)
- **Développement DataGrid from scratch** : 3-6 mois, risque qualité élevé

---

## Ordre de développement recommandé

1. DataGrid minimal — Tabulator + pagination + colonnes + popup édition simple
2. Droits hiérarchiques — fondation sans laquelle rien d'autre n'a de sens
3. Annuaire des personnalités — premier module métier concret (voir ADR-037)
4. Fonctionnalités avancées — workflow, commentaires, historique, import
5. DataPilote — niveau 3, quand le DataGrid est stable

---

## Références
- ADR-027 : SUPER_ADMIN_ALLOWED_IPS
- ADR-033 : ressources locales et en-têtes HTTP sécurité
- ADR-037 : gouvernance des données personnelles et conformité RGPD
- Migration `2026_03_02_000001_create_departments_table.php`
- Migration `2026_03_05_214025_add_label_to_departments_table.php`
- Tabulator 6.x : https://tabulator.info
- Apache ECharts : https://echarts.apache.org
