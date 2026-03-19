{{-- _list_table.blade.php — Tableau de tâches réutilisable
     Reçoit : $tasks (Collection), $statusColors, $pColors (hérités du scope parent)
--}}
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
    @foreach($tasks->sortByDesc(fn($t) => match($t->status){'in_progress'=>3,'in_review'=>2,'todo'=>1,default=>0}) as $task)
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
