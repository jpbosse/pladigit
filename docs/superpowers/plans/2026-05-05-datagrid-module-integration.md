# DataGrid — Intégration module (superadmin + sidebar + dashboard)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rendre le module DataGrid sélectionnable par le superadmin, visible dans le dashboard tenant (tuile couleur primaire organisation) et accessible depuis la sidebar.

**Architecture:** 4 couches touchées — l'enum `ModuleKey` expose DataGrid, un contrôleur tenant liste les grilles, le layout app.blade et le dashboard blade s'adaptent au module activé. Aucune migration requise.

**Tech Stack:** Laravel 11, Blade, Feather-style SVG inline, CSS custom properties (`var(--pd-navy)`), middleware `module:datagrid`, PHPUnit.

---

## Fichiers touchés

| Rôle | Fichier | Action |
|---|---|---|
| Enum modules | `app/Enums/ModuleKey.php` | Modifier `isAvailable()` |
| Routes tenant | `routes/tenant.php` | Ajouter `datagrid.index` |
| Contrôleur | `app/Http/Controllers/Datagrid/DatagridController.php` | Créer |
| Vue index | `resources/views/datagrid/index.blade.php` | Créer |
| Sidebar | `resources/views/layouts/app.blade.php` | Ajouter nav item |
| Dashboard | `resources/views/dashboard.blade.php` | Ajouter tuile |
| Tests accès | `tests/Feature/ModuleAccessTest.php` | Ajouter 2 tests |
| Tests dashboard | `tests/Feature/DashboardTest.php` | Ajouter 1 test |

---

## Task 1 : Activer DataGrid dans `ModuleKey::isAvailable()`

**Files:**
- Modify: `app/Enums/ModuleKey.php`
- Test: `tests/Feature/ModuleAccessTest.php`

- [ ] **Étape 1 : Écrire le test qui échoue**

Dans `tests/Feature/ModuleAccessTest.php`, ajouter après le dernier test de la classe :

```php
public function test_datagrid_est_dans_la_liste_available(): void
{
    $available = array_map(fn (ModuleKey $m) => $m->value, ModuleKey::available());

    $this->assertContains('datagrid', $available);
}
```

- [ ] **Étape 2 : Vérifier que le test échoue**

```bash
php artisan test tests/Feature/ModuleAccessTest.php --filter=test_datagrid_est_dans_la_liste_available
```

Attendu : `FAIL` — `datagrid` absent de `available()`.

- [ ] **Étape 3 : Modifier `isAvailable()` dans `ModuleKey`**

Dans `app/Enums/ModuleKey.php`, remplacer :

```php
public function isAvailable(): bool
{
    return in_array($this, [self::MEDIA, self::PROJECTS, self::GED], true);
}
```

par :

```php
public function isAvailable(): bool
{
    return in_array($this, [self::MEDIA, self::PROJECTS, self::GED, self::DATAGRID], true);
}
```

- [ ] **Étape 4 : Vérifier que le test passe**

```bash
php artisan test tests/Feature/ModuleAccessTest.php --filter=test_datagrid_est_dans_la_liste_available
```

Attendu : `PASS`.

- [ ] **Étape 5 : Vérifier qu'aucun test existant n'est cassé**

```bash
php artisan test tests/Feature/ModuleAccessTest.php
```

Attendu : tous verts.

- [ ] **Étape 6 : Commit**

```bash
git add app/Enums/ModuleKey.php tests/Feature/ModuleAccessTest.php
git commit -m "feat: activer ModuleKey::DATAGRID dans isAvailable()"
```

---

## Task 2 : Contrôleur + route tenant `datagrid.index`

**Files:**
- Create: `app/Http/Controllers/Datagrid/DatagridController.php`
- Modify: `routes/tenant.php`
- Test: `tests/Feature/ModuleAccessTest.php`

- [ ] **Étape 1 : Écrire les deux tests qui échouent**

Dans `tests/Feature/ModuleAccessTest.php`, ajouter :

