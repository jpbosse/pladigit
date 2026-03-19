{{-- _kanban.blade.php — Kanban par jalon, ADR-008 révisé --}}
{{--
    - Jalons atteints      : repliés, ligne compacte verte
    - Jalons en retard     : repliés, ligne compacte rouge
    - Prochain jalon actif : déplié automatiquement (1 seul)
    - Sans jalon           : replié
    - Terminées masquées par défaut
    - Heures estimées/réalisées sur les cartes
--}}

@php
$columns = [
    'todo'        => ['label' => 'À faire',  'color' => '#94A3B8'],
    'in_progress' => ['label' => 'En cours', 'color' => '#3B82F6'],
    'in_review'   => ['label' => 'En revue', 'color' => '#8B5CF6'],
    'done'        => ['label' => 'Terminé',  'color' => '#16A34A'],
];
$priorityColors = [
    'urgent' => ['bg' => '#FEE2E2', 'text' => '#991B1B'],
    'high'   => ['bg' => '#FEF3C7', 'text' => '#92400E'],
    'medium' => ['bg' => '#DBEAFE', 'text' => '#1E40AF'],
    'low'    => ['bg' => '#D1FAE5', 'text' => '#065F46'],
];

// Compteurs globaux (hors done)
$colCounts = ['todo' => 0, 'in_progress' => 0, 'in_review' => 0, 'done' => 0];
foreach ($tasksByMilestone as $group) {
    foreach ($group['tasks'] as $t) {
        if (isset($colCounts[$t->status])) $colCounts[$t->status]++;
    }
    // Compter aussi les tâches dans les jalons enfants d'une phase
    foreach (($group['children'] ?? collect()) as $child) {
        foreach ($child['tasks'] as $t) {
            if (isset($colCounts[$t->status])) $colCounts[$t->status]++;
        }
    }
}

// Identifier le prochain jalon actif à déplier automatiquement
// = premier jalon non atteint dont la due_date est dans le futur
$nextActiveGroupId = null;
foreach ($tasksByMilestone as $group) {
    $ms = $group['milestone'];
    if ($ms && !$ms->isReached() && $ms->due_date && $ms->due_date->isFuture()) {
        $nextActiveGroupId = 'group-' . $ms->id;
        break;
    }
}
// Si aucun jalon futur, déplier le premier groupe non atteint
if (!$nextActiveGroupId) {
    foreach ($tasksByMilestone as $group) {
        $ms = $group['milestone'];
        if (!$ms || !$ms->isReached()) {
            $nextActiveGroupId = 'group-' . ($ms ? $ms->id : 'unassigned');
            break;
        }
    }
}
@endphp

<div x-data="kanban()" x-init="init()">

