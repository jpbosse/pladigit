{{-- resources/views/projects/partials/_list.blade.php --}}
@php
$allTasks = $project->rootTasks()
    ->with(['assignee', 'milestone'])
    ->orderByRaw("FIELD(status,'in_progress','in_review','todo','done')")
    ->orderByRaw("FIELD(priority,'urgent','high','medium','low')")
    ->get();
@endphp

<div x-data="{ filter: '' }">

    <div style="display:flex;gap:8px;margin-bottom:12px;align-items:center;">
        <input type="text" x-model="filter" placeholder="Filtrer les tâches…"
               style="flex:1;max-width:300px;" class="pd-input pd-input-sm">
        @if($canEdit)
        <button @click="$dispatch('open-new-task', { status: 'todo' })"
                class="pd-btn pd-btn-sm pd-btn-primary">+ Nouvelle tâche</button>
        @endif
    </div>

    <div style="overflow-x:auto;">
    <table style="width:100%;border-collapse:collapse;font-size:13px;">
        <thead>
        <tr style="background:var(--pd-surface);border-bottom:0.5px solid var(--pd-border);">
            <th style="padding:10px 12px;text-align:left;font-weight:600;color:var(--pd-muted);font-size:11px;text-transform:uppercase;letter-spacing:.04em;width:40%">Tâche</th>
            <th style="padding:10px 12px;text-align:left;font-weight:600;color:var(--pd-muted);font-size:11px;text-transform:uppercase;letter-spacing:.04em;">Statut</th>
            <th style="padding:10px 12px;text-align:left;font-weight:600;color:var(--pd-muted);font-size:11px;text-transform:uppercase;letter-spacing:.04em;">Priorité</th>
            <th style="padding:10px 12px;text-align:left;font-weight:600;color:var(--pd-muted);font-size:11px;text-transform:uppercase;letter-spacing:.04em;">Assigné</th>
            <th style="padding:10px 12px;text-align:left;font-weight:600;color:var(--pd-muted);font-size:11px;text-transform:uppercase;letter-spacing:.04em;">Échéance</th>
            <th style="padding:10px 12px;text-align:left;font-weight:600;color:var(--pd-muted);font-size:11px;text-transform:uppercase;letter-spacing:.04em;">Heures</th>
        </tr>
        </thead>
        <tbody>
        @forelse($allTasks as $task)
        @php
            $statusColors = [
                'todo'        => ['#E2E8F0','#475569'],
                'in_progress' => ['#DBEAFE','#1E40AF'],
                'in_review'   => ['#EDE9FE','#5B21B6'],
                'done'        => ['#D1FAE5','#065F46'],
            ];
            $pColors = [
                'urgent' => ['#FEE2E2','#991B1B'],
                'high'   => ['#FEF3C7','#92400E'],
                'medium' => ['#DBEAFE','#1E40AF'],
                'low'    => ['#D1FAE5','#065F46'],
            ];
        @endphp
        <tr x-show="!filter || '{{ addslashes(strtolower($task->title)) }}'.includes(filter.toLowerCase())"
            style="border-bottom:0.5px solid var(--pd-border);cursor:pointer;transition:background .1s;"
            @click="$dispatch('open-task', { taskId: {{ $task->id }} })"
            @mouseenter="$el.style.background='var(--pd-surface)'"
            @mouseleave="$el.style.background='transparent'">

            <td style="padding:10px 12px;">
                <div style="{{ $task->status === 'done' ? 'text-decoration:line-through;color:var(--pd-muted);' : '' }}">
                    {{ $task->title }}
                </div>
                @if($task->milestone)
                <div style="font-size:11px;color:var(--pd-muted);">🏁 {{ $task->milestone->title }}</div>
                @endif
            </td>
            <td style="padding:10px 12px;">
                <span style="font-size:11px;padding:2px 8px;border-radius:10px;font-weight:500;background:{{ $statusColors[$task->status][0] }};color:{{ $statusColors[$task->status][1] }};">
                    {{ \App\Models\Tenant\Task::statusLabels()[$task->status] }}
                </span>
            </td>
            <td style="padding:10px 12px;">
                <span style="font-size:11px;padding:2px 8px;border-radius:10px;font-weight:500;background:{{ $pColors[$task->priority][0] }};color:{{ $pColors[$task->priority][1] }};">
                    {{ \App\Models\Tenant\Task::priorityLabels()[$task->priority] }}
                </span>
            </td>
            <td style="padding:10px 12px;color:var(--pd-muted);">{{ $task->assignee?->name ?? '—' }}</td>
            <td style="padding:10px 12px;{{ $task->due_date && $task->due_date->isPast() && $task->status !== 'done' ? 'color:var(--pd-danger);font-weight:500;' : 'color:var(--pd-muted);' }}">
                {{ $task->due_date?->format('d/m/Y') ?? '—' }}
            </td>
            <td style="padding:10px 12px;color:var(--pd-muted);">
                @if($task->estimated_hours) {{ $task->actual_hours ?? '?' }}/{{ $task->estimated_hours }}h
                @else —
                @endif
            </td>
        </tr>
        @empty
        <tr><td colspan="6" style="padding:24px;text-align:center;color:var(--pd-muted);">Aucune tâche dans ce projet.</td></tr>
        @endforelse
        </tbody>
    </table>
    </div>
</div>
