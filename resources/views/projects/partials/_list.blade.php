{{-- _list.blade.php — Liste par jalon, en cours par défaut --}}
@php
$statusColors = [
    'todo'        => ['bg'=>'#E2E8F0','text'=>'#475569'],
    'in_progress' => ['bg'=>'#DBEAFE','text'=>'#1E40AF'],
    'in_review'   => ['bg'=>'#EDE9FE','text'=>'#5B21B6'],
    'done'        => ['bg'=>'#D1FAE5','text'=>'#065F46'],
];
$pColors = [
    'urgent' => ['bg'=>'#FEE2E2','text'=>'#991B1B'],
    'high'   => ['bg'=>'#FEF3C7','text'=>'#92400E'],
    'medium' => ['bg'=>'#DBEAFE','text'=>'#1E40AF'],
    'low'    => ['bg'=>'#D1FAE5','text'=>'#065F46'],
];

// Identifier le jalon actif
$nextActiveMs = null;
foreach ($project->milestones->sortBy('due_date') as $ms) {
    if (!$ms->isReached() && $ms->due_date && $ms->due_date->isFuture()) {
        $nextActiveMs = $ms->id;
        break;
    }
}
if (!$nextActiveMs && $project->milestones->isNotEmpty()) {
    $nextActiveMs = $project->milestones->sortBy('due_date')->first()->id;
}
@endphp

<div x-data="{ showAll: false, filter: '' }">

