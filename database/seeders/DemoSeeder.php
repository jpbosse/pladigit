<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Tenant\Department;
use App\Models\Tenant\MediaAlbum;
use App\Models\Tenant\Project;
use App\Models\Tenant\ProjectMember;
use App\Models\Tenant\Share;
use App\Models\Tenant\Task;
use App\Models\Tenant\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeder de démonstration — base tenant demo.
 *
 * Crée :
 *   - 6 utilisateurs (un par rôle) — mot de passe : demo1234
 *   - 3 directions + 2 services
 *   - 3 projets avec tâches et membres
 *   - 3 albums photo avec visibilités variées
 *
 * Les documents GED et photos sont injectés par DemoResetCommand
 * depuis storage/demo_ged/ et storage/demo_photos/.
 *
 * Usage direct : php artisan db:seed --class=DemoSeeder --database=tenant
 * Usage recommandé : php artisan demo:reset
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('  Seeding demo tenant...');

        $users = $this->createUsers();
        $this->command->info('  ✓ Utilisateurs');

        $depts = $this->createDepartments($users);
        $this->command->info('  ✓ Départements');

        $this->createProjects($users, $depts);
        $this->command->info('  ✓ Projets & tâches');

        $this->createAlbums($users, $depts);
        $this->command->info('  ✓ Albums photo');

        $this->command->info('');
        $this->command->info('  Comptes de démo (mot de passe : demo1234)');
        $this->command->info('  ─────────────────────────────────────────');
        foreach ($users as $user) {
            $this->command->info(sprintf('  %-35s %s', $user->email, $user->role));
        }
    }

    // ─────────────────────────────────────────────────────────────
    //  Utilisateurs
    // ─────────────────────────────────────────────────────────────

    private function createUsers(): array
    {
        $password = Hash::make('demo1234');

        $definitions = [
            ['role' => UserRole::ADMIN->value,          'name' => 'Admin Démo',         'email' => 'admin@demo.pladigit.fr'],
            ['role' => UserRole::PRESIDENT->value,      'name' => 'Marie Dupont',        'email' => 'president@demo.pladigit.fr'],
            ['role' => UserRole::DGS->value,            'name' => 'Jean-Pierre Martin',  'email' => 'dgs@demo.pladigit.fr'],
            ['role' => UserRole::RESP_DIRECTION->value, 'name' => 'Sophie Lambert',      'email' => 'resp.direction@demo.pladigit.fr'],
            ['role' => UserRole::RESP_SERVICE->value,   'name' => 'Thomas Bernard',      'email' => 'resp.service@demo.pladigit.fr'],
            ['role' => UserRole::USER->value,           'name' => 'Lucie Moreau',        'email' => 'user@demo.pladigit.fr'],
        ];

        $users = [];
        foreach ($definitions as $def) {
            $users[$def['role']] = User::updateOrCreate(
                ['email' => $def['email']],
                [
                    'name'               => $def['name'],
                    'password_hash'      => $password,
                    'role'               => $def['role'],
                    'status'             => 'active',
                    'force_pwd_change'   => false,
                    'totp_enabled'       => false,
                    'password_changed_at' => now(),
                ]
            );
        }

        return $users;
    }

    // ─────────────────────────────────────────────────────────────
    //  Départements
    // ─────────────────────────────────────────────────────────────

    private function createDepartments(array $users): array
    {
        $adminId = $users[UserRole::ADMIN->value]->id;

        $dst = Department::updateOrCreate(
            ['name' => 'Direction des Services Techniques'],
            ['type' => 'direction', 'label' => 'direction', 'color' => '#1e40af', 'sort_order' => 1, 'created_by' => $adminId]
        );

        $rh = Department::updateOrCreate(
            ['name' => 'Direction des Ressources Humaines'],
            ['type' => 'direction', 'label' => 'direction', 'color' => '#7c3aed', 'sort_order' => 2, 'created_by' => $adminId]
        );

        $sg = Department::updateOrCreate(
            ['name' => 'Secrétariat Général'],
            ['type' => 'direction', 'label' => 'direction', 'color' => '#065f46', 'sort_order' => 3, 'created_by' => $adminId]
        );

        $voirie = Department::updateOrCreate(
            ['name' => 'Service Voirie'],
            ['type' => 'service', 'label' => 'service', 'color' => '#0369a1', 'sort_order' => 1, 'parent_id' => $dst->id, 'created_by' => $adminId]
        );

        $paie = Department::updateOrCreate(
            ['name' => 'Service Paie'],
            ['type' => 'service', 'label' => 'service', 'color' => '#6d28d9', 'sort_order' => 1, 'parent_id' => $rh->id, 'created_by' => $adminId]
        );

        $dst->members()->syncWithoutDetaching([
            $users[UserRole::RESP_DIRECTION->value]->id => ['is_manager' => true],
            $users[UserRole::RESP_SERVICE->value]->id   => ['is_manager' => false],
            $users[UserRole::USER->value]->id            => ['is_manager' => false],
        ]);
        $voirie->members()->syncWithoutDetaching([
            $users[UserRole::RESP_SERVICE->value]->id => ['is_manager' => true],
            $users[UserRole::USER->value]->id          => ['is_manager' => false],
        ]);
        $rh->members()->syncWithoutDetaching([
            $users[UserRole::RESP_DIRECTION->value]->id => ['is_manager' => true],
        ]);

        return compact('dst', 'rh', 'sg', 'voirie', 'paie');
    }

    // ─────────────────────────────────────────────────────────────
    //  Projets & tâches
    // ─────────────────────────────────────────────────────────────

    private function createProjects(array $users, array $depts): void
    {
        $admin   = $users[UserRole::ADMIN->value];
        $dgs     = $users[UserRole::DGS->value];
        $respDir = $users[UserRole::RESP_DIRECTION->value];
        $respSvc = $users[UserRole::RESP_SERVICE->value];
        $agent   = $users[UserRole::USER->value];

        // ── Projet 1 : PLU ───────────────────────────────────────
        $plu = Project::create([
            'name'        => 'Révision du Plan Local d\'Urbanisme (PLU)',
            'description' => 'Mise à jour du PLU communal — prise en compte des évolutions réglementaires et du projet de territoire 2025-2030.',
            'status'      => 'active',
            'start_date'  => now()->subMonths(3),
            'due_date'    => now()->addMonths(9),
            'color'       => '#1e40af',
            'is_private'  => false,
            'created_by'  => $dgs->id,
        ]);

        ProjectMember::insert([
            ['project_id' => $plu->id, 'user_id' => $dgs->id,     'role' => 'manager'],
            ['project_id' => $plu->id, 'user_id' => $respDir->id,  'role' => 'member'],
            ['project_id' => $plu->id, 'user_id' => $agent->id,    'role' => 'viewer'],
        ]);

        $pluTasks = [
            ['title' => 'Diagnostic territorial', 'status' => 'done',        'priority' => 'high',   'assigned_to' => $respDir->id, 'due_date' => now()->subMonths(2)],
            ['title' => 'Concertation publique — Phase 1', 'status' => 'done', 'priority' => 'high', 'assigned_to' => $dgs->id,     'due_date' => now()->subMonth()],
            ['title' => 'Rédaction du PADD', 'status' => 'in_progress',      'priority' => 'high',   'assigned_to' => $respDir->id, 'due_date' => now()->addMonths(2)],
            ['title' => 'OAP — Secteurs à enjeux', 'status' => 'todo',       'priority' => 'medium', 'assigned_to' => $respDir->id, 'due_date' => now()->addMonths(4)],
            ['title' => 'Rapport de présentation', 'status' => 'todo',       'priority' => 'medium', 'assigned_to' => $agent->id,   'due_date' => now()->addMonths(6)],
            ['title' => 'Enquête publique', 'status' => 'todo',              'priority' => 'high',   'assigned_to' => $dgs->id,     'due_date' => now()->addMonths(8)],
        ];

        foreach ($pluTasks as $i => $t) {
            Task::create(array_merge($t, [
                'project_id'  => $plu->id,
                'created_by'  => $dgs->id,
                'sort_order'  => $i + 1,
            ]));
        }

        // ── Projet 2 : Rénovation salle des fêtes ────────────────
        $sdf = Project::create([
            'name'        => 'Rénovation salle des fêtes — Tranche 2',
            'description' => 'Mise aux normes accessibilité, remplacement du système électrique et réfection de la toiture.',
            'status'      => 'active',
            'start_date'  => now()->subMonth(),
            'due_date'    => now()->addMonths(5),
            'color'       => '#b45309',
            'is_private'  => false,
            'created_by'  => $respDir->id,
        ]);

        ProjectMember::insert([
            ['project_id' => $sdf->id, 'user_id' => $respDir->id, 'role' => 'manager'],
            ['project_id' => $sdf->id, 'user_id' => $respSvc->id, 'role' => 'member'],
            ['project_id' => $sdf->id, 'user_id' => $agent->id,   'role' => 'member'],
        ]);

        $sdfTasks = [
            ['title' => 'Appel d\'offres — Électricité', 'status' => 'done',        'priority' => 'high',   'assigned_to' => $respSvc->id, 'due_date' => now()->subWeeks(3)],
            ['title' => 'Travaux électriques', 'status' => 'in_progress',           'priority' => 'high',   'assigned_to' => $respSvc->id, 'due_date' => now()->addWeeks(3)],
            ['title' => 'Mise en accessibilité PMR', 'status' => 'in_progress',     'priority' => 'high',   'assigned_to' => $agent->id,   'due_date' => now()->addMonth()],
            ['title' => 'Appel d\'offres — Toiture', 'status' => 'todo',            'priority' => 'medium', 'assigned_to' => $respSvc->id, 'due_date' => now()->addMonths(2)],
            ['title' => 'Réfection de la toiture', 'status' => 'todo',              'priority' => 'medium', 'assigned_to' => null,          'due_date' => now()->addMonths(4)],
            ['title' => 'Réception des travaux', 'status' => 'todo',                'priority' => 'low',    'assigned_to' => $respDir->id,  'due_date' => now()->addMonths(5)],
        ];

        foreach ($sdfTasks as $i => $t) {
            Task::create(array_merge($t, [
                'project_id' => $sdf->id,
                'created_by' => $respDir->id,
                'sort_order' => $i + 1,
            ]));
        }

        // ── Projet 3 : RGPD ──────────────────────────────────────
        $rgpd = Project::create([
            'name'        => 'Mise en conformité RGPD 2025',
            'description' => 'Actualisation du registre des traitements, mise à jour des mentions légales et formation des agents.',
            'status'      => 'completed',
            'start_date'  => now()->subMonths(6),
            'due_date'    => now()->subMonth(),
            'color'       => '#065f46',
            'is_private'  => false,
            'created_by'  => $admin->id,
        ]);

        ProjectMember::insert([
            ['project_id' => $rgpd->id, 'user_id' => $admin->id,   'role' => 'manager'],
            ['project_id' => $rgpd->id, 'user_id' => $dgs->id,     'role' => 'member'],
            ['project_id' => $rgpd->id, 'user_id' => $respDir->id,  'role' => 'viewer'],
        ]);

        $rgpdTasks = [
            ['title' => 'Inventaire des traitements de données', 'status' => 'done', 'priority' => 'high',   'assigned_to' => $admin->id,   'due_date' => now()->subMonths(5)],
            ['title' => 'Mise à jour du registre RGPD', 'status' => 'done',          'priority' => 'high',   'assigned_to' => $admin->id,   'due_date' => now()->subMonths(4)],
            ['title' => 'Rédaction des mentions légales', 'status' => 'done',        'priority' => 'medium', 'assigned_to' => $respDir->id,  'due_date' => now()->subMonths(3)],
            ['title' => 'Formation agents — Sensibilisation', 'status' => 'done',    'priority' => 'medium', 'assigned_to' => $dgs->id,     'due_date' => now()->subMonths(2)],
            ['title' => 'Audit interne final', 'status' => 'done',                   'priority' => 'high',   'assigned_to' => $admin->id,   'due_date' => now()->subMonth()],
        ];

        foreach ($rgpdTasks as $i => $t) {
            Task::create(array_merge($t, [
                'project_id' => $rgpd->id,
                'created_by' => $admin->id,
                'sort_order' => $i + 1,
            ]));
        }
    }

    // ─────────────────────────────────────────────────────────────
    //  Albums photo
    // ─────────────────────────────────────────────────────────────

    private function createAlbums(array $users, array $depts): void
    {
        $admin   = $users[UserRole::ADMIN->value];
        $respDir = $users[UserRole::RESP_DIRECTION->value];

        $albumPublic = MediaAlbum::updateOrCreate(
            ['name' => 'Fête de la commune 2025'],
            [
                'description' => 'Photos de la fête annuelle de la commune — juin 2025.',
                'visibility'  => 'public',
                'created_by'  => $admin->id,
            ]
        );

        $albumVoirie = MediaAlbum::updateOrCreate(
            ['name' => 'Travaux voirie 2025'],
            [
                'description' => 'Suivi photographique des chantiers de voirie en cours.',
                'visibility'  => 'restricted',
                'created_by'  => $respDir->id,
            ]
        );

        foreach ([UserRole::RESP_SERVICE->value, UserRole::USER->value] as $role) {
            Share::updateOrCreate(
                [
                    'shareable_type'   => 'media_album',
                    'shareable_id'     => $albumVoirie->id,
                    'shared_with_type' => 'role',
                    'shared_with_role' => $role,
                ],
                [
                    'can_view'     => true,
                    'can_download' => $role === UserRole::RESP_SERVICE->value,
                    'can_edit'     => false,
                    'can_manage'   => false,
                    'shared_by'    => $admin->id,
                ]
            );
        }

        MediaAlbum::updateOrCreate(
            ['name' => 'Conseil municipal — Archives'],
            [
                'description' => 'Photos des séances du conseil municipal.',
                'visibility'  => 'private',
                'created_by'  => $users[UserRole::PRESIDENT->value]->id,
            ]
        );
    }
}
