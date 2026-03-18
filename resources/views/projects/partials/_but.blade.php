{{-- _but.blade.php — But & description --}}
@php $members = $project->projectMembers->sortByDesc(fn($m) => $m->role === 'owner'); @endphp

<div class="section-hdr">
    <div>
        <div class="section-title">But &amp; description</div>
        <div class="section-sub">Informations générales du projet</div>
    </div>
    @if($canManage)
    <a href="{{ route('projects.edit', $project) }}" class="btn-sm">Modifier</a>
    @endif
</div>

<div class="stat-grid">
    <div class="stat-card" style="border-top:3px solid {{ $project->color }};grid-column:span 2;">
        <div class="stat-lbl">Avancement global</div>
        <div style="display:flex;align-items:baseline;gap:10px;">
            <div class="stat-val">{{ $progression }}%</div>
            <div style="font-size:12px;color:var(--pd-muted);">{{ $taskStats['done'] }}/{{ $taskStats['total'] }} tâches</div>
        </div>
        <div class="bbar-wrap" style="margin-top:8px;height:8px;">
            <div class="bbar-fill" style="width:{{ $progression }}%;background:{{ $project->color }};"></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-lbl">Échéance</div>
        <div class="stat-val" style="font-size:16px;">{{ $project->due_date?->translatedFormat('d M Y') ?? '—' }}</div>
        @if($project->due_date)
        <div class="stat-sub" style="color:{{ $project->due_date->isPast() ? 'var(--pd-danger)' : 'var(--pd-muted)' }};">
            {{ $project->due_date->isPast() ? 'Dépassée' : $project->due_date->diffForHumans() }}
        </div>
        @endif
    </div>
    <div class="stat-card">
        <div class="stat-lbl">Équipe</div>
        <div class="stat-val">{{ $members->count() }}</div>
        <div class="stat-sub">membres actifs</div>
    </div>
</div>

{{-- Description --}}
@if($project->description)
<div class="pd-card" style="margin-bottom:14px;">
    <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--pd-muted);margin-bottom:8px;">Objectif</div>
    <div style="font-size:13px;line-height:1.7;color:var(--pd-text);" class="trix-content">
        {!! $project->description !!}
    </div>
</div>
@endif

{{-- Jalons --}}
@if($project->milestones->count())
<div class="pd-card" style="margin-bottom:14px;">
    <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--pd-muted);margin-bottom:12px;">Jalons</div>
    @foreach($project->milestones as $ms)
    @php $pct = $ms->progressionPercent(); @endphp
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px;">
        <div style="width:9px;height:9px;border-radius:50%;background:{{ $ms->color ?? '#94A3B8' }};flex-shrink:0;"></div>
        <div style="flex:1;">
            <div style="display:flex;justify-content:space-between;font-size:12px;font-weight:500;">
                <span>{{ $ms->title }}</span>
                <span style="font-size:11px;color:{{ $ms->isLate() ? 'var(--pd-danger)' : 'var(--pd-muted)' }};">
                    {{ $ms->due_date?->translatedFormat('d M Y') }}
                    @if($ms->isReached()) · ✓ @elseif($ms->isLate()) · En retard @endif
                </span>
            </div>
            <div class="bbar-wrap"><div class="bbar-fill" style="width:{{ $pct }}%;background:{{ $ms->color ?? '#94A3B8' }};"></div></div>
        </div>
        <span style="font-size:11px;color:var(--pd-muted);min-width:30px;text-align:right;">{{ $pct }}%</span>
    </div>
    @endforeach
</div>
@endif

{{-- Membres --}}
<div class="pd-card">
    <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--pd-muted);margin-bottom:10px;">Équipe</div>
    @foreach($members as $pm)
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;">
        <div class="sh-avatar" style="background:var(--pd-bg2);color:var(--pd-navy);">{{ strtoupper(substr($pm->user->name,0,2)) }}</div>
        <div style="flex:1;"><div style="font-size:12px;font-weight:500;">{{ $pm->user->name }}</div><div style="font-size:11px;color:var(--pd-muted);">{{ \App\Enums\ProjectRole::tryFrom($pm->role)?->label() ?? $pm->role }}</div></div>
    </div>
    @endforeach
</div>
