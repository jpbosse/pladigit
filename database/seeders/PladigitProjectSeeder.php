<?php

namespace Database\Seeders;

use App\Enums\ProjectRole;
use App\Models\Tenant\Event;
use App\Models\Tenant\EventParticipant;
use App\Models\Tenant\Project;
use App\Models\Tenant\ProjectMember;
use App\Models\Tenant\ProjectMilestone;
use App\Models\Tenant\Task;
use App\Models\Tenant\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeder de démonstration — Projet Pladigit complet.
 *
 * Principe : "Piloter Pladigit avec Pladigit".
 * Ce seeder insère le projet Pladigit lui-même dans le module Gestion de Projet.
 * Il sert à la fois de démonstration pour les clients et d'outil de pilotage réel.
 *
 * Données insérées (spec Synthese_Pladigit_DonneesProjet.docx) :
 *   - 1 projet  : Pladigit — Développement plateforme 2025→2029
 *   - 1 membre  : Jean-Pierre Bossé (owner)
 *   - 13 jalons : J1→J13 (2 atteints, 1 en cours, 10 planifiés)
 *   - ~108 tâches : réparties sur toutes les phases, statuts réels
 *   - 10 événements agenda : sessions de développement passées + jalons futurs
 *
 * Usage :
 *   php artisan db:seed --class=PladigitProjectSeeder
 *
 * Prérequis :
 *   - Connexion tenant active (TenantManager::connectTo())
 *   - Au moins un utilisateur Admin actif dans la base tenant
 *   - Migrations tenant appliquées (projects, tasks, project_milestones, etc.)
 *
 * Comportement :
 *   - Idempotent : si le projet existe déjà (par name), le seeder recrée
 *     toutes les données fraîches après purge (ne modifie pas le projet existant)
 *   - Ordre FK respecté : jalons → tâches → événements
 */
class PladigitProjectSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🌱 PladigitProjectSeeder — démarrage');

        // Récupérer l'admin principal
        /** @var User $admin */
        $admin = User::on('tenant')
            ->where('role', 'admin')
            ->where('status', 'active')
            ->orderBy('id')
            ->firstOrFail();

        $this->command->info("   Admin : {$admin->name} (id={$admin->id})");

        // ── Purge idempotente ─────────────────────────────────────────
        DB::connection('tenant')->statement('SET FOREIGN_KEY_CHECKS=0');
        DB::connection('tenant')->table('event_participants')->delete();
        DB::connection('tenant')->table('events')->whereNotNull('project_id')->delete();
        DB::connection('tenant')->table('task_comments')->delete();
        DB::connection('tenant')->table('task_dependencies')->delete();
        DB::connection('tenant')->table('tasks')->delete();
        DB::connection('tenant')->table('project_milestones')->delete();
        DB::connection('tenant')->table('project_members')->delete();
        DB::connection('tenant')->table('projects')->delete();
        DB::connection('tenant')->statement('SET FOREIGN_KEY_CHECKS=1');

        // ── Projet ────────────────────────────────────────────────────
        $project = Project::on('tenant')->create([
            'created_by'  => $admin->id,
            'name'        => 'Pladigit — Développement plateforme 2025→2029',
            'description' => 'Plateforme SaaS de digitalisation interne, alternative souveraine open source (AGPL-3.0) aux outils Microsoft. Développée pour Les Bézots (Soullans, Vendée) — 48 mois, 13 phases, Oct 2025 → Sep 2029.',
            'status'      => 'active',
            'start_date'  => '2025-10-01',
            'due_date'    => '2029-09-30',
            'color'       => '#1E3A5F',
        ]);

        ProjectMember::on('tenant')->create([
            'project_id' => $project->id,
            'user_id'    => $admin->id,
            'role'       => ProjectRole::OWNER->value,
        ]);

        $this->command->info("   Projet : id={$project->id}");

        // ── 13 jalons ─────────────────────────────────────────────────
        $milestones = $this->seedMilestones($project);
        $this->command->info('   Jalons : ' . count($milestones));

        // ── ~108 tâches ───────────────────────────────────────────────
        $taskCount = $this->seedTasks($project, $admin, $milestones);
        $this->command->info("   Tâches : {$taskCount}");

        // ── 10 événements ─────────────────────────────────────────────
        $eventCount = $this->seedEvents($project, $admin);
        $this->command->info("   Événements : {$eventCount}");

        $this->command->info('✅ PladigitProjectSeeder — terminé');
    }

    // ──────────────────────────────────────────────────────────────────
    // 13 JALONS
    // ──────────────────────────────────────────────────────────────────

    /**
     * @return array<string, ProjectMilestone>
     */
    private function seedMilestones(Project $project): array
    {
        // [titre, due_date, reached_at|null, color, description]
        $defs = [
            'j1'  => [
                'Phase 1 — Socle technique livré',
                '2025-12-31', '2025-12-31', '#16A34A',
                '47 tests / 77 assertions. CI vert. Multi-tenant, auth locale, 2FA, ForcePwdChange.',
            ],
            'j2'  => [
                'Phase 2 — Auth LDAP + branding livré',
                '2026-03-31', '2026-03-13', '#16A34A',
                '237 tests / 546 assertions. LDAP, branding, sync-ldap, directions. CDC v2.0 produit.',
            ],
            'j3'  => [
                'Phase 3 — Photothèque NAS',
                '2026-06-30', null, '#3B82F6',
                'En cours. 311 tests / 693 assertions au 16/03/2026.',
            ],
            'j4'  => [
                'Phase 4 — Photothèque avancée',
                '2026-09-30', null, '#3B82F6',
                'Albums avancés, EXIF complet, export ZIP, partage temporaire, tagging IA.',
            ],
            'j5'  => [
                'Phase 5bis — Module Gestion de projet',
                '2026-12-31', null, '#8B5CF6',
                'CDC v1.0 produit le 16/03/2026. Kanban Alpine.js, Gantt SVG PHP, Agenda intégré.',
            ],
            'j6'  => [
                'Phase 5 — GED documentaire',
                '2027-03-31', null, '#3B82F6',
                'Arborescence documentaire, versionning, recherche plein texte. Livewire initialisé ici.',
            ],
            'j7'  => [
                'Phase 6 — Collabora Online',
                '2027-06-30', null, '#1E3A5F',
                'Intégration WOPI, édition collaborative ODT/ODS/ODP.',
            ],
            'j8'  => [
                'Phase 7 — ERP DataGrid',
                '2027-09-30', null, '#1E3A5F',
                'Tables no-code, audit trail, export CSV/Excel.',
            ],
            'j9'  => [
                'Phase 8 — Chat temps réel',
                '2027-12-31', null, '#1E3A5F',
                'Canaux, messagerie 1:1, WebSocket Soketi.',
            ],
            'j10' => [
                'Phase 9 — Chat & Fil RSS',
                '2028-03-31', null, '#1E3A5F',
                'Fil d\'actualités RSS, agrégateur, widget dashboard.',
            ],
            'j11' => [
                'Phases 10–11 — News + Sondages',
                '2028-06-30', null, '#1E3A5F',
                'Sondages, questionnaires, résultats temps réel.',
            ],
            'j12' => [
                'Phase 12 — Production + audit sécurité',
                '2028-09-30', null, '#EA580C',
                'VPS prod, monitoring, PRA, audit sécurité externe. Certification RGPD.',
            ],
            'j13' => [
                'Phase 13 — Publication open source',
                '2029-09-30', null, '#6366F1',
                'Publication AGPL-3.0, documentation communautaire, gouvernance.',
            ],
        ];

        $milestones = [];
        foreach ($defs as $key => [$title, $dueDate, $reachedAt, $color, $description]) {
            $milestones[$key] = ProjectMilestone::on('tenant')->create([
                'project_id'  => $project->id,
                'title'       => $title,
                'description' => $description,
                'due_date'    => $dueDate,
                'reached_at'  => $reachedAt ? Carbon::parse($reachedAt) : null,
                'color'       => $color,
            ]);
        }

        return $milestones;
    }

    // ──────────────────────────────────────────────────────────────────
    // ~108 TÂCHES
    // ──────────────────────────────────────────────────────────────────

    private function seedTasks(Project $project, User $admin, array $ms): int
    {
        $order = 0;

        /**
         * Créer une tâche.
         *
         * @param  array<string, mixed>  $attrs
         */
        $t = function (
            string $title,
            string $status,
            string $priority,
            ?string $msKey,
            ?string $start,
            ?string $due,
            int $estimated,
            ?int $actual = null,
            string $desc = ''
        ) use ($project, $admin, $ms, &$order): Task {
            return Task::on('tenant')->create([
                'project_id'      => $project->id,
                'created_by'      => $admin->id,
                'assigned_to'     => $admin->id,
                'parent_task_id'  => null,
                'milestone_id'    => $msKey ? ($ms[$msKey]->id ?? null) : null,
                'title'           => $title,
                'description'     => $desc ?: null,
                'status'          => $status,
                'priority'        => $priority,
                'start_date'      => $start,
                'due_date'        => $due,
                'estimated_hours' => $estimated,
                'actual_hours'    => $actual,
                'sort_order'      => $order++,
            ]);
        };

        // ── PHASE 1 — Socle technique (7 tâches DONE) ────────────────
        $t('Infrastructure multi-tenant opérationnelle',         'done', 'urgent', 'j1', '2025-10-01', '2025-10-31', 16, 18, 'TenantManager, bases MySQL dédiées par organisation. ADR-002.');
        $t('Authentification locale bcrypt coût 12',             'done', 'urgent', 'j1', '2025-10-01', '2025-11-15', 12, 11, 'Sessions, verrouillage compte N tentatives, CSRF.');
        $t('2FA TOTP — Google Authenticator / Aegis',            'done', 'high',   'j1', '2025-10-15', '2025-11-30', 10, 12, 'Codes de secours AES-256, rate limiting throttle:5,10.');
        $t('Middleware ForcePwdChange',                          'done', 'high',   'j1', '2025-11-01', '2025-11-25', 4,  4,  'Changement obligatoire au premier login.');
        $t('Super Admin — interface /super-admin',               'done', 'medium', 'j1', '2025-11-15', '2025-12-10', 8,  9,  'Interface création organisations, isolation totale.');
        $t('CI/CD GitHub Actions — PHPUnit + Pint + PHPStan',    'done', 'high',   'j1', '2025-10-15', '2025-10-31', 6,  6);
        $t('Suite de tests Phase 1 — 47 tests / 77 assertions',  'done', 'high',   'j1', '2025-11-01', '2025-12-31', 8,  9,  'TwoFactorSecurityTest, ForcePwdChangeTest, PasswordPolicyTest, LoginTest, UserRoleTest.');

        // ── PHASE 2 — Auth LDAP + branding (11 tâches DONE) ─────────
        $t('Page profil utilisateur',                            'done', 'medium', 'j2', '2026-01-05', '2026-01-20', 6,  7,  'Informations personnelles, changement mdp, gestion 2FA, codes secours.');
        $t('UserRole enum — source unique de vérité',            'done', 'high',   'j2', '2026-01-05', '2026-01-15', 4,  4,  'App\\Enums\\UserRole — level(), atLeast(), label() en français.');
        $t('Structure organisationnelle directions/services',    'done', 'high',   'j2', '2026-01-10', '2026-01-31', 10, 11, 'Tables departments (type: direction|service, parent_id) et user_department (pivot is_manager).');
        $t('Invitation utilisateur par email',                   'done', 'medium', 'j2', '2026-01-15', '2026-02-10', 8,  8,  'Token 64 chars, durée 72h, remplacement mot de passe en clair.');
        $t('Dashboard Blade — widgets par rôle',                 'done', 'medium', 'j2', '2026-01-20', '2026-02-15', 10, 11, '7 widgets. Décision Livewire réservé Phase 3+.');
        $t('Branding — logo, couleurs, nom organisation',        'done', 'medium', 'j2', '2026-02-01', '2026-02-20', 6,  6,  'Personnalisation visuelle par organisation. BrandingTest 6/6.');
        $t('Routes LDAP/SMTP activées depuis interface admin',   'done', 'medium', 'j2', '2026-02-10', '2026-02-28', 4,  4,  '5 routes décommentées, liens menu ajoutés.');
        $t('LdapAuthService — bugs cas4/cas5/cas1-3 corrigés',   'done', 'high',   'j2', '2026-03-01', '2026-03-13', 6,  8,  'Ordre resolveRole/attempt, gestion exceptions, paramètres nommés.');
        $t('SyncLdapUsers — option --tenant',                    'done', 'medium', 'j2', '2026-03-05', '2026-03-13', 4,  4,  '5 tests. Filtre sur organizations.slug.');
        $t('BrandingTest — isolation tenant et reset',           'done', 'medium', 'j2', '2026-03-10', '2026-03-13', 3,  3,  '6/6 verts. helper persistCurrentOrg().');
        $t('CDC v2.0 — refonte documentaire',                    'done', 'medium', 'j2', '2026-03-13', '2026-03-13', 4,  4,  'Document principal + 7 annexes séparées. All validations PASSED.');

        // ── PHASE 3 — Photothèque NAS LIVRÉE (21 tâches DONE) ────────
        $t('MediaService — purge orphelins NAS',                 'done', 'high',   'j3', '2026-03-14', '2026-03-14', 4,  4,  'purgeAllOrphanItems() + purgeOrphanAlbums().');
        $t('MediaService — ingestNasFile() EXIF + miniature',    'done', 'high',   'j3', '2026-03-14', '2026-03-14', 4,  5,  'Lecture fichier + extraction EXIF + miniature à la sync.');
        $t('Suppression physique fichiers NAS',                  'done', 'high',   'j3', '2026-03-14', '2026-03-14', 6,  7,  'deleteFile() + mkdir() sur les 3 drivers (Local, SFTP, SMB).');
        $t('NasManager — correction critique colonnes',          'done', 'urgent', 'j3', '2026-03-14', '2026-03-14', 3,  2,  'Réalignement nas_photo_driver, nas_photo_local_path...');
        $t('Interface albums — boutons suppression + sync',      'done', 'medium', 'j3', '2026-03-14', '2026-03-14', 2,  2,  'Bouton ✏️ + 🗑 au survol, bouton sync 🔄 dans header.');
        $t('Nginx client_max_body_size 100M',                    'done', 'high',   'j3', '2026-03-14', '2026-03-14', 1,  1,  'Uploads > 1 Mo désormais possibles.');
        $t('Modules activables par organisation',                'done', 'urgent', 'j3', '2026-03-15', '2026-03-15', 8,  9,  'ModuleKey enum 8 modules. Organization::hasModule(). Middleware RequireModule.');
        $t('Watermark sur téléchargements (GD/TTF)',             'done', 'high',   'j3', '2026-03-15', '2026-03-15', 6,  7,  'WatermarkService — Police DejaVuSans-Bold, UTF-8 natif. Config par tenant.');
        $t('Super Admin — refonte onglets',                      'done', 'medium', 'j3', '2026-03-15', '2026-03-15', 4,  4,  '4 onglets : Admin/Modules/SMTP/LDAP. Style inline.');
        $t('Arbre hiérarchique récursif utilisateur',            'done', 'medium', 'j3', '2026-03-15', '2026-03-15', 3,  3,  '_dept_checkboxes.blade.php — N niveaux.');
        $t('TenantProvisioningService — rollback atomique',      'done', 'high',   'j3', '2026-03-15', '2026-03-15', 4,  4,  'DROP DATABASE si migrations échouent. ProvisioningException.');
        $t('MediaService::upload() — transaction BDD + NAS',     'done', 'high',   'j3', '2026-03-15', '2026-03-15', 3,  3,  'DB::transaction() + compensation NAS si échec.');
        $t('Footer statique + pages légales AGPL-3.0',           'done', 'medium', 'j3', '2026-03-15', '2026-03-15', 3,  3,  '/mentions-legales + /confidentialite. Pages autonomes.');
        $t('Migration nas_photo_* — colonnes corrigées',         'done', 'urgent', 'j3', '2026-03-16', '2026-03-16', 2,  2,  'Renommage colonnes sur tenants existants. fix-nas-columns.');
        $t('Doublons albums — filtre parent_id',                 'done', 'high',   'j3', '2026-03-16', '2026-03-16', 2,  2,  'findOrCreateAlbumForPath() — 59 → 48 albums après dédoublonnage.');
        $t('Popup connexion pladigit.fr',                        'done', 'high',   'j3', '2026-03-16', '2026-03-16', 4,  5,  'Alpine.js + cookie pladigit_org 1 an. CSRF cross-domaine exempté.');
        $t('Queue Laravel — ProcessMediaUpload',                 'done', 'urgent', 'j3', '2026-03-16', '2026-03-16', 6,  7,  'Job miniature + EXIF async. Service systemd pladigit-queue.');
        $t('Import ZIP en arrière-plan',                         'done', 'high',   'j3', '2026-03-16', '2026-03-16', 5,  6,  'Job ProcessZipImport — extraction + ingestion via queue.');
        $t('Permissions storage/ — www-data + deploy',           'done', 'high',   'j3', '2026-03-16', '2026-03-16', 1,  1,  'usermod -aG deploy www-data. Correction définitive.');
        $t('311 tests / 693 assertions — CI vert',               'done', 'high',   'j3', '2026-03-16', '2026-03-16', 4,  4,  'ModuleKeyTest, ModuleAccessTest, WatermarkTest, TenantProvisioningTest.');
        $t('TaskController::show() + route GET tâche',           'done', 'urgent', 'j5', '2026-03-17', '2026-03-17', 2,  2,  'Endpoint JSON pour le slide-over Alpine.js. Route projects.tasks.show.');

        // ── PHASE 3 — EN COURS (2 tâches IN_PROGRESS) ────────────────
        $t('Album public → droits restreints automatiques',      'in_progress', 'urgent', 'j3', '2026-03-17', '2026-03-20', 3,  null, 'Bloquant avant premier client.');
        $t('Espace stockage masqué dans module',                 'in_progress', 'urgent', 'j3', '2026-03-17', '2026-03-20', 4,  null, 'Bloquant avant premier client.');

        // ── PHASE 3 — RESTANTES (7 tâches TODO) ──────────────────────
        $t('Image de couverture sur les albums',                 'todo', 'medium', 'j3', null, null, 4,  null, 'Upload cover_path, affichage sur les cartes albums.');
        $t('Partage par lien temporaire',                        'todo', 'medium', 'j3', null, null, 6,  null, 'Génération token + expiration configurable.');
        $t('Tri par date EXIF (prise de vue réelle)',            'todo', 'medium', 'j3', null, null, 3,  null, 'Sort par exif_taken_at au lieu de created_at.');
        $t('Détection doublons inter-albums',                    'todo', 'medium', 'j3', null, null, 4,  null, 'SHA-256 cross-album. Signalement sans blocage.');
        $t("Export ZIP d'un album",                             'todo', 'medium', 'j3', null, null, 5,  null, 'Job ProcessZipExport — génération async + lien téléchargement.');
        $t('Recherche dans la photothèque',                      'todo', 'low',    'j3', null, null, 6,  null, 'Fulltext sur title, caption, tags EXIF.');
        $t('serve() images > 10 Mo → stream() automatique',     'todo', 'low',    'j3', null, null, 2,  null, 'Auto-détection de la taille avant serve().');

        // ── PHASE 4 — Photothèque avancée (9 tâches TODO) ────────────
        $t('Albums — sous-albums avec glisser-déposer',          'todo', 'medium', 'j4', null, '2026-07-31', 8);
        $t('Métadonnées EXIF — vue complète',                    'todo', 'medium', 'j4', null, '2026-07-31', 5,  null, 'Page détail : GPS, appareil, exposition, ISO.');
        $t('Galerie visionneuse plein écran',                    'todo', 'medium', 'j4', null, '2026-08-31', 6,  null, 'Lightbox Alpine.js — navigation clavier, zoom.');
        $t('Tags manuels sur les médias',                        'todo', 'medium', 'j4', null, '2026-08-31', 5);
        $t('Intégration Ollama / LLaVA — tagging IA',            'todo', 'medium', 'j4', null, '2026-09-30', 16, null, 'Modèle LLaVA 7B Apache 2.0. VPS dédié Ollama recommandé.');
        $t("Droits album — héritage parent",                    'todo', 'medium', 'j4', null, '2026-09-30', 5,  null, 'Les sous-albums héritent des droits du parent sauf override.');
        $t('Index BDD department_id + tenant',                   'todo', 'low',    'j4', null, null, 2,  null, 'Performance : requêtes filtrées par département.');
        $t('Purge sessions expirées — Artisan planifié',         'todo', 'low',    'j4', null, null, 2,  null, 'schedule()->daily() dans routes/console.php.');
        $t('Cache thumbnails — Cache-Control renforcé',          'todo', 'low',    'j4', null, null, 2,  null, 'Headers expires + etag sur /media/*/serve.');

        // ── PHASE 5bis — Gestion de projet (19 tâches TODO) ──────────
        $t('README.md — mise à jour planning Phase 5bis',        'todo', 'urgent', 'j5', null, null, 1,  null, 'Tableau Phases planifiées : corriger vers Phase 5bis. CI badge à jour.');
        $t('ModuleKey::isAvailable() — phase <= 5',              'todo', 'urgent', 'j5', null, null, 1,  null, 'Activer PROJECTS dans navigation et Super Admin.');
        $t('Migrations complémentaires — 4 fichiers',            'todo', 'high',   'j5', null, null, 3,  null, 'start_date tasks, milestones table, task_dependencies, project_id events.');
        $t('Enums — ProjectRole (OWNER/MEMBER/VIEWER)',          'todo', 'high',   'j5', null, null, 2,  null, 'canEdit(), canManage(), label() en français.');
        $t('Modèles Eloquent — Project, Task, etc.',             'todo', 'high',   'j5', null, null, 6,  null, 'Project, ProjectMember, Task, TaskComment, ProjectMilestone — connection tenant.');
        $t('ProjectPolicy + TaskPolicy',                         'todo', 'high',   'j5', null, null, 4,  null, 'before() Admin/Président/DGS. ProjectRole local pour les autres.');
        $t('Factories — Project, Task, ProjectMilestone',        'todo', 'medium', 'j5', null, null, 3);
        $t('KanbanController — Alpine.js AJAX',                  'todo', 'high',   'j5', null, null, 8,  null, 'move() + reorder() — PATCH AJAX, pas de Livewire.');
        $t('Contrôleurs Projects — 6 fichiers',                  'todo', 'high',   'j5', null, null, 24, null, 'ProjectController, TaskController, TaskCommentController, ProjectMemberController, ProjectMilestoneController, ProjectEventController.');
        $t('Routes /projects — middleware module:projects',      'todo', 'high',   'j5', null, null, 3,  null, '21 routes.');
        $t('Vue index.blade.php — liste projets',                'todo', 'medium', 'j5', null, null, 4,  null, 'Grille de cartes + filtres statut.');
        $t('Vue show.blade.php — 4 onglets',                     'todo', 'high',   'j5', null, null, 8,  null, 'Kanban / Gantt / Liste / Agenda. Onglet actif dans URL ?view=.');
        $t('Partial _kanban.blade.php — Alpine.js',              'todo', 'high',   'j5', null, null, 8,  null, 'Drag & drop colonnes, PATCH AJAX. Zéro Livewire.');
        $t('Partial _gantt.blade.php — SVG PHP',                 'todo', 'high',   'j5', null, null, 8,  null, 'Barres SVG calculées PHP, drag horizontal Alpine.js.');
        $t('Partial _agenda.blade.php + export iCal',            'todo', 'medium', 'j5', null, null, 5,  null, 'Liste événements + export .ics.');
        $t('Sidebar + Dashboard — widgets projets',              'todo', 'medium', 'j5', null, null, 3,  null, 'Lien Projets conditionnel hasModule(PROJECTS). Widgets Mes projets + Mes tâches.');
        $t('TestCase.php — cleanDatabase() étendu',              'todo', 'high',   'j5', null, null, 1,  null, 'Ajouter tables projects, tasks, milestones, dependencies, events, participants.');
        $t('Suite tests — ~100 tests / ~250 assertions',         'todo', 'high',   'j5', null, null, 12, null, '12 fichiers de tests. Objectif ~411 tests CI.');
        $t('Seeder PladigitProjectSeeder.php',                   'todo', 'urgent', 'j5', null, null, 2,  null, 'Insérer le projet Pladigit complet — ce seeder est la concrétisation.');

        // ── PHASE 5 — GED (8 tâches TODO) ────────────────────────────
        $t('Initialisation Livewire 3 — build pipeline validé',  'todo', 'urgent', 'j6', null, '2027-01-31', 8,  null, 'layouts/app.blade.php + vite.config.js + validation CI.');
        $t('Structure arborescence documentaire',                'todo', 'high',   'j6', null, '2027-01-31', 16, null, 'Table folders (auto-référencée), table documents.');
        $t('Upload documents — drag & drop',                     'todo', 'high',   'j6', null, '2027-01-31', 12, null, 'Formats ODF, DOCX, PDF, XLSX. Extraction texte.');
        $t('Versionning documents',                              'todo', 'high',   'j6', null, '2027-02-28', 12, null, 'Table document_versions — historique, restauration.');
        $t('Recherche plein texte GED',                          'todo', 'medium', 'j6', null, '2027-02-28', 10, null, 'MySQL FULLTEXT ou Meilisearch. Index sur contenu extrait.');
        $t('NAS GED — driver séparé NAS photothèque',            'todo', 'medium', 'j6', null, '2027-03-31', 4,  null, 'Connexion NAS dédiée (nas_ged_*) — déjà dans TenantSettings.');
        $t('Droits par dossier GED',                             'todo', 'medium', 'j6', null, '2027-03-31', 8,  null, 'Héritage hiérarchique, override par dossier.');
        $t('Intégration Collabora Online (aperçu GED)',          'todo', 'medium', 'j6', null, '2027-03-31', 8);

        // ── PHASE 6 — Collabora Online (4 tâches) ────────────────────
        $t('Architecture WOPI — serveur Collabora',              'todo', 'high',   'j7', null, '2027-04-30', 16, null, 'Intégration WOPI, édition ODT/ODS/ODP, sécurité JWT.');
        $t('Édition ODT en ligne',                               'todo', 'high',   'j7', null, '2027-05-31', 12);
        $t('Édition ODS (tableur) en ligne',                     'todo', 'medium', 'j7', null, '2027-05-31', 8);
        $t('Compatibilité DOCX/XLSX import/export',              'todo', 'medium', 'j7', null, '2027-06-30', 8);

        // ── PHASE 7 — ERP DataGrid (3 tâches) ────────────────────────
        $t('Tables no-code ERP — interface création',            'todo', 'high',   'j8', null, '2027-07-31', 20, null, 'Types de champs, validation, sécurité DDL.');
        $t('Audit trail ERP — historique modifications',         'todo', 'high',   'j8', null, '2027-08-31', 10);
        $t('Export CSV/Excel ERP',                               'todo', 'medium', 'j8', null, '2027-09-30', 6);

        // ── PHASE 8 — Chat temps réel (3 tâches) ─────────────────────
        $t('Chat — canaux et messagerie 1:1',                    'todo', 'high',   'j9', null, '2027-10-31', 24, null, 'WebSocket Soketi. Canaux publics/privés, 1:1.');
        $t('Chat — pièces jointes et notifications',             'todo', 'medium', 'j9', null, '2027-11-30', 10);
        $t('Chat — historique et recherche',                     'todo', 'medium', 'j9', null, '2027-12-31', 8);

        // ── PHASE 9–11 — RSS + Sondages (4 tâches) ───────────────────
        $t('Fil RSS — agrégateur et widget dashboard',           'todo', 'medium', 'j10', null, '2028-01-31', 12);
        $t('Sondages — formulaires et résultats temps réel',     'todo', 'medium', 'j11', null, '2028-04-30', 16);
        $t('Questionnaires — résultats et export',               'todo', 'medium', 'j11', null, '2028-05-31', 10);
        $t('Sondages — accès par rôle',                          'todo', 'low',    'j11', null, '2028-06-30', 4);

        // ── PHASE 12 — Production + sécurité (10 tâches) ─────────────
        $t('VPS production — Nginx + PHP-FPM + MySQL',           'todo', 'urgent', 'j12', null, '2028-07-31', 24, null, 'OVH ou Scaleway France. Ubuntu 24 LTS.');
        $t('Monitoring Statping + Sentry auto-hébergé',          'todo', 'high',   'j12', null, '2028-07-31', 8,  null, 'Health check HTTP, alertes SMS/email.');
        $t('Audit sécurité externe + pentest',                   'todo', 'urgent', 'j12', null, '2028-08-31', 40, null, 'Prestataire externe. Avant toute mise en production publique.');
        $t('PRA — tests scénarios A/B/C',                        'todo', 'high',   'j12', null, '2028-09-30', 16, null, 'RTO/RPO validés. Annexe M.');
        $t('CSP + headers sécurité complets (Apache2)',           'todo', 'high',   'j12', null, '2028-08-31', 6,  null, 'Content-Security-Policy, HSTS renforcés.');
        $t('Rate limiting sous-domaines (mod_evasive)',          'todo', 'medium', 'j12', null, '2028-09-30', 4,  null, 'Protection DDoS niveau serveur web.');
        $t('Rotation clés AES TOTP/LDAP',                        'todo', 'high',   'j12', null, '2028-09-30', 6,  null, 'Procédure rotation sans interruption de service.');
        $t('Rotation audit_logs > 12 mois',                      'todo', 'medium', 'j12', null, '2028-09-30', 3);
        $t('Enforcement quota strict',                           'todo', 'medium', 'j12', null, '2028-09-30', 4,  null, 'Blocage upload si quota dépassé.');
        $t('Audit cross-tenant TenantManager',                   'todo', 'high',   'j12', null, '2028-09-30', 8,  null, 'Vérification isolation absolue entre tenants.');

        // ── PHASE 13 — Open source (4 tâches) ────────────────────────
        $t('Documentation publique — docs.pladigit.fr',          'todo', 'high',   'j13', null, '2029-03-31', 40);
        $t('Publication dépôt GitHub public AGPL-3.0',           'todo', 'high',   'j13', null, '2029-06-30', 8);
        $t('Guide de contribution (CONTRIBUTING.md)',            'todo', 'medium', 'j13', null, '2029-06-30', 8);
        $t('Package Composer pladigit/core',                     'todo', 'medium', 'j13', null, '2029-09-30', 16);

        return $order;
    }

    // ──────────────────────────────────────────────────────────────────
    // 10 ÉVÉNEMENTS AGENDA
    // ──────────────────────────────────────────────────────────────────

    private function seedEvents(Project $project, User $admin): int
    {
        // [title, description, starts_at, ends_at, visibility, color]
        $events = [
            [
                'title'       => 'Session dev Phase 1',
                'description' => 'Développement Phase 1 — Socle multi-tenant, auth, 2FA. 47 tests livrés.',
                'starts_at'   => '2025-10-01 09:00:00',
                'ends_at'     => '2025-12-31 18:00:00',
                'visibility'  => 'private',
                'color'       => '#16A34A',
            ],
            [
                'title'       => 'Session dev Phase 2 — début',
                'description' => 'Profil, directions/services, UserRole enum, invitation email, dashboard Blade.',
                'starts_at'   => '2026-01-15 09:00:00',
                'ends_at'     => '2026-02-28 18:00:00',
                'visibility'  => 'private',
                'color'       => '#3B82F6',
            ],
            [
                'title'       => 'Session dev 13 mars 2026',
                'description' => 'Finalisation Phase 2 : LdapAuthTest, SyncLdapUsersTest, BrandingTest. CDC v2.0 produit. 237 tests.',
                'starts_at'   => '2026-03-13 09:00:00',
                'ends_at'     => '2026-03-13 20:00:00',
                'visibility'  => 'private',
                'color'       => '#3B82F6',
            ],
            [
                'title'       => 'Session dev 14 mars 2026',
                'description' => 'Phase 3 : sync NAS purge, suppression physique, correction NasManager, interface albums.',
                'starts_at'   => '2026-03-14 09:00:00',
                'ends_at'     => '2026-03-14 20:00:00',
                'visibility'  => 'private',
                'color'       => '#EA580C',
            ],
            [
                'title'       => 'Session dev 15 mars 2026',
                'description' => 'Phase 3 : modules activables, watermark GD/TTF, Super Admin refonte, footer légal. 311 tests.',
                'starts_at'   => '2026-03-15 09:00:00',
                'ends_at'     => '2026-03-15 22:00:00',
                'visibility'  => 'private',
                'color'       => '#EA580C',
            ],
            [
                'title'       => 'Session dev 16 mars 2026',
                'description' => 'Phase 3 : colonnes NAS, doublons albums, popup connexion, queue Redis, import ZIP.',
                'starts_at'   => '2026-03-16 09:00:00',
                'ends_at'     => '2026-03-16 22:00:00',
                'visibility'  => 'private',
                'color'       => '#EA580C',
            ],
            [
                'title'       => 'Jalon Phase 3 — cible livraison',
                'description' => 'Date cible livraison Phase 3 — Photothèque NAS complète.',
                'starts_at'   => '2026-06-30 09:00:00',
                'ends_at'     => '2026-06-30 18:00:00',
                'visibility'  => 'restricted',
                'color'       => '#3B82F6',
            ],
            [
                'title'       => 'Jalon Phase 5bis — cible livraison',
                'description' => 'Date cible livraison Phase 5bis — Module Gestion de projet opérationnel.',
                'starts_at'   => '2026-12-31 09:00:00',
                'ends_at'     => '2026-12-31 18:00:00',
                'visibility'  => 'restricted',
                'color'       => '#8B5CF6',
            ],
            [
                'title'       => 'Audit sécurité externe',
                'description' => 'Phase 12 — Audit sécurité externe avant mise en production publique.',
                'starts_at'   => '2028-07-01 09:00:00',
                'ends_at'     => '2028-09-30 18:00:00',
                'visibility'  => 'restricted',
                'color'       => '#DC2626',
            ],
            [
                'title'       => 'Publication open source',
                'description' => 'Phase 13 — Publication du dépôt en AGPL-3.0. Annonce communautaire.',
                'starts_at'   => '2028-10-01 09:00:00',
                'ends_at'     => '2028-10-01 18:00:00',
                'visibility'  => 'public',
                'color'       => '#6366F1',
            ],
        ];

        foreach ($events as $def) {
            $event = Event::on('tenant')->create([
                ...$def,
                'project_id' => $project->id,
                'created_by' => $admin->id,
                'all_day'    => false,
            ]);

            EventParticipant::on('tenant')->create([
                'event_id' => $event->id,
                'user_id'  => $admin->id,
                'status'   => 'accepted',
            ]);
        }

        return count($events);
    }
}