{{-- ── Barre d'outils ── --}}
<div style="display:flex;align-items:center;gap:10px;margin-bottom:14px;flex-wrap:wrap;">
    @if($canEdit)
    <button @click="openNewTask('todo')" class="pd-btn pd-btn-sm pd-btn-primary">
        + Nouvelle tâche
    </button>
    @endif
    <div style="display:flex;gap:6px;margin-left:auto;align-items:center;">
        <label style="display:flex;align-items:center;gap:6px;font-size:12px;color:var(--pd-muted);cursor:pointer;">
            <input type="checkbox" x-model="showDone" style="accent-color:var(--pd-navy);">
            Afficher terminées
            <span style="font-size:11px;padding:1px 6px;background:var(--pd-bg2);border-radius:10px;border:0.5px solid var(--pd-border);">
                {{ $colCounts['done'] }}
            </span>
        </label>
    </div>
</div>

{{-- ── En-têtes colonnes fixes ── --}}
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:4px;padding:6px 4px;background:var(--pd-surface2);border-radius:8px;border:0.5px solid var(--pd-border);">
    @foreach($columns as $status => $col)
    <div style="display:flex;align-items:center;gap:6px;justify-content:center;">
        <div style="width:7px;height:7px;border-radius:50%;background:{{ $col['color'] }};flex-shrink:0;"></div>
        <span style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--pd-muted);">
            {{ $col['label'] }}
        </span>
        <span style="font-size:10px;padding:1px 6px;background:var(--pd-bg);border:0.5px solid var(--pd-border);border-radius:10px;color:var(--pd-muted);">
            {{ $colCounts[$status] }}
        </span>
    </div>
    @endforeach
</div>

{{-- ── Groupes par jalon ── --}}
@foreach($tasksByMilestone as $groupIndex => $group)
@php
    $milestone   = $group['milestone'];
    $groupTasks  = $group['tasks'];
    $children    = $group['children'] ?? collect();
    $isPhaseGroup = $milestone && $milestone->isPhase() && $children->isNotEmpty();
    $groupId     = 'group-' . ($milestone ? $milestone->id : 'unassigned');
    $isReached   = $milestone && $milestone->isReached();
    $isLate      = $milestone && $milestone->isLate();
    $activeCount = $groupTasks->where('status', '!=', 'done')->count();
    $doneCount   = $groupTasks->where('status', 'done')->count();
    $isDefaultOpen = ($groupId === $nextActiveGroupId);

    // Heures totales du jalon
    $totalEstimated = $groupTasks->sum('estimated_hours');
    $totalActual    = $groupTasks->sum('actual_hours');

    // État visuel de l'en-tête
    if ($isReached) {
        $headerBg   = '#F0FDF4';
        $headerBdr  = '#86EFAC';
        $dotColor   = '#16A34A';
        $statusIcon = '✓';
        $statusColor= '#16A34A';
    } elseif ($isLate) {
        $headerBg   = '#FFF5F5';
        $headerBdr  = '#FCA5A5';
        $dotColor   = '#E24B4A';
        $statusIcon = '!';
        $statusColor= '#E24B4A';
    } else {
        $headerBg   = 'var(--pd-surface)';
        $headerBdr  = 'var(--pd-border)';
        $dotColor   = $milestone->color ?? '#94A3B8';
        $statusIcon = '';
        $statusColor= 'var(--pd-muted)';
    }
@endphp

<div x-data="{ open: false }"
     style="margin-top:10px;border:0.5px solid {{ $headerBdr }};border-radius:10px;overflow:hidden;background:{{ $isReached ? '#F0FDF4' : 'var(--pd-surface)' }};">

    {{-- En-tête du groupe --}}
    <div @click="open = !open"
         style="display:flex;align-items:center;gap:10px;padding:10px 14px;cursor:pointer;user-select:none;background:{{ $headerBg }};">

        {{-- Indicateur statut --}}
        @if($isReached)
        <div style="width:20px;height:20px;border-radius:50%;background:#16A34A;color:#fff;font-size:11px;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-weight:700;">✓</div>
        @elseif($isLate)
        <div style="width:20px;height:20px;border-radius:50%;background:#FEE2E2;border:1.5px solid #E24B4A;color:#E24B4A;font-size:11px;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-weight:700;">!</div>
        @else
        <div style="width:20px;height:20px;border-radius:50%;background:{{ $dotColor }};opacity:.85;flex-shrink:0;"></div>
        @endif

        {{-- Titre + date --}}
        <div style="flex:1;min-width:0;">
            <span style="font-size:13px;font-weight:700;color:{{ $isReached ? '#065F46' : 'var(--pd-text)' }};">
                @if($isPhaseGroup)<span style="font-size:9px;font-weight:700;background:var(--pd-navy);color:#fff;padding:1px 5px;border-radius:4px;margin-right:5px;letter-spacing:.04em;">PHASE</span>@endif
                {{ $milestone ? $milestone->title : 'Sans jalon' }}
            </span>
            @if($milestone?->due_date)
            <span style="font-size:11px;color:{{ $statusColor }};margin-left:8px;">
                @if($milestone->start_date){{ $milestone->start_date->format('d/m') }} → @endif
                {{ $milestone->due_date->translatedFormat('d M Y') }}
                @if($isReached) · Atteint @elseif($isLate) · En retard @endif
            </span>
            @endif
        </div>

        {{-- Compteurs --}}
        <div style="display:flex;align-items:center;gap:8px;font-size:11px;color:var(--pd-muted);">
            @if($activeCount > 0)
            <span style="background:var(--pd-bg2);padding:2px 8px;border-radius:10px;border:0.5px solid var(--pd-border);">
                {{ $activeCount }} active{{ $activeCount > 1 ? 's' : '' }}
            </span>
            @endif
            @if($doneCount > 0)
            <span style="background:#D1FAE5;color:#065F46;padding:2px 8px;border-radius:10px;">
                {{ $doneCount }} ✓
            </span>
            @endif
            @if($totalEstimated > 0)
            <span style="color:var(--pd-muted);">
                {{ number_format($totalActual ?? 0, 1) }}h / {{ number_format($totalEstimated, 1) }}h
            </span>
            @endif
        </div>

        {{-- Chevron --}}
        <div :style="open ? 'transform:rotate(0deg)' : 'transform:rotate(-90deg)'"
             style="transition:transform .2s;color:var(--pd-muted);font-size:14px;flex-shrink:0;">▾</div>
    </div>

    {{-- Corps kanban (4 colonnes) — visible seulement si déplié --}}
    <div x-show="open"
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0 -translate-y-1"
         x-transition:enter-end="opacity-100 translate-y-0"
         style="padding:10px;border-top:0.5px solid {{ $headerBdr }};">

        @if($isPhaseGroup)
        {{-- ── Phase : sous-groupes par jalon enfant ── --}}
        @foreach($children as $child)
        @php
            $childMs    = $child['milestone'];
            $childTasks = $child['tasks'];
            $childId    = 'group-child-' . $childMs->id;
            $childReach = $childMs->isReached();
            $childLate  = $childMs->isLate();
            $childColor = $childMs->color ?? ($milestone->color ?? '#EA580C');
        @endphp
        <div style="margin-bottom:10px;border:0.5px solid var(--pd-border);border-radius:8px;overflow:hidden;"
             x-data="{ childOpen: false }">
            {{-- En-tête jalon enfant --}}
            <div @click="childOpen=!childOpen"
                 style="display:flex;align-items:center;gap:8px;padding:7px 10px;cursor:pointer;
                        background:{{ $childReach ? '#F0FDF4' : ($childLate ? '#FEF2F2' : 'var(--pd-surface2)') }};">
                <div style="width:8px;height:8px;border-radius:50%;background:{{ $childColor }};flex-shrink:0;"></div>
                <span style="font-size:11px;font-weight:600;flex:1;color:{{ $childReach ? '#065F46' : 'var(--pd-text)' }};">
                    🏁 {{ $childMs->title }}
                </span>
                <span style="font-size:10px;color:{{ $childLate ? 'var(--pd-danger)' : 'var(--pd-muted)' }};">
                    {{ $childMs->due_date?->format('d/m/Y') }}
                    @if($childReach) ✓ @elseif($childLate) · Retard @endif
                </span>
                <span style="font-size:10px;color:var(--pd-muted);">{{ $childTasks->count() }} tâche{{ $childTasks->count()>1?'s':'' }}</span>
                <span :style="childOpen ? 'transform:rotate(0deg)' : 'transform:rotate(-90deg)'"
                      style="transition:transform .15s;color:var(--pd-muted);font-size:12px;">▾</span>
            </div>
            {{-- Colonnes enfant --}}
            <div x-show="childOpen" style="padding:8px;">
                <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:6px;">
                    @foreach($columns as $status => $col)
                    @php $childCellTasks = $childTasks->where('status', $status)->values(); @endphp
                    <div data-col="{{ $status }}" data-group="{{ $childId }}"
                         @dragover.prevent="onDragOver($event, '{{ $status }}')"
                         @drop.prevent="onDrop($event, '{{ $status }}')"
                         @dragleave="onDragLeave($event)"
                         :class="{ 'pd-kanban-col-over': dragOver === '{{ $status }}' }"
                         style="background:var(--pd-bg);border-radius:6px;padding:6px;border:0.5px solid var(--pd-border);min-height:40px;">
                        <div class="pd-kanban-cards" id="col-{{ $status }}-{{ $childId }}"
                             style="display:flex;flex-direction:column;gap:5px;">
                            @foreach($childCellTasks as $task)
                            @php
                                $hasHours  = $task->estimated_hours > 0;
                                $hoursPct  = $hasHours ? min(100, round(($task->actual_hours ?? 0) / $task->estimated_hours * 100)) : 0;
                                $hoursOver = $hasHours && ($task->actual_hours ?? 0) > $task->estimated_hours;
                            @endphp
                            <div class="pd-kanban-card" draggable="true"
                                 data-task-id="{{ $task->id }}" data-status="{{ $status }}"
                                 @dragstart="onDragStart($event, {{ $task->id }}, '{{ $status }}')"
                                 @dragend="onDragEnd()"
                                 @dragover.prevent="onCardDragOver($event, $el)"
                                 @drop.prevent.stop="onCardDrop($event, {{ $task->id }}, '{{ $status }}', $el)"
                                 @click="if(!isDragging) openTask({{ $task->id }})"
                                 @if($status === 'done') x-show="showDone" @endif
                                 style="background:var(--pd-surface);border:0.5px solid var(--pd-border);border-radius:7px;padding:8px 9px;cursor:grab;{{ $status === 'done' ? 'opacity:.55;' : '' }}">
                                <div style="font-size:12px;font-weight:500;color:var(--pd-text);line-height:1.4;margin-bottom:6px;">{{ $task->title }}</div>
                                @if($hasHours)
                                <div style="margin-bottom:5px;">
                                    <div style="height:3px;background:var(--pd-bg2);border-radius:2px;overflow:hidden;">
                                        <div style="height:100%;width:{{ $hoursPct }}%;background:{{ $hoursOver ? '#E24B4A' : '#3B82F6' }};border-radius:2px;"></div>
                                    </div>
                                </div>
                                @endif
                                <div style="display:flex;align-items:center;gap:4px;flex-wrap:wrap;">
                                    <span style="font-size:9px;padding:2px 5px;border-radius:7px;font-weight:700;background:{{ $priorityColors[$task->priority]['bg'] }};color:{{ $priorityColors[$task->priority]['text'] }};">
                                        {{ \App\Models\Tenant\Task::priorityLabels()[$task->priority] }}
                                    </span>
                                    @if($task->assignee)
                                    <div style="width:16px;height:16px;border-radius:50%;background:var(--pd-navy);color:#fff;font-size:7px;font-weight:700;display:flex;align-items:center;justify-content:center;margin-left:auto;">
                                        {{ strtoupper(substr($task->assignee->name,0,2)) }}
                                    </div>
                                    @endif
                                    @if($task->due_date)
                                    <span style="font-size:10px;color:{{ $task->due_date->isPast() && $task->status !== 'done' ? 'var(--pd-danger)' : 'var(--pd-muted)' }};">
                                        {{ $task->due_date->format('d/m') }}
                                    </span>
                                    @endif
                                </div>
                            </div>
                            @endforeach
                        </div>
                        @if($canEdit && $status === 'todo')
                        <button @click.stop="openNewTask('todo', {{ $childMs->id }})"
                                style="width:100%;margin-top:4px;padding:3px;font-size:11px;color:var(--pd-muted);background:none;border:0.5px dashed var(--pd-border);border-radius:5px;cursor:pointer;"
                                onmouseover="this.style.borderColor='#3B82F6';this.style.color='#3B82F6'"
                                onmouseout="this.style.borderColor='var(--pd-border)';this.style.color='var(--pd-muted)'">
                            + Ajouter
                        </button>
                        @endif
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
        @endforeach

        @else
        {{-- ── Jalon autonome ou sans jalon : colonnes classiques ── --}}
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:8px;">
            @foreach($columns as $status => $col)
            @php $cellTasks = $groupTasks->where('status', $status)->values(); @endphp

            <div data-col="{{ $status }}"
                 data-group="{{ $groupId }}"
                 @dragover.prevent="onDragOver($event, '{{ $status }}')"
                 @drop.prevent="onDrop($event, '{{ $status }}')"
                 @dragleave="onDragLeave($event)"
                 :class="{ 'pd-kanban-col-over': dragOver === '{{ $status }}' }"
                 style="background:var(--pd-bg);border-radius:8px;padding:8px;border:0.5px solid var(--pd-border);min-height:50px;">

                <div class="pd-kanban-cards" id="col-{{ $status }}-{{ $groupId }}"
                     style="display:flex;flex-direction:column;gap:6px;">

                    @foreach($cellTasks as $task)
                    @php
                        $hasHours = $task->estimated_hours > 0;
                        $hoursPct = $hasHours ? min(100, round(($task->actual_hours ?? 0) / $task->estimated_hours * 100)) : 0;
                        $hoursOver = $hasHours && ($task->actual_hours ?? 0) > $task->estimated_hours;
                    @endphp
                    <div class="pd-kanban-card"
                         draggable="true"
                         data-task-id="{{ $task->id }}"
                         data-status="{{ $status }}"
                         @dragstart="onDragStart($event, {{ $task->id }}, '{{ $status }}')"
                         @dragend="onDragEnd()"
                         @dragover.prevent="onCardDragOver($event, $el)"
                         @drop.prevent.stop="onCardDrop($event, {{ $task->id }}, '{{ $status }}', $el)"
                         @click="if(!isDragging) openTask({{ $task->id }})"
                         @if($status === 'done') x-show="showDone" @endif
                         style="background:var(--pd-surface);border:0.5px solid var(--pd-border);border-radius:8px;padding:9px 10px;cursor:grab;transition:box-shadow .12s,opacity .15s;{{ $status === 'done' ? 'opacity:.55;' : '' }}">

                        <div style="font-size:12px;font-weight:500;color:var(--pd-text);line-height:1.4;margin-bottom:7px;">
                            {{ $task->title }}
                        </div>

                        @if($hasHours)
                        <div style="margin-bottom:6px;">
                            <div style="height:3px;background:var(--pd-bg2);border-radius:2px;overflow:hidden;">
                                <div style="height:100%;width:{{ $hoursPct }}%;background:{{ $hoursOver ? '#E24B4A' : '#3B82F6' }};border-radius:2px;transition:width .3s;"></div>
                            </div>
                            <div style="font-size:10px;color:{{ $hoursOver ? 'var(--pd-danger)' : 'var(--pd-muted)' }};margin-top:2px;text-align:right;">
                                {{ number_format($task->actual_hours ?? 0, 1) }}h / {{ number_format($task->estimated_hours, 1) }}h
                            </div>
                        </div>
                        @endif

                        <div style="display:flex;align-items:center;gap:4px;flex-wrap:wrap;">
                            <span style="font-size:9px;padding:2px 5px;border-radius:7px;font-weight:700;background:{{ $priorityColors[$task->priority]['bg'] }};color:{{ $priorityColors[$task->priority]['text'] }};">
                                {{ \App\Models\Tenant\Task::priorityLabels()[$task->priority] }}
                            </span>

                            @if($task->children->count())
                            <span style="font-size:10px;color:var(--pd-muted);">
                                {{ $task->children->where('status','done')->count() }}/{{ $task->children->count() }}
                            </span>
                            @endif

                            @if($task->assignee)
                            <div style="width:18px;height:18px;border-radius:50%;background:var(--pd-navy);color:#fff;font-size:7px;font-weight:700;display:flex;align-items:center;justify-content:center;margin-left:auto;flex-shrink:0;">
                                {{ strtoupper(substr($task->assignee->name,0,2)) }}
                            </div>
                            @endif

                            @if($task->due_date)
                            <span style="font-size:10px;color:{{ $task->due_date->isPast() && $task->status !== 'done' ? 'var(--pd-danger)' : 'var(--pd-muted)' }};{{ $task->assignee ? '' : 'margin-left:auto;' }}">
                                {{ $task->due_date->format('d/m') }}
                            </span>
                            @endif
                        </div>

                    </div>
                    @endforeach

                </div>

                @if($canEdit && $status === 'todo')
                <button @click.stop="openNewTask('todo', {{ $milestone ? $milestone->id : 'null' }})"
                        style="width:100%;margin-top:5px;padding:4px;font-size:11px;color:var(--pd-muted);background:none;border:0.5px dashed var(--pd-border);border-radius:6px;cursor:pointer;"
                        onmouseover="this.style.borderColor='#3B82F6';this.style.color='#3B82F6'"
                        onmouseout="this.style.borderColor='var(--pd-border)';this.style.color='var(--pd-muted)'">
                    + Ajouter
                </button>
                @endif

            </div>
            @endforeach
        </div>
        @endif

    </div>
</div>

@endforeach

</div>

<script>
function kanban() {
    return {
        dragOver: null,
        dragging: null,
        draggingStatus: null,
        showDone: false,

        init() {},

        onDragStart(e, taskId, status) {
            this.dragging = taskId;
            this.draggingStatus = status;
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', taskId);
            e.currentTarget.style.opacity = '0.4';
        },

        onDragEnd() {
            this.dragging = null;
            this.draggingStatus = null;
            document.querySelectorAll('.pd-kanban-card').forEach(c => {
                c.style.opacity = '';
                c.style.borderTop = '';
                c.style.borderBottom = '';
            });
        },

        onDragOver(e, status) {
            e.preventDefault();
            this.dragOver = status;
        },

        onDragLeave(e) {
            if (!e.currentTarget.contains(e.relatedTarget)) {
                this.dragOver = null;
            }
        },

        // Drag over une carte — indicateur visuel de position
        onCardDragOver(e, cardEl) {
            e.preventDefault();
            e.stopPropagation();
            const rect   = cardEl.getBoundingClientRect();
            const isTop  = e.clientY < rect.top + rect.height / 2;
            document.querySelectorAll('.pd-kanban-card').forEach(c => {
                c.style.borderTop    = '';
                c.style.borderBottom = '';
            });
            if (isTop) {
                cardEl.style.borderTop    = '2px solid var(--pd-navy)';
                cardEl.style.borderBottom = '';
            } else {
                cardEl.style.borderBottom = '2px solid var(--pd-navy)';
                cardEl.style.borderTop    = '';
            }
        },

        // Drop sur une carte — insertion avant ou après
        onCardDrop(e, targetTaskId, targetStatus, cardEl) {
            e.preventDefault();
            e.stopPropagation();
            this.dragOver = null;

            const taskId = parseInt(e.dataTransfer.getData('text/plain'));
            if (!taskId || taskId === targetTaskId) return;

            // Réinitialiser les bordures
            document.querySelectorAll('.pd-kanban-card').forEach(c => {
                c.style.borderTop = '';
                c.style.borderBottom = '';
            });

            const rect    = cardEl.getBoundingClientRect();
            const isTop   = e.clientY < rect.top + rect.height / 2;
            const colEl   = cardEl.closest('[data-col]');
            const container = colEl ? colEl.querySelector('.pd-kanban-cards') : null;

            // Construire l'ordre final
            let orderedIds = [];
            if (container) {
                container.querySelectorAll('.pd-kanban-card').forEach(c => {
                    const id = parseInt(c.dataset.taskId);
                    if (id !== taskId) orderedIds.push(id);
                });
            }

            // Insérer à la bonne position
            const insertIdx = orderedIds.indexOf(targetTaskId);
            if (isTop) {
                orderedIds.splice(insertIdx, 0, taskId);
            } else {
                orderedIds.splice(insertIdx + 1, 0, taskId);
            }

            const sortOrder = orderedIds.indexOf(taskId);

            // Mettre à jour le DOM immédiatement
            if (container) {
                const draggingCard = document.querySelector(`[data-task-id="${taskId}"]`);
                if (draggingCard) {
                    draggingCard.dataset.status = targetStatus;
                    if (isTop) {
                        container.insertBefore(draggingCard, cardEl);
                    } else {
                        cardEl.after(draggingCard);
                    }
                }
            }

            this.patchMove(taskId, targetStatus, sortOrder, orderedIds, colEl);
        },

        onDrop(e, newStatus) {
            e.preventDefault();
            this.dragOver = null;
            const taskId = parseInt(e.dataTransfer.getData('text/plain'));
            if (!taskId) return;

            // Réinitialiser les bordures
            document.querySelectorAll('.pd-kanban-card').forEach(c => {
                c.style.borderTop = '';
                c.style.borderBottom = '';
            });

            const dropTarget = e.currentTarget;

            // Si même colonne et drop sur la colonne (pas sur une carte) → fin de liste
            let orderedIds = [];
            dropTarget.querySelectorAll('.pd-kanban-card').forEach(c => {
                const id = parseInt(c.dataset.taskId);
                if (id !== taskId) orderedIds.push(id);
            });
            orderedIds.push(taskId);

            const sortOrder = orderedIds.length - 1;

            // Mettre à jour le DOM immédiatement
            const card = document.querySelector(`[data-task-id="${taskId}"]`);
            if (card) {
                card.dataset.status = newStatus;
                const cardsContainer = dropTarget.querySelector('.pd-kanban-cards');
                if (cardsContainer) cardsContainer.appendChild(card);
            }

            this.patchMove(taskId, newStatus, sortOrder, orderedIds, dropTarget);
        },

        patchMove(taskId, newStatus, sortOrder, orderedIds, targetColEl) {
            fetch(`{{ route('projects.kanban.move', $project) }}`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ task_id: taskId, new_status: newStatus, sort_order: sortOrder, ordered_ids: orderedIds }),
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    const card = document.querySelector(`[data-task-id="${taskId}"]`);
                    if (card && targetColEl) {
                        card.dataset.status = newStatus;
                        const cardsContainer = targetColEl.querySelector('.pd-kanban-cards');
                        if (cardsContainer) {
                            cardsContainer.appendChild(card);
                        }
                    }
                }
            })
            .catch(err => console.error('Kanban move error:', err));
        },

        openTask(taskId) {
            window.dispatchEvent(new CustomEvent('open-task', { detail: { taskId } }));
        },

        openNewTask(status, milestoneId = null) {
            window.dispatchEvent(new CustomEvent('open-new-task', { detail: { status, milestoneId } }));
        },
    };
}
</script>

