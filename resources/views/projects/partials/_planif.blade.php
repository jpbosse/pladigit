{{-- _planif.blade.php — Tâches & planning (onglets) --}}
@php
$totalEstimated = $project->tasks()->whereNotNull('estimated_hours')->sum('estimated_hours');
$totalActual    = $project->tasks()->whereNotNull('actual_hours')->sum('actual_hours');
$hoursPct       = $totalEstimated > 0 ? min(100, round($totalActual / $totalEstimated * 100)) : 0;
$hoursOver      = $totalEstimated > 0 && $totalActual > $totalEstimated;
@endphp

<div x-data="{ tab: '{{ request('view','liste') }}', switchTab(t) { this.tab = t; window.dispatchEvent(new CustomEvent('close-event-slideover')); } }">

{{-- ── Bandeau heures total ── --}}
@if($totalEstimated > 0)
<div style="display:flex;align-items:center;gap:20px;padding:14px 18px;background:var(--pd-surface);border:0.5px solid var(--pd-border);border-radius:10px;margin-bottom:16px;">

    {{-- Heures réalisées --}}
    <div style="text-align:center;flex-shrink:0;">
        <div style="font-size:28px;font-weight:700;color:{{ $hoursOver ? 'var(--pd-danger)' : 'var(--pd-navy)' }};line-height:1;">
            {{ number_format($totalActual, 1) }}h
        </div>
        <div style="font-size:11px;color:var(--pd-muted);margin-top:2px;">réalisées</div>
    </div>

    {{-- Séparateur --}}
    <div style="font-size:22px;color:var(--pd-border);font-weight:300;">/</div>

    {{-- Heures estimées --}}
    <div style="text-align:center;flex-shrink:0;">
        <div style="font-size:28px;font-weight:700;color:var(--pd-text);line-height:1;">
            {{ number_format($totalEstimated, 1) }}h
        </div>
        <div style="font-size:11px;color:var(--pd-muted);margin-top:2px;">estimées</div>
    </div>

    {{-- Barre + % --}}
    <div style="flex:1;">
        <div style="display:flex;justify-content:space-between;font-size:11px;color:var(--pd-muted);margin-bottom:5px;">
            <span>Consommation</span>
            <span style="font-weight:600;color:{{ $hoursOver ? 'var(--pd-danger)' : 'var(--pd-text)' }};">
                {{ $hoursPct }}%{{ $hoursOver ? ' — dépassement' : '' }}
            </span>
        </div>
        <div style="height:8px;background:var(--pd-bg2);border-radius:4px;overflow:hidden;">
            <div style="height:100%;width:{{ $hoursPct }}%;background:{{ $hoursOver ? '#E24B4A' : 'var(--pd-navy)' }};border-radius:4px;transition:width .4s;"></div>
        </div>
    </div>

    {{-- Restant --}}
    @if(!$hoursOver)
    <div style="text-align:center;flex-shrink:0;padding-left:4px;">
        <div style="font-size:22px;font-weight:700;color:var(--pd-muted);line-height:1;">
            {{ number_format($totalEstimated - $totalActual, 1) }}h
        </div>
        <div style="font-size:11px;color:var(--pd-muted);margin-top:2px;">restantes</div>
    </div>
    @else
    <div style="text-align:center;flex-shrink:0;padding-left:4px;">
        <div style="font-size:22px;font-weight:700;color:var(--pd-danger);line-height:1;">
            +{{ number_format($totalActual - $totalEstimated, 1) }}h
        </div>
        <div style="font-size:11px;color:var(--pd-danger);margin-top:2px;">dépassement</div>
    </div>
    @endif

</div>
@else
{{-- Pas encore d'heures estimées --}}
<div style="padding:12px 16px;background:var(--pd-surface2);border-radius:10px;border:0.5px solid var(--pd-border);margin-bottom:16px;display:flex;align-items:center;gap:10px;">
    <div style="font-size:22px;font-weight:700;color:var(--pd-muted);">—</div>
    <div style="font-size:12px;color:var(--pd-muted);">
        {{ $taskStats['total'] }} tâche{{ $taskStats['total']>1?'s':'' }} · {{ $progression }}% complété
        · Aucune durée estimée pour l'instant
    </div>
</div>
@endif

{{-- ── Onglets ── --}}
<div class="sub-tabs">
    <button class="sub-tab" :class="{active:tab==='liste'}"    @click="switchTab('liste')">Liste</button>
    <button class="sub-tab" :class="{active:tab==='kanban'}"   @click="switchTab('kanban')">Kanban</button>
    <button class="sub-tab" :class="{active:tab==='gantt'}"    @click="switchTab('gantt')">Gantt</button>
    <button class="sub-tab" :class="{active:tab==='agenda'}"   @click="switchTab('agenda')">Agenda</button>
    <button class="sub-tab" :class="{active:tab==='workload'}" @click="switchTab('workload')">Charge</button>
</div>

<div x-show="tab==='kanban'"   x-cloak>@include('projects.partials._kanban')</div>
<div x-show="tab==='gantt'"    x-cloak>@include('projects.partials._gantt')</div>
<div x-show="tab==='liste'"    x-cloak>@include('projects.partials._list')</div>
<div x-show="tab==='agenda'"   x-cloak>@include('projects.partials._agenda')</div>
<div x-show="tab==='workload'" x-cloak>@include('projects.partials._workload')</div>

</div>
