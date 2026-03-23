<?php

namespace Tests\Feature\Projects;

use App\Models\Tenant\Project;
use App\Models\Tenant\ProjectMember;
use App\Models\Tenant\ProjectMilestone;
use App\Models\Tenant\User;
use Tests\TestCase;

/**
 * Tests de l'export iCal jalons (milestones.ics).
 *
 * Couvre :
 *   - Content-Type text/calendar
 *   - X-WR-CALNAME contient le nom du projet
 *   - Jalon normal → emoji 🏁, CATEGORIES:Jalon
 *   - Jalon atteint → STATUS:COMPLETED, CATEGORIES:Jalon Atteint
 *   - Jalon en retard → CATEGORIES:Jalon En retard
 *   - Phase → CATEGORIES:Phase
 *   - PERCENT-COMPLETE présent
 *   - Non-membre → 403
 *   - Nom du fichier contient le slug du projet
 */
class ProjectMilestonesIcalTest extends TestCase
{
    private User $owner;

    private User $member;

    private User $outsider;

    private Project $project;

    /** Phase parente — les jalons de test en sont des enfants (parent_id != null → isPhase() = false). */
    private ProjectMilestone $phase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $this->member = User::factory()->create(['role' => 'user',  'status' => 'active']);
        $this->outsider = User::factory()->create(['role' => 'user',  'status' => 'active']);

        $this->project = Project::factory()->create([
            'created_by' => $this->owner->id,
            'name' => 'Projet Milestones iCal',
            'start_date' => '2026-01-01',
            'due_date' => '2026-12-31',
        ]);

        ProjectMember::create(['project_id' => $this->project->id, 'user_id' => $this->owner->id,  'role' => 'owner']);
        ProjectMember::create(['project_id' => $this->project->id, 'user_id' => $this->member->id, 'role' => 'viewer']);

        // Phase parente (parent_id = null + aura des enfants → isPhase() = true)
        $this->phase = ProjectMilestone::on('tenant')->create([
            'project_id' => $this->project->id,
            'title' => 'Phase principale',
            'due_date' => '2026-12-31',
            'parent_id' => null,
            'color' => '#1E3A5F',
        ]);
    }

    /** Crée un jalon enfant (parent_id non null → isPhase() = false). */
    private function makeJalon(array $attrs): ProjectMilestone
    {
        return ProjectMilestone::on('tenant')->create(array_merge([
            'project_id' => $this->project->id,
            'parent_id' => $this->phase->id,
            'color' => '#EA580C',
        ], $attrs));
    }

    /** Content-Type doit être text/calendar. */
    public function test_retourne_content_type_calendar(): void
    {
        $this->actingAs($this->owner);

        $response = $this->get(route('projects.export.milestones-ical', $this->project));

        $response->assertOk()
            ->assertHeader('Content-Type', 'text/calendar; charset=UTF-8');
    }

    /** Le nom du calendrier contient le nom du projet. */
    public function test_calname_contient_nom_projet(): void
    {
        $this->actingAs($this->owner);

        $response = $this->get(route('projects.export.milestones-ical', $this->project));

        $this->assertStringContainsString('Projet Milestones iCal', $response->getContent());
    }

    /** Un jalon normal apparaît avec la catégorie Jalon et le préfixe 🏁. */
    public function test_jalon_normal_present(): void
    {
        $this->makeJalon(['title' => 'Livraison v1', 'due_date' => '2026-06-30']);

        $this->actingAs($this->owner);
        $content = $this->get(route('projects.export.milestones-ical', $this->project))->getContent();

        $this->assertStringContainsString('Livraison v1', $content);
        $this->assertStringContainsString('CATEGORIES:Jalon', $content);
        $this->assertStringContainsString('STATUS:IN-PROCESS', $content);
    }

    /** Un jalon atteint a STATUS:COMPLETED et la catégorie Jalon Atteint. */
    public function test_jalon_atteint_status_completed(): void
    {
        $this->makeJalon(['title' => 'Jalon terminé', 'due_date' => '2026-03-01', 'reached_at' => now()]);

        $this->actingAs($this->owner);
        $content = $this->get(route('projects.export.milestones-ical', $this->project))->getContent();

        $this->assertStringContainsString('Jalon terminé', $content);
        $this->assertStringContainsString('STATUS:COMPLETED', $content);
        $this->assertStringContainsString('Jalon Atteint', $content);
    }

    /** Un jalon en retard a la catégorie Jalon En retard. */
    public function test_jalon_en_retard_categorie(): void
    {
        $this->makeJalon(['title' => 'Jalon dépassé', 'due_date' => '2025-01-01']);

        $this->actingAs($this->owner);
        $content = $this->get(route('projects.export.milestones-ical', $this->project))->getContent();

        $this->assertStringContainsString('Jalon dépassé', $content);
        $this->assertStringContainsString('Jalon En retard', $content);
    }

    /** Une phase apparaît avec la catégorie Phase. */
    public function test_phase_categorie(): void
    {
        // Une phase = jalon parent_id null qui a des enfants
        $phase = ProjectMilestone::on('tenant')->create([
            'project_id' => $this->project->id,
            'title' => 'Phase de démarrage',
            'due_date' => '2026-04-30',
            'parent_id' => null,
            'color' => '#1E3A5F',
        ]);

        // Créer un jalon enfant pour que isPhase() soit vrai
        ProjectMilestone::on('tenant')->create([
            'project_id' => $this->project->id,
            'title' => 'Jalon enfant',
            'due_date' => '2026-03-31',
            'parent_id' => $phase->id,
            'color' => '#EA580C',
        ]);

        $this->actingAs($this->owner);
        $content = $this->get(route('projects.export.milestones-ical', $this->project))->getContent();

        $this->assertStringContainsString('Phase de démarrage', $content);
        $this->assertStringContainsString('CATEGORIES:Phase', $content);
    }

    /** PERCENT-COMPLETE est présent dans le fichier iCal. */
    public function test_percent_complete_present(): void
    {
        $this->makeJalon(['title' => 'Jalon avec avancement', 'due_date' => '2026-09-30']);

        $this->actingAs($this->owner);
        $content = $this->get(route('projects.export.milestones-ical', $this->project))->getContent();

        $this->assertStringContainsString('PERCENT-COMPLETE:', $content);
    }

    /** Un membre (viewer) peut exporter. */
    public function test_membre_peut_exporter(): void
    {
        $this->actingAs($this->member);

        $response = $this->get(route('projects.export.milestones-ical', $this->project));

        $response->assertOk();
    }

    /** Un non-membre obtient 403. */
    public function test_non_membre_obtient_403(): void
    {
        $this->actingAs($this->outsider);

        $response = $this->get(route('projects.export.milestones-ical', $this->project));

        $response->assertForbidden();
    }

    /** Le nom du fichier contient le slug du projet. */
    public function test_nom_fichier_contient_slug_projet(): void
    {
        $this->actingAs($this->owner);

        $response = $this->get(route('projects.export.milestones-ical', $this->project));

        $disposition = $response->headers->get('Content-Disposition');
        $this->assertStringContainsString('projet-milestones-ical', $disposition);
    }
}
