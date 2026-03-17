# Installation — Module Gestion de Projet Pladigit

## Extraction

```bash
cd /var/www/pladigit
tar -xzf pladigit_module_projects_v2.tar.gz
```

L'archive dépose les fichiers directement dans l'arborescence Laravel existante.
Aucun dossier intermédiaire — extraction à la racine du projet.

## Ordre d'intégration

### 1. Migrations
```bash
php artisan migrate:tenants --force
```

### 2. Routes
Dans routes/web.php, dans le groupe middleware(['auth', 'force-pwd-change']),
ajouter avant la fermeture du groupe :

    require base_path('routes/projects.php');

### 3. Policies — AppServiceProvider
Dans app/Providers/AppServiceProvider.php, méthode boot() :

    Gate::policy(\App\Models\Tenant\Project::class, \App\Policies\ProjectPolicy::class);
    Gate::policy(\App\Models\Tenant\Task::class,    \App\Policies\TaskPolicy::class);

### 4. ModuleKey — activer PROJECTS
Dans app/Enums/ModuleKey.php :

    // Remplacer :
    return $this->phase() <= 3;
    // Par :
    return $this->phase() <= 5;

### 5. Sidebar — layouts/app.blade.php
Après le lien Photothèque, ajouter :

    @if($tenant?->hasModule(\App\Enums\ModuleKey::PROJECTS))
    <a href="{{ route('projects.index') }}"
       class="pd-nav-item {{ str_starts_with($route, 'projects.') ? 'active' : '' }}">
        <svg class="pd-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"
             stroke-width="1.8" stroke-linecap="round">
            <rect x="3" y="3" width="7" height="7"/>
            <rect x="14" y="3" width="7" height="7"/>
            <rect x="14" y="14" width="7" height="7"/>
            <rect x="3" y="14" width="7" height="7"/>
        </svg>
        <span class="pd-nav-label">Projets</span>
        <span class="pd-nav-tip">Projets</span>
    </a>
    @endif

### 6. TestCase — cleanDatabase()
Dans tests/TestCase.php, ajouter dans la liste des tables :

    'task_dependencies', 'task_comments', 'tasks',
    'project_milestones', 'project_members', 'projects',
    'event_participants', 'events',

### 7. Seeder (optionnel — charge le projet Pladigit lui-même)
```bash
php artisan db:seed --class=PladigitProjectSeeder
```

### 8. Activer le module dans le Super Admin
/super-admin → Organisation → onglet Modules → cocher Gestion de projet → Enregistrer

## Permissions après extraction (en root)

```bash
chown -R deploy:deploy /var/www/pladigit
chmod -R 755 /var/www/pladigit
chmod -R 775 /var/www/pladigit/storage
chmod -R 775 /var/www/pladigit/bootstrap/cache
```

## Vider les caches Laravel
```bash
sudo -u deploy php artisan config:clear
sudo -u deploy php artisan route:clear
sudo -u deploy php artisan view:clear
sudo -u deploy php artisan cache:clear
```

## Lancer les tests
```bash
sudo -u deploy php artisan test tests/Feature/Projects tests/Unit/Enums/ProjectRoleTest.php tests/Unit/Models/ProjectModelTest.php
```
