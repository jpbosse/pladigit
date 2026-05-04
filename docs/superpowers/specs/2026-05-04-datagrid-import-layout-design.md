# DataGrid Import — Layout deux colonnes & typographie

**Date :** 2026-05-04
**Scope :** Pages d'import DataGrid (tenant + super admin)
**Branch :** develop

---

## Contexte

Les pages d'import DataGrid affichent les grilles existantes et le wizard en
empilement vertical. L'utilisateur souhaite une présentation côte-à-côte plus
lisible, et juge la police des champs monospace (noms techniques, descriptions)
trop grosse et grossière.

---

## Design

### 1. Layout deux colonnes

```
┌──────────────────┬──────────────────────────────────────┐
│  Grilles         │  Wizard d'import (3 étapes)          │
│  existantes      │                                      │
│  280 px fixe     │  flex : 1                            │
└──────────────────┴──────────────────────────────────────┘
```

- Conteneur flex (`display:flex; gap:24px; align-items:flex-start`)
- **Colonne gauche** : `width:280px; flex-shrink:0; position:sticky; top:24px`
  - Fond `--pd-surface`, border `--pd-border`, `border-radius:12px`
  - En-tête : « Grilles existantes » 11px uppercase + badge count
  - Liste : libellé 13px semi-bold, nom technique 11px monospace muted, badge colonnes
  - État vide : texte centré italique
  - Scroll indépendant max-height ~60vh
- **Colonne droite** : `flex:1; min-width:0`
  - Contient le wizard tel quel (fil d'Ariane + stepper + carte)
- **Responsive** ≤ 800 px : `flex-direction:column` — sidebar empilée au-dessus du wizard, pleine largeur, sans accordion

### 2. Typographie — champs monospace

Champs concernés : nom technique de la grille, noms techniques des colonnes,
description. Actuellement `font-size:12px` monospace, perçu trop grand/grossier.

Correction :
- `font-size:11px` (au lieu de 12px) pour les inputs monospace
- `font-size:11px` pour le champ description (input texte standard)
- Couleur `--pd-muted` sur les valeurs secondaires déjà en `--pd-text` quand
  elles ne sont pas focus
- Pas de changement sur les labels (déjà en 11-12px)

---

## Fichiers modifiés

### Tenant
- `resources/views/livewire/tenant/datagrid/import-wizard.blade.php`
  — wrapper deux colonnes, sidebar grilles existantes chargées dans le composant
- `app/Livewire/Tenant/Datagrid/ImportWizard.php`
  — propriété `$existingGrids` chargée dans `mount()`

### Super admin
- `resources/views/super-admin/datagrids/import.blade.php`
  — restructuration en flex deux colonnes (les grilles sont déjà disponibles via
  `$grids` passé par le contrôleur)

---

## Ce qui ne change pas

- Logique métier du wizard (upload, validation, import)
- Stepper à 3 étapes
- Comportement Livewire
- Tests existants

---

## Vérification

1. Afficher `/datagrid/import` côté tenant — sidebar visible, wizard fonctionnel
2. Afficher `/super-admin/datagrids/{org}/import` — même layout
3. Tester l'import complet (3 étapes) sur les deux pages
4. Vérifier responsive à 800 px (DevTools)
5. `php artisan test` — 807/807 toujours verts
