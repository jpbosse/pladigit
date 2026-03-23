@extends('layouts.app')
@section('title', 'Tableau de bord — Projets')

@push('styles')
<style>
/* ── Layout général ───────────────────────────────────────────────── */
.mpd-wrap        { padding: 20px; max-width: 1400px; }
.mpd-hdr         { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; padding-bottom: 14px; border-bottom: 0.5px solid var(--pd-border); }
.mpd-title       { font-size: 20px; font-weight: 700; color: var(--pd-navy); margin: 0; }
.mpd-sub         { font-size: 12px; color: var(--pd-muted); margin: 3px 0 0; }

/* ── Onglets de navigation ────────────────────────────────────────── */
.mpd-tabs        { display: flex; gap: 2px; border-bottom: 0.5px solid var(--pd-border); margin-bottom: 20px; }
.mpd-tab         { padding: 8px 16px; font-size: 12px; font-weight: 500; color: var(--pd-muted); cursor: pointer; border: none; background: none; border-bottom: 2px solid transparent; transition: all .15s; }
.mpd-tab:hover   { color: var(--pd-navy); }
.mpd-tab.active  { color: var(--pd-navy); border-bottom-color: var(--pd-navy); }

/* ── Grilles ─────────────────────────────────────────────────────── */
.mpd-kpi-grid    { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 20px; }
.mpd-grid-2      { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px; }
.mpd-grid-3      { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; margin-bottom: 16px; }
@media (max-width: 1100px) { .mpd-kpi-grid { grid-template-columns: repeat(2, 1fr); } .mpd-grid-3 { grid-template-columns: 1fr 1fr; } }
@media (max-width: 720px)  { .mpd-kpi-grid, .mpd-grid-2, .mpd-grid-3 { grid-template-columns: 1fr; } }

/* ── Carte KPI ───────────────────────────────────────────────────── */
.kpi-card        { background: var(--pd-surface); border: 0.5px solid var(--pd-border); border-radius: 10px; padding: 16px; }
.kpi-lbl         { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: var(--pd-muted); margin-bottom: 6px; }
.kpi-val         { font-size: 28px; font-weight: 700; color: var(--pd-navy); line-height: 1; margin-bottom: 4px; }
.kpi-sub         { font-size: 11px; color: var(--pd-muted); }
.kpi-bar         { height: 4px; background: var(--pd-border); border-radius: 2px; margin-top: 10px; overflow: hidden; }
.kpi-bar-fill    { height: 100%; border-radius: 2px; transition: width .3s; }

/* ── Carte section ───────────────────────────────────────────────── */
.mpd-card        { background: var(--pd-surface); border: 0.5px solid var(--pd-border); border-radius: 10px; padding: 16px; }
.mpd-card-title  { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: var(--pd-muted); margin-bottom: 12px; display: flex; align-items: center; justify-content: space-between; }

/* ── Ligne d'alerte ──────────────────────────────────────────────── */
.alert-row       { display: flex; align-items: flex-start; gap: 10px; padding: 9px 12px; border-radius: 8px; font-size: 12px; margin-bottom: 6px; }
.alert-row:last-child { margin-bottom: 0; }

