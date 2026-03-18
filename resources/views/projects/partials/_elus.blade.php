{{-- _elus.blade.php — Tableau de bord élus --}}
@php
$bs = $budgetSummary;
$fmt = fn($v) => number_format($v,0,',',' ').' €';
$totalPct = $bs['total']['planned']>0 ? round($bs['total']['committed']/$bs['total']['planned']*100) : 0;
$risksByCrit = $activeRisks->groupBy(fn($r)=>$r->criticality());
$resistants  = $project->stakeholders->where('adhesion','resistant');
$lateComm    = $project->commActions->filter(fn($a)=>$a->isLate());
$obsTypes    = \App\Models\Tenant\ProjectObservation::typeConfig();
@endphp

<div class="section-hdr">
    <div>
        <div class="section-title">Tableau de bord élus</div>
        <div class="section-sub">Synthèse exécutive · {{ now()->translatedFormat('F Y') }}</div>
    </div>
    <button class="btn-sm" onclick="window.print()" style="display:flex;align-items:center;gap:5px;">
        <svg width="13" height="13" viewBox="0 0 16 16" fill="none"><rect x="3" y="1" width="10" height="5" rx="1" stroke="currentColor" stroke-width="1.2"/><path d="M3 11H1V7a2 2 0 012-2h10a2 2 0 012 2v4h-2" stroke="currentColor" stroke-width="1.2"/><rect x="3" y="10" width="10" height="5" rx="1" stroke="currentColor" stroke-width="1.2"/></svg>
        Imprimer
    </button>
</div>

{{-- ── 4 métriques clés ── --}}
<div class="stat-grid" style="margin-bottom:20px;">
    @php
    $daysLeft = $project->due_date ? now()->diffInDays($project->due_date, false) : null;
    $onTime   = $daysLeft === null || $daysLeft >= 0;
    @endphp
    <div class="stat-card" style="border-top:3px solid {{ $progression>=75?'#16A34A':($progression>=40?'#D97706':'#E24B4A') }};">
        <div class="stat-lbl">Avancement</div>
        <div class="stat-val" style="color:{{ $progression>=75?'#16A34A':($progression>=40?'#D97706':'#E24B4A') }};">{{ $progression }}%</div>
        <div class="stat-sub">{{ $taskStats['done'] }}/{{ $taskStats['total'] }} tâches terminées</div>
        <div class="bbar-wrap" style="margin-top:6px;"><div class="bbar-fill" style="width:{{ $progression }}%;background:{{ $progression>=75?'#16A34A':($progression>=40?'#D97706':'#E24B4A') }};"></div></div>
    </div>
    <div class="stat-card" style="border-top:3px solid {{ $onTime?'#1E3A5F':'#E24B4A' }};">
        <div class="stat-lbl">Délais</div>
        <div class="stat-val" style="font-size:15px;color:{{ $onTime?'var(--pd-navy)':'var(--pd-danger)' }};">
            @if($daysLeft===null) — @elseif($daysLeft<0) {{ abs($daysLeft) }}j de retard @else {{ $daysLeft }}j restants @endif
        </div>
        <div class="stat-sub">{{ $project->due_date?->translatedFormat('d M Y') ?? 'Non défini' }}</div>
    </div>
    <div class="stat-card" style="border-top:3px solid {{ $totalPct>100?'#E24B4A':($totalPct>85?'#D97706':'#16A34A') }};">
        <div class="stat-lbl">Budget consommé</div>
        <div class="stat-val" style="color:{{ $totalPct>100?'var(--pd-danger)':'var(--pd-navy)' }};">{{ $totalPct }}%</div>
        <div class="stat-sub">{{ $fmt($bs['total']['committed']) }} / {{ $fmt($bs['total']['planned']) }}</div>
    </div>
    <div class="stat-card" style="border-top:3px solid {{ $criticalRisksCount>0?'#E24B4A':($activeRisks->count()>0?'#D97706':'#16A34A') }};">
        <div class="stat-lbl">Risques actifs</div>
        <div class="stat-val" style="color:{{ $criticalRisksCount>0?'var(--pd-danger)':'var(--pd-navy)' }};">{{ $activeRisks->count() }}</div>
        <div class="stat-sub">dont {{ $criticalRisksCount }} critique{{ $criticalRisksCount>1?'s':'' }}</div>
    </div>
</div>

{{-- ── Points de vigilance ── --}}
@php
$alerts = collect();
if($budgetAlerts->count()) $alerts->push(['level'=>'warn','cat'=>'Budget','msg'=>'Dépassement prévisible sur '.implode(', ',$budgetAlerts->pluck('label')->toArray()).' — arbitrage requis.']);
if($criticalRisksCount)    $alerts->push(['level'=>'danger','cat'=>'Risque','msg'=>$criticalRisksCount.' risque'.($criticalRisksCount>1?'s':'').' critique'.($criticalRisksCount>1?'s':'').' identifié'.($criticalRisksCount>1?'s':'').' — plan de mitigation à valider.']);
if($resistants->count())   $alerts->push(['level'=>'danger','cat'=>'Changement','msg'=>$resistants->count().' partie'.($resistants->count()>1?'s':'').' prenante'.($resistants->count()>1?'s':'').' résistante'.($resistants->count()>1?'s':'').' : '.implode(', ',$resistants->map->displayName()->toArray()).'.']);
if($lateComm->count())     $alerts->push(['level'=>'warn','cat'=>'Communication','msg'=>$lateComm->count().' action'.($lateComm->count()>1?'s':'').' de communication en retard.']);
if(!$daysLeft || $daysLeft>14) {
    $reachedMilestones = $project->milestones->filter(fn($m)=>$m->isReached());
    if($reachedMilestones->count()) $alerts->push(['level'=>'ok','cat'=>'Jalons','msg'=>$reachedMilestones->count().' jalon'.($reachedMilestones->count()>1?'s':'').' atteint'.($reachedMilestones->count()>1?'s':'').' : '.implode(', ',$reachedMilestones->pluck('title')->toArray()).'.']);
}
@endphp