<script>
// ── Réordonnancement interne Kanban — JS natif (indépendant d'Alpine) ──────────
(function () {
    let draggingId   = null;
    let draggingCard = null;

    function getCards() {
        return document.querySelectorAll('.pd-kanban-card');
    }

    function clearIndicators() {
        document.querySelectorAll('.pd-kanban-card').forEach(c => {
            c.style.borderTop    = '';
            c.style.borderBottom = '';
            c.style.marginTop    = '';
            c.style.marginBottom = '';
        });
    }

    function attachEvents() {
        getCards().forEach(card => {
            if (card.dataset.sortBound) return;
            card.dataset.sortBound = '1';

            card.addEventListener('dragover', function (e) {
                e.preventDefault();
                e.stopPropagation();
                if (!draggingId || parseInt(this.dataset.taskId) === draggingId) return;
                clearIndicators();
                const rect  = this.getBoundingClientRect();
                const isTop = e.clientY < rect.top + rect.height / 2;
                this.style.borderTop    = isTop  ? '2px solid #1E3A5F' : '';
                this.style.borderBottom = !isTop ? '2px solid #1E3A5F' : '';
            });

            card.addEventListener('drop', function (e) {
                e.preventDefault();
                e.stopPropagation();
                clearIndicators();

                const targetId = parseInt(this.dataset.taskId);
                if (!draggingId || draggingId === targetId) return;

                const rect    = this.getBoundingClientRect();
                const isTop   = e.clientY < rect.top + rect.height / 2;
                const colEl   = this.closest('[data-col]');
                const container = colEl ? colEl.querySelector('.pd-kanban-cards') : null;

                if (!container || !draggingCard) return;

                // Déplacer dans le DOM
                if (isTop) {
                    container.insertBefore(draggingCard, this);
                } else {
                    this.after(draggingCard);
                }

                // Construire le nouvel ordre
                const newStatus = colEl.dataset.col;
                draggingCard.dataset.status = newStatus;

                const orderedIds = [];
                container.querySelectorAll('.pd-kanban-card').forEach(c => {
                    orderedIds.push(parseInt(c.dataset.taskId));
                });

                const sortOrder = orderedIds.indexOf(draggingId);

                // PATCH vers le serveur
                fetch(`{{ route('projects.kanban.move', $project) }}`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        task_id:     draggingId,
                        new_status:  newStatus,
                        sort_order:  sortOrder,
                        ordered_ids: orderedIds,
                    }),
                })
                .then(r => r.json())
                .then(data => { if (!data.success) console.error('Kanban sort error', data); })
                .catch(err => console.error('Kanban sort error:', err));
            });
        });
    }

    // Intercepter dragstart sur toutes les cartes pour mémoriser la carte active
    let isDragging = false;

    document.addEventListener('dragstart', function (e) {
        const card = e.target.closest('.pd-kanban-card');
        if (!card) return;
        isDragging   = true;
        draggingId   = parseInt(card.dataset.taskId);
        draggingCard = card;
    });

    document.addEventListener('dragend', function () {
        clearIndicators();
        draggingId   = null;
        draggingCard = null;
        // Laisser un tick avant de remettre isDragging à false
        // pour que le click qui suit dragend ne déclenche pas openTask
        setTimeout(() => { isDragging = false; }, 50);
    });

    // Clic sur carte — géré par Alpine @click sur chaque carte
    // (pas de listener global pour éviter les conflits avec les autres vues)

    // Attacher au chargement + après chaque rechargement de section Alpine
    document.addEventListener('DOMContentLoaded', attachEvents);
    // Ré-attacher si Alpine met à jour le DOM (sections dépliées)
    document.addEventListener('alpine:initialized', attachEvents);
    // Ré-attacher après une mutation DOM (ouverture jalons)
    const observer = new MutationObserver(attachEvents);
    document.addEventListener('DOMContentLoaded', function () {
        const kanban = document.querySelector('[x-data="kanban()"]') || document.body;
        observer.observe(kanban, { childList: true, subtree: true });
    });
})();
</script>
