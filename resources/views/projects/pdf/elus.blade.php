<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Tableau de bord élus — {{ $project->name }}</title>
<style>
/* ── Reset & base ─────────────────────────────────────────────── */
* { box-sizing: border-box; margin: 0; padding: 0; }
body {
    font-family: DejaVu Sans, Arial, sans-serif;
    font-size: 11px;
    color: #1a2535;
    line-height: 1.5;
    background: #fff;
}

/* ── Page ────────────────────────────────────────────────────── */
@page {
    margin: 18mm 15mm 15mm 15mm;
    size: A4 portrait;
}

/* ── En-tête ──────────────────────────────────────────────────── */
.header {
    border-bottom: 2px solid #1E3A5F;
    padding-bottom: 10px;
    margin-bottom: 16px;
}
.header-title {
    font-size: 18px;
    font-weight: bold;
    color: #1E3A5F;
}
.header-sub {
    font-size: 10px;
    color: #6b7c96;
    margin-top: 3px;
}
.header-date {
    font-size: 10px;
    color: #6b7c96;
    text-align: right;
}
.header-row {
    display: table;
    width: 100%;
}
.header-left  { display: table-cell; vertical-align: bottom; }
.header-right { display: table-cell; vertical-align: bottom; text-align: right; }

/* ── KPIs ─────────────────────────────────────────────────────── */
.kpi-row {
    display: table;
    width: 100%;
    margin-bottom: 14px;
    border-collapse: separate;
    border-spacing: 6px 0;
}
.kpi-cell {
    display: table-cell;
    width: 25%;
    border: 0.5px solid #dde5f0;
    border-radius: 6px;
    padding: 10px 12px;
    vertical-align: top;
}
.kpi-lbl {
    font-size: 9px;
    font-weight: bold;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #6b7c96;
    margin-bottom: 4px;
}
.kpi-val {
    font-size: 22px;
    font-weight: bold;
    color: #1E3A5F;
    line-height: 1;
    margin-bottom: 3px;
}
.kpi-sub { font-size: 9px; color: #6b7c96; }

/* ── Barres de progression ────────────────────────────────────── */
.bar-wrap {
    height: 5px;
    background: #dde5f0;
    border-radius: 3px;
    margin-top: 6px;
    overflow: hidden;
}
.bar-fill { height: 100%; border-radius: 3px; }

/* ── Sections ─────────────────────────────────────────────────── */
.section {
    margin-bottom: 14px;
    page-break-inside: avoid;
}
.section-title {
    font-size: 10px;
    font-weight: bold;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: #6b7c96;
    margin-bottom: 8px;
    padding-bottom: 4px;
    border-bottom: 0.5px solid #dde5f0;
}

/* ── Alertes ──────────────────────────────────────────────────── */
.alert {
    padding: 7px 10px;
    border-radius: 5px;
    margin-bottom: 5px;
    font-size: 10px;
}
.alert-danger  { background: #FEE2E2; border-left: 3px solid #E24B4A; }
.alert-warn    { background: #FEF3C7; border-left: 3px solid #D97706; }
.alert-ok      { background: #D1FAE5; border-left: 3px solid #16A34A; }
.alert-cat     { font-weight: bold; margin-right: 6px; }
.alert-danger .alert-cat  { color: #991B1B; }
.alert-warn   .alert-cat  { color: #92400E; }
.alert-ok     .alert-cat  { color: #065F46; }

/* ── Tableau ──────────────────────────────────────────────────── */
table { width: 100%; border-collapse: collapse; font-size: 10px; }
th {
    background: #F0F4FA;
    padding: 5px 8px;
    text-align: left;
    font-size: 9px;
    font-weight: bold;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #6b7c96;
    border-bottom: 0.5px solid #dde5f0;
}
td {
    padding: 6px 8px;
    border-bottom: 0.5px solid #dde5f0;
    vertical-align: top;
}
tr:last-child td { border-bottom: none; }

/* ── Badge ────────────────────────────────────────────────────── */
.badge {
    display: inline-block;
    padding: 1px 6px;
    border-radius: 8px;
    font-size: 9px;
    font-weight: bold;
}
.badge-red    { background: #FEE2E2; color: #991B1B; }
.badge-orange { background: #FEF3C7; color: #92400E; }
.badge-blue   { background: #DBEAFE; color: #1E40AF; }
.badge-green  { background: #D1FAE5; color: #065F46; }
.badge-gray   { background: #F1F5F9; color: #475569; }

/* ── Budget grille ────────────────────────────────────────────── */
.budget-row { display: table; width: 100%; margin-bottom: 10px; }
.budget-cell { display: table-cell; width: 50%; padding-right: 10px; vertical-align: top; }
.budget-cell:last-child { padding-right: 0; }
.budget-label { font-size: 11px; font-weight: bold; color: #1E3A5F; margin-bottom: 4px; }
.budget-nums  { font-size: 10px; color: #6b7c96; margin-bottom: 4px; }

/* ── Observations ─────────────────────────────────────────────── */
.obs-item {
    padding: 8px 10px;
    border-bottom: 0.5px solid #dde5f0;
    font-size: 10px;
}
.obs-item:last-child { border-bottom: none; }
.obs-meta { color: #6b7c96; font-size: 9px; margin-bottom: 3px; }
.obs-body { color: #1a2535; line-height: 1.5; }

/* ── Pied de page ─────────────────────────────────────────────── */
.footer {
    position: fixed;
    bottom: 0;
    left: 0; right: 0;
    font-size: 8px;
    color: #6b7c96;
    border-top: 0.5px solid #dde5f0;
    padding-top: 5px;
    display: table;
    width: 100%;
}
.footer-left  { display: table-cell; }
.footer-right { display: table-cell; text-align: right; }

/* ── Deux colonnes ────────────────────────────────────────────── */
.two-col { display: table; width: 100%; border-spacing: 8px 0; }
.col-left  { display: table-cell; width: 50%; vertical-align: top; }
.col-right { display: table-cell; width: 50%; vertical-align: top; }

.card {
    border: 0.5px solid #dde5f0;
    border-radius: 6px;
    padding: 10px 12px;
    margin-bottom: 8px;
}
</style>
</head>
<body>

{{-- ── Pied de page fixe ────────────────────────────────────────────── --}}
<div class="footer">
    <div class="footer-left">Pladigit — {{ $project->name }} — Confidentiel</div>
    <div class="footer-right">Généré le {{ now()->translatedFormat('d F Y à H:i') }}</div>
</div>

{{-- ── En-tête ──────────────────────────────────────────────────────── --}}
<div class="header">
    <div class="header-row">
        <div class="header-left">
            <div class="header-title">{{ $project->name }}</div>
            <div class="header-sub">Tableau de bord élus · Synthèse exécutive</div>
        </div>
        <div class="header-right">
            <div class="header-date">{{ now()->translatedFormat('F Y') }}</div>
            @php
                $statusLabels = \App\Models\Tenant\Project::statusLabels();
                $statusColors = ['active'=>'green','on_hold'=>'orange','completed'=>'blue','draft'=>'gray','archived'=>'gray'];
            @endphp
            <span class="badge badge-{{ $statusColors[$project->status] ?? 'gray' }}">
                {{ $statusLabels[$project->status] ?? $project->status }}
            </span>
            @if($project->is_private)
            <span class="badge badge-gray" style="margin-left:4px;">🔒 Privé</span>
            @endif
        </div>
    </div>
</div>

{{-- ── KPIs ──────────────────────────────────────────────────────────── --}}
@php
$daysLeft = $project->due_date ? now()->diffInDays($project->due_date, false) : null;
$onTime   = $daysLeft === null || $daysLeft >= 0;
$bs       = $budgetSummary;
$fmt      = fn($v) => number_format($v, 0, ',', ' ').' €';
$totalPct = $bs['total']['planned'] > 0
    ? round($bs['total']['committed'] / $bs['total']['planned'] * 100) : 0;
$progColor  = $progression >= 75 ? '#16A34A' : ($progression >= 40 ? '#D97706' : '#E24B4A');
$budgColor  = $totalPct > 100 ? '#E24B4A' : ($totalPct > 85 ? '#D97706' : '#16A34A');
$riskColor  = $criticalRisksCount > 0 ? '#E24B4A' : '#16A34A';
@endphp

<div class="kpi-row">
    <div class="kpi-cell" style="border-top: 3px solid {{ $progColor }};">
        <div class="kpi-lbl">Avancement</div>
        <div class="kpi-val" style="color: {{ $progColor }};">{{ $progression }}%</div>
        <div class="kpi-sub">{{ $taskStats['done'] }}/{{ $taskStats['total'] }} tâches terminées</div>
        <div class="bar-wrap"><div class="bar-fill" style="width:{{ $progression }}%;background:{{ $progColor }};"></div></div>
    </div>
    <div class="kpi-cell" style="border-top: 3px solid {{ $onTime ? '#1E3A5F' : '#E24B4A' }};">
        <div class="kpi-lbl">Délais</div>
        <div class="kpi-val" style="font-size:14px;color:{{ $onTime ? '#1E3A5F' : '#E24B4A' }};">
            @if($daysLeft === null) —
            @elseif($daysLeft < 0) {{ abs($daysLeft) }}j retard
            @else {{ $daysLeft }}j restants
            @endif
        </div>
        <div class="kpi-sub">{{ $project->due_date?->translatedFormat('d M Y') ?? 'Non défini' }}</div>
    </div>
    <div class="kpi-cell" style="border-top: 3px solid {{ $budgColor }};">
        <div class="kpi-lbl">Budget engagé</div>
        <div class="kpi-val" style="color: {{ $budgColor }};">{{ $totalPct }}%</div>
        <div class="kpi-sub">{{ $fmt($bs['total']['committed']) }} / {{ $fmt($bs['total']['planned']) }}</div>
        <div class="bar-wrap"><div class="bar-fill" style="width:{{ min($totalPct,100) }}%;background:{{ $budgColor }};"></div></div>
    </div>
    <div class="kpi-cell" style="border-top: 3px solid {{ $riskColor }};">
        <div class="kpi-lbl">Risques actifs</div>
        <div class="kpi-val" style="color: {{ $riskColor }};">{{ $activeRisks->count() }}</div>
        <div class="kpi-sub">dont {{ $criticalRisksCount }} critique{{ $criticalRisksCount > 1 ? 's' : '' }}</div>
    </div>
</div>

{{-- ── Points de vigilance ──────────────────────────────────────────── --}}
@php
$alerts = collect();
$resistants = $project->stakeholders->where('adhesion','resistant');
$lateComm   = $project->commActions->filter(fn($a) => $a->isLate());
$lateMs     = $project->milestones->filter(fn($m) => $m->isLate());
if($daysLeft !== null && $daysLeft < 0)
    $alerts->push(['lvl'=>'danger','cat'=>'Délais','msg'=>abs($daysLeft).'j de retard sur l\'échéance cible.']);
if($criticalRisksCount)
    $alerts->push(['lvl'=>'danger','cat'=>'Risques','msg'=>$criticalRisksCount.' risque'.($criticalRisksCount>1?'s critiques':'critique').' sans plan de mitigation validé.']);
if($budgetAlerts->count())
    $alerts->push(['lvl'=>'warn','cat'=>'Budget','msg'=>'Dépassement prévisible sur '.implode(', ',$budgetAlerts->pluck('label')->toArray()).'.']);
if($resistants->count())
    $alerts->push(['lvl'=>'danger','cat'=>'Changement','msg'=>$resistants->count().' partie'.($resistants->count()>1?'s prenantes résistantes':'prenante résistante').'.']);
if($lateComm->count())
    $alerts->push(['lvl'=>'warn','cat'=>'Communication','msg'=>$lateComm->count().' action'.($lateComm->count()>1?'s':'').' de communication en retard.']);
if($lateMs->count())
    $alerts->push(['lvl'=>'warn','cat'=>'Jalons','msg'=>$lateMs->count().' jalon'.($lateMs->count()>1?'s':'').' en retard.']);
if($alerts->isEmpty())
    $alerts->push(['lvl'=>'ok','cat'=>'Bilan','msg'=>'Aucun point de vigilance — projet en bonne santé.']);
@endphp

<div class="section">
    <div class="section-title">Points de vigilance</div>
    @foreach($alerts as $a)
    <div class="alert alert-{{ $a['lvl'] }}">
        <span class="alert-cat">{{ $a['cat'] }}</span>{{ $a['msg'] }}
    </div>
    @endforeach
</div>

{{-- ── Budget + Jalons (2 colonnes) ────────────────────────────────── --}}
<div class="two-col">
    <div class="col-left">
        <div class="section">
            <div class="section-title">Budget</div>
            @foreach(['invest'=>['label'=>'Investissement','color'=>'#1E3A5F'],'fonct'=>['label'=>'Fonctionnement','color'=>'#7C3AED']] as $type => $cfg)
            @php $pct = $bs[$type]['planned']>0 ? round($bs[$type]['committed']/$bs[$type]['planned']*100) : 0; @endphp
            <div class="budget-cell" style="display:block;margin-bottom:8px;">
                <div class="budget-label">{{ $cfg['label'] }}</div>
                <div class="budget-nums">{{ $fmt($bs[$type]['committed']) }} / {{ $fmt($bs[$type]['planned']) }} — {{ $pct }}% engagé</div>
                <div class="bar-wrap"><div class="bar-fill" style="width:{{ min($pct,100) }}%;background:{{ $cfg['color'] }};"></div></div>
                <div style="font-size:9px;color:#6b7c96;margin-top:3px;">Mandaté : {{ $fmt($bs[$type]['paid']) }}</div>
            </div>
            @endforeach
            <div style="border-top:0.5px solid #dde5f0;padding-top:6px;margin-top:4px;">
                <strong>Total : {{ $fmt($bs['total']['committed']) }} / {{ $fmt($bs['total']['planned']) }}</strong>
            </div>
        </div>
    </div>
    <div class="col-right">
        <div class="section">
            <div class="section-title">Jalons</div>
            @forelse($project->milestones->take(6) as $ms)
            @php
                $msReached = $ms->isReached();
                $msLate    = $ms->isLate();
                $msPct     = $ms->progressionPercent();
            @endphp
            <div style="display:table;width:100%;margin-bottom:6px;">
                <div style="display:table-cell;vertical-align:middle;width:14px;">
                    @if($msReached)<span style="color:#16A34A;font-size:11px;">✓</span>
                    @elseif($msLate)<span style="color:#E24B4A;font-size:11px;">!</span>
                    @else<span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:{{ $ms->color ?? '#EA580C' }};"></span>
                    @endif
                </div>
                <div style="display:table-cell;vertical-align:middle;padding-left:6px;">
                    <div style="font-size:10px;font-weight:{{ $msLate ? 'bold' : 'normal' }};color:{{ $msLate ? '#E24B4A' : '#1a2535' }};">
                        {{ $ms->title }}
                    </div>
                    <div style="font-size:9px;color:#6b7c96;">
                        {{ $ms->due_date?->translatedFormat('d M Y') }}
                        @if($msReached) · Atteint @elseif($msLate) · En retard @endif
                    </div>
                </div>
                <div style="display:table-cell;vertical-align:middle;text-align:right;font-size:10px;font-weight:bold;color:#1E3A5F;">
                    {{ $msPct }}%
                </div>
            </div>
            @empty
            <div style="font-size:10px;color:#6b7c96;">Aucun jalon défini.</div>
            @endforelse
        </div>
    </div>
</div>

{{-- ── Risques actifs ────────────────────────────────────────────────── --}}
@if($activeRisks->isNotEmpty())
<div class="section" style="page-break-inside:avoid;">
    <div class="section-title">Risques actifs ({{ $activeRisks->count() }})</div>
    <table>
        <thead>
            <tr>
                <th style="width:35%;">Risque</th>
                <th>Catégorie</th>
                <th>Criticité</th>
                <th>Statut</th>
                <th style="width:25%;">Plan de mitigation</th>
            </tr>
        </thead>
        <tbody>
            @foreach($activeRisks->take(8) as $risk)
            @php
                $crit = $risk->criticality();
                $critBadge = match($crit) {
                    'critique' => 'badge-red',
                    'élevé'    => 'badge-orange',
                    'modéré'   => 'badge-blue',
                    default    => 'badge-gray',
                };
            @endphp
            <tr>
                <td>{{ $risk->title }}</td>
                <td>{{ ucfirst($risk->category) }}</td>
                <td><span class="badge {{ $critBadge }}">{{ ucfirst($crit) }}</span></td>
                <td>{{ ucfirst($risk->status) }}</td>
                <td style="font-size:9px;color:#6b7c96;">{{ \Illuminate\Support\Str::limit($risk->mitigation_plan ?? '—', 50) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif

{{-- ── Observations élus ─────────────────────────────────────────────── --}}
@if($project->observations->isNotEmpty())
<div class="section" style="page-break-inside:avoid;">
    <div class="section-title">Observations & décisions ({{ $project->observations->count() }})</div>
    @php $obsTypes = \App\Models\Tenant\ProjectObservation::typeConfig(); @endphp
    @foreach($project->observations->take(6) as $obs)
    @php $oc = $obsTypes[$obs->type] ?? ['label'=>$obs->type,'bg'=>'#F1F5F9','text'=>'#475569']; @endphp
    <div class="obs-item">
        <div class="obs-meta">
            <strong>{{ $obs->user->name ?? '—' }}</strong>
            <span style="display:inline-block;padding:1px 5px;border-radius:6px;font-size:8px;font-weight:bold;background:{{ $oc['bg'] }};color:{{ $oc['text'] }};margin-left:5px;">{{ $oc['label'] }}</span>
            <span style="margin-left:8px;">{{ $obs->created_at->translatedFormat('d M Y') }}</span>
        </div>
        <div class="obs-body">{{ $obs->body }}</div>
    </div>
    @endforeach
</div>
@endif

{{-- ── Équipe ────────────────────────────────────────────────────────── --}}
<div class="section" style="page-break-inside:avoid;">
    <div class="section-title">Équipe projet</div>
    <table>
        <thead>
            <tr>
                <th>Membre</th>
                <th>Rôle projet</th>
            </tr>
        </thead>
        <tbody>
            @foreach($project->projectMembers->sortByDesc(fn($m)=>$m->role==='owner') as $pm)
            <tr>
                <td>{{ $pm->user->name ?? '—' }}</td>
                <td>{{ \App\Enums\ProjectRole::tryFrom($pm->role)?->label() ?? $pm->role }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>

</body>
</html>