```php
public function test_accès_datagrid_accordé_si_module_datagrid_activé(): void
{
    $this->persistCurrentOrg(['enabled_modules' => ['datagrid']]);

    $this->actingAs($this->admin, 'tenant')
        ->get(route('datagrid.index'))
        ->assertOk();
}

public function test_accès_datagrid_refusé_si_module_datagrid_désactivé(): void
{
    $this->persistCurrentOrg(['enabled_modules' => []]);

    $this->actingAs($this->admin, 'tenant')
        ->get(route('datagrid.index'))
        ->assertForbidden();
}
```

- [ ] **Étape 2 : Vérifier que les tests échouent**

```bash
php artisan test tests/Feature/ModuleAccessTest.php --filter=datagrid
```

Attendu : `FAIL` — route `datagrid.index` inexistante.

- [ ] **Étape 3 : Créer le contrôleur**

Créer `app/Http/Controllers/Datagrid/DatagridController.php` :

```php
<?php

namespace App\Http\Controllers\Datagrid;

use App\Http\Controllers\Controller;
use App\Models\Tenant\DatagridTable;
use Illuminate\View\View;

class DatagridController extends Controller
{
    public function index(): View
    {
        $tables = DatagridTable::withCount('columns')
            ->orderBy('label')
            ->get();

        return view('datagrid.index', compact('tables'));
    }
}
```

- [ ] **Étape 4 : Ajouter la route dans `routes/tenant.php`**

Remplacer le contenu de `routes/tenant.php` par :

```php
<?php

use App\Http\Controllers\Datagrid\DatagridController;
use App\Livewire\Tenant\Datagrid\ImportWizard;

// ── DataGrid — ERP no-code (Phase 8) ───────────────────────────────────────
Route::prefix('datagrid')->name('datagrid.')->middleware('module:datagrid')->group(function () {

    Route::get('/', [DatagridController::class, 'index'])
        ->name('index');

    Route::get('import', ImportWizard::class)
        ->name('import');

});
```

- [ ] **Étape 5 : Créer la vue index minimale**

Créer `resources/views/datagrid/index.blade.php` :

```blade
@extends('layouts.app')
@section('title', 'DataGrid')

@section('content')
<div style="padding:32px 40px;">

    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;">
        <div>
            <h1 style="font-size:22px;font-weight:700;color:var(--pd-text);margin:0 0 4px;">Grilles DataGrid</h1>
            <p style="font-size:13px;color:var(--pd-muted);margin:0;">
                {{ $tables->count() }} grille{{ $tables->count() !== 1 ? 's' : '' }} disponible{{ $tables->count() !== 1 ? 's' : '' }}
            </p>
        </div>
        @if(session('super_admin_logged_in'))
        <a href="{{ route('datagrid.import') }}"
           style="padding:9px 18px;background:var(--pd-navy);color:#fff;border-radius:9px;
                  font-size:13px;font-weight:600;text-decoration:none;">
            + Importer une grille
        </a>
        @endif
    </div>

    @if($tables->isEmpty())
    <div style="text-align:center;padding:64px 24px;color:var(--pd-muted);">
        <div style="font-size:48px;margin-bottom:16px;">📇</div>
        <p style="font-size:15px;font-weight:600;color:var(--pd-text);margin:0 0 8px;">Aucune grille configurée</p>
        <p style="font-size:13px;margin:0;">Demandez à votre administrateur d'importer un fichier Excel.</p>
    </div>
    @else
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;">
        @foreach($tables as $table)
        <div style="background:var(--pd-bg);border:1px solid var(--pd-border);border-radius:12px;padding:20px;">
            <div style="font-size:15px;font-weight:600;color:var(--pd-text);margin-bottom:4px;">
                {{ $table->label }}
            </div>
            @if($table->description)
            <div style="font-size:12px;color:var(--pd-muted);margin-bottom:12px;">{{ $table->description }}</div>
            @endif
            <div style="font-size:11px;color:var(--pd-muted);">
                {{ $table->columns_count }} colonne{{ $table->columns_count !== 1 ? 's' : '' }}
            </div>
        </div>
        @endforeach
    </div>
    @endif

</div>
@endsection
```

