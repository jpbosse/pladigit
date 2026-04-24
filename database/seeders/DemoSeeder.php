<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Tenant\Department;
use App\Models\Tenant\MediaAlbum;
use App\Models\Tenant\Project;
use App\Models\Tenant\ProjectMember;
use App\Models\Tenant\ProjectMilestone;
use App\Models\Tenant\Task;
use App\Models\Tenant\TaskComment;
use App\Models\Tenant\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * DemoSeeder — Données de démonstration pour le tenant "demo".
 *
 * Commune fictive : Saint-Aubin-les-Communes (2 800 hab.)
 *
 * CE QUI EST CRÉÉ ICI :
 *   - 8 utilisateurs couvrant tous les rôles (mot de passe : demo1234)
 *   - Structure organisationnelle : 3 directions, 6 services
 *   - 3 projets municipaux avec jalons, tâches et commentaires
 *   - 4 albums photo (structure uniquement, sans MediaItem)
 *
 * CE QUI N'EST PAS CRÉÉ ICI (géré par DemoResetCommand) :
 *   - Documents GED  → seedGedFiles()  lit storage/demo_ged/
 *   - Photos         → seedPhotos()    lit storage/demo_photos/
 *
 * Usage direct : php artisan db:seed --class=DemoSeeder
 * Usage recommandé : php artisan demo:reset
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('  Seeding demo tenant — Saint-Aubin-les-Communes...');

        $users = $this->createUsers();
        $this->command->info('  ✓ Utilisateurs (' . count($users) . ')');

        $depts = $this->createDepartments($users);
        $this->command->info('  ✓ Départements');

        $this->createProjects($users, $depts);
        $this->command->info('  ✓ Projets, jalons & tâches');

        $this->createAlbums($users);
        $this->command->info('  ✓ Albums photo (structure)');

        $this->command->info('');
        $this->command->info('  Comptes de démo (mot de passe : demo1234)');
        $this->command->info('  ──────────────────────────────────────────────────');
        foreach ($users as $user) {
            $this->command->info(sprintf('  %-42s %s', $user->email, $user->role));
        }
    }

    // ─────────────────────────────────────────────────────────────
    //  Utilisateurs
    // ─────────────────────────────────────────────────────────────

    private function createUsers(): array
    {
        $password = Hash::make('demo1234');

        $definitions = [
            ['role' => UserRole::ADMIN->value,          'name' => 'Isabelle Fontaine',   'email' => 'admin@demo.pladigit.fr'],
            ['role' => UserRole::PRESIDENT->value,      'name' => 'Jean-Marie Lebreton', 'email' => 'maire@demo.pladigit.fr'],
            ['role' => UserRole::DGS->value,            'name' => 'Sophie Marchand',     'email' => 'dgs@demo.pladigit.fr'],
            ['role' => UserRole::RESP_DIRECTION->value, 'name' => 'Thomas Girard',       'email' => 'technique@demo.pladigit.fr'],
            ['role' => UserRole::RESP_SERVICE->value,   'name' => 'Laurent Dubois',      'email' => 'urbanisme@demo.pladigit.fr'],
            ['role' => UserRole::RESP_SERVICE->value,   'name' => 'Nathalie Petit',      'email' => 'communication@demo.pladigit.fr'],
            ['role' => UserRole::USER->value,           'name' => 'Éric Moreau',         'email' => 'agent1@demo.pladigit.fr'],
            ['role' => UserRole::USER->value,           'name' => 'Marie-Claire Aubert', 'email' => 'agent2@demo.pladigit.fr'],
        ];

        $users = [];
        foreach ($definitions as $def) {
            $user = User::on('tenant')->updateOrCreate(
                ['email' => $def['email']],
                [
                    'name'                => $def['name'],
                    'password_hash'       => $password,
                    'role'                => $def['role'],
                    'status'              => 'active',
                    'force_pwd_change'    => false,
                    'totp_enabled'        => false,
                    'password_changed_at' => now(),
                ]
            );
            // Clé = email (deux utilisateurs ont le même rôle resp_service)
            $users[$def['email']] = $user;
        }

        return $users;
    }

    // ─────────────────────────────────────────────────────────────
    //  Départements
    // ─────────────────────────────────────────────────────────────

    private function createDepartments(array $users): array
    {
        $admin    = $users['admin@demo.pladigit.fr'];
        $dgs      = $users['dgs@demo.pladigit.fr'];
        $thomas   = $users['technique@demo.pladigit.fr'];
        $laurent  = $users['urbanisme@demo.pladigit.fr'];
        $nathalie = $users['communication@demo.pladigit.fr'];
        $eric     = $users['agent1@demo.pladigit.fr'];
        $marie    = $users['agent2@demo.pladigit.fr'];

        // ── Directions ────────────────────────────────────────────
        $dst = Department::on('tenant')->updateOrCreate(
            ['name' => 'Direction des Services Techniques'],
            ['type' => 'direction', 'label' => 'DST', 'color' => '#1E3A5F',
             'sort_order' => 1, 'created_by' => $admin->id]
        );

        $dgsDir = Department::on('tenant')->updateOrCreate(
            ['name' => 'Direction Générale des Services'],
            ['type' => 'direction', 'label' => 'DGS', 'color' => '#0F766E',
             'sort_order' => 2, 'created_by' => $admin->id]
        );

        $dac = Department::on('tenant')->updateOrCreate(
            ['name' => "Direction de l'Animation et de la Culture"],
            ['type' => 'direction', 'label' => 'DAC', 'color' => '#7C3AED',
             'sort_order' => 3, 'created_by' => $admin->id]
        );

        // ── Services ──────────────────────────────────────────────
        $voirie = Department::on('tenant')->updateOrCreate(
            ['name' => 'Service Voirie et Espaces Verts'],
            ['type' => 'service', 'label' => null, 'color' => null,
             'sort_order' => 1, 'parent_id' => $dst->id, 'created_by' => $admin->id]
        );

        $urbanisme = Department::on('tenant')->updateOrCreate(
            ['name' => 'Service Urbanisme'],
            ['type' => 'service', 'label' => null, 'color' => null,
             'sort_order' => 2, 'parent_id' => $dst->id, 'created_by' => $admin->id]
        );

        $rh = Department::on('tenant')->updateOrCreate(
            ['name' => 'Service Ressources Humaines'],
            ['type' => 'service', 'label' => null, 'color' => null,
             'sort_order' => 1, 'parent_id' => $dgsDir->id, 'created_by' => $admin->id]
        );

        $communication = Department::on('tenant')->updateOrCreate(
            ['name' => 'Service Communication'],
            ['type' => 'service', 'label' => null, 'color' => null,
             'sort_order' => 2, 'parent_id' => $dgsDir->id, 'created_by' => $admin->id]
        );

        $evenementiel = Department::on('tenant')->updateOrCreate(
            ['name' => 'Service Événementiel'],
            ['type' => 'service', 'label' => null, 'color' => null,
             'sort_order' => 1, 'parent_id' => $dac->id, 'created_by' => $admin->id]
        );

        // ── Affectations ──────────────────────────────────────────
        $dst->members()->syncWithoutDetaching([
            $thomas->id => ['is_manager' => true],
        ]);
        $voirie->members()->syncWithoutDetaching([
            $eric->id => ['is_manager' => false],
        ]);
        $urbanisme->members()->syncWithoutDetaching([
            $laurent->id => ['is_manager' => true],
        ]);
        $dgsDir->members()->syncWithoutDetaching([
            $dgs->id => ['is_manager' => true],
        ]);
        $rh->members()->syncWithoutDetaching([
            $marie->id => ['is_manager' => false],
        ]);
        $communication->members()->syncWithoutDetaching([
            $nathalie->id => ['is_manager' => true],
        ]);

        return compact('dst', 'dgsDir', 'dac', 'voirie', 'urbanisme', 'rh', 'communication', 'evenementiel');
    }

    // ─────────────────────────────────────────────────────────────
    //  Projets, jalons & tâches
    // ─────────────────────────────────────────────────────────────

    private function createProjects(array $users, array $depts): void
    {
        $thomas   = $users['technique@demo.pladigit.fr'];
        $laurent  = $users['urbanisme@demo.pladigit.fr'];
        $nathalie = $users['communication@demo.pladigit.fr'];
        $dgs      = $users['dgs@demo.pladigit.fr'];
        $maire    = $users['maire@demo.pladigit.fr'];
        $eric     = $users['agent1@demo.pladigit.fr'];
        $marie    = $users['agent2@demo.pladigit.fr'];

        // ── Projet 1 : Réfection Rue des Acacias ─────────────────
        $voirie = Project::on('tenant')->create([
            'created_by'  => $thomas->id,
            'name'        => 'Réfection Rue des Acacias',
            'description' => "Travaux de réfection complète de la chaussée et des trottoirs de la rue des Acacias (450 ml). Remplacement des réseaux d'eau pluviale en sous-œuvre.",
            'status'      => 'active',
            'start_date'  => Carbon::parse('2025-03-01'),
            'due_date'    => Carbon::parse('2025-09-30'),
            'color'       => '#EA580C',
            'is_private'  => false,
        ]);

        ProjectMember::on('tenant')->insert([
            ['project_id' => $voirie->id, 'user_id' => $thomas->id, 'role' => 'owner',  'created_at' => now(), 'updated_at' => now()],
            ['project_id' => $voirie->id, 'user_id' => $eric->id,   'role' => 'member', 'created_at' => now(), 'updated_at' => now()],
            ['project_id' => $voirie->id, 'user_id' => $dgs->id,    'role' => 'viewer', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $mPrep = ProjectMilestone::on('tenant')->create([
            'project_id' => $voirie->id, 'title' => 'Phase préparatoire',
            'due_date' => '2025-03-31',
            'color' => '#EA580C', 'sort_order' => 1,
        ]);
        $mAO = ProjectMilestone::on('tenant')->create([
            'project_id' => $voirie->id, 'title' => "Appel d'offres lancé",
            'due_date' => '2025-04-15',
            'color' => '#EA580C', 'sort_order' => 2, 'parent_id' => $mPrep->id,
        ]);
        $mTravaux = ProjectMilestone::on('tenant')->create([
            'project_id' => $voirie->id, 'title' => 'Travaux — Tranche 1',
            'due_date' => '2025-06-30',
            'color' => '#EA580C', 'sort_order' => 3,
        ]);

        $voirieTasks = [
            ['title' => 'Rédiger le cahier des charges',         'status' => 'done',        'priority' => 'high',   'milestone_id' => $mPrep->id,    'assigned_to' => $thomas->id, 'due_date' => '2025-03-20', 'sort_order' => 1],
            ['title' => 'Lever topographique de la rue',          'status' => 'done',        'priority' => 'high',   'milestone_id' => $mPrep->id,    'assigned_to' => $eric->id,   'due_date' => '2025-03-25', 'sort_order' => 2],
            ['title' => "Publication appel d'offres BOAMP",       'status' => 'done',        'priority' => 'urgent', 'milestone_id' => $mAO->id,      'assigned_to' => $thomas->id, 'due_date' => '2025-04-01', 'sort_order' => 1],
            ['title' => 'Analyse des offres reçues',              'status' => 'in_progress', 'priority' => 'high',   'milestone_id' => $mAO->id,      'assigned_to' => $thomas->id, 'due_date' => '2025-04-30', 'sort_order' => 2],
            ['title' => 'Déviation de circulation mise en place', 'status' => 'todo',        'priority' => 'high',   'milestone_id' => $mTravaux->id, 'assigned_to' => $eric->id,   'due_date' => '2025-05-10', 'sort_order' => 1],
            ['title' => 'Décaissement et pose réseaux EP',        'status' => 'todo',        'priority' => 'medium', 'milestone_id' => $mTravaux->id, 'assigned_to' => $eric->id,   'due_date' => '2025-05-31', 'sort_order' => 2],
            ['title' => 'Enrobés — couche de base',               'status' => 'todo',        'priority' => 'medium', 'milestone_id' => $mTravaux->id, 'assigned_to' => $eric->id,   'due_date' => '2025-06-20', 'sort_order' => 3],
        ];

        foreach ($voirieTasks as $i => $t) {
            $task = Task::on('tenant')->create(array_merge($t, [
                'project_id' => $voirie->id,
                'created_by' => $thomas->id,
            ]));
            if ($i === 2) {
                TaskComment::on('tenant')->create([
                    'task_id' => $task->id, 'user_id' => $thomas->id,
                    'body'    => 'Publié sur le BOAMP ce matin. Délai de réception des offres fixé au 30 mars.',
                ]);
            }
            if ($i === 3) {
                TaskComment::on('tenant')->create([
                    'task_id' => $task->id, 'user_id' => $eric->id,
                    'body'    => "Reçu 4 offres. L'entreprise Bonneau TP est la mieux-disante. Je transmets le dossier à Thomas.",
                ]);
            }
        }

        // ── Projet 2 : Révision du PLU ───────────────────────────
        $plu = Project::on('tenant')->create([
            'created_by'  => $laurent->id,
            'name'        => "Révision du Plan Local d'Urbanisme",
            'description' => "Révision générale du PLU conformément aux prescriptions du SCoT du Pays de Loire. Concertation publique, diagnostic territorial et définition des orientations d'aménagement.",
            'status'      => 'active',
            'start_date'  => Carbon::parse('2025-01-15'),
            'due_date'    => Carbon::parse('2026-06-30'),
            'color'       => '#0F766E',
            'is_private'  => false,
        ]);

        ProjectMember::on('tenant')->insert([
            ['project_id' => $plu->id, 'user_id' => $laurent->id, 'role' => 'owner',  'created_at' => now(), 'updated_at' => now()],
            ['project_id' => $plu->id, 'user_id' => $dgs->id,     'role' => 'member', 'created_at' => now(), 'updated_at' => now()],
            ['project_id' => $plu->id, 'user_id' => $maire->id,   'role' => 'viewer', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $mDiag = ProjectMilestone::on('tenant')->create([
            'project_id' => $plu->id, 'title' => 'Diagnostic territorial',
            'due_date' => '2025-06-30',
            'color' => '#0F766E', 'sort_order' => 1,
        ]);
        $mConc = ProjectMilestone::on('tenant')->create([
            'project_id' => $plu->id, 'title' => 'Concertation publique ouverte',
            'due_date' => '2025-04-01',
            'color' => '#0F766E', 'sort_order' => 2, 'parent_id' => $mDiag->id,
        ]);
        $mArret = ProjectMilestone::on('tenant')->create([
            'project_id' => $plu->id, 'title' => "Arrêt du projet de PLU",
            'due_date' => '2026-01-31',
            'color' => '#0F766E', 'sort_order' => 3,
        ]);

        $pluTasks = [
            ['title' => 'Recenser les études existantes',      'status' => 'done',        'priority' => 'medium', 'milestone_id' => $mDiag->id,  'assigned_to' => $laurent->id, 'due_date' => '2025-02-28', 'sort_order' => 1],
            ['title' => 'Analyse démographique 2015-2024',     'status' => 'done',        'priority' => 'medium', 'milestone_id' => $mDiag->id,  'assigned_to' => $laurent->id, 'due_date' => '2025-03-31', 'sort_order' => 2],
            ['title' => 'Organiser les réunions publiques',    'status' => 'in_progress', 'priority' => 'high',   'milestone_id' => $mConc->id,  'assigned_to' => $laurent->id, 'due_date' => '2025-04-15', 'sort_order' => 1],
            ['title' => 'Créer le registre de concertation',   'status' => 'in_progress', 'priority' => 'medium', 'milestone_id' => $mConc->id,  'assigned_to' => $marie->id,   'due_date' => '2025-04-20', 'sort_order' => 2],
            ['title' => 'Rédiger le rapport de présentation',  'status' => 'todo',        'priority' => 'high',   'milestone_id' => $mArret->id, 'assigned_to' => $laurent->id, 'due_date' => '2025-11-30', 'sort_order' => 1],
        ];

        foreach ($pluTasks as $i => $t) {
            $task = Task::on('tenant')->create(array_merge($t, [
                'project_id' => $plu->id,
                'created_by' => $laurent->id,
            ]));
            if ($i === 2) {
                TaskComment::on('tenant')->create([
                    'task_id' => $task->id, 'user_id' => $laurent->id,
                    'body'    => 'Salle de la mairie retenue pour le 22 avril. Ordre du jour envoyé aux personnes publiques associées.',
                ]);
            }
        }

        // ── Projet 3 : Fête de la Saint-Aubin 2025 ───────────────
        $fete = Project::on('tenant')->create([
            'created_by'  => $nathalie->id,
            'name'        => 'Fête de la Saint-Aubin 2025',
            'description' => "Organisation de la fête annuelle de la commune : concerts, animations pour enfants, marché artisanal, feu d'artifice. Budget prévisionnel : 18 500 €.",
            'status'      => 'active',
            'start_date'  => Carbon::parse('2025-05-01'),
            'due_date'    => Carbon::parse('2025-07-15'),
            'color'       => '#7C3AED',
            'is_private'  => false,
        ]);

        ProjectMember::on('tenant')->insert([
            ['project_id' => $fete->id, 'user_id' => $nathalie->id, 'role' => 'owner',  'created_at' => now(), 'updated_at' => now()],
            ['project_id' => $fete->id, 'user_id' => $marie->id,    'role' => 'member', 'created_at' => now(), 'updated_at' => now()],
            ['project_id' => $fete->id, 'user_id' => $maire->id,    'role' => 'viewer', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $mLogistique = ProjectMilestone::on('tenant')->create([
            'project_id' => $fete->id, 'title' => 'Préparation logistique',
            'due_date' => '2025-06-01',
            'color' => '#7C3AED', 'sort_order' => 1,
        ]);
        $mJourJ = ProjectMilestone::on('tenant')->create([
            'project_id' => $fete->id, 'title' => 'Jour J — Fête de la commune',
            'due_date' => '2025-07-12',
            'color' => '#DC2626', 'sort_order' => 2,
        ]);

        $feteTasks = [
            ['title' => 'Réserver la salle des fêtes',             'status' => 'done',        'priority' => 'urgent', 'milestone_id' => $mLogistique->id, 'assigned_to' => $nathalie->id, 'due_date' => '2025-05-05', 'sort_order' => 1],
            ['title' => 'Contacter les prestataires son/lumière',   'status' => 'done',        'priority' => 'high',   'milestone_id' => $mLogistique->id, 'assigned_to' => $marie->id,    'due_date' => '2025-05-10', 'sort_order' => 2],
            ['title' => 'Lancer la communication (affichage)',      'status' => 'in_progress', 'priority' => 'medium', 'milestone_id' => $mLogistique->id, 'assigned_to' => $nathalie->id, 'due_date' => '2025-06-01', 'sort_order' => 3],
            ['title' => 'Organiser le marché artisanal',            'status' => 'in_progress', 'priority' => 'medium', 'milestone_id' => $mLogistique->id, 'assigned_to' => $marie->id,    'due_date' => '2025-06-15', 'sort_order' => 4],
            ['title' => 'Bilan financier post-événement',           'status' => 'todo',        'priority' => 'low',    'milestone_id' => $mJourJ->id,      'assigned_to' => $nathalie->id, 'due_date' => '2025-07-25', 'sort_order' => 1],
        ];

        foreach ($feteTasks as $i => $t) {
            $task = Task::on('tenant')->create(array_merge($t, [
                'project_id' => $fete->id,
                'created_by' => $nathalie->id,
            ]));
            if ($i === 2) {
                TaskComment::on('tenant')->create([
                    'task_id' => $task->id, 'user_id' => $nathalie->id,
                    'body'    => 'Bon de commande affiches validé. Tirage 200 exemplaires format A2.',
                ]);
            }
        }
    }

    // ─────────────────────────────────────────────────────────────
    //  Albums photo — structure uniquement, sans MediaItem
    //  Les photos sont injectées par DemoResetCommand::seedPhotos()
    //  qui cherche l'album par nom dans la table.
    // ─────────────────────────────────────────────────────────────

    private function createAlbums(array $users): void
    {
        $thomas   = $users['technique@demo.pladigit.fr'];
        $nathalie = $users['communication@demo.pladigit.fr'];
        $eric     = $users['agent1@demo.pladigit.fr'];

        // Cet album doit s'appeler exactement 'Fête de la commune 2025'
        // car DemoResetCommand::seedPhotos() le cherche par ce nom.
        MediaAlbum::on('tenant')->updateOrCreate(
            ['name' => 'Fête de la commune 2025'],
            [
                'description' => 'Photos de la fête annuelle de la commune — été 2025.',
                'visibility'  => 'public',
                'created_by'  => $nathalie->id,
            ]
        );

        $vieMunicipale = MediaAlbum::on('tenant')->updateOrCreate(
            ['name' => 'Vie municipale 2025'],
            [
                'description' => 'Photos officielles des événements et réunions de la commune.',
                'visibility'  => 'restricted',
                'created_by'  => $nathalie->id,
            ]
        );

        MediaAlbum::on('tenant')->updateOrCreate(
            ['name' => 'Conseil municipal — Janvier 2025'],
            [
                'description' => 'Photos de la séance du conseil municipal du 14 janvier 2025.',
                'visibility'  => 'restricted',
                'created_by'  => $nathalie->id,
                'parent_id'   => $vieMunicipale->id,
            ]
        );

        $chantiers = MediaAlbum::on('tenant')->updateOrCreate(
            ['name' => 'Chantiers et travaux'],
            [
                'description' => 'Suivi photographique des chantiers en cours sur la commune.',
                'visibility'  => 'restricted',
                'created_by'  => $thomas->id,
            ]
        );

        MediaAlbum::on('tenant')->updateOrCreate(
            ['name' => 'Rue des Acacias — Avancement'],
            [
                'description' => 'Photos hebdomadaires du chantier de réfection.',
                'visibility'  => 'restricted',
                'created_by'  => $eric->id,
                'parent_id'   => $chantiers->id,
            ]
        );
    }
}
