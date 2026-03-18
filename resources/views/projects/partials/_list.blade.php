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
    $milestone   = $group['milestone'];
    $groupTasks  = $group['tasks'];
    $isReached   = $milestone && $milestone->isReached();
    $isLate      = $milestone && $milestone->isLate();
    $isOpen      = $milestone && $milestone->id === $nextActiveMs;
    $activeCount = $groupTasks->where('status','!=','done')->count();
    $doneCount   = $groupTasks->where('status','done')->count();
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

        @if($groupTasks->isEmpty())
        <div style="padding:14px 16px;font-size:12px;color:var(--pd-muted);">Aucune tâche dans ce jalon.</div>
        @else
        <table style="width:100%;border-collapse:collapse;font-size:12px;">
            <thead>
            <tr style="background:var(--pd-surface2);border-bottom:0.5px solid var(--pd-border);">
                <th style="padding:8px 14px;text-align:left;font-weight:600;color:var(--pd-muted);font-size:10px;text-transform:uppercase;letter-spacing:.04em;width:38%;">Tâche</th>
                <th style="padding:8px 10px;font-weight:600;color:var(--pd-muted);font-size:10px;text-transform:uppercase;letter-spacing:.04em;">Statut</th>
                <th style="padding:8px 10px;font-weight:600;color:var(--pd-muted);font-size:10px;text-transform:uppercase;letter-spacing:.04em;">Priorité</th>
                <th style="padding:8px 10px;font-weight:600;color:var(--pd-muted);font-size:10px;text-transform:uppercase;letter-spacing:.04em;">Assigné</th>
                <th style="padding:8px 10px;font-weight:600;color:var(--pd-muted);font-size:10px;text-transform:uppercase;letter-spacing:.04em;">Échéance</th>
                <th style="padding:8px 10px;font-weight:600;color:var(--pd-muted);font-size:10px;text-transform:uppercase;letter-spacing:.04em;">Heures</th>
            </tr>
            </thead>
            <tbody>
            @foreach($groupTasks->sortByDesc(fn($t) => match($t->status){'in_progress'=>3,'in_review'=>2,'todo'=>1,default=>0}) as $task)
            @php
                $sc = $statusColors[$task->status] ?? ['bg'=>'#F1F5F9','text'=>'#475569'];
                $pc = $pColors[$task->priority]    ?? ['bg'=>'#F1F5F9','text'=>'#475569'];
                $isInProgress = $task->status === 'in_progress';
            @endphp
            <tr x-show="showAll || {{ $isInProgress ? 'true' : 'false' }} || (filter && '{{ addslashes(strtolower($task->title)) }}'.includes(filter.toLowerCase()))"
                style="border-bottom:0.5px solid var(--pd-border);cursor:pointer;transition:background .1s;{{ $task->status==='done' ? 'opacity:.6;' : '' }}"
                @click="$dispatch('open-task',{taskId:{{ $task->id }}})"
                @mouseenter="$el.style.background='var(--pd-surface2)'"
                @mouseleave="$el.style.background='transparent'">

                <td style="padding:9px 14px;">
                    {{-- Indicateur en cours --}}
                    @if($isInProgress)
                    <span style="display:inline-block;width:6px;height:6px;border-radius:50%;background:#3B82F6;margin-right:6px;vertical-align:middle;"></span>
                    @endif
                    <span style="{{ $task->status==='done' ? 'text-decoration:line-through;color:var(--pd-muted);' : '' }}">
                        {{ $task->title }}
                    </span>
                </td>
                <td style="padding:9px 10px;">
                    <span style="font-size:10px;padding:2px 7px;border-radius:8px;font-weight:600;background:{{ $sc['bg'] }};color:{{ $sc['text'] }};">
                        {{ \App\Models\Tenant\Task::statusLabels()[$task->status] }}
                    </span>
                </td>
                <td style="padding:9px 10px;">
                    <span style="font-size:10px;padding:2px 7px;border-radius:8px;font-weight:600;background:{{ $pc['bg'] }};color:{{ $pc['text'] }};">
                        {{ \App\Models\Tenant\Task::priorityLabels()[$task->priority] }}
                    </span>
                </td>
                <td style="padding:9px 10px;color:var(--pd-muted);">
                    @if($task->assignee)
                    <div style="display:flex;align-items:center;gap:6px;">
                        <div style="width:20px;height:20px;border-radius:50%;background:var(--pd-navy);color:#fff;font-size:8px;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            {{ strtoupper(substr($task->assignee->name,0,2)) }}
                        </div>
                        {{ $task->assignee->name }}
                    </div>
                    @else —
                    @endif
                </td>
                <td style="padding:9px 10px;font-size:11px;color:{{ $task->due_date?->isPast() && $task->status!=='done' ? 'var(--pd-danger)' : 'var(--pd-muted)' }};">
                    {{ $task->due_date?->translatedFormat('d M Y') ?? '—' }}
                </td>
                <td style="padding:9px 10px;font-size:11px;color:var(--pd-muted);">
                    @if($task->estimated_hours)
                    {{ number_format($task->actual_hours??0,1) }}h / {{ number_format($task->estimated_hours,1) }}h
                    @else —
                    @endif
                </td>
            </tr>
            @endforeach
            </tbody>
        </table>
        @endif
    </div>

</div>
@endforeach

</div>