- [ ] **Étape 6 : Vérifier que les tests passent**

```bash
php artisan test tests/Feature/ModuleAccessTest.php --filter=datagrid
```

Attendu : `PASS` (les deux tests).

- [ ] **Étape 7 : Vérifier toute la suite**

```bash
php artisan test tests/Feature/ModuleAccessTest.php
```

Attendu : tous verts.

- [ ] **Étape 8 : Commit**

```bash
git add app/Http/Controllers/Datagrid/DatagridController.php \
        routes/tenant.php \
        resources/views/datagrid/index.blade.php \
        tests/Feature/ModuleAccessTest.php
git commit -m "feat: route et contrôleur datagrid.index avec gating module:datagrid"
```

---

## Task 3 : Tuile DataGrid dans le dashboard

**Files:**
- Modify: `resources/views/dashboard.blade.php`
- Test: `tests/Feature/DashboardTest.php`

- [ ] **Étape 1 : Écrire le test qui échoue**

Dans `tests/Feature/DashboardTest.php`, ajouter un test (après les tests de tuiles existants) :

```php
public function test_dashboard_affiche_tuile_datagrid_si_module_activé(): void
{
    $org = app(\App\Services\TenantManager::class)->current();
    $org->enableModule(\App\Enums\ModuleKey::DATAGRID);
    $org->save();

    $this->actingAs($this->admin ?? \App\Models\Tenant\User::factory()->create(['role' => 'admin', 'status' => 'active']))
        ->get(route('dashboard'))
        ->assertSee('DataGrid');
}
```

> **Note :** Si `DashboardTest` n'expose pas `$this->admin`, utiliser `User::factory()->create(...)` directement comme ci-dessus. Adapter à la structure setUp existante du fichier.

- [ ] **Étape 2 : Vérifier que le test échoue**

```bash
php artisan test tests/Feature/DashboardTest.php --filter=test_dashboard_affiche_tuile_datagrid
```

Attendu : `FAIL` — "DataGrid" absent du HTML.

- [ ] **Étape 3 : Ajouter la tuile dans `dashboard.blade.php`**

Trouver le bloc `$activeModules` (ligne ~155). Après la tuile PROJECTS, ajouter :

```php
if ($org?->hasModule(\App\Enums\ModuleKey::DATAGRID)) {
    $activeModules[] = [
        'icon'  => '📇',
        'name'  => 'DataGrid',
        'desc'  => 'Annuaire, mandats, protocole, RGPD intégré',
        'color' => 'var(--pd-navy)',
        'bg'    => 'color-mix(in srgb, var(--pd-navy) 12%, transparent)',
        'route' => 'datagrid.index',
    ];
}
```

Le bloc complet devient :

```php
$activeModules = [];
if ($org?->hasModule(\App\Enums\ModuleKey::MEDIA)) {
    $activeModules[] = ['icon'=>'📷','name'=>'Photothèque','desc'=>'Albums, médias NAS, watermark, partage','color'=>'#2ECC71','bg'=>'rgba(46,204,113,0.1)','route'=>'media.albums.index'];
}
if ($org?->hasModule(\App\Enums\ModuleKey::PROJECTS)) {
    $activeModules[] = ['icon'=>'📋','name'=>'Projets','desc'=>'Kanban, Gantt, tâches, agenda partagé','color'=>'#3B82F6','bg'=>'rgba(59,130,246,0.1)','route'=>'projects.index'];
}
if ($org?->hasModule(\App\Enums\ModuleKey::DATAGRID)) {
    $activeModules[] = [
        'icon'  => '📇',
        'name'  => 'DataGrid',
        'desc'  => 'Annuaire, mandats, protocole, RGPD intégré',
        'color' => 'var(--pd-navy)',
        'bg'    => 'color-mix(in srgb, var(--pd-navy) 12%, transparent)',
        'route' => 'datagrid.index',
    ];
}
```

- [ ] **Étape 4 : Vérifier que le test passe**

```bash
php artisan test tests/Feature/DashboardTest.php --filter=test_dashboard_affiche_tuile_datagrid
```

Attendu : `PASS`.

- [ ] **Étape 5 : Vérifier toute la suite dashboard**

```bash
php artisan test tests/Feature/DashboardTest.php
```

Attendu : tous verts.

- [ ] **Étape 6 : Commit**

```bash
git add resources/views/dashboard.blade.php tests/Feature/DashboardTest.php
git commit -m "feat: tuile DataGrid dans le dashboard — couleur primaire organisation"
```

---

## Task 4 : Nav item DataGrid dans la sidebar

**Files:**
- Modify: `resources/views/layouts/app.blade.php`

Pas de test dédié — la visibilité conditionnelle est couverte par le gating du contrôleur (Task 2).

- [ ] **Étape 1 : Repérer la position GED dans la sidebar**

Dans `resources/views/layouts/app.blade.php`, chercher le bloc GED (autour de la ligne 183) :

```blade
@if(app(\App\Services\TenantManager::class)->current()?->hasModule(\App\Enums\ModuleKey::GED))
<a href="{{ route('ged.index') }}" class="pd-nav-item {{ str_starts_with($route, 'ged.') ? 'active' : '' }}">
    <span class="pd-nav-icon">...</span>
    <span class="pd-nav-label">GED</span>
    <span class="pd-nav-tip">GED documentaire</span>
</a>
@endif
```

- [ ] **Étape 2 : Insérer le nav item DataGrid immédiatement après le bloc GED**

```blade
@if(app(\App\Services\TenantManager::class)->current()?->hasModule(\App\Enums\ModuleKey::DATAGRID))
        <a href="{{ route('datagrid.index') }}" class="pd-nav-item {{ str_starts_with($route, 'datagrid.') ? 'active' : '' }}">
            <span class="pd-nav-icon"><svg style="width:18px;height:18px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round;" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="3" y1="15" x2="21" y2="15"/><line x1="9" y1="3" x2="9" y2="21"/></svg></span>
            <span class="pd-nav-label">DataGrid</span>
            <span class="pd-nav-tip">Annuaire DataGrid</span>
        </a>
@endif
```

- [ ] **Étape 3 : Vérifier visuellement en navigateur**

Activer le module DataGrid sur l'organisation courante via le superadmin (`/super-admin/organizations/{id}` → onglet Modules → cocher DataGrid → Enregistrer), puis charger `/dashboard`. Vérifier :
- La tuile "DataGrid" apparaît dans la grille des modules.
- Le lien "DataGrid" apparaît dans la sidebar entre GED et Projets.
- Cliquer sur la tuile / le lien → `/datagrid` → page liste (vide ou avec des grilles).
- Désactiver le module → les deux disparaissent.

- [ ] **Étape 4 : Lancer la suite de tests complète**

```bash
php artisan test tests/Feature/ModuleAccessTest.php tests/Feature/DashboardTest.php
```

Attendu : tous verts.

- [ ] **Étape 5 : Commit**

```bash
git add resources/views/layouts/app.blade.php
git commit -m "feat: nav item DataGrid dans la sidebar — gating hasModule(DATAGRID)"
```

---

## Self-review

**Couverture spec :**
- [x] DataGrid sélectionnable dans la liste superadmin → Task 1 (`isAvailable`)
- [x] Icône dans le dashboard avec couleur primaire organisation → Task 3
- [x] Place dans la sidebar entre GED et Projets → Task 4
- [x] Route tenant `datagrid.index` gardée par `module:datagrid` → Task 2
- [x] Vue index liste les DatagridTable → Task 2 step 5

**Placeholders :** aucun TBD ni TODO.

**Cohérence des types :**
- `DatagridTable::withCount('columns')` → `$table->columns_count` dans la vue ✓
- `route('datagrid.index')` utilisé dans dashboard, sidebar, contrôleur ✓
- `ModuleKey::DATAGRID` utilisé partout ✓