<div class="pd-card" style="margin-bottom:16px;">
    <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--pd-muted);margin-bottom:12px;">Points de vigilance</div>
    @forelse($alerts as $alert)
    <div style="display:flex;align-items:flex-start;gap:10px;padding:9px 12px;border-radius:8px;font-size:12px;margin-bottom:6px;
        background:{{ $alert['level']==='danger'?'#FEE2E2':($alert['level']==='warn'?'#FEF3C7':'#D1FAE5') }};
        border-left:3px solid {{ $alert['level']==='danger'?'#E24B4A':($alert['level']==='warn'?'#D97706':'#16A34A') }};">
        <span style="font-weight:700;color:{{ $alert['level']==='danger'?'#991B1B':($alert['level']==='warn'?'#92400E':'#065F46') }};flex-shrink:0;">{{ $alert['cat'] }}</span>
        <span style="color:{{ $alert['level']==='danger'?'#7F1D1D':($alert['level']==='warn'?'#78350F':'#14532D') }};">{{ $alert['msg'] }}</span>
    </div>
    @empty
    <div style="text-align:center;padding:12px;color:var(--pd-muted);font-size:12px;">Aucun point de vigilance — projet en bonne santé.</div>
    @endforelse
</div>

{{-- ── Budget synthèse ── --}}
<div class="pd-card" style="margin-bottom:16px;">
    <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--pd-muted);margin-bottom:12px;">Budget</div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
        @foreach(['invest'=>['label'=>'Investissement','color'=>'#1E3A5F'],'fonct'=>['label'=>'Fonctionnement','color'=>'#7C3AED']] as $type=>$cfg)
        @php $pct = $bs[$type]['planned']>0 ? round($bs[$type]['committed']/$bs[$type]['planned']*100) : 0; @endphp
        <div>
            <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:4px;">
                <span style="font-weight:600;">{{ $cfg['label'] }}</span>
                <span style="color:var(--pd-muted);">{{ $fmt($bs[$type]['committed']) }} / {{ $fmt($bs[$type]['planned']) }}</span>
            </div>
            <div class="bbar-wrap"><div class="bbar-fill" style="width:{{ min($pct,100) }}%;background:{{ $cfg['color'] }};"></div></div>
            <div style="font-size:11px;color:var(--pd-muted);margin-top:3px;">{{ $pct }}% engagé · {{ $fmt($bs[$type]['paid']) }} mandaté</div>
        </div>
        @endforeach
    </div>
</div>

{{-- ── Observations élus ── --}}
<div class="pd-card">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
        <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--pd-muted);">Observations &amp; questions des élus</div>
    </div>

    @foreach($project->observations->take(10) as $obs)
    @php $oc = $obsTypes[$obs->type]; @endphp
    <div class="obs-item">
        <div style="width:28px;height:28px;border-radius:50%;background:var(--pd-bg2);color:var(--pd-navy);font-size:10px;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
            {{ strtoupper(substr($obs->user->name,0,2)) }}
        </div>
        <div style="flex:1;">
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:3px;">
                <span style="font-size:12px;font-weight:500;">{{ $obs->user->name }}</span>
                <span class="pd-badge" style="background:{{ $oc['bg'] }};color:{{ $oc['text'] }};">{{ $oc['label'] }}</span>
                <span style="font-size:11px;color:var(--pd-muted);margin-left:auto;">{{ $obs->created_at->diffForHumans() }}</span>
            </div>
            <div style="font-size:12px;color:var(--pd-text);line-height:1.5;">{{ $obs->body }}</div>
        </div>
        @if(auth()->id()===$obs->user_id || $canManage)
        <form method="POST" action="{{ route('projects.observations.destroy', [$project, $obs]) }}" style="align-self:flex-start;">
            @csrf @method('DELETE')
            <button type="submit" style="background:none;border:none;cursor:pointer;color:var(--pd-muted);font-size:14px;padding:0 2px;">×</button>
        </form>
        @endif
    </div>
    @endforeach

    {{-- Formulaire ajout observation --}}
    <form method="POST" action="{{ route('projects.observations.store', $project) }}" style="margin-top:14px;display:flex;flex-direction:column;gap:8px;">
        @csrf
        <div style="display:flex;gap:8px;">
            <select name="type" class="pd-input" style="width:140px;flex-shrink:0;">
                @foreach($obsTypes as $val=>$cfg)
                <option value="{{ $val }}">{{ $cfg['label'] }}</option>
                @endforeach
            </select>
            <textarea name="body" class="pd-input" rows="2" placeholder="Votre observation, question ou validation..." style="flex:1;" required></textarea>
        </div>
        <div style="text-align:right;">
            <button type="submit" class="btn-sm btn-navy">Publier</button>
        </div>
    </form>
</div>

<style>
@media print {
    .proj-sidenav, .sn-elus, nav { display:none !important; }
    .proj-shell { display:block !important; }
    .proj-main  { padding:0 !important; }
    [x-cloak]   { display:block !important; }
    [x-show="section==='elus'"] { display:block !important; }
}
</style>