/* ── Liste tâches ────────────────────────────────────────────────── */
.task-row        { display: flex; align-items: center; gap: 10px; padding: 9px 0; border-bottom: 0.5px solid var(--pd-border); font-size: 12px; }
.task-row:last-child { border-bottom: none; }
.task-proj-dot   { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
.task-title      { flex: 1; color: var(--pd-text); font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.task-proj       { font-size: 10px; color: var(--pd-muted); white-space: nowrap; }
.task-due        { font-size: 10px; color: var(--pd-muted); white-space: nowrap; }

/* ── Badges priorité ─────────────────────────────────────────────── */
.prio-badge      { font-size: 10px; padding: 1px 6px; border-radius: 6px; font-weight: 600; flex-shrink: 0; }
.prio-urgent     { background: #FEE2E2; color: #991B1B; }
.prio-high       { background: #FEF3C7; color: #92400E; }
.prio-medium     { background: #DBEAFE; color: #1E40AF; }
.prio-low        { background: #F1F5F9; color: #475569; }

/* ── Jalon ligne ─────────────────────────────────────────────────── */
.ms-row          { display: flex; align-items: center; gap: 10px; padding: 8px 0; border-bottom: 0.5px solid var(--pd-border); font-size: 12px; }
.ms-row:last-child { border-bottom: none; }
.ms-dot          { font-size: 14px; flex-shrink: 0; }
.ms-title        { flex: 1; font-weight: 500; color: var(--pd-text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ms-proj         { font-size: 10px; color: var(--pd-muted); white-space: nowrap; }
.ms-date         { font-size: 10px; font-weight: 600; white-space: nowrap; flex-shrink: 0; }

/* ── Carte projet mini ───────────────────────────────────────────── */
.proj-mini       { display: flex; align-items: center; gap: 10px; padding: 9px 0; border-bottom: 0.5px solid var(--pd-border); }
.proj-mini:last-child { border-bottom: none; }
.proj-mini-bar   { flex: 1; }
.proj-mini-name  { font-size: 12px; font-weight: 600; color: var(--pd-text); margin-bottom: 4px; }
.proj-mini-meta  { font-size: 10px; color: var(--pd-muted); }
.prog-bar        { height: 5px; background: var(--pd-border); border-radius: 3px; overflow: hidden; margin-top: 4px; }
.prog-fill       { height: 100%; border-radius: 3px; }
.proj-pct        { font-size: 12px; font-weight: 700; color: var(--pd-navy); width: 34px; text-align: right; flex-shrink: 0; }

/* ── Stat badge ──────────────────────────────────────────────────── */
.stat-pill       { display: inline-flex; align-items: center; gap: 4px; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
.pill-green      { background: #D1FAE5; color: #065F46; }
.pill-yellow     { background: #FEF3C7; color: #92400E; }
.pill-red        { background: #FEE2E2; color: #991B1B; }
.pill-blue       { background: #DBEAFE; color: #1E40AF; }
.pill-gray       { background: #F1F5F9; color: #475569; }

/* ── Vue liste projets (onglet Projets) ──────────────────────────── */
.pl-row          { display: flex; align-items: center; gap: 12px; padding: 11px 0; border-bottom: 0.5px solid var(--pd-border); font-size: 12px; text-decoration: none; color: inherit; transition: background .1s; }
.pl-row:last-child { border-bottom: none; }
.pl-row:hover    { background: var(--pd-bg2); border-radius: 6px; padding-left: 6px; }
.pl-color        { width: 4px; height: 36px; border-radius: 2px; flex-shrink: 0; }
.pl-name         { font-weight: 600; color: var(--pd-text); flex: 1; }
.pl-status       { width: 80px; flex-shrink: 0; }
.pl-prog         { width: 100px; flex-shrink: 0; }
.pl-tasks        { width: 60px; text-align: right; color: var(--pd-muted); flex-shrink: 0; }
.pl-date         { width: 80px; text-align: right; color: var(--pd-muted); flex-shrink: 0; }
.pl-members      { width: 60px; text-align: right; color: var(--pd-muted); flex-shrink: 0; }
</style>
@endpush

@section('content')
@php
$fmt    = fn($v) => number_format($v, 0, ',', ' ').' €';
$pLabels = \App\Models\Tenant\Project::statusLabels();
$pColors = \App\Models\Tenant\Project::statusColors();
$prioLabels = ['urgent'=>'Urgente','high'=>'Haute','medium'=>'Moyenne','low'=>'Basse'];
@endphp

<div class="mpd-wrap">

{{-- ── En-tête ─────────────────────────────────────────────────────────── --}}
<div class="mpd-hdr">
    <div>
        <h1 class="mpd-title">Tableau de bord — Projets</h1>
        <p class="mpd-sub">{{ now()->translatedFormat('l d F Y') }} · {{ $totalProjects }} projet{{ $totalProjects > 1 ? 's' : '' }} en cours</p>
    </div>
    <div style="display:flex;gap:8px;">
        <a href="{{ route('projects.index') }}" class="pd-btn pd-btn-secondary" style="font-size:12px;">
            ☰ Liste
        </a>
        @can('create', \App\Models\Tenant\Project::class)
        <a href="{{ route('projects.create') }}" class="pd-btn pd-btn-primary" style="font-size:12px;">
            + Nouveau projet
        </a>
        @endcan
    </div>
</div>

{{-- ── Navigation onglets ──────────────────────────────────────────────── --}}
<div x-data="{ tab: '{{ request('tab', 'synthese') }}' }">
<div class="mpd-tabs">
    <button class="mpd-tab" :class="{ active: tab === 'synthese' }" @click="tab='synthese'; history.replaceState(null,'',location.pathname+'?tab=synthese')">Synthèse</button>
    <button class="mpd-tab" :class="{ active: tab === 'projets' }"  @click="tab='projets';  history.replaceState(null,'',location.pathname+'?tab=projets')">Projets ({{ $totalProjects }})</button>
    <button class="mpd-tab" :class="{ active: tab === 'taches' }"   @click="tab='taches';   history.replaceState(null,'',location.pathname+'?tab=taches')">Mes tâches ({{ $myTasks->count() }})</button>
    <button class="mpd-tab" :class="{ active: tab === 'jalons' }"   @click="tab='jalons';   history.replaceState(null,'',location.pathname+'?tab=jalons')">
        Jalons
        @if($lateMilestones->count())
        <span style="display:inline-block;background:#FEE2E2;color:#991B1B;border-radius:8px;font-size:9px;font-weight:700;padding:1px 5px;margin-left:4px;">{{ $lateMilestones->count() }} retard</span>
        @endif
    </button>

</div>{{-- /mpd-tabs barre --}}
{{-- ════════════════════════════════════════════════════════════════════════ --}}
{{-- ONGLET SYNTHÈSE                                                          --}}
{{-- ════════════════════════════════════════════════════════════════════════ --}}
<div style="width:100%;" x-show="tab === 'synthese'" x-cloak>

    {{-- KPIs globaux --}}
    <div class="mpd-kpi-grid">

        {{-- Projets actifs --}}
        <div class="kpi-card" style="border-top: 3px solid var(--pd-navy);">
            <div class="kpi-lbl">Projets actifs</div>
            <div class="kpi-val">{{ $activeProjects }}</div>
            <div class="kpi-sub">
                @if($onHoldProjects) {{ $onHoldProjects }} en pause · @endif
                {{ $totalProjects }} au total
            </div>
        </div>

        {{-- Avancement moyen --}}
        @php $avgColor = $avgProgression >= 75 ? '#16A34A' : ($avgProgression >= 40 ? '#D97706' : '#E24B4A'); @endphp
        <div class="kpi-card" style="border-top: 3px solid {{ $avgColor }};">
            <div class="kpi-lbl">Avancement moyen</div>
            <div class="kpi-val" style="color: {{ $avgColor }};">{{ $avgProgression }}%</div>
            <div class="kpi-sub">
                @if($lateProjects->count())
                    <span style="color:#E24B4A;font-weight:600;">{{ $lateProjects->count() }} projet{{ $lateProjects->count()>1?'s':'' }} en retard</span>
                @else
                    Tous dans les délais
                @endif
            </div>
            <div class="kpi-bar"><div class="kpi-bar-fill" style="width:{{ $avgProgression }}%;background:{{ $avgColor }};"></div></div>
        </div>

        {{-- Budget global --}}
        @php $budgetColor = $budgetPct > 100 ? '#E24B4A' : ($budgetPct > 85 ? '#D97706' : '#16A34A'); @endphp
        <div class="kpi-card" style="border-top: 3px solid {{ $budgetColor }};">
            <div class="kpi-lbl">Budget engagé</div>
            <div class="kpi-val" style="color: {{ $budgetColor }};">{{ $budgetPct }}%</div>
            <div class="kpi-sub">{{ $fmt($budgetCommitted) }} / {{ $fmt($budgetPlanned) }}</div>
            <div class="kpi-bar"><div class="kpi-bar-fill" style="width:{{ min($budgetPct,100) }}%;background:{{ $budgetColor }};"></div></div>
        </div>

        {{-- Risques critiques --}}
        @php $riskColor = $criticalRisks->count() > 0 ? '#E24B4A' : '#16A34A'; @endphp
        <div class="kpi-card" style="border-top: 3px solid {{ $riskColor }};">
            <div class="kpi-lbl">Risques critiques</div>
            <div class="kpi-val" style="color: {{ $riskColor }};">{{ $criticalRisks->count() }}</div>
            <div class="kpi-sub">
                @if($criticalRisks->count())
                    Sur {{ $criticalRisks->pluck('project')->unique('id')->count() }} projet{{ $criticalRisks->pluck('project')->unique('id')->count()>1?'s':'' }}
                @else
                    Aucun risque critique actif
                @endif
            </div>
        </div>

    </div>

    {{-- ── Rangée principale : alertes + jalons ── --}}
    <div class="mpd-grid-2">

        {{-- Alertes transversales --}}
        <div class="mpd-card">
            <div class="mpd-card-title">Points de vigilance</div>

            @php
            $alerts = collect();
            if ($lateProjects->count())
                $alerts->push(['lvl'=>'danger', 'cat'=>'Délais', 'msg' => $lateProjects->count().' projet'.($lateProjects->count()>1?'s':'').' dépass'.($lateProjects->count()>1?'ent':'e').' la date cible : '.implode(', ', $lateProjects->pluck('name')->take(3)->toArray()).($lateProjects->count()>3?' et '.(($lateProjects->count()-3)).' autre(s)':'').'.']);
            if ($lateMilestones->count())
                $alerts->push(['lvl'=>'danger', 'cat'=>'Jalons', 'msg' => $lateMilestones->count().' jalon'.($lateMilestones->count()>1?'s':'').' en retard — action requise.']);
            if ($criticalRisks->count())
                $alerts->push(['lvl'=>'danger', 'cat'=>'Risques', 'msg' => $criticalRisks->count().' risque'.($criticalRisks->count()>1?'s critiques':'critique').' actif'.($criticalRisks->count()>1?'s':'').' sans plan de mitigation validé.']);
            if ($budgetPct > 100)
                $alerts->push(['lvl'=>'danger', 'cat'=>'Budget', 'msg' => 'Dépassement budgétaire global ('.$budgetPct.'% engagé) — arbitrage requis.']);
            if ($myOverdueTasks)
                $alerts->push(['lvl'=>'warn',   'cat'=>'Tâches', 'msg' => $myOverdueTasks.' de mes tâches '.($myOverdueTasks>1?'sont':'est').' en retard.']);
            if ($upcomingMilestones->count())
                $alerts->push(['lvl'=>'ok',     'cat'=>'Jalons', 'msg' => $upcomingMilestones->count().' jalon'.($upcomingMilestones->count()>1?'s':'').' à venir dans les 30 prochains jours.']);
            @endphp

            @forelse($alerts as $a)
            @php
            $bg   = $a['lvl']==='danger' ? '#FEE2E2' : ($a['lvl']==='warn' ? '#FEF3C7' : '#D1FAE5');
            $bd   = $a['lvl']==='danger' ? '#E24B4A' : ($a['lvl']==='warn' ? '#D97706' : '#16A34A');
            $tc   = $a['lvl']==='danger' ? '#991B1B' : ($a['lvl']==='warn' ? '#92400E' : '#065F46');
            $tc2  = $a['lvl']==='danger' ? '#7F1D1D' : ($a['lvl']==='warn' ? '#78350F' : '#14532D');
            @endphp
            <div class="alert-row" style="background:{{ $bg }};border-left:3px solid {{ $bd }};">
                <span style="font-weight:700;color:{{ $tc }};flex-shrink:0;">{{ $a['cat'] }}</span>
                <span style="color:{{ $tc2 }};">{{ $a['msg'] }}</span>
            </div>
            @empty
            <div style="text-align:center;padding:24px;color:var(--pd-muted);font-size:12px;">
                ✓ Aucun point de vigilance — tous les projets sont en bonne santé.
            </div>
            @endforelse
        </div>

        {{-- Jalons à venir --}}
        <div class="mpd-card">
            <div class="mpd-card-title">
                <span>Prochains jalons <span style="color:var(--pd-muted);font-weight:400;">(30j)</span></span>
                @if($lateMilestones->count())
                <span class="stat-pill pill-red">{{ $lateMilestones->count() }} en retard</span>
                @endif
            </div>

            @if($lateMilestones->isNotEmpty())
            <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:#991B1B;margin-bottom:6px;">En retard</div>
            @foreach($lateMilestones->take(3) as $ms)
            <div class="ms-row">
                <span class="ms-dot">🔴</span>
                <div style="flex:1;min-width:0;">
                    <div class="ms-title">{{ $ms->title }}</div>
                    <div class="ms-proj" style="color:{{ $ms->project->color }};">▪ {{ $ms->project->name }}</div>
                </div>
                <span class="ms-date" style="color:#E24B4A;">{{ $ms->due_date->format('d/m/Y') }}</span>
                <a href="{{ route('projects.show', $ms->project) }}" style="font-size:11px;color:var(--pd-navy);text-decoration:none;padding:2px 8px;border:0.5px solid var(--pd-border);border-radius:6px;">→</a>
            </div>
            @endforeach
            @if($lateMilestones->count() > 3)
            <div style="font-size:11px;color:var(--pd-muted);padding:4px 0;">+ {{ $lateMilestones->count()-3 }} autre(s)…</div>
            @endif
            @endif

            @if($upcomingMilestones->isNotEmpty())
            @if($lateMilestones->isNotEmpty())
            <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--pd-muted);margin:10px 0 6px;">À venir</div>
            @endif
            @foreach($upcomingMilestones->take(5) as $ms)
            @php
            $daysLeft = now()->diffInDays($ms->due_date, false);
            $msColor  = $daysLeft <= 7 ? '#D97706' : 'var(--pd-navy)';
            @endphp
            <div class="ms-row">
                <span class="ms-dot">🏁</span>
                <div style="flex:1;min-width:0;">
                    <div class="ms-title">{{ $ms->title }}</div>
                    <div class="ms-proj" style="color:{{ $ms->project->color }};">▪ {{ $ms->project->name }}</div>
                </div>
                <span class="ms-date" style="color:{{ $msColor }};">
                    @if($daysLeft <= 0) Aujourd'hui
                    @elseif($daysLeft === 1) Demain
                    @elseif($daysLeft <= 7) {{ $daysLeft }}j
                    @else {{ $ms->due_date->format('d/m') }}
                    @endif
                </span>
                <a href="{{ route('projects.show', $ms->project) }}" style="font-size:11px;color:var(--pd-navy);text-decoration:none;padding:2px 8px;border:0.5px solid var(--pd-border);border-radius:6px;">→</a>
            </div>
            @endforeach
            @endif

            @if($upcomingMilestones->isEmpty() && $lateMilestones->isEmpty())
            <div style="text-align:center;padding:24px;color:var(--pd-muted);font-size:12px;">Aucun jalon dans les 30 prochains jours.</div>
            @endif
        </div>

    </div>

    {{-- ── Rangée secondaire : mes tâches urgentes + avancement projets ── --}}
    <div class="mpd-grid-2">

        {{-- Mes tâches urgentes --}}
        <div class="mpd-card">
            <div class="mpd-card-title">
                <span>Mes tâches urgentes / haute priorité</span>
                @if($myUrgentTasks)
                <span class="stat-pill pill-red">{{ $myUrgentTasks }}</span>
                @else
                <span class="stat-pill pill-green">✓ OK</span>
                @endif
            </div>
            @forelse($myTasks->whereIn('priority',['urgent','high'])->take(8) as $task)
            @php $isOverdue = $task->due_date && $task->due_date->isPast(); @endphp
            <div class="task-row">
                <span class="task-proj-dot" style="background:{{ $task->project?->color ?? '#1E3A5F' }};"></span>
                <div style="flex:1;min-width:0;">
                    <div class="task-title">
                        <a href="{{ route('projects.show', $task->project) }}?view=planif" style="color:inherit;text-decoration:none;">
                            {{ $task->title }}
                        </a>
                    </div>
                    <div class="task-proj">{{ $task->project?->name }}</div>
                </div>
                <span class="prio-badge prio-{{ $task->priority }}">{{ $prioLabels[$task->priority] ?? $task->priority }}</span>
                @if($task->due_date)
                <span class="task-due" style="{{ $isOverdue ? 'color:#E24B4A;font-weight:600;' : '' }}">
                    {{ $isOverdue ? '⚠ ' : '' }}{{ $task->due_date->format('d/m') }}
                </span>
                @endif
            </div>
            @empty
            <div style="text-align:center;padding:24px;color:var(--pd-muted);font-size:12px;">Aucune tâche urgente ou haute priorité assignée.</div>
            @endforelse
            @if($myTasks->whereIn('priority',['urgent','high'])->count() > 8)
            <div style="font-size:11px;color:var(--pd-muted);padding-top:8px;text-align:right;">+ {{ $myTasks->whereIn('priority',['urgent','high'])->count()-8 }} autre(s)…</div>
            @endif
        </div>

        {{-- Avancement des projets actifs --}}
        <div class="mpd-card">
            <div class="mpd-card-title">Avancement des projets actifs</div>
            @forelse($projects->where('status','active')->sortBy(fn($p)=>$p->progressionPercent()) as $p)
            @php
            $pct = $p->progressionPercent();
            $pctColor = $pct >= 75 ? '#16A34A' : ($pct >= 40 ? '#D97706' : 'var(--pd-navy)');
            $isLate   = $p->due_date && $p->due_date->isPast();
            @endphp
            <div class="proj-mini">
                <div style="width:10px;height:10px;border-radius:50%;background:{{ $p->color }};flex-shrink:0;margin-top:2px;"></div>
                <div class="proj-mini-bar">
                    <div style="display:flex;align-items:center;justify-content:space-between;">
                        <a href="{{ route('projects.show', $p) }}" class="proj-mini-name" style="text-decoration:none;color:var(--pd-text);">
                            {{ $p->name }}
                        </a>
                        @if($isLate)<span style="font-size:9px;color:#E24B4A;font-weight:700;margin-left:6px;">RETARD</span>@endif
                    </div>
                    <div class="proj-mini-meta">
                        {{ $p->tasks_count }} tâche{{ $p->tasks_count>1?'s':'' }}
                        · {{ $p->project_members_count }} membre{{ $p->project_members_count>1?'s':'' }}
                        @if($p->due_date) · ⟶ {{ $p->due_date->format('d/m/Y') }} @endif
                    </div>
                    <div class="prog-bar"><div class="prog-fill" style="width:{{ $pct }}%;background:{{ $pctColor }};"></div></div>
                </div>
                <div class="proj-pct" style="color:{{ $pctColor }};">{{ $pct }}%</div>
            </div>
            @empty
            <div style="text-align:center;padding:24px;color:var(--pd-muted);font-size:12px;">Aucun projet actif.</div>
            @endforelse

            @if($projects->where('status','on_hold')->isNotEmpty())
            <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--pd-muted);margin:12px 0 8px;">En pause</div>
            @foreach($projects->where('status','on_hold') as $p)
            <div class="proj-mini">
                <div style="width:10px;height:10px;border-radius:50%;background:{{ $p->color }};flex-shrink:0;opacity:.5;"></div>
                <div class="proj-mini-bar">
                    <div class="proj-mini-name" style="opacity:.6;">{{ $p->name }}</div>
                    <div class="proj-mini-meta">{{ $p->tasks_count }} tâche{{ $p->tasks_count>1?'s':'' }}</div>
                </div>
                <div class="proj-pct" style="opacity:.5;">{{ $p->progressionPercent() }}%</div>
            </div>
            @endforeach
            @endif
        </div>
    </div>

</div>{{-- /synthese --}}

{{-- ════════════════════════════════════════════════════════════════════════ --}}
{{-- ONGLET PROJETS                                                           --}}
{{-- ════════════════════════════════════════════════════════════════════════ --}}
<div style="width:100%;" x-show="tab === 'projets'" x-cloak>
    <div class="mpd-card">
        {{-- En-tête colonnes --}}
        <div style="display:flex;align-items:center;gap:12px;padding:0 0 8px;border-bottom:0.5px solid var(--pd-border);font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--pd-muted);">
            <div style="width:4px;flex-shrink:0;"></div>
            <div style="flex:1;">Nom</div>
            <div style="width:80px;flex-shrink:0;">Statut</div>
            <div style="width:100px;flex-shrink:0;">Progression</div>
            <div style="width:60px;text-align:right;flex-shrink:0;">Tâches</div>
            <div style="width:60px;text-align:right;flex-shrink:0;">Membres</div>
            <div style="width:80px;text-align:right;flex-shrink:0;">Échéance</div>
        </div>

        @forelse($projects->sortBy('status') as $p)
        @php
        $pct   = $p->progressionPercent();
        $sc    = $pColors[$p->status] ?? ['bg'=>'#F1F5F9','text'=>'#475569'];
        $isLate = $p->due_date && $p->due_date->isPast() && $p->status === 'active';
        @endphp
        <a href="{{ route('projects.show', $p) }}" class="pl-row">
            <div class="pl-color" style="background:{{ $p->color }};"></div>
            <div class="pl-name">
                {{ $p->name }}
                @if($isLate)<span style="font-size:9px;background:#FEE2E2;color:#991B1B;padding:1px 5px;border-radius:4px;margin-left:6px;font-weight:700;">RETARD</span>@endif
            </div>
            <div class="pl-status">
                <span style="display:inline-block;padding:2px 8px;border-radius:10px;font-size:10px;font-weight:600;background:{{ $sc['bg'] }};color:{{ $sc['text'] }};">
                    {{ $pLabels[$p->status] ?? $p->status }}
                </span>
            </div>
            <div class="pl-prog">
                <div style="display:flex;align-items:center;gap:6px;">
                    <div style="flex:1;height:5px;background:var(--pd-border);border-radius:3px;overflow:hidden;">
                        <div style="height:100%;width:{{ $pct }}%;background:{{ $p->color }};border-radius:3px;"></div>
                    </div>
                    <span style="font-size:10px;font-weight:600;color:var(--pd-navy);width:26px;text-align:right;">{{ $pct }}%</span>
                </div>
            </div>
            <div class="pl-tasks">{{ $p->tasks_count }}</div>
            <div class="pl-members">{{ $p->project_members_count }}</div>
            <div class="pl-date" style="{{ $isLate ? 'color:#E24B4A;font-weight:600;' : '' }}">
                {{ $p->due_date ? $p->due_date->format('d/m/Y') : '—' }}
            </div>
        </a>
        @empty
        <div style="text-align:center;padding:40px;color:var(--pd-muted);">Aucun projet.</div>
        @endforelse
    </div>
</div>{{-- /projets --}}

{{-- ════════════════════════════════════════════════════════════════════════ --}}
{{-- ONGLET MES TÂCHES                                                        --}}
{{-- ════════════════════════════════════════════════════════════════════════ --}}
<div style="width:100%;" x-show="tab === 'taches'" x-cloak>
    <div class="mpd-card">
        @php
        $statusLabels = ['todo'=>'À faire','in_progress'=>'En cours','in_review'=>'En revue'];
        $tasksByStatus = $myTasks->groupBy('status');
        @endphp

        @foreach(['in_progress','in_review','todo'] as $st)
        @if(isset($tasksByStatus[$st]) && $tasksByStatus[$st]->isNotEmpty())
        <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--pd-muted);margin-bottom:8px;{{ !$loop->first ? 'margin-top:18px;' : '' }}">
            {{ $statusLabels[$st] }} ({{ $tasksByStatus[$st]->count() }})
        </div>
        @foreach($tasksByStatus[$st] as $task)
        @php $isOverdue = $task->due_date && $task->due_date->isPast(); @endphp
        <div class="task-row">
            <span class="task-proj-dot" style="background:{{ $task->project?->color ?? '#1E3A5F' }};"></span>
            <div style="flex:1;min-width:0;">
                <div class="task-title">
                    <a href="{{ route('projects.show', $task->project) }}?view=planif" style="color:inherit;text-decoration:none;">
                        {{ $task->title }}
                    </a>
                </div>
                <div style="display:flex;gap:8px;align-items:center;margin-top:2px;">
                    <span class="task-proj" style="color:{{ $task->project?->color ?? '#1E3A5F' }};">{{ $task->project?->name }}</span>
                    @if($task->milestone)<span class="task-proj">· 🏁 {{ $task->milestone->title }}</span>@endif
                </div>
            </div>
            <span class="prio-badge prio-{{ $task->priority }}">{{ $prioLabels[$task->priority] ?? $task->priority }}</span>
            @if($task->estimated_hours)
            <span style="font-size:10px;color:var(--pd-muted);">{{ $task->actual_hours ?? 0 }}h / {{ $task->estimated_hours }}h</span>
            @endif
            @if($task->due_date)
            <span class="task-due" style="{{ $isOverdue ? 'color:#E24B4A;font-weight:700;' : '' }}">
                {{ $isOverdue ? '⚠ ' : '' }}{{ $task->due_date->format('d/m/Y') }}
            </span>
            @endif
        </div>
        @endforeach
        @endif
        @endforeach

        @if($myTasks->isEmpty())
        <div style="text-align:center;padding:40px;color:var(--pd-muted);">Aucune tâche en cours assignée.</div>
        @endif
    </div>
</div>{{-- /taches --}}

{{-- ════════════════════════════════════════════════════════════════════════ --}}
{{-- ONGLET JALONS                                                            --}}
{{-- ════════════════════════════════════════════════════════════════════════ --}}
<div style="width:100%;" x-show="tab === 'jalons'" x-cloak>
    <div class="mpd-grid-2" style="margin-top:0;">

        {{-- Jalons en retard --}}
        <div class="mpd-card">
            <div class="mpd-card-title" style="color:#991B1B;">
                Jalons en retard ({{ $lateMilestones->count() }})
            </div>
            @forelse($lateMilestones as $ms)
            @php $daysLate = $ms->due_date->diffInDays(now()); @endphp
            <div class="ms-row">
                <span class="ms-dot">🔴</span>
                <div style="flex:1;min-width:0;">
                    <div class="ms-title">{{ $ms->title }}</div>
                    <div class="ms-proj" style="color:{{ $ms->project->color }};">▪ {{ $ms->project->name }}</div>
                </div>
                <div style="text-align:right;">
                    <div class="ms-date" style="color:#E24B4A;">{{ $ms->due_date->format('d/m/Y') }}</div>
                    <div style="font-size:9px;color:#991B1B;">{{ $daysLate }}j de retard</div>
                </div>
                <a href="{{ route('projects.show', $ms->project) }}" style="font-size:11px;color:var(--pd-navy);text-decoration:none;padding:2px 8px;border:0.5px solid var(--pd-border);border-radius:6px;">→</a>
            </div>
            @empty
            <div style="text-align:center;padding:24px;color:var(--pd-muted);font-size:12px;">✓ Aucun jalon en retard.</div>
            @endforelse
        </div>

        {{-- Jalons à venir --}}
        <div class="mpd-card">
            <div class="mpd-card-title">Jalons à venir (30 jours)</div>
            @forelse($upcomingMilestones as $ms)
            @php
            $daysLeft = now()->diffInDays($ms->due_date, false);
            $msColor  = $daysLeft <= 7 ? '#D97706' : 'var(--pd-navy)';
            @endphp
            <div class="ms-row">
                <span class="ms-dot">🏁</span>
                <div style="flex:1;min-width:0;">
                    <div class="ms-title">{{ $ms->title }}</div>
                    <div class="ms-proj" style="color:{{ $ms->project->color }};">▪ {{ $ms->project->name }}</div>
                </div>
                <div style="text-align:right;">
                    <div class="ms-date" style="color:{{ $msColor }};">{{ $ms->due_date->format('d/m/Y') }}</div>
                    <div style="font-size:9px;color:var(--pd-muted);">
                        @if($daysLeft === 0) Aujourd'hui
                        @elseif($daysLeft === 1) Demain
                        @else dans {{ $daysLeft }}j
                        @endif
                    </div>
                </div>
                <a href="{{ route('projects.show', $ms->project) }}" style="font-size:11px;color:var(--pd-navy);text-decoration:none;padding:2px 8px;border:0.5px solid var(--pd-border);border-radius:6px;">→</a>
            </div>
            @empty
            <div style="text-align:center;padding:24px;color:var(--pd-muted);font-size:12px;">Aucun jalon dans les 30 prochains jours.</div>
            @endforelse
        </div>

    </div>
</div>{{-- /jalons --}}

</div>{{-- /mpd-tabs x-data --}}
</div>{{-- /mpd-wrap --}}
@endsection