{{-- ── Barre d'outils ── --}}
<div style="display:flex;align-items:center;gap:8px;margin-bottom:14px;flex-wrap:wrap;">
    <input type="text" x-model="filter" placeholder="Filtrer les tâches…"
           class="pd-input" style="flex:1;max-width:280px;">
    <label style="display:flex;align-items:center;gap:6px;font-size:12px;color:var(--pd-muted);cursor:pointer;">
        <input type="checkbox" x-model="showAll" style="accent-color:var(--pd-navy);">
        Toutes les tâches
    </label>
    @if($canEdit)
    <button @click="$dispatch('open-new-task',{status:'todo'})"
            class="pd-btn pd-btn-sm pd-btn-primary" style="margin-left:auto;">
        + Nouvelle tâche
    </button>
    @endif
</div>

{{-- ── Groupes par jalon ── --}}
@foreach($tasksByMilestone as $group)
@php
    $milestone    = $group['milestone'];
    $groupTasks   = $group['tasks'];
    $children     = $group['children'] ?? collect();
    $isPhaseGroup = $milestone && $milestone->isPhase() && $children->isNotEmpty();
    $isReached    = $milestone && $milestone->isReached();
    $isLate       = $milestone && $milestone->isLate();
    $isOpen       = $milestone && $milestone->id === $nextActiveMs;
    // Pour les phases : compter les tâches de tous les enfants
    $activeCount  = $isPhaseGroup
        ? $children->sum(fn($c) => $c['tasks']->where('status','!=','done')->count())
        : $groupTasks->where('status','!=','done')->count();
    $doneCount    = $isPhaseGroup
        ? $children->sum(fn($c) => $c['tasks']->where('status','done')->count())
        : $groupTasks->where('status','done')->count();
    if ($isReached)  { $hdrBg='#F0FDF4'; $hdrBdr='#86EFAC'; }
    elseif ($isLate) { $hdrBg='#FFF5F5'; $hdrBdr='#FCA5A5'; }
    else             { $hdrBg='var(--pd-surface)'; $hdrBdr='var(--pd-border)'; }
@endphp

<div x-data="{ open: {{ $isOpen ? 'true' : 'false' }} }"
     style="border:0.5px solid {{ $hdrBdr }};border-radius:10px;overflow:hidden;margin-bottom:8px;">

    {{-- En-tête groupe --}}
    <div @click="open=!open"
         style="display:flex;align-items:center;gap:10px;padding:9px 14px;cursor:pointer;user-select:none;background:{{ $hdrBg }};">

        @if($isReached)
        <div style="width:18px;height:18px;border-radius:50%;background:#16A34A;color:#fff;font-size:10px;display:flex;align-items:center;justify-content:center;font-weight:700;flex-shrink:0;">✓</div>
        @elseif($isLate)
        <div style="width:18px;height:18px;border-radius:50%;background:#FEE2E2;border:1.5px solid #E24B4A;color:#E24B4A;font-size:10px;display:flex;align-items:center;justify-content:center;font-weight:700;flex-shrink:0;">!</div>
        @else
        <div style="width:18px;height:18px;border-radius:50%;background:{{ $milestone->color ?? '#94A3B8' }};flex-shrink:0;"></div>
        @endif

        <span style="font-size:13px;font-weight:700;flex:1;color:{{ $isReached ? '#065F46' : 'var(--pd-text)' }};">
            {{ $milestone ? $milestone->title : 'Sans jalon' }}
        </span>

        @if($milestone?->due_date)
        <span style="font-size:11px;color:{{ $isLate ? '#E24B4A' : 'var(--pd-muted)' }};">
            {{ $milestone->due_date->translatedFormat('d M Y') }}
            @if($isReached) · ✓ @elseif($isLate) · En retard @endif
        </span>
        @endif

        <div style="display:flex;gap:5px;font-size:10px;">
            @if($activeCount > 0)<span style="background:var(--pd-bg2);padding:2px 7px;border-radius:8px;color:var(--pd-muted);">{{ $activeCount }} active{{ $activeCount>1?'s':'' }}</span>@endif
            @if($doneCount > 0)<span style="background:#D1FAE5;color:#065F46;padding:2px 7px;border-radius:8px;">{{ $doneCount }} ✓</span>@endif
        </div>

        <div :style="open?'transform:rotate(0deg)':'transform:rotate(-90deg)'"
             style="transition:transform .2s;color:var(--pd-muted);font-size:13px;flex-shrink:0;">▾</div>
    </div>

    {{-- Tableau tâches --}}
    <div x-show="open"
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100">

        @if($isPhaseGroup)
        {{-- Phase : sous-groupes par jalon enfant --}}
        @foreach($children as $child)
        @php
            $childMs    = $child['milestone'];
            $childTasks = $child['tasks'];
            $childReach = $childMs->isReached();
            $childLate  = $childMs->isLate();
            $childColor = $childMs->color ?? ($milestone->color ?? '#EA580C');
        @endphp
        <div style="margin:6px 10px;border:0.5px solid var(--pd-border);border-radius:8px;overflow:hidden;"
             x-data="{ childOpen: {{ !$childReach ? 'true' : 'false' }} }">
            <div @click="childOpen=!childOpen"
                 style="display:flex;align-items:center;gap:8px;padding:7px 12px;cursor:pointer;
                        background:{{ $childReach ? '#F0FDF4' : ($childLate ? '#FEF2F2' : 'var(--pd-surface2)') }};">
                <div style="width:7px;height:7px;border-radius:50%;background:{{ $childColor }};"></div>
                <span style="font-size:11px;font-weight:600;flex:1;color:{{ $childReach ? '#065F46' : 'var(--pd-text)' }};">
                    🏁 {{ $childMs->title }}
                </span>
                <span style="font-size:10px;color:{{ $childLate ? 'var(--pd-danger)' : 'var(--pd-muted)' }};">
                    {{ $childMs->due_date?->format('d/m/Y') }}
                    @if($childReach) ✓ @elseif($childLate) · Retard @endif
                </span>
                <span style="font-size:10px;color:var(--pd-muted);">{{ $childTasks->count() }} tâche{{ $childTasks->count()>1?'s':'' }}</span>
                <span :style="childOpen ? '' : 'transform:rotate(-90deg)'" style="transition:transform .15s;color:var(--pd-muted);font-size:12px;">▾</span>
            </div>
            <div x-show="childOpen">
                @if($childTasks->isEmpty())
                <div style="padding:10px 14px;font-size:12px;color:var(--pd-muted);">Aucune tâche dans ce jalon.</div>
                @else
                @include('projects.partials._list_table', ['tasks' => $childTasks])
                @endif
            </div>
        </div>
        @endforeach
        @else
        {{-- Jalon autonome ou sans jalon --}}
        @if($groupTasks->isEmpty())
        <div style="padding:14px 16px;font-size:12px;color:var(--pd-muted);">Aucune tâche dans ce jalon.</div>
        @else
        @include('projects.partials._list_table', ['tasks' => $groupTasks])
        @endif
        @endif
    </div>

</div>
@endforeach

</div>