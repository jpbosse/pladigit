# DataGrid Import — Layout deux colonnes & typographie

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Afficher les grilles existantes dans une sidebar gauche (280 px) et le wizard d'import à droite, sur les deux pages d'import DataGrid (tenant + super admin), tout en affinant la typographie des champs monospace/description.

**Architecture:** Modification pure de vues et d'un composant Livewire — pas de nouvelle route ni de migration. La sidebar tenant est alimentée par une propriété `$existingGrids` chargée dans `mount()`. La sidebar super admin réutilise `$grids` déjà passé par le contrôleur.

**Tech Stack:** Laravel 11, Livewire 4, Blade, inline CSS avec variables CSS `--pd-*`.

---

## Fichiers touchés

| Fichier | Action |
|---------|--------|
| `app/Livewire/Tenant/Datagrid/ImportWizard.php` | Modifier — ajouter `$existingGrids` + chargement `mount()` |
| `resources/views/livewire/tenant/datagrid/import-wizard.blade.php` | Modifier — layout 2 colonnes + sidebar + typo |
| `resources/views/super-admin/datagrids/import.blade.php` | Modifier — layout 2 colonnes, sidebar à gauche |
| `resources/views/livewire/super-admin/datagrid/import-wizard.blade.php` | Modifier — typo monospace 11px |

---

## Task 1 — Tenant PHP : propriété `$existingGrids`

**Fichiers :**
- Modifier : `app/Livewire/Tenant/Datagrid/ImportWizard.php`

- [ ] **1.1 — Ajouter la propriété publique**

  Dans la section des propriétés (après `public ?string $errorMessage = null;`), ajouter :

  ```php
  /** @var array<int, array{label:string, name:string, columns_count:int}> */
  public array $existingGrids = [];
  ```

- [ ] **1.2 — Charger les grilles dans `mount()`**

  Le composant n'a pas encore de `mount()`. L'ajouter juste avant `updatedTableLabel()` :

  ```php
  public function mount(): void
  {
      $this->existingGrids = DatagridTable::withCount('columns')
          ->orderBy('label')
          ->get()
          ->map(fn ($g) => [
              'label' => $g->label,
              'name'  => $g->name,
              'columns_count' => $g->columns_count,
          ])
          ->toArray();
  }
  ```

- [ ] **1.3 — Vérifier que Pint + PHPStan passent**

  ```bash
  ./vendor/bin/pint app/Livewire/Tenant/Datagrid/ImportWizard.php
  ./vendor/bin/phpstan analyse app/Livewire/Tenant/Datagrid/ImportWizard.php --level=5
  ```

  Attendu : aucune erreur.

- [ ] **1.4 — Commit**

  ```bash
  git add app/Livewire/Tenant/Datagrid/ImportWizard.php
  git commit -m "feat: charger les grilles existantes dans le wizard tenant (sidebar)"
  ```

---

## Task 2 — Tenant view : layout deux colonnes + sidebar + typo

**Fichiers :**
- Modifier : `resources/views/livewire/tenant/datagrid/import-wizard.blade.php`

Le fichier actuel a une structure plate : fil d'Ariane → stepper → carte wizard.
On enveloppe l'ensemble dans un conteneur flex et on insère la sidebar.

- [ ] **2.1 — Remplacer le conteneur racine par un flex deux colonnes**

  Changer la première ligne :
  ```html
  {{-- AVANT --}}
  <div style="max-width:860px;margin:32px auto;padding:0 20px;">
  ```
  par :
  ```html
  {{-- APRÈS --}}
  <div style="max-width:1180px;margin:32px auto;padding:0 20px;">
  <div style="display:flex;gap:24px;align-items:flex-start;">
  ```

  Et fermer ce flex juste avant la dernière `</div>` du fichier :
  ```html
  </div>{{-- /flex --}}
  </div>{{-- /wrapper --}}
  ```

- [ ] **2.2 — Insérer la sidebar gauche**

  Juste après `<div style="display:flex;gap:24px;align-items:flex-start;">` et AVANT le fil d'Ariane existant, insérer une colonne droite englobante ET la sidebar :

  ```html
  {{-- ── Sidebar gauche — grilles existantes ────────────────────── --}}
  <div style="width:280px;flex-shrink:0;position:sticky;top:24px;">
      <div style="background:var(--pd-surface);border:0.5px solid var(--pd-border);
                  border-radius:12px;overflow:hidden;">
          <div style="padding:11px 16px;border-bottom:0.5px solid var(--pd-border);
                      display:flex;align-items:center;justify-content:space-between;">
              <span style="font-size:11px;font-weight:600;color:var(--pd-muted);
                           text-transform:uppercase;letter-spacing:.05em;">
                  Grilles existantes
              </span>
              <span style="font-size:11px;background:var(--pd-bg2);color:var(--pd-muted);
                           padding:1px 8px;border-radius:10px;">
                  {{ count($existingGrids) }}
              </span>
          </div>
          <div style="max-height:62vh;overflow-y:auto;">
              @forelse($existingGrids as $grid)
              <div style="padding:9px 16px;border-bottom:0.5px solid var(--pd-border);">
                  <div style="font-size:12px;font-weight:600;color:var(--pd-text);
                               white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                      {{ $grid['label'] }}
                  </div>
                  <div style="font-size:10px;color:var(--pd-muted);font-family:monospace;
                               margin-top:2px;">
                      {{ $grid['name'] }}
                  </div>
                  <div style="font-size:10px;color:var(--pd-muted);margin-top:2px;">
                      {{ $grid['columns_count'] }} col.
                  </div>
              </div>
              @empty
              <div style="padding:20px;text-align:center;font-size:12px;
                           color:var(--pd-muted);font-style:italic;">
                  Aucune grille définie
              </div>
              @endforelse
          </div>
      </div>
  </div>

  {{-- ── Zone wizard droite ───────────────────────────────────────── --}}
  <div style="flex:1;min-width:0;">
  ```

  Fermer `</div>{{-- /zone wizard --}}` juste avant `</div>{{-- /flex --}}`.

- [ ] **2.3 — Corriger la typo monospace (étape 2)**

  Dans le tableau des colonnes (étape 2), les champs **nom technique** et **description** passent de `font-size:12px` à `font-size:11px` :

  ```html
  {{-- AVANT (nom technique colonne) --}}
  style="width:100%;min-width:100px;padding:5px 8px;
         font-size:11px;font-family:monospace;"

  {{-- APRÈS — déjà 11px, RAS --}}
  ```

  Le champ **description** (actuellement `class="pd-input"` sans font-size explicite) :
  ```html
  {{-- AVANT --}}
  <input type="text" wire:model="tableDescription"
         class="pd-input" placeholder="Description courte de cette grille"
         style="width:100%;">

  {{-- APRÈS --}}
  <input type="text" wire:model="tableDescription"
         class="pd-input" placeholder="Description courte de cette grille"
         style="width:100%;font-size:11px;color:var(--pd-muted);">
  ```

  Le champ **nom technique de la grille** (actuellement `font-size:12px`) :
  ```html
  {{-- AVANT --}}
  style="width:100%;font-family:monospace;font-size:12px;"

  {{-- APRÈS --}}
  style="width:100%;font-family:monospace;font-size:11px;"
  ```

- [ ] **2.4 — Responsive : media query inline**

  Juste avant `</div>{{-- /wrapper --}}` (tout en bas), ajouter :

  ```html
  <style>
  @media (max-width: 800px) {
      .dg-import-flex { flex-direction: column !important; }
      .dg-import-sidebar { width: 100% !important; position: static !important; }
  }
  </style>
  ```

  Et ajouter `class="dg-import-flex"` au div flex et `class="dg-import-sidebar"` au div sidebar.

- [ ] **2.5 — Vérifier visuellement**

  Accéder à `/datagrid/import` (tenant) en étant connecté. Vérifier :
  - Sidebar visible à gauche avec les grilles
  - Wizard à droite, stepper fonctionnel
  - Aucune erreur JS console

- [ ] **2.6 — Commit**

  ```bash
  git add resources/views/livewire/tenant/datagrid/import-wizard.blade.php
  git commit -m "feat: layout deux colonnes wizard tenant — sidebar grilles + typo"
  ```

---

## Task 3 — Super admin : layout deux colonnes

**Fichiers :**
- Modifier : `resources/views/super-admin/datagrids/import.blade.php`

Le fichier actuel a : header full-width → grilles table → wizard Livewire.
On garde le header full-width et on restructure en flex sous lui.

