@extends('layouts.app')
@section('title', 'Projets — Vue d\'ensemble')

@section('content')
<div style="padding:20px;">

{{-- ── En-tête ── --}}
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:12px;">
    <div>
        <h1 style="font-size:20px;font-weight:700;color:var(--pd-navy);margin:0;">Projets</h1>
        <p style="font-size:13px;color:var(--pd-muted);margin:2px 0 0;">Vue d'ensemble — {{ $stats['total'] }} projet{{ $stats['total']>1?'s':'' }}</p>
    </div>
    <div style="display:flex;gap:8px;">
        <a href="{{ route('projects.templates.index') }}" class="pd-btn pd-btn-secondary pd-btn-sm">📋 Modèles</a>
        @can('create', \App\Models\Tenant\Project::class)
        <a href="{{ route('projects.create') }}" class="pd-btn pd-btn-primary pd-btn-sm">+ Nouveau projet</a>
        @endcan
    </div>
</div>

{{-- ── Métriques ── --}}
<div style="display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-bottom:24px;">
    <div style="background:var(--pd-surface);border:0.5px solid var(--pd-border);border-radius:10px;padding:14px 16px;">
        <div style="font-size:11px;color:var(--pd-muted);margin-bottom:4px;">Total</div>
        <div style="font-size:26px;font-weight:700;color:var(--pd-navy);">{{ $stats['total'] }}</div>
    </div>
    <div style="background:var(--pd-surface);border:0.5px solid var(--pd-border);border-radius:10px;padding:14px 16px;">
        <div style="font-size:11px;color:var(--pd-muted);margin-bottom:4px;">Actifs</div>
        <div style="font-size:26px;font-weight:700;color:#059669;">{{ $stats['active'] }}</div>
    </div>
    <div style="background:var(--pd-surface);border:0.5px solid {{ $stats['delayed']>0?'#FCA5A5':'var(--pd-border)' }};border-radius:10px;padding:14px 16px;{{ $stats['delayed']>0?'background:#FEF2F2;':'' }}">
        <div style="font-size:11px;color:var(--pd-muted);margin-bottom:4px;">En alerte</div>
        <div style="font-size:26px;font-weight:700;color:{{ $stats['delayed']>0?'#DC2626':'var(--pd-muted)' }};">{{ $stats['delayed'] }}</div>
    </div>
    <div style="background:var(--pd-surface);border:0.5px solid var(--pd-border);border-radius:10px;padding:14px 16px;">
        <div style="font-size:11px;color:var(--pd-muted);margin-bottom:4px;">Progression moy.</div>
        <div style="font-size:26px;font-weight:700;color:var(--pd-navy);">{{ $stats['avg_pct'] }}%</div>
    </div>
</div>

{{-- ── Jalons proches + Alertes ── --}}
@if($upcomingMilestones->count() > 0 || $alertProjects->count() > 0)
<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:24px;">

    {{-- Jalons 30 jours --}}
    @if($upcomingMilestones->count() > 0)
    <div style="background:var(--pd-surface);border:0.5px solid var(--pd-border);border-radius:10px;padding:16px;">
        <div style="font-size:12px;font-weight:700;color:var(--pd-navy);margin-bottom:12px;display:flex;align-items:center;gap:6px;">
            🏁 <span>Jalons dans les 30 jours</span>
        </div>
        @foreach($upcomingMilestones as $ms)
        @php
            $daysLeft = (int) now()->startOfDay()->diffInDays($ms->due_date->startOfDay(), false);
            $urgent   = $daysLeft <= 7;
        @endphp
        <div style="display:flex;align-items:center;gap:10px;padding:7px 0;border-bottom:0.5px solid var(--pd-border);">
            <div style="width:36px;text-align:center;flex-shrink:0;">
                <div style="font-size:14px;font-weight:700;color:{{ $urgent?'#DC2626':'var(--pd-navy)' }};">{{ $ms->due_date->format('d') }}</div>
                <div style="font-size:9px;text-transform:uppercase;color:var(--pd-muted);">{{ $ms->due_date->translatedFormat('M') }}</div>
            </div>
            <div style="flex:1;min-width:0;">
                <div style="font-size:12px;font-weight:600;color:var(--pd-text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $ms->title }}</div>
                <div style="font-size:10px;color:var(--pd-muted);">{{ $ms->project->name }}</div>
            </div>
            <span style="font-size:10px;padding:2px 7px;border-radius:6px;white-space:nowrap;background:{{ $urgent?'#FEE2E2':'#EFF6FF' }};color:{{ $urgent?'#DC2626':'#1D4ED8' }};">
                {{ $daysLeft === 0 ? 'Aujourd\'hui' : ($daysLeft === 1 ? 'Demain' : 'J-'.$daysLeft) }}
            </span>
        </div>
        @endforeach
    </div>
    @endif

    {{-- Projets en alerte --}}
    @if($alertProjects->count() > 0)
    <div style="background:#FEF2F2;border:0.5px solid #FCA5A5;border-radius:10px;padding:16px;">
        <div style="font-size:12px;font-weight:700;color:#DC2626;margin-bottom:12px;display:flex;align-items:center;gap:6px;">
            ⚠️ <span>Projets en alerte</span>
        </div>
        @foreach($alertProjects as $project)
        <a href="{{ route('projects.show', $project) }}"
           style="display:flex;align-items:center;gap:10px;padding:7px 0;border-bottom:0.5px solid #FECACA;text-decoration:none;">
            <div style="width:10px;height:10px;border-radius:50%;background:{{ $project->color }};flex-shrink:0;"></div>
            <div style="flex:1;min-width:0;">
                <div style="font-size:12px;font-weight:600;color:var(--pd-text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $project->name }}</div>
                <div style="font-size:10px;color:var(--pd-muted);">{{ \App\Models\Tenant\Project::statusLabels()[$project->status] }}</div>
            </div>
            <div style="font-size:11px;font-weight:600;color:#DC2626;">{{ $project->progressionPercent() }}%</div>
        </a>
        @endforeach
    </div>
    @endif

