{{-- resources/views/projects/partials/_kanban.blade.php --}}
{{--
    Kanban Alpine.js pur — ADR-008 révisé.
    Drag & drop via l'API HTML5 Drag and Drop + fetch() PATCH vers KanbanController.
    Pas de Livewire (non initialisé dans le projet).
    TODO: migrer vers Livewire en Phase 5 une fois le build pipeline validé.
--}}

@php
$columns = [
    'todo'        => ['label' => 'À faire',   'color' => '#94A3B8'],
    'in_progress' => ['label' => 'En cours',  'color' => '#3B82F6'],
    'in_review'   => ['label' => 'En revue',  'color' => '#8B5CF6'],
    'done'        => ['label' => 'Terminé',   'color' => '#16A34A'],
];
$priorityColors = [
    'urgent' => ['bg'=>'#FEE2E2','text'=>'#991B1B'],
    'high'   => ['bg'=>'#FEF3C7','text'=>'#92400E'],
    'medium' => ['bg'=>'#DBEAFE','text'=>'#1E40AF'],
    'low'    => ['bg'=>'#D1FAE5','text'=>'#065F46'],
];
@endphp

<div x-data="kanban()" x-init="init()" style="overflow-x:auto;">

    {{-- Bouton nouvelle tâche --}}
    @if($canEdit)
    <div style="margin-bottom:12px;">
        <button @click="openNewTask('todo')"
                class="pd-btn pd-btn-sm pd-btn-secondary">
            + Nouvelle tâche
        </button>
    </div>
    @endif

    <div style="display:grid;grid-template-columns:repeat(4,minmax(220px,1fr));gap:12px;min-width:900px;">

        @foreach($columns as $status => $col)
        <div data-col="{{ $status }}"
             @dragover.prevent="onDragOver($event, '{{ $status }}')"
             @drop.prevent="onDrop($event, '{{ $status }}')"
             @dragleave="onDragLeave($event)"
             :class="{ 'pd-kanban-col-over': dragOver === '{{ $status }}' }"
             style="background:var(--pd-surface);border-radius:10px;padding:12px;border:0.5px solid var(--pd-border);">

            {{-- En-tête colonne --}}
            <div style="display:flex;align-items:center;gap:6px;margin-bottom:10px;">
                <div style="width:8px;height:8px;border-radius:50%;background:{{ $col['color'] }};"></div>
                <span style="font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:.04em;color:var(--pd-muted);">
                    {{ $col['label'] }}
                </span>
                <span style="font-size:11px;padding:1px 6px;background:var(--pd-bg);border:0.5px solid var(--pd-border);border-radius:10px;color:var(--pd-muted);margin-left:auto;">
                    {{ ($tasksByStatus[$status] ?? collect())->count() }}
                </span>
            </div>

            {{-- Cartes --}}
            <div class="pd-kanban-cards" id="col-{{ $status }}" style="min-height:60px;display:flex;flex-direction:column;gap:8px;">
                @foreach($tasksByStatus[$status] ?? [] as $task)
                <div class="pd-kanban-card"
                     draggable="true"
                     data-task-id="{{ $task->id }}"
                     data-status="{{ $status }}"
                     @dragstart="onDragStart($event, {{ $task->id }}, '{{ $status }}')"
                     @dragend="onDragEnd()"
                     @click="openTask({{ $task->id }})"
                     style="background:var(--pd-bg);border:0.5px solid var(--pd-border);border-radius:8px;padding:10px 12px;cursor:grab;">

                    <div style="font-size:13px;color:var(--pd-text);line-height:1.4;margin-bottom:8px;">
                        {{ $task->title }}
                    </div>

                    <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
                        {{-- Priorité --}}
                        <span style="font-size:10px;padding:2px 6px;border-radius:10px;font-weight:500;background:{{ $priorityColors[$task->priority]['bg'] }};color:{{ $priorityColors[$task->priority]['text'] }};">
                            {{ \App\Models\Tenant\Task::priorityLabels()[$task->priority] }}
                        </span>

                        {{-- Sous-tâches --}}
                        @if($task->children->count())
                        <span style="font-size:11px;color:var(--pd-muted);">
                            {{ $task->children->where('status','done')->count() }}/{{ $task->children->count() }}
                        </span>
                        @endif

                        {{-- Assigné --}}
                        @if($task->assignee)
                        <div style="width:20px;height:20px;border-radius:50%;background:var(--pd-navy-light);color:var(--pd-navy);font-size:9px;font-weight:600;display:flex;align-items:center;justify-content:center;margin-left:auto;">
                            {{ strtoupper(substr($task->assignee->name, 0, 2)) }}
                        </div>
                        @endif

                        {{-- Échéance --}}
                        @if($task->due_date)
                        <span style="font-size:11px;color:{{ $task->due_date->isPast() && $task->status !== 'done' ? 'var(--pd-danger)' : 'var(--pd-muted)' }};margin-left:{{ $task->assignee ? '0' : 'auto' }};">
                            {{ $task->due_date->format('d/m') }}
                        </span>
                        @endif
                    </div>
                </div>
                @endforeach
            </div>

            {{-- Ajouter une tâche dans cette colonne --}}
            @if($canEdit)
            <button @click="openNewTask('{{ $status }}')"
                    style="width:100%;margin-top:8px;padding:6px;font-size:12px;color:var(--pd-muted);background:none;border:0.5px dashed var(--pd-border);border-radius:6px;cursor:pointer;text-align:left;">
                + Ajouter une tâche
            </button>
            @endif

        </div>
        @endforeach

    </div>
