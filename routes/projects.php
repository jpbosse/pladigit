<?php

use App\Http\Controllers\Projects\KanbanController;
use App\Http\Controllers\Projects\ProjectBudgetController;
use App\Http\Controllers\Projects\ProjectChangeController;
use App\Http\Controllers\Projects\ProjectController;
use App\Http\Controllers\Projects\ProjectEventController;
use App\Http\Controllers\Projects\ProjectMemberController;
use App\Http\Controllers\Projects\ProjectMilestoneController;
use App\Http\Controllers\Projects\ProjectObservationController;
use App\Http\Controllers\Projects\ProjectStakeholderController;
use App\Http\Controllers\Projects\ProjectTemplateController;
use App\Http\Controllers\Projects\TaskCommentController;
use App\Http\Controllers\Projects\TaskController;

/*
|--------------------------------------------------------------------------
| Routes module Gestion de Projet
|--------------------------------------------------------------------------
|
| Ce fichier est inclus dans routes/web.php dans le groupe middleware tenant
| et auth via :
|
|   Route::middleware(['auth', 'force-pwd-change'])->group(function () {
|       require base_path('routes/projects.php');
|   });
|
| Toutes les routes sont protégées par le middleware module:projects.
|
*/

Route::prefix('projects')
    ->name('projects.')
    ->middleware('module:projects')
    ->group(function () {

        // ── Tableau de bord multi-projets ────────────────────────────────
        Route::get('/dashboard', [ProjectController::class, 'dashboard'])->name('dashboard');

        // ── Modèles de projets (templates) ────────────────────────────────
        Route::prefix('templates')->name('templates.')->group(function () {
            Route::get('/', [ProjectTemplateController::class, 'index'])->name('index');
            Route::get('/create', [ProjectTemplateController::class, 'create'])->name('create');
            Route::post('/', [ProjectTemplateController::class, 'store'])->name('store');
            Route::get('/{template}/edit', [ProjectTemplateController::class, 'edit'])->name('edit');
            Route::put('/{template}', [ProjectTemplateController::class, 'update'])->name('update');
            Route::delete('/{template}', [ProjectTemplateController::class, 'destroy'])->name('destroy');
            Route::post('/{template}/apply', [ProjectTemplateController::class, 'apply'])->name('apply');
        });

        // ── Projets ───────────────────────────────────────────────────────
        Route::get('/', [ProjectController::class, 'index'])->name('index');
        Route::get('/create', [ProjectController::class, 'create'])->name('create');
        Route::post('/', [ProjectController::class, 'store'])->name('store');
        Route::get('/{project}', [ProjectController::class, 'show'])->name('show');
        Route::get('/{project}/edit', [ProjectController::class, 'edit'])->name('edit');
        Route::put('/{project}', [ProjectController::class, 'update'])->name('update');
        Route::delete('/{project}', [ProjectController::class, 'destroy'])->name('destroy');

        // Duplication
        Route::post('/{project}/duplicate', [ProjectController::class, 'duplicate'])->name('duplicate');

        // Créer un template depuis un projet existant
        Route::post('/{project}/save-as-template', [ProjectTemplateController::class, 'fromProject'])->name('save_as_template');

        // Export iCal
        Route::get('/{project}/export/ical', [ProjectController::class, 'exportIcal'])
            ->name('export.ical');

        // Export PDF tableau de bord élus
        Route::get('/{project}/export/pdf', [ProjectController::class, 'exportPdf'])
            ->name('export.pdf');

        // Export ZIP élus (PDF + pièces jointes)
        Route::get('/{project}/export/zip', [ProjectController::class, 'exportZip'])
            ->name('export.zip');

        // ── Kanban AJAX (Alpine.js — ADR-008 révisé, pas de Livewire) ─────
        Route::patch('/{project}/kanban/move', [KanbanController::class, 'move'])->name('kanban.move');
        Route::patch('/{project}/kanban/reorder', [KanbanController::class, 'reorder'])->name('kanban.reorder');

        // ── Tâches ────────────────────────────────────────────────────────
        Route::get('/{project}/tasks/{task}', [TaskController::class, 'show'])->name('tasks.show');
        Route::post('/{project}/tasks', [TaskController::class, 'store'])->name('tasks.store');
        Route::patch('/{project}/tasks/{task}', [TaskController::class, 'update'])->name('tasks.update');
        Route::patch('/{project}/tasks/{task}/dates', [TaskController::class, 'updateDates'])->name('tasks.dates');
        Route::delete('/{project}/tasks/{task}', [TaskController::class, 'destroy'])->name('tasks.destroy');

        // ── Commentaires ──────────────────────────────────────────────────
        Route::post('/{project}/tasks/{task}/comments', [TaskCommentController::class, 'store'])->name('tasks.comments.store');
        Route::delete('/{project}/tasks/{task}/comments/{comment}', [TaskCommentController::class, 'destroy'])->name('tasks.comments.destroy');

        // ── Membres ───────────────────────────────────────────────────────
        Route::post('/{project}/members', [ProjectMemberController::class, 'store'])->name('members.store');
        Route::delete('/{project}/members/{user}', [ProjectMemberController::class, 'destroy'])->name('members.destroy');

        // ── Jalons & Phases ──────────────────────────────────────────────────
        Route::post('/{project}/phases', [ProjectMilestoneController::class, 'storePhase'])->name('phases.store');
        Route::post('/{project}/milestones', [ProjectMilestoneController::class, 'store'])->name('milestones.store');
        Route::patch('/{project}/milestones/{milestone}', [ProjectMilestoneController::class, 'update'])->name('milestones.update');
        Route::patch('/{project}/milestones/{milestone}/move', [ProjectMilestoneController::class, 'move'])->name('milestones.move');
        Route::delete('/{project}/milestones/{milestone}', [ProjectMilestoneController::class, 'destroy'])->name('milestones.destroy');

        // ── Événements agenda ─────────────────────────────────────────────
        Route::post('/{project}/events', [ProjectEventController::class, 'store'])->name('events.store');

        // ── Budget ────────────────────────────────────────────────────────
        Route::post('/{project}/budgets', [ProjectBudgetController::class, 'store'])->name('budgets.store');
        Route::patch('/{project}/budgets/{budget}', [ProjectBudgetController::class, 'update'])->name('budgets.update');
        Route::delete('/{project}/budgets/{budget}', [ProjectBudgetController::class, 'destroy'])->name('budgets.destroy');

        // ── Parties prenantes ─────────────────────────────────────────────
        Route::post('/{project}/stakeholders', [ProjectStakeholderController::class, 'store'])->name('stakeholders.store');
        Route::patch('/{project}/stakeholders/{stakeholder}', [ProjectStakeholderController::class, 'update'])->name('stakeholders.update');
        Route::delete('/{project}/stakeholders/{stakeholder}', [ProjectStakeholderController::class, 'destroy'])->name('stakeholders.destroy');

        // ── Conduite du changement : communication ────────────────────────
        Route::post('/{project}/comm-actions', [ProjectChangeController::class, 'storeCommAction'])->name('comm_actions.store');
        Route::patch('/{project}/comm-actions/{action}', [ProjectChangeController::class, 'updateCommAction'])->name('comm_actions.update');
        Route::delete('/{project}/comm-actions/{action}', [ProjectChangeController::class, 'destroyCommAction'])->name('comm_actions.destroy');

        // ── Conduite du changement : risques ──────────────────────────────
        Route::post('/{project}/risks', [ProjectChangeController::class, 'storeRisk'])->name('risks.store');
        Route::patch('/{project}/risks/{risk}', [ProjectChangeController::class, 'updateRisk'])->name('risks.update');
        Route::delete('/{project}/risks/{risk}', [ProjectChangeController::class, 'destroyRisk'])->name('risks.destroy');

        // ── Observations élus ─────────────────────────────────────────────
        Route::post('/{project}/observations', [ProjectObservationController::class, 'store'])->name('observations.store');
        Route::delete('/{project}/observations/{observation}', [ProjectObservationController::class, 'destroy'])->name('observations.destroy');
    });
