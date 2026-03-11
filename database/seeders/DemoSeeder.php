<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Tenant\Department;
use App\Models\Tenant\MediaAlbum;
use App\Models\Tenant\Share;
use App\Models\Tenant\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeder de démonstration — base tenant demo.
 *
 * Crée :
 *   - 6 utilisateurs (un par rôle)
 *   - 3 directions + 2 services
 *   - Affectations utilisateurs / départements
 *   - 3 albums avec visibilités variées + partages de test
 *
 * Mot de passe unique pour tous : demo1234
 * Usage : php artisan db:seed --class=DemoSeeder --database=tenant
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🌱 Seeding demo tenant...');

        // ── Utilisateurs ──────────────────────────────────────────
        $users = $this->createUsers();
        $this->command->info('✓ Utilisateurs créés');

        // ── Départements ──────────────────────────────────────────
        $depts = $this->createDepartments($users);
        $this->command->info('✓ Départements créés');

        // ── Albums ────────────────────────────────────────────────
        $this->createAlbums($users, $depts);
        $this->command->info('✓ Albums créés');

        $this->command->info('');
        $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->command->info('  Comptes de démonstration (mot de passe : demo1234)');
        $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        foreach ($users as $role => $user) {
            $this->command->info(sprintf('  %-20s %s', $user->email, "({$user->role})"));
        }
        $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
    }

    // ─────────────────────────────────────────────────────────────
    //  Utilisateurs
    // ─────────────────────────────────────────────────────────────

    private function createUsers(): array
    {
        $password = Hash::make('demo1234');

        $definitions = [
            UserRole::ADMIN->value => [
                'name' => 'Admin Démo',
                'email' => 'admin@demo.pladigit.fr',
            ],
            UserRole::PRESIDENT->value => [
                'name' => 'Marie Dupont',
                'email' => 'president@demo.pladigit.fr',
            ],
            UserRole::DGS->value => [
                'name' => 'Jean-Pierre Martin',
                'email' => 'dgs@demo.pladigit.fr',
            ],
            UserRole::RESP_DIRECTION->value => [
                'name' => 'Sophie Lambert',
                'email' => 'resp.direction@demo.pladigit.fr',
            ],
            UserRole::RESP_SERVICE->value => [
                'name' => 'Thomas Bernard',
                'email' => 'resp.service@demo.pladigit.fr',
            ],
            UserRole::USER->value => [
                'name' => 'Lucie Moreau',
                'email' => 'user@demo.pladigit.fr',
            ],
        ];

        $users = [];
        foreach ($definitions as $role => $data) {
            $users[$role] = User::updateOrCreate(
                ['email' => $data['email']],
                array_merge($data, [
                    'password_hash' => $password,
                    'role' => $role,
                    'status' => 'active',
                    'force_pwd_change' => false,
                    'totp_enabled' => false,
                    'password_changed_at' => now(),
                ])
            );
        }

        return $users;
    }

    // ─────────────────────────────────────────────────────────────
    //  Départements
    // ─────────────────────────────────────────────────────────────

    private function createDepartments(array $users): array
    {
        // Direction DST
        $dst = Department::updateOrCreate(
            ['name' => 'Direction des Services Techniques'],
            ['type' => 'direction', 'label' => 'direction', 'color' => '#1e40af', 'sort_order' => 1, 'created_by' => $users[UserRole::ADMIN->value]->id]
        );

        // Direction RH
        $rh = Department::updateOrCreate(
            ['name' => 'Direction des Ressources Humaines'],
            ['type' => 'direction', 'label' => 'direction', 'color' => '#7c3aed', 'sort_order' => 2, 'created_by' => $users[UserRole::ADMIN->value]->id]
        );

        // Service Voirie (sous DST)
        $voirie = Department::updateOrCreate(
            ['name' => 'Service Voirie'],
            ['type' => 'service', 'label' => 'service', 'color' => '#0369a1', 'sort_order' => 1, 'parent_id' => $dst->id, 'created_by' => $users[UserRole::ADMIN->value]->id]
        );

        // Service Paie (sous RH)
        $paie = Department::updateOrCreate(
            ['name' => 'Service Paie'],
            ['type' => 'service', 'label' => 'service', 'color' => '#6d28d9', 'sort_order' => 1, 'parent_id' => $rh->id, 'created_by' => $users[UserRole::ADMIN->value]->id]
        );

        // Affectations
        $dst->members()->syncWithoutDetaching([
            $users[UserRole::RESP_DIRECTION->value]->id => ['is_manager' => true],
            $users[UserRole::RESP_SERVICE->value]->id => ['is_manager' => false],
            $users[UserRole::USER->value]->id => ['is_manager' => false],
        ]);

        $voirie->members()->syncWithoutDetaching([
            $users[UserRole::RESP_SERVICE->value]->id => ['is_manager' => true],
            $users[UserRole::USER->value]->id => ['is_manager' => false],
        ]);

        $rh->members()->syncWithoutDetaching([
            $users[UserRole::RESP_DIRECTION->value]->id => ['is_manager' => true],
        ]);

        return compact('dst', 'rh', 'voirie', 'paie');
    }

    // ─────────────────────────────────────────────────────────────
    //  Albums
    // ─────────────────────────────────────────────────────────────

    private function createAlbums(array $users, array $depts): void
    {
        $admin = $users[UserRole::ADMIN->value];
        $respDir = $users[UserRole::RESP_DIRECTION->value];

        // Album 1 — Public (visible par tous)
        $albumPublic = MediaAlbum::updateOrCreate(
            ['name' => 'Fête de la commune 2025'],
            [
                'description' => 'Photos de la fête annuelle de la commune.',
                'visibility' => 'public',
                'created_by' => $admin->id,
            ]
        );

        // Album 2 — Restreint avec droits par rôle
        $albumRestreint = MediaAlbum::updateOrCreate(
            ['name' => 'Travaux voirie 2025'],
            [
                'description' => 'Suivi photographique des chantiers en cours.',
                'visibility' => 'restricted',
                'created_by' => $respDir->id,
            ]
        );

        // Droits par rôle : resp_service et user peuvent voir
        foreach ([UserRole::RESP_SERVICE->value, UserRole::USER->value] as $role) {
            Share::updateOrCreate(
                [
                    'shareable_type' => 'media_album',
                    'shareable_id' => $albumRestreint->id,
                    'shared_with_type' => 'role',
                    'shared_with_role' => $role,
                ],
                [
                    'can_view' => true,
                    'can_download' => $role === UserRole::RESP_SERVICE->value,
                    'can_edit' => false,
                    'can_manage' => false,
                    'shared_by' => $admin->id,
                ]
            );
        }

        // Override utilisateur : Lucie (user) peut aussi télécharger
        Share::updateOrCreate(
            [
                'shareable_type' => 'media_album',
                'shareable_id' => $albumRestreint->id,
                'shared_with_type' => 'user',
                'shared_with_id' => $users[UserRole::USER->value]->id,
            ],
            [
                'can_view' => true,
                'can_download' => true,
                'can_edit' => false,
                'can_manage' => false,
                'shared_by' => $admin->id,
            ]
        );

        // Album 3 — Privé (créateur uniquement)
        MediaAlbum::updateOrCreate(
            ['name' => 'RH — Documents confidentiels'],
            [
                'description' => 'Album privé RH — accès restreint au créateur.',
                'visibility' => 'private',
                'created_by' => $users[UserRole::RESP_DIRECTION->value]->id,
            ]
        );
    }
}
