{{-- resources/views/projects/show.blade.php --}}
@extends('layouts.app')

@section('title', $project->name)

@push('styles')
<style>
.proj-header      { display:flex; align-items:flex-start; gap:16px; margin-bottom:1.5rem; padding-bottom:1rem; border-bottom: 0.5px solid var(--pd-border); max-width:100%; }
.proj-header-actions { display:flex; gap:8px; flex-shrink:0; }
.proj-color-dot   { width:14px; height:14px; border-radius:50%; flex-shrink:0; margin-top:5px; }
.proj-tabs        { display:flex; gap:0; border-bottom: 0.5px solid var(--pd-border); margin-bottom:1.5rem; }
.proj-tab         { padding:10px 18px; font-size:13px; font-weight:500; color:var(--pd-muted); cursor:pointer; border:none; background:none; border-bottom:2px solid transparent; transition:color .15s,border-color .15s; }
.proj-tab.active  { color:var(--pd-navy); border-bottom-color:var(--pd-navy); }
.proj-layout      { display:grid; grid-template-columns:1fr 240px; gap:20px; }
.proj-sidebar     { display:flex; flex-direction:column; gap:14px; }
.proj-sidebar-card{ background:var(--pd-surface); border:0.5px solid var(--pd-border); border-radius:10px; padding:14px; }
.proj-sidebar-title{ font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.05em; color:var(--pd-muted); margin-bottom:10px; }
.stat-row         { display:flex; justify-content:space-between; align-items:center; margin-bottom:6px; font-size:13px; }
.stat-label       { color:var(--pd-muted); }
.member-item      { display:flex; align-items:center; gap:8px; margin-bottom:8px; font-size:13px; }
.member-avatar    { width:26px; height:26px; border-radius:50%; background:var(--pd-navy-light); color:var(--pd-navy); font-size:10px; font-weight:600; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
</style>
@endpush

@section('content')
<div style="padding:20px;">

@php
    $canManage = $userRole?->canManage() || in_array(auth()->user()?->role, ['admin','president','dgs']);
    $canEdit   = $userRole?->canEdit()   || $canManage;
    $initView  = request('view', 'kanban');
@endphp

{{-- En-tête projet --}}
<div class="proj-header">
    <div class="proj-color-dot" style="background:{{ $project->color }};"></div>
    <div style="flex:1;">
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:4px;">
            <h1 style="font-size:20px;font-weight:600;margin:0;">{{ $project->name }}</h1>
            <span class="pd-badge pd-badge-{{ $project->status }}">
                {{ \App\Models\Tenant\Project::statusLabels()[$project->status] ?? $project->status }}
            </span>
        </div>
        @if($project->description)
            <p style="font-size:13px;color:var(--pd-muted);margin:0;">{{ $project->description }}</p>
        @endif
        <div style="display:flex;gap:16px;margin-top:8px;font-size:12px;color:var(--pd-muted);">
            @if($project->start_date)
                <span>Du {{ $project->start_date->format('d/m/Y') }}</span>
            @endif
            @if($project->due_date)
                <span>au {{ $project->due_date->format('d/m/Y') }}</span>
            @endif
            <span>{{ $progression }}% complété</span>
        </div>
    </div>
    @if($canManage)
    <div class="proj-header-actions">
        <a href="{{ route('projects.edit', $project) }}" class="pd-btn pd-btn-sm pd-btn-secondary">Modifier</a>
    </div>
    @endif
</div>

{{-- Onglets — état géré par Alpine.js, tab actif mémorisé dans l'URL --}}
<div x-data="{ tab: '{{ $initView }}' }">

    <div class="proj-tabs">
        @foreach(['kanban'=>'Kanban', 'gantt'=>'Gantt', 'list'=>'Liste', 'agenda'=>'Agenda'] as $key => $label)
        <button class="proj-tab" :class="{ active: tab === '{{ $key }}' }"
                @click="tab = '{{ $key }}'; history.replaceState(null,'',`?view={{ $key }}`)">
            {{ $label }}
        </button>
        @endforeach
    </div>

    <div class="proj-layout">

        {{-- Contenu principal --}}
        <div>
            {{-- Kanban --}}
            <div x-show="tab === 'kanban'" x-cloak>
                @include('projects.partials._kanban')
            </div>

            {{-- Gantt --}}
            <div x-show="tab === 'gantt'" x-cloak>
                @include('projects.partials._gantt')
            </div>

            {{-- Liste --}}
            <div x-show="tab === 'list'" x-cloak>
                @include('projects.partials._list')
            </div>

            {{-- Agenda --}}
            <div x-show="tab === 'agenda'" x-cloak>
                @include('projects.partials._agenda')
            </div>
        </div>

        {{-- Sidebar --}}
        <aside class="proj-sidebar">

            {{-- Progression --}}
            <div class="proj-sidebar-card">
                <div class="proj-sidebar-title">Progression</div>
                <div style="text-align:center;font-size:28px;font-weight:700;color:var(--pd-navy);margin-bottom:8px;">
                    {{ $progression }}%
                </div>
                <div class="pd-progress-bar" style="margin-bottom:12px;">
                    <div class="pd-progress-fill" style="width:{{ $progression }}%;background:{{ $project->color }};"></div>
                </div>
                @foreach(['todo'=>'À faire','in_progress'=>'En cours','in_review'=>'En revue','done'=>'Terminé'] as $s => $label)
                <div class="stat-row">
                    <span class="stat-label">{{ $label }}</span>
                    <span style="font-weight:500;">{{ $taskStats[$s] }}</span>
                </div>
                @endforeach
                <div class="stat-row" style="border-top:0.5px solid var(--pd-border);padding-top:6px;margin-top:4px;">
                    <span class="stat-label">Total</span>
                    <span style="font-weight:600;">{{ $taskStats['total'] }}</span>
                </div>
            </div>

            {{-- Jalons --}}
            @if($project->milestones->count())
            <div class="proj-sidebar-card">
                <div class="proj-sidebar-title">Jalons</div>
                @foreach($project->milestones->take(5) as $milestone)
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;font-size:12px;">
                    <div style="width:10px;height:10px;transform:rotate(45deg);border:2px solid {{ $milestone->color }};flex-shrink:0;{{ $milestone->isReached() ? 'background:'.$milestone->color.';' : '' }}"></div>
                    <span style="{{ $milestone->isReached() ? 'text-decoration:line-through;color:var(--pd-muted);' : ($milestone->isLate() ? 'color:var(--pd-danger);' : '') }}">
                        {{ Str::limit($milestone->title, 30) }}
                    </span>
                    <span style="margin-left:auto;color:var(--pd-muted);">{{ $milestone->due_date->format('d/m') }}</span>
                </div>
                @endforeach
            </div>
            @endif

            {{-- Membres --}}
            <div class="proj-sidebar-card">
                <div class="proj-sidebar-title">Membres ({{ $project->projectMembers->count() }})</div>
                @foreach($project->projectMembers->take(8) as $member)
                <div class="member-item">
                    <div class="member-avatar">{{ strtoupper(substr($member->user->name ?? '?', 0, 2)) }}</div>
                    <div style="flex:1;overflow:hidden;">
                        <div style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $member->user->name ?? '—' }}</div>
                        <div style="font-size:11px;color:var(--pd-muted);">{{ \App\Enums\ProjectRole::from($member->role)->label() }}</div>
                    </div>
                </div>
                @endforeach
                @if($canManage)
                <a href="{{ route('projects.edit', $project) }}#membres" style="font-size:12px;color:var(--pd-navy);">
                    Gérer les membres →
                </a>
                @endif
            </div>

            {{-- Actions --}}
            <div class="proj-sidebar-card">
                <div class="proj-sidebar-title">Actions</div>
                <a href="{{ route('projects.export.ical', $project) }}" class="pd-btn pd-btn-sm pd-btn-secondary" style="width:100%;text-align:center;margin-bottom:6px;display:block;">
                    Exporter iCal
                </a>
                @if($canManage)
                <form method="POST" action="{{ route('projects.destroy', $project) }}"
                      onsubmit="return confirm('Supprimer ce projet ? Cette action est irréversible.')">
                    @csrf @method('DELETE')
                    <button type="submit" class="pd-btn pd-btn-sm pd-btn-danger" style="width:100%;">
                        Supprimer le projet
                    </button>
                </form>
                @endif
            </div>

        </aside>
    </div>
</div>

{{-- Slide-over tâche — masqué par défaut --}}
@include('projects.partials._task_slideover')

</div>
@endsection