- [ ] **3.1 — Restructurer en deux colonnes**

  Remplacer tout le contenu après le bloc header (après `</div>` fermant le div `margin-bottom:28px`) jusqu'à `@endsection` par :

  ```html
  {{-- ── Layout deux colonnes ───────────────────────────────────────── --}}
  <div class="dg-import-flex" style="display:flex;gap:24px;align-items:flex-start;">

      {{-- Sidebar gauche — grilles existantes --}}
      <div class="dg-import-sidebar" style="width:280px;flex-shrink:0;position:sticky;top:24px;">
          <div style="background:var(--pd-surface);border:0.5px solid var(--pd-border);
                      border-radius:12px;overflow:hidden;">
              <div style="padding:11px 16px;border-bottom:0.5px solid var(--pd-border);
                          display:flex;align-items:center;justify-content:space-between;">
                  <span style="font-size:11px;font-weight:600;color:var(--pd-muted);
                               text-transform:uppercase;letter-spacing:.05em;">
                      Grilles existantes
                  </span>
                  <span style="font-size:11px;background:var(--pd-bg2);color:var(--pd-muted);
                               padding:1px 8px;border-radius:10px;">
                      {{ $grids->count() }}
                  </span>
              </div>
              <div style="max-height:62vh;overflow-y:auto;">
                  @forelse($grids as $grid)
                  <div style="padding:9px 16px;border-bottom:0.5px solid var(--pd-border);">
                      <div style="font-size:12px;font-weight:600;color:var(--pd-text);
                                   white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                          {{ $grid->label }}
                      </div>
                      <div style="font-size:10px;color:var(--pd-muted);font-family:monospace;
                                   margin-top:2px;">
                          {{ $grid->name }}
                      </div>
                  </div>
                  @empty
                  <div style="padding:20px;text-align:center;font-size:12px;
                               color:var(--pd-muted);font-style:italic;">
                      Aucune grille définie
                  </div>
                  @endforelse
              </div>
          </div>
      </div>

      {{-- Zone wizard droite --}}
      <div style="flex:1;min-width:0;">
          @livewire('super-admin.datagrid.import-wizard', ['organizationId' => $org->id])
      </div>

  </div>

  <style>
  @media (max-width: 800px) {
      .dg-import-flex { flex-direction: column !important; }
      .dg-import-sidebar { width: 100% !important; position: static !important; }
  }
  </style>

  @endsection
  ```

- [ ] **3.2 — Vérifier visuellement**

  Accéder à `/super-admin/datagrids/{org}/import`. Vérifier :
  - Header full-width (fil d'Ariane + bouton Retour)
  - Sidebar visible à gauche
  - Wizard à droite

- [ ] **3.3 — Commit**

  ```bash
  git add resources/views/super-admin/datagrids/import.blade.php
  git commit -m "feat: layout deux colonnes page import super admin"
  ```

---

## Task 4 — Super admin wizard : typo monospace

**Fichiers :**
- Modifier : `resources/views/livewire/super-admin/datagrid/import-wizard.blade.php`

Les mêmes corrections que Task 2.3, appliquées à la vue super admin.

- [ ] **4.1 — Nom technique de la grille : 12px → 11px**

  Chercher :
  ```html
  style="width:100%;font-family:monospace;font-size:12px;"
  ```
  Remplacer par :
  ```html
  style="width:100%;font-family:monospace;font-size:11px;"
  ```

- [ ] **4.2 — Description : ajouter 11px + couleur muted**

  Chercher le champ `tableDescription` dans cette vue et ajouter `font-size:11px;color:var(--pd-muted);` à son `style`.

- [ ] **4.3 — Noms techniques colonnes : vérifier 11px**

  Le champ `columns.{{ $i }}.name` dans le tableau doit avoir `font-size:11px`. Vérifier et corriger si nécessaire.

- [ ] **4.4 — Pint**

  ```bash
  ./vendor/bin/pint resources/  # pint ne touche pas les blade, mais vérifier PHP si modifié
  ```

- [ ] **4.5 — Commit**

  ```bash
  git add resources/views/livewire/super-admin/datagrid/import-wizard.blade.php
  git commit -m "style: typo monospace 11px wizard super admin"
  ```

---

## Task 5 — Validation finale

- [ ] **5.1 — Tests complets**

  ```bash
  TELESCOPE_ENABLED=false php artisan test
  ```

  Attendu : 807 passed (ou plus), 0 failed.

- [ ] **5.2 — PHPStan**

  ```bash
  ./vendor/bin/phpstan analyse app/Livewire/Tenant/Datagrid/ImportWizard.php --level=5
  ```

  Attendu : No errors.

- [ ] **5.3 — Test fonctionnel**

  - Connexion tenant → `/datagrid/import` → importer un fichier → vérifier les 3 étapes
  - Connexion super admin → `/super-admin/datagrids/{org}/import` → même vérification
  - Réduire la fenêtre à < 800 px → vérifier l'empilement vertical

---

## Self-Review

**Spec coverage :**
- ✅ Sidebar 280px fixe, sticky — Task 2.1/2.2, Task 3.1
- ✅ Liste grilles : label + nom technique + badges — Task 2.2, Task 3.1
- ✅ État vide — Task 2.2, Task 3.1
- ✅ Scroll indépendant max-height 62vh — Task 2.2, Task 3.1
- ✅ Responsive ≤ 800px empilé — Task 2.4, Task 3.1
- ✅ Typo monospace/description 11px — Task 2.3, Task 4.1-4.3
- ✅ Tenant : `$existingGrids` via `withCount('columns')` — Task 1
- ✅ Super admin : réutilise `$grids` du contrôleur — Task 3.1

**Placeholders :** Aucun.

**Cohérence types :** `$existingGrids` est `array<int, array{label, name, columns_count}>` dans le PHP et accédé via `$grid['label']` dans Blade. `$grids` super admin est une Collection de stdClass, accédé via `$grid->label`.