</div>

<script>
function kanban() {
    return {
        dragOver: null,
        dragging: null,
        draggingStatus: null,

        init() {},

        onDragStart(e, taskId, status) {
            this.dragging = taskId;
            this.draggingStatus = status;
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', taskId);
            e.currentTarget.style.opacity = '0.5';
        },

        onDragEnd() {
            this.dragging = null;
            this.draggingStatus = null;
            document.querySelectorAll('.pd-kanban-card').forEach(c => c.style.opacity = '1');
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

        onDrop(e, newStatus) {
            e.preventDefault();
            this.dragOver = null;
            const taskId = parseInt(e.dataTransfer.getData('text/plain'));

            if (!taskId || this.draggingStatus === newStatus) return;

            // Calculer le nouvel ordre dans la colonne cible
            const col = document.getElementById('col-' + newStatus);
            const cards = [...col.querySelectorAll('.pd-kanban-card')];
            const orderedIds = cards.map(c => parseInt(c.dataset.taskId)).filter(id => id !== taskId);
            orderedIds.push(taskId);

            this.patchMove(taskId, newStatus, orderedIds.length - 1, orderedIds);
        },

        patchMove(taskId, newStatus, sortOrder, orderedIds) {
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
                    // Déplacer la carte dans le DOM sans rechargement
                    const card = document.querySelector(`[data-task-id="${taskId}"]`);
                    if (card) {
                        card.dataset.status = newStatus;
                        document.getElementById('col-' + newStatus).appendChild(card);
                        this.updateColCounts();
                    }
                }
            })
            .catch(err => console.error('Kanban move error:', err));
        },

        updateColCounts() {
            document.querySelectorAll('[data-col]').forEach(col => {
                const status = col.dataset.col;
                const count = col.querySelectorAll('.pd-kanban-card').length;
                const badge = col.querySelector('.pd-kanban-count');
                if (badge) badge.textContent = count;
            });
        },

        openTask(taskId) {
            // Déclenche le slide-over tâche
            window.dispatchEvent(new CustomEvent('open-task', { detail: { taskId } }));
        },

        openNewTask(status) {
            window.dispatchEvent(new CustomEvent('open-new-task', { detail: { status } }));
        },
    };
}
</script>
