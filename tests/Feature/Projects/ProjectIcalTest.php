<?php

namespace Tests\Feature\Projects;

use App\Models\Tenant\Event;
use App\Models\Tenant\Project;
use App\Models\Tenant\ProjectMember;
use App\Models\Tenant\ProjectMilestone;
use App\Models\Tenant\User;
use Tests\TestCase;

/**
 * Tests de l'export iCal d'un projet.
 *
 * Couvre :
 *   - Format de réponse Content-Type text/calendar
 *   - Présence des événements du projet
 *   - Présence des jalons comme VEVENT
 *   - Événements privés exclus pour les non-créateurs
 *   - 403 pour un non-membre
 */
class ProjectIcalTest extends TestCase
{
    private User $owner;

    private User $viewer;

    private User $outsider;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner    = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $this->viewer   = User::factory()->create(['role' => 'user',  'status' => 'active']);
        $this->outsider = User::factory()->create(['role' => 'user',  'status' => 'active']);

        $this->project = Project::factory()->create([
            'created_by' => $this->owner->id,
            'name'       => 'Projet iCal Test',
            'start_date' => '2026-01-01',
            'due_date'   => '2026-12-31',
        ]);

        ProjectMember::create(['project_id' => $this->project->id, 'user_id' => $this->owner->id,  'role' => 'owner']);
        ProjectMember::create(['project_id' => $this->project->id, 'user_id' => $this->viewer->id, 'role' => 'viewer']);
    }

    public function test_export_ical_retourne_content_type_calendar(): void
    {
        $this->actingAs($this->owner);

        $response = $this->get(route('projects.export.ical', $this->project));

        $response->assertOk()
            ->assertHeader('Content-Type', 'text/calendar; charset=UTF-8');
    }

    public function test_export_ical_contient_header_vcalendar(): void
    {
        $this->actingAs($this->owner);

        $response = $this->get(route('projects.export.ical', $this->project));

        $content = $response->getContent();
        $this->assertStringContainsString('BEGIN:VCALENDAR', $content);
        $this->assertStringContainsString('END:VCALENDAR', $content);
        $this->assertStringContainsString('VERSION:2.0', $content);
        $this->assertStringContainsString('PRODID:-//Pladigit', $content);
    }

    public function test_export_inclut_les_evenements_publics(): void
    {
        Event::on('tenant')->create([
            'project_id'  => $this->project->id,
            'created_by'  => $this->owner->id,
            'title'       => 'Réunion de lancement',
            'starts_at'   => '2026-03-01 10:00:00',
            'ends_at'     => '2026-03-01 12:00:00',
            'visibility'  => 'public',
            'all_day'     => false,
            'color'       => '#1E3A5F',
        ]);

        $this->actingAs($this->viewer);

        $response = $this->get(route('projects.export.ical', $this->project));

        $this->assertStringContainsString('Réunion de lancement', $response->getContent());
    }

    public function test_export_exclut_evenements_prives_pour_non_createur(): void
    {
        Event::on('tenant')->create([
            'project_id'  => $this->project->id,
            'created_by'  => $this->owner->id,
            'title'       => 'Note privée owner',
            'starts_at'   => '2026-03-01 10:00:00',
            'ends_at'     => '2026-03-01 11:00:00',
            'visibility'  => 'private',
            'all_day'     => false,
            'color'       => '#1E3A5F',
        ]);

        // Le viewer ne doit pas voir l'événement privé du owner
        $this->actingAs($this->viewer);
        $response = $this->get(route('projects.export.ical', $this->project));
        $this->assertStringNotContainsString('Note privée owner', $response->getContent());
    }

    public function test_export_inclut_evenement_prive_pour_son_createur(): void
    {
        Event::on('tenant')->create([
            'project_id'  => $this->project->id,
            'created_by'  => $this->owner->id,
            'title'       => 'Note privée owner',
            'starts_at'   => '2026-03-01 10:00:00',
            'ends_at'     => '2026-03-01 11:00:00',
            'visibility'  => 'private',
            'all_day'     => false,
            'color'       => '#1E3A5F',
        ]);

        // Le owner voit son propre événement privé
        $this->actingAs($this->owner);
        $response = $this->get(route('projects.export.ical', $this->project));
        $this->assertStringContainsString('Note privée owner', $response->getContent());
    }

    public function test_export_inclut_les_jalons(): void
    {
        ProjectMilestone::on('tenant')->create([
            'project_id' => $this->project->id,
            'title'      => 'Jalon Phase 3',
            'due_date'   => '2026-06-30',
            'color'      => '#EA580C',
        ]);

        $this->actingAs($this->owner);

        $response = $this->get(route('projects.export.ical', $this->project));

        $content = $response->getContent();
        $this->assertStringContainsString('Jalon Phase 3', $content);
        $this->assertStringContainsString('milestone-', $content); // UID du jalon
    }

    public function test_non_membre_obtient_403(): void
    {
        $this->actingAs($this->outsider);

        $response = $this->get(route('projects.export.ical', $this->project));

        $response->assertForbidden();
    }

    public function test_nom_fichier_contient_slug_projet(): void
    {
        $this->actingAs($this->owner);

        $response = $this->get(route('projects.export.ical', $this->project));

        $disposition = $response->headers->get('Content-Disposition');
        $this->assertStringContainsString('projet-ical-test.ics', $disposition);
    }
}
