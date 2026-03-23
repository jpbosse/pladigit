{{-- _list_table.blade.php — Tableau de tâches réutilisable
     Reçoit : $tasks (Collection), $statusColors, $pColors (hérités du scope parent)
--}}
<table style="width:100%;border-collapse:collapse;font-size:12px;"
       x-data="{
           sortBy: 'default',
           sortDir: 'asc',
           sortCol(by) {
               if (this.sortBy === by) {
                   this.sortDir = this.sortDir === 'asc' ? 'desc' : 'asc';
               } else {
                   this.sortBy  = by;
                   this.sortDir = 'asc';
               }
               // Synchroniser le select global dans _planif
               window.dispatchEvent(new CustomEvent('sort-tasks', {detail:{by: this.sortBy, dir: this.sortDir}}));
               // Trier ce tbody directement
               const attrMap = {title:'sortTitle', due_date:'sortDue', priority:'sortPriority', assignee:'sortAssignee'};
               const attr = attrMap[this.sortBy];
               if (!attr) return;
               const tbody = $el.querySelector('tbody');
               const rows  = Array.from(tbody.querySelectorAll('tr[data-sort-title]'));
               const dir   = this.sortDir;
               rows.sort((a, b) => {
                   let va = a.dataset[attr] ?? '';
                   let vb = b.dataset[attr] ?? '';
                   if (this.sortBy === 'priority') {
                       va = parseFloat(va)||0; vb = parseFloat(vb)||0;
                       return dir === 'asc' ? va-vb : vb-va;
                   }
                   return dir === 'asc' ? va.localeCompare(vb,'fr') : vb.localeCompare(va,'fr');
               });
               rows.forEach(r => tbody.appendChild(r));
           },
           arrow(col) {
               if (this.sortBy !== col) return '↕';
               return this.sortDir === 'asc' ? '↑' : '↓';
           },
           arrowColor(col) {
               return this.sortBy === col ? 'var(--pd-navy)' : 'var(--pd-border)';
           }
       }">
    <thead>
    <tr style="background:var(--pd-surface2);border-bottom:0.5px solid var(--pd-border);">

        {{-- Tâche --}}
        <th @click="sortCol('title')" style="padding:8px 14px;text-align:left;font-weight:600;color:var(--pd-muted);font-size:10px;text-transform:uppercase;letter-spacing:.04em;width:38%;cursor:pointer;user-select:none;white-space:nowrap;"
            :style="sortBy==='title' ? 'color:var(--pd-navy)' : ''">
            Tâche <span x-text="arrow('title')" :style="'color:'+arrowColor('title')"></span>
        </th>

        {{-- Statut — pas de tri (enum complexe) --}}
        <th style="padding:8px 10px;font-weight:600;color:var(--pd-muted);font-size:10px;text-transform:uppercase;letter-spacing:.04em;">Statut</th>

        {{-- Priorité --}}
        <th @click="sortCol('priority')" style="padding:8px 10px;font-weight:600;color:var(--pd-muted);font-size:10px;text-transform:uppercase;letter-spacing:.04em;cursor:pointer;user-select:none;white-space:nowrap;"
            :style="sortBy==='priority' ? 'color:var(--pd-navy)' : ''">
            Priorité <span x-text="arrow('priority')" :style="'color:'+arrowColor('priority')"></span>
        </th>

        {{-- Assigné --}}
        <th @click="sortCol('assignee')" style="padding:8px 10px;font-weight:600;color:var(--pd-muted);font-size:10px;text-transform:uppercase;letter-spacing:.04em;cursor:pointer;user-select:none;white-space:nowrap;"
            :style="sortBy==='assignee' ? 'color:var(--pd-navy)' : ''">
            Assigné <span x-text="arrow('assignee')" :style="'color:'+arrowColor('assignee')"></span>
        </th>

        {{-- Échéance --}}
        <th @click="sortCol('due_date')" style="padding:8px 10px;font-weight:600;color:var(--pd-muted);font-size:10px;text-transform:uppercase;letter-spacing:.04em;cursor:pointer;user-select:none;white-space:nowrap;"
            :style="sortBy==='due_date' ? 'color:var(--pd-navy)' : ''">
            Échéance <span x-text="arrow('due_date')" :style="'color:'+arrowColor('due_date')"></span>
        </th>

        {{-- Heures — pas de tri --}}
        <th style="padding:8px 10px;font-weight:600;color:var(--pd-muted);font-size:10px;text-transform:uppercase;letter-spacing:.04em;">Heures</th>
    </tr>
    </thead>
    <tbody>
    @foreach($tasks->sortByDesc(fn($t) => match($t->status){'in_progress'=>3,'in_review'=>2,'todo'=>1,default=>0}) as $task)
    @php
        $sc = $statusColors[$task->status] ?? ['bg'=>'#F1F5F9','text'=>'#475569'];
        $pc = $pColors[$task->priority]    ?? ['bg'=>'#F1F5F9','text'=>'#475569'];
        $isInProgress = $task->status === 'in_progress';
        $priorityWeight = match($task->priority) { 'urgent'=>4,'high'=>3,'medium'=>2,default=>1 };
    @endphp
    <tr x-show="showAll || {{ $isInProgress ? 'true' : 'false' }} || (filter && '{{ addslashes(strtolower($task->title)) }}'.includes(filter.toLowerCase()))"
        data-sort-title="{{ strtolower($task->title) }}"
        data-sort-due="{{ $task->due_date?->format('Y-m-d') ?? '9999-99-99' }}"
        data-sort-priority="{{ $priorityWeight }}"
        data-sort-assignee="{{ strtolower($task->assignee?->name ?? '') }}"
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
