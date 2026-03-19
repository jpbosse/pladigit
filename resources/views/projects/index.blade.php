@extends('layouts.app')
@section('title', 'Projets')

@push('styles')
<style>
.proj-page-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--pd-border);
    width: 100%;
}


.proj-page-title { font-size: 20px; font-weight: 700; color: var(--pd-navy); margin: 0; }
.proj-page-sub   { font-size: 13px; color: var(--pd-muted); margin: 2px 0 0; }
.proj-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 16px;
}
.proj-card {
    background: var(--pd-bg); border: 1px solid var(--pd-border);
    border-radius: 10px; padding: 16px;
    transition: box-shadow .15s;
}
.proj-card:hover { box-shadow: 0 2px 12px rgba(0,0,0,.08); }
.proj-card-title { font-size: 14px; font-weight: 600; margin: 0 0 6px; }
.proj-card-desc  { font-size: 12px; color: var(--pd-muted); margin: 0 0 12px; }
.proj-progress-bar { height: 5px; background: var(--pd-border); border-radius: 3px; overflow: hidden; margin-bottom: 4px; }
.proj-progress-fill { height: 100%; border-radius: 3px; }
.proj-meta { display: flex; gap: 12px; font-size: 11px; color: var(--pd-muted); margin-top: 8px; }
.proj-filter-chip {
    padding: 4px 12px; border-radius: 20px; font-size: 12px;
    border: 1px solid var(--pd-border); color: var(--pd-muted);
    text-decoration: none; transition: all .15s;
}
.proj-filter-chip.active, .proj-filter-chip:hover {
    background: var(--pd-navy); color: #fff; border-color: var(--pd-navy);
}
.proj-badge {
    font-size: 11px; padding: 2px 8px; border-radius: 10px; font-weight: 500;
}
.proj-badge-draft    { background: #F1F5F9; color: #475569; border: 1px dashed #CBD5E1; }
.proj-badge-active    { background: #D1FAE5; color: #065F46; }
.proj-badge-on_hold   { background: #FEF3C7; color: #92400E; }
.proj-badge-completed { background: #DBEAFE; color: #1E40AF; }
.proj-badge-archived  { background: #E2E8F0; color: #475569; }
.proj-empty { text-align: center; padding: 60px 20px; color: var(--pd-muted); }
</style>
@endpush

@section('content')
<div style="padding:20px;">

<div class="proj-page-header">
    <div>
        <h1 class="proj-page-title">Projets</h1>
        <p class="proj-page-sub">{{ $projects->total() }} projet{{ $projects->total() > 1 ? 's' : '' }}</p>
    </div>
    <div style="display:flex;gap:8px;">
        <a href="{{ route('projects.templates.index') }}" class="pd-btn pd-btn-secondary">
            📋 Modèles
        </a>
        <a href="{{ route('projects.dashboard') }}" class="pd-btn pd-btn-secondary">
            📊 Tableau de bord
        </a>
        @can('create', \App\Models\Tenant\Project::class)
        <a href="{{ route('projects.create') }}" class="pd-btn pd-btn-primary">
            + Nouveau projet
        </a>
        @endcan
    </div>
</div>

{{-- Filtres --}}
<div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:1.5rem;">
    @foreach([''=>'Tous', 'draft'=>'Brouillons', 'active'=>'Actifs', 'on_hold'=>'En pause', 'completed'=>'Terminés', 'archived'=>'Archivés'] as $val => $label)
    <a href="{{ request()->fullUrlWithQuery(['status' => $val ?: null]) }}"
       class="proj-filter-chip {{ request('status', '') === $val ? 'active' : '' }}">
        {{ $label }}
    </a>
    @endforeach
</div>

{{-- Grille projets --}}
@if($projects->isEmpty())
    <div class="proj-empty">
        <p style="margin-bottom:16px;">Aucun projet trouvé.</p>
        @can('create', \App\Models\Tenant\Project::class)
            <a href="{{ route('projects.create') }}" class="pd-btn pd-btn-primary">Créer le premier projet</a>
        @endcan
    </div>
@else
<div class="proj-grid">
    @foreach($projects as $project)
    <div class="proj-card" style="border-top: 4px solid {{ $project->color }};">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:8px;">
            <h3 class="proj-card-title">
                <a href="{{ route('projects.show', $project) }}" style="color:inherit;text-decoration:none;">
                    {{ $project->name }}
                </a>
            </h3>
            <span class="proj-badge proj-badge-{{ $project->status }}">
                {{ \App\Models\Tenant\Project::statusLabels()[$project->status] ?? $project->status }}
            </span>
            @if($project->is_private)
            <span class="proj-badge" style="background:#EDE9FE;color:#6D28D9;border:1px solid #C4B5FD;">🔒 Privé</span>
            @endif
        </div>

        @if($project->description)
            <p class="proj-card-desc">{{ Str::limit($project->description, 100) }}</p>
        @endif

        @php $pct = $project->progressionPercent(); @endphp
        <div>
            <div style="display:flex;justify-content:space-between;font-size:11px;color:var(--pd-muted);margin-bottom:4px;">
                <span>Progression</span><span>{{ $pct }}%</span>
            </div>
            <div class="proj-progress-bar">
                <div class="proj-progress-fill" style="width:{{ $pct }}%;background:{{ $project->color }};"></div>
            </div>
        </div>

        <div class="proj-meta">
            <span>📅 {{ $project->due_date ? $project->due_date->format('d/m/Y') : '—' }}</span>
            <span>👥 {{ $project->project_members_count }} membre{{ $project->project_members_count > 1 ? 's' : '' }}</span>
            <span>✓ {{ $project->tasks_count }} tâche{{ $project->tasks_count > 1 ? 's' : '' }}</span>
        </div>
    </div>
    @endforeach
</div>

<div style="margin-top:2rem;">
    {{ $projects->links() }}
</div>
@endif

</div>
@endsection