</div>
@endif

{{-- ── Filtres ── --}}
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;flex-wrap:wrap;gap:8px;">
    <div style="display:flex;gap:6px;flex-wrap:wrap;">
        @foreach([''=>'Tous', 'active'=>'Actifs', 'draft'=>'Brouillons', 'on_hold'=>'En pause', 'completed'=>'Terminés', 'archived'=>'Archivés'] as $val => $label)
        <a href="{{ request()->fullUrlWithQuery(['status' => $val ?: null]) }}"
           style="padding:4px 12px;border-radius:20px;font-size:11px;border:0.5px solid {{ request('status','')===$val?'var(--pd-navy)':'var(--pd-border)' }};color:{{ request('status','')===$val?'#fff':'var(--pd-muted)' }};background:{{ request('status','')===$val?'var(--pd-navy)':'var(--pd-surface)' }};text-decoration:none;white-space:nowrap;">
            {{ $label }}
        </a>
        @endforeach
    </div>
    <div style="font-size:11px;color:var(--pd-muted);">{{ $projects->total() }} résultat{{ $projects->total()>1?'s':'' }}</div>
</div>

{{-- ── Grille projets ── --}}
@if($projects->isEmpty())
<div style="text-align:center;padding:60px 20px;color:var(--pd-muted);">
    <div style="font-size:2.5rem;margin-bottom:12px;">📋</div>
    <p style="margin-bottom:16px;">Aucun projet trouvé.</p>
    @can('create', \App\Models\Tenant\Project::class)
    <a href="{{ route('projects.create') }}" class="pd-btn pd-btn-primary">Créer le premier projet</a>
    @endcan
</div>
@else
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:14px;">
    @foreach($projects as $project)
    @php
        $pct        = $project->progressionPercent();
        $nextMs     = $project->milestones->first();
        $daysLeft   = $nextMs ? (int) now()->startOfDay()->diffInDays($nextMs->due_date->startOfDay(), false) : null;
        $statusColors = \App\Models\Tenant\Project::statusColors();
        $statusLabels = \App\Models\Tenant\Project::statusLabels();
    @endphp
    <a href="{{ route('projects.show', $project) }}"
       style="display:block;background:var(--pd-surface);border:0.5px solid var(--pd-border);border-radius:10px;border-top:3px solid {{ $project->color }};padding:14px 16px;text-decoration:none;transition:box-shadow .15s;"
       onmouseover="this.style.boxShadow='0 2px 12px rgba(0,0,0,.08)'"
       onmouseout="this.style.boxShadow='none'">

        {{-- Titre + badge statut --}}
        <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:8px;margin-bottom:6px;">
            <div style="font-size:13px;font-weight:700;color:var(--pd-text);line-height:1.3;flex:1;min-width:0;">
                {{ $project->name }}
                @if($project->is_private)<span style="font-size:10px;"> 🔒</span>@endif
            </div>
            <span style="font-size:10px;padding:2px 7px;border-radius:6px;white-space:nowrap;flex-shrink:0;background:{{ $statusColors[$project->status]['bg'] ?? '#F1F5F9' }};color:{{ $statusColors[$project->status]['text'] ?? '#475569' }};">
                {{ $statusLabels[$project->status] ?? $project->status }}
            </span>
        </div>

        {{-- Description --}}
        @if($project->description)
        <div style="font-size:11px;color:var(--pd-muted);margin-bottom:10px;line-height:1.4;">{{ Str::limit(strip_tags($project->description), 80) }}</div>
        @endif

        {{-- Barre de progression --}}
        <div style="margin-bottom:10px;">
            <div style="display:flex;justify-content:space-between;font-size:10px;color:var(--pd-muted);margin-bottom:3px;">
                <span>Progression</span><span style="font-weight:600;color:{{ $pct >= 100?'#059669':'var(--pd-text)' }};">{{ $pct }}%</span>
            </div>
            <div style="height:4px;background:var(--pd-border);border-radius:2px;overflow:hidden;">
                <div style="height:100%;border-radius:2px;width:{{ $pct }}%;background:{{ $project->color }};"></div>
            </div>
        </div>

        {{-- Meta --}}
        <div style="display:flex;align-items:center;gap:10px;font-size:10px;color:var(--pd-muted);flex-wrap:wrap;">
            @if($nextMs && $daysLeft !== null)
            <span style="color:{{ $daysLeft<=7?'#DC2626':'var(--pd-muted)' }};">
                🏁 {{ $daysLeft===0?'Aujourd\'hui':($daysLeft===1?'Demain':'J-'.$daysLeft) }} — {{ Str::limit($nextMs->title,25) }}
            </span>
            @else
            <span>📅 {{ $project->due_date?$project->due_date->format('d/m/Y'):'—' }}</span>
            @endif
            <span>👥 {{ $project->project_members_count }}</span>
            <span>✓ {{ $project->tasks_count }}</span>
        </div>
    </a>
    @endforeach
</div>

<div style="margin-top:1.5rem;">
    {{ $projects->links() }}
</div>
@endif

</div>
@endsection
