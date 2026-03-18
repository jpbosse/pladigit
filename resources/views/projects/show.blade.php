{{-- resources/views/projects/show.blade.php --}}
@extends('layouts.app')
@section('title', $project->name)

@push('styles')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/trix@2.0.8/dist/trix.css">
    <style>
    .trix-content { font-size:13px; line-height:1.7; color:var(--pd-text); }
    .trix-content strong { font-weight:700; }
    .trix-content em { font-style:italic; }
    .trix-content ul, .trix-content ol { padding-left:1.5em; margin:.5em 0; }
    .trix-content li { margin:.2em 0; }
    .trix-content h1 { font-size:16px; font-weight:700; margin:.8em 0 .3em; }
    .trix-content blockquote { border-left:3px solid var(--pd-border); padding-left:12px; color:var(--pd-muted); margin:.5em 0; }
    </style>
<style>
.proj-shell    { display:grid; grid-template-columns:220px 1fr; gap:0; }
.proj-sidenav  { background:var(--pd-surface); border-right:0.5px solid var(--pd-border); display:flex; flex-direction:column; position:sticky; top:0; max-height:calc(100vh - 60px); overflow-y:auto; }
.proj-main     { padding:20px; overflow:auto; }
.sn-project    { padding:14px 16px 12px; border-bottom:0.5px solid var(--pd-border); }
.sn-proj-name  { font-size:13px; font-weight:700; color:var(--pd-text); line-height:1.3; }
.sn-section    { padding:10px 8px 4px; }
.sn-lbl        { font-size:9px; font-weight:700; text-transform:uppercase; letter-spacing:.08em; color:var(--pd-muted); padding:0 8px; margin-bottom:3px; display:block; }
.sn-item       { display:flex; align-items:center; gap:9px; padding:7px 10px; border-radius:8px; cursor:pointer; transition:background .12s; border:none; background:none; width:100%; text-align:left; color:var(--pd-text); }
.sn-item:hover { background:var(--pd-bg2); }
.sn-item.active{ background:var(--pd-bg2); color:var(--pd-navy); }
.sn-icon       { width:15px; height:15px; opacity:.5; flex-shrink:0; }
.sn-item.active .sn-icon { opacity:1; }
.sn-label      { font-size:12px; font-weight:500; flex:1; }
.sn-badge      { font-size:10px; padding:1px 5px; border-radius:8px; background:var(--pd-bg2); color:var(--pd-muted); border:0.5px solid var(--pd-border); }
.sn-badge.warn { background:#FEF3C7; color:#92400E; border-color:#FCD34D; }
.sn-badge.danger{ background:#FEE2E2; color:#991B1B; border-color:#FCA5A5; }
.sn-elus       { margin-top:auto; border-top:0.5px solid var(--pd-border); padding:8px 8px 12px; }
.section-hdr   { margin-bottom:18px; padding-bottom:12px; border-bottom:0.5px solid var(--pd-border); display:flex; align-items:flex-start; justify-content:space-between; }
.section-title { font-size:16px; font-weight:700; color:var(--pd-navy); }
.section-sub   { font-size:12px; color:var(--pd-muted); margin-top:2px; }
.pd-card       { background:var(--pd-surface); border:0.5px solid var(--pd-border); border-radius:10px; padding:14px 16px; }
.stat-grid     { display:grid; grid-template-columns:repeat(4,1fr); gap:10px; margin-bottom:16px; }
.stat-card     { background:var(--pd-surface); border:0.5px solid var(--pd-border); border-radius:10px; padding:12px 14px; }
.stat-lbl      { font-size:10px; color:var(--pd-muted); text-transform:uppercase; letter-spacing:.05em; margin-bottom:4px; }
.stat-val      { font-size:20px; font-weight:700; color:var(--pd-navy); }
.stat-sub      { font-size:11px; color:var(--pd-muted); margin-top:2px; }
.pd-table      { width:100%; border-collapse:collapse; font-size:12px; }
.pd-table th   { font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.05em; color:var(--pd-muted); padding:6px 10px; border-bottom:0.5px solid var(--pd-border); text-align:left; }
.pd-table td   { padding:9px 10px; border-bottom:0.5px solid var(--pd-border); vertical-align:middle; }
.pd-table tr:last-child td { border-bottom:none; }
.pd-badge      { display:inline-flex; align-items:center; font-size:10px; font-weight:600; padding:2px 7px; border-radius:10px; }
.btn-sm        { padding:5px 10px; font-size:11px; border-radius:7px; border:0.5px solid var(--pd-border); background:var(--pd-surface); color:var(--pd-text); cursor:pointer; transition:background .12s; }
.btn-sm:hover  { background:var(--pd-bg2); }
.btn-navy      { background:var(--pd-navy); color:#fff; border-color:var(--pd-navy); }
.btn-navy:hover{ opacity:.9; }
.bbar-wrap     { height:6px; background:var(--pd-bg2); border-radius:3px; overflow:hidden; margin-top:4px; }
.bbar-fill     { height:100%; border-radius:3px; }
.sub-tabs      { display:flex; gap:0; border-bottom:0.5px solid var(--pd-border); margin-bottom:16px; }
.sub-tab       { padding:8px 16px; font-size:12px; font-weight:500; color:var(--pd-muted); cursor:pointer; border:none; background:none; border-bottom:2px solid transparent; transition:all .15s; }
.sub-tab.active{ color:var(--pd-navy); border-bottom-color:var(--pd-navy); }
.sh-avatar     { width:30px; height:30px; border-radius:50%; font-size:10px; font-weight:700; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.obs-item      { display:flex; gap:10px; padding:10px 0; border-bottom:0.5px solid var(--pd-border); }
.obs-item:last-child{ border:none; }
</style>
@endpush

@section('content')
@php
$canManage = $userRole?->canManage() || in_array(auth()->user()?->role, ['admin','president','dgs']);
$canEdit   = $userRole?->canEdit() || $canManage;
@endphp

<div class="proj-shell" x-data="{ section: '{{ request('section','but') }}', go(s){ this.section=s; const u=new URL(location); u.searchParams.set('section',s); history.replaceState(null,'',u); } }">

{{-- ── SIDEBAR ─────────────────────────────────────────────────── --}}
<nav class="proj-sidenav">
    <div class="sn-project">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:5px;">
            <div style="width:10px;height:10px;border-radius:50%;background:{{ $project->color }};flex-shrink:0;"></div>
            <div class="sn-proj-name">{{ $project->name }}</div>
        </div>
        <span class="pd-badge" style="background:{{ \App\Models\Tenant\Project::statusColors()[$project->status]['bg'] }};color:{{ \App\Models\Tenant\Project::statusColors()[$project->status]['text'] }};">
            {{ \App\Models\Tenant\Project::statusLabels()[$project->status] }}
        </span>
        <span style="font-size:11px;color:var(--pd-muted);margin-left:6px;">{{ $progression }}%</span>
    </div>

    <div class="sn-section">
        <span class="sn-lbl">Projet</span>
        <button class="sn-item" :class="{active:section==='but'}" @click="go('but')">
            <svg class="sn-icon" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="6" stroke="currentColor" stroke-width="1.2"/><circle cx="8" cy="8" r="2.5" fill="currentColor"/></svg>
            <span class="sn-label">But &amp; description</span>
        </button>
        <button class="sn-item" :class="{active:section==='finances'}" @click="go('finances')">
            <svg class="sn-icon" viewBox="0 0 16 16" fill="none"><rect x="2" y="5" width="12" height="9" rx="2" stroke="currentColor" stroke-width="1.2"/><path d="M5 5V4a3 3 0 016 0v1" stroke="currentColor" stroke-width="1.2"/><path d="M8 9v2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
            <span class="sn-label">Finances</span>
            @if($budgetAlerts->count())<span class="sn-badge warn">{{ $budgetAlerts->count() }} alerte{{ $budgetAlerts->count()>1?'s':'' }}</span>@endif
        </button>
    </div>

    <div class="sn-section">
        <span class="sn-lbl">Planification</span>
        <button class="sn-item" :class="{active:section==='planif'}" @click="go('planif')">
            <svg class="sn-icon" viewBox="0 0 16 16" fill="none"><rect x="1" y="3" width="4" height="10" rx="1.5" stroke="currentColor" stroke-width="1.2"/><rect x="6" y="3" width="4" height="7" rx="1.5" stroke="currentColor" stroke-width="1.2"/><rect x="11" y="3" width="4" height="5" rx="1.5" stroke="currentColor" stroke-width="1.2"/></svg>
            <span class="sn-label">Tâches &amp; planning</span>
            @php $activeTasks = $taskStats['todo']+$taskStats['in_progress']+$taskStats['in_review']; @endphp
            @if($activeTasks)<span class="sn-badge">{{ $activeTasks }}</span>@endif
        </button>
    </div>

    <div class="sn-section">
        <span class="sn-lbl">Conduite du changement</span>
        <button class="sn-item" :class="{active:section==='parties'}" @click="go('parties')">
            <svg class="sn-icon" viewBox="0 0 16 16" fill="none"><circle cx="6" cy="5" r="2.5" stroke="currentColor" stroke-width="1.2"/><circle cx="11" cy="6" r="2" stroke="currentColor" stroke-width="1.2"/><path d="M1 13c0-2.2 2.2-4 5-4s5 1.8 5 4" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/><path d="M11 10c1.7.3 3 1.4 3 3" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/></svg>
            <span class="sn-label">Parties prenantes</span>
            @php $resistant=$project->stakeholders->where('adhesion','resistant')->count(); @endphp
            @if($resistant)<span class="sn-badge danger">{{ $resistant }} résistant{{ $resistant>1?'s':'' }}</span>@endif
        </button>
        <button class="sn-item" :class="{active:section==='comcom'}" @click="go('comcom')">
            <svg class="sn-icon" viewBox="0 0 16 16" fill="none"><path d="M2 3h12v8H9l-3 2V11H2z" stroke="currentColor" stroke-width="1.2" stroke-linejoin="round"/></svg>
            <span class="sn-label">Plan de communication</span>
            @php $lateComm=$project->commActions->filter(fn($a)=>$a->isLate())->count(); @endphp
            @if($lateComm)<span class="sn-badge warn">{{ $lateComm }} en retard</span>@endif
        </button>
        <button class="sn-item" :class="{active:section==='risques'}" @click="go('risques')">
            <svg class="sn-icon" viewBox="0 0 16 16" fill="none"><path d="M8 2L14 13H2L8 2z" stroke="currentColor" stroke-width="1.2" stroke-linejoin="round"/><path d="M8 6v3M8 11v.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
            <span class="sn-label">Freins &amp; risques</span>
            @if($criticalRisksCount)<span class="sn-badge danger">{{ $criticalRisksCount }} critique{{ $criticalRisksCount>1?'s':'' }}</span>@endif
        </button>
    </div>

    <div class="sn-elus">
        <button class="sn-item" :class="{active:section==='elus'}" @click="go('elus')" style="background:var(--pd-bg2);border-radius:8px;">
            <svg class="sn-icon" style="opacity:1;" viewBox="0 0 16 16" fill="none"><rect x="2" y="2" width="5" height="5" rx="1" stroke="currentColor" stroke-width="1.2"/><rect x="9" y="2" width="5" height="5" rx="1" stroke="currentColor" stroke-width="1.2"/><rect x="2" y="9" width="5" height="5" rx="1" stroke="currentColor" stroke-width="1.2"/><rect x="9" y="9" width="5" height="5" rx="1" stroke="currentColor" stroke-width="1.2"/></svg>
            <span class="sn-label" style="font-weight:700;color:var(--pd-navy);">Tableau de bord élus</span>
        </button>
    </div>
</nav>

{{-- ── CONTENU ──────────────────────────────────────────────────── --}}
<div class="proj-main">
    <div x-show="section==='but'"      x-cloak>@include('projects.partials._but')</div>
    <div x-show="section==='finances'" x-cloak>@include('projects.partials._finances')</div>
    <div x-show="section==='planif'"   x-cloak>@include('projects.partials._planif')</div>
    <div x-show="section==='parties'"  x-cloak>@include('projects.partials._stakeholders')</div>
    <div x-show="section==='comcom'"   x-cloak>@include('projects.partials._comcom')</div>
    <div x-show="section==='risques'"  x-cloak>@include('projects.partials._risques')</div>
    <div x-show="section==='elus'"     x-cloak>@include('projects.partials._elus')</div>
    @include('projects.partials._task_slideover')
</div>

</div>
@endsection
