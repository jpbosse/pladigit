<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajout des index manquants sur les tables tenant.
 *
 * Audit réalisé le 2026-03-25 via SHOW INDEX / information_schema.STATISTICS.
 * Seuls les index réellement absents sont listés — les FK et unique constraints
 * existantes couvrent déjà la plupart des colonnes de jointure.
 *
 * Exclusions notables :
 *  - media_items/albums/share_links : tous les index sont déjà présents
 *  - album_user_permissions.user_id : couvert par la FK et l'unique
 *  - is_duplicate : colonne ajoutée dans la migration 2026_04_03 (pas encore en template)
 */
return new class extends Migration
{
    public function connection(): string
    {
        return 'tenant';
    }

    public function up(): void
    {
        // ── 1. Projets & tâches ──────────────────────────────────────────

        Schema::connection('tenant')->table('projects', function (Blueprint $table) {
            $table->index('status');
            $table->index('deleted_at');
            $table->index(['status', 'updated_at']);
        });

        Schema::connection('tenant')->table('tasks', function (Blueprint $table) {
            $table->index('deleted_at');
            $table->index(['project_id', 'status']);
            $table->index(['assigned_to', 'status']);
        });

        Schema::connection('tenant')->table('task_comments', function (Blueprint $table) {
            $table->index('deleted_at');
        });

        Schema::connection('tenant')->table('project_milestones', function (Blueprint $table) {
            $table->index('deleted_at');
        });

        Schema::connection('tenant')->table('project_stakeholders', function (Blueprint $table) {
            $table->index('deleted_at');
        });

        Schema::connection('tenant')->table('project_risks', function (Blueprint $table) {
            $table->index('deleted_at');
        });

        Schema::connection('tenant')->table('project_observations', function (Blueprint $table) {
            $table->index('deleted_at');
        });

        Schema::connection('tenant')->table('project_budgets', function (Blueprint $table) {
            $table->index('deleted_at');
        });

        Schema::connection('tenant')->table('project_comm_actions', function (Blueprint $table) {
            $table->index('deleted_at');
        });

        Schema::connection('tenant')->table('project_documents', function (Blueprint $table) {
            $table->index('deleted_at');
        });

        // ── 2. Infrastructure transverse ─────────────────────────────────

        Schema::connection('tenant')->table('users', function (Blueprint $table) {
            $table->index('deleted_at');
        });

        Schema::connection('tenant')->table('notifications', function (Blueprint $table) {
            // Existant : (user_id, read) — ajout de (user_id, created_at) pour tri chrono
            $table->index(['user_id', 'created_at']);
        });

        Schema::connection('tenant')->table('shares', function (Blueprint $table) {
            $table->index('shared_by');
        });

        // ── 3. Departments & GED ─────────────────────────────────────────

        Schema::connection('tenant')->table('departments', function (Blueprint $table) {
            $table->index('deleted_at');
        });

        Schema::connection('tenant')->table('user_department', function (Blueprint $table) {
            $table->index('is_manager');
        });

        Schema::connection('tenant')->table('documents', function (Blueprint $table) {
            $table->index('deleted_at');
            $table->index(['folder_id', 'status']);
        });

        Schema::connection('tenant')->table('events', function (Blueprint $table) {
            $table->index('deleted_at');
            $table->index(['project_id', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('projects', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['deleted_at']);
            $table->dropIndex(['status', 'updated_at']);
        });

        Schema::connection('tenant')->table('tasks', function (Blueprint $table) {
            $table->dropIndex(['deleted_at']);
            $table->dropIndex(['project_id', 'status']);
            $table->dropIndex(['assigned_to', 'status']);
        });

        Schema::connection('tenant')->table('task_comments', function (Blueprint $table) {
            $table->dropIndex(['deleted_at']);
        });

        Schema::connection('tenant')->table('project_milestones', function (Blueprint $table) {
            $table->dropIndex(['deleted_at']);
        });

        Schema::connection('tenant')->table('project_stakeholders', function (Blueprint $table) {
            $table->dropIndex(['deleted_at']);
        });

        Schema::connection('tenant')->table('project_risks', function (Blueprint $table) {
            $table->dropIndex(['deleted_at']);
        });

        Schema::connection('tenant')->table('project_observations', function (Blueprint $table) {
            $table->dropIndex(['deleted_at']);
        });

        Schema::connection('tenant')->table('project_budgets', function (Blueprint $table) {
            $table->dropIndex(['deleted_at']);
        });

        Schema::connection('tenant')->table('project_comm_actions', function (Blueprint $table) {
            $table->dropIndex(['deleted_at']);
        });

        Schema::connection('tenant')->table('project_documents', function (Blueprint $table) {
            $table->dropIndex(['deleted_at']);
        });

        Schema::connection('tenant')->table('users', function (Blueprint $table) {
            $table->dropIndex(['deleted_at']);
        });

        Schema::connection('tenant')->table('notifications', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'created_at']);
        });

        Schema::connection('tenant')->table('shares', function (Blueprint $table) {
            $table->dropIndex(['shared_by']);
        });

        Schema::connection('tenant')->table('departments', function (Blueprint $table) {
            $table->dropIndex(['deleted_at']);
        });

        Schema::connection('tenant')->table('user_department', function (Blueprint $table) {
            $table->dropIndex(['is_manager']);
        });

        Schema::connection('tenant')->table('documents', function (Blueprint $table) {
            $table->dropIndex(['deleted_at']);
            $table->dropIndex(['folder_id', 'status']);
        });

        Schema::connection('tenant')->table('events', function (Blueprint $table) {
            $table->dropIndex(['deleted_at']);
            $table->dropIndex(['project_id', 'starts_at']);
        });
    }
};
