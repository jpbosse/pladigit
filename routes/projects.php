<?php

use App\Http\Controllers\Projects\KanbanController;
use App\Http\Controllers\Projects\ProjectController;
use App\Http\Controllers\Projects\ProjectEventController;
use App\Http\Controllers\Projects\ProjectMemberController;
use App\Http\Controllers\Projects\ProjectMilestoneController;
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

        // ── Projets ───────────────────────────────────────────────────────
        Route::get('/', [ProjectController::class, 'index'])->name('index');
        Route::get('/create', [ProjectController::class, 'create'])->name('create');
        Route::post('/', [ProjectController::class, 'store'])->name('store');
        Route::get('/{project}', [ProjectController::class, 'show'])->name('show');
        Route::get('/{project}/edit', [ProjectController::class, 'edit'])->name('edit');
        Route::put('/{project}', [ProjectController::class, 'update'])->name('update');
        Route::delete('/{project}', [ProjectController::class, 'destroy'])->name('destroy');

        // Export iCal
        Route::get('/{project}/export/ical', [ProjectController::class, 'exportIcal'])
            ->name('export.ical');

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

        // ── Jalons ────────────────────────────────────────────────────────
        Route::post('/{project}/milestones', [ProjectMilestoneController::class, 'store'])->name('milestones.store');
        Route::patch('/{project}/milestones/{milestone}', [ProjectMilestoneController::class, 'update'])->name('milestones.update');
        Route::delete('/{project}/milestones/{milestone}', [ProjectMilestoneController::class, 'destroy'])->name('milestones.destroy');

        // ── Événements agenda ─────────────────────────────────────────────
        Route::post('/{project}/events', [ProjectEventController::class, 'store'])->name('events.store');
    });
