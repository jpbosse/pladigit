{{-- resources/views/projects/partials/_gantt.blade.php --}}
{{--
    Gantt — rendu SVG côté PHP, drag horizontal Alpine.js (ADR-009).
    Amélioré : zoom, largeur adaptative, meilleure lisibilité.
    La mise à jour des dates se fait par PATCH AJAX vers TaskController::updateDates().
--}}

@php
use Carbon\Carbon;

$tasksForGantt = $ganttTasks ?? collect();

// Calculer l'étendue temporelle du projet
$allDates = $tasksForGantt->flatMap(fn($t) => [$t->start_date, $t->due_date])->filter();
$milestonesDates = $project->milestones->pluck('due_date')->filter();
$allDates = $allDates->merge($milestonesDates);

$projectStart = $project->start_date ?? ($allDates->min() ?? now());
$projectEnd   = $project->due_date   ?? ($allDates->max() ?? now()->addMonths(3));

// Étendre pour la lisibilité
$viewStart = $projectStart->copy()->startOfMonth();
$viewEnd   = $projectEnd->copy()->endOfMonth();
$totalDays = max(1, $viewStart->diffInDays($viewEnd));
$totalMonths = $viewStart->diffInMonths($viewEnd);

// ── Largeur SVG adaptative selon la durée avec zoom ───────────────────────────
$labelWidth = 220; // Largeur fixe pour les labels (augmentée)
$minBarArea = 600; // Minimum pour la zone des barres
$dayWidth   = max(3, min(12, 4000 / max(1, $totalDays))); // 3-12px par jour

// Calcul automatique de la largeur
$barArea = max($minBarArea, $totalDays * $dayWidth);
$svgWidth = $labelWidth + $barArea;

$rowHeight  = 40;  // Augmenté pour meilleure lisibilité
$barH       = 22;  // Hauteur des barres
$barY       = ($rowHeight - $barH) / 2;
$headerH    = 60;  // En-tête plus grand

$rowCount  = $tasksForGantt->count() + $project->milestones->count();
$svgHeight = $headerH + ($rowCount * $rowHeight) + 30;

// Helper : position X d'une date
$xPos = fn($date) => $labelWidth + ($viewStart->diffInDays($date) / $totalDays * $barArea);

// Couleurs priorité
$prioBg     = ['urgent'=>'#FCA5A5','high'=>'#FCD34D','medium'=>'#93C5FD','low'=>'#86EFAC'];
$prioStroke = ['urgent'=>'#DC2626','high'=>'#D97706','medium'=>'#2563EB','low'=>'#16A34A'];

// ── Axe temporel adaptatif ───────────────────────────────────────────────────
$periods = [];
$cursor  = $viewStart->copy();

if ($totalMonths > 48) {
    // Semestres
    $cursor->startOfYear();
    while ($cursor->lte($viewEnd)) {
        $periods[] = [
            'label'    => 'S'.($cursor->month <= 6 ? '1' : '2').' '.$cursor->year,
            'sublabel' => '',
            'x'        => $xPos($cursor),
            'major'    => true,
        ];
        $cursor->addMonths(6);
    }
} elseif ($totalMonths > 18) {
    // Trimestres avec année
    $cursor->startOfQuarter();
    while ($cursor->lte($viewEnd)) {
        $q = $cursor->quarter;
        $periods[] = [
            'label'    => 'T'.$q.' '.$cursor->year,
            'sublabel' => $cursor->translatedFormat('M').'–'.Carbon::parse($cursor)->addMonths(2)->translatedFormat('M'),
            'x'        => $xPos($cursor),
            'major'    => ($q === 1),
        ];
        $cursor->addMonths(3);
    }
} else {
    // Mensuel classique
    $cursor->startOfMonth();
    while ($cursor->lte($viewEnd)) {
        $periods[] = [
            'label'    => $cursor->translatedFormat('M'),
            'sublabel' => ($cursor->month === 1) ? (string)$cursor->year : '',
            'x'        => $xPos($cursor),
            'major'    => ($cursor->month === 1),
        ];
        $cursor->addMonth();
    }
}
@endphp

<div x-data="ganttZoom()" x-init="init()" style="margin-bottom:10px;">
    
    {{-- Contrôles zoom --}}
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;flex-wrap:wrap;gap:8px;">
        <span style="font-size:13px;color:var(--pd-muted);">
            {{ $tasksForGantt->count() }} tâche{{ $tasksForGantt->count() > 1 ? 's' : '' }} planifiée{{ $tasksForGantt->count() > 1 ? 's' : '' }}
            @if($tasksForGantt->count() < ($taskStats['total'] ?? 0))
                <span style="color:var(--pd-warning);"> — {{ ($taskStats['total'] ?? 0) - $tasksForGantt->count() }} sans date</span>
            @endif
        </span>
        <div style="display:flex;align-items:center;gap:8px;">
            <span style="font-size:12px;color:var(--pd-muted);">Zoom :</span>
            <button @click="zoomOut()" class="pd-btn pd-btn-sm pd-btn-secondary" style="padding:4px 10px;">−</button>
            <span style="font-size:12px;font-weight:600;min-width:40px;text-align:center;" x-text="Math.round(zoom * 100) + '%'"></span>
            <button @click="zoomIn()" class="pd-btn pd-btn-sm pd-btn-secondary" style="padding:4px 10px;">+</button>
            <button @click="resetZoom()" class="pd-btn pd-btn-sm pd-btn-secondary" style="padding:4px 10px;margin-left:4px;">Reset</button>
        </div>
    </div>
    
    {{-- Conteneur scrollable --}}
    <div class="gantt-container" 
         style="overflow-x:auto;overflow-y:auto;max-height:65vh;border:1px solid var(--pd-border);border-radius:10px;background:var(--pd-surface);"
         @wheel="handleWheel($event)">
        
        <div :style="'transform:scaleX(' + zoom + ');transform-origin:left top;width:' + ({{ $svgWidth }} * zoom) + 'px;'">
            <svg width="{{ $svgWidth }}" height="{{ $svgHeight }}"
                 xmlns="http://www.w3.org/2000/svg"
                 id="gantt-svg"
                 style="font-family:'DM Sans',Arial,sans-serif;display:block;">

                {{-- Fond --}}
                <rect x="0" y="0" width="{{ $svgWidth }}" height="{{ $svgHeight }}" fill="var(--pd-bg, #fff)"/>

                {{-- En-tête labels --}}
                <rect x="0" y="0" width="{{ $labelWidth }}" height="{{ $headerH }}" fill="var(--pd-surface, #f5f7fa)"/>
                <text x="12" y="{{ $headerH / 2 + 5 }}" font-size="12" fill="var(--pd-muted, #64748B)" font-weight="600">TÂCHE</text>

                {{-- En-tête mois/trimestres/semestres --}}
                <rect x="{{ $labelWidth }}" y="0" width="{{ $barArea }}" height="{{ $headerH }}" fill="var(--pd-surface, #f5f7fa)"/>
                @foreach($periods as $period)
                {{-- Ligne verticale de séparation --}}
                <line x1="{{ $period['x'] }}" y1="0" x2="{{ $period['x'] }}" y2="{{ $svgHeight }}"
                      stroke="{{ $period['major'] ? 'var(--pd-border, #CBD5E1)' : 'var(--pd-border, #E2E8F0)' }}"
                      stroke-width="{{ $period['major'] ? '1' : '0.5' }}"/>
                {{-- Label principal --}}
                <text x="{{ $period['x'] + 8 }}" y="24"
                      font-size="12" font-weight="{{ $period['major'] ? '700' : '500' }}"
                      fill="{{ $period['major'] ? 'var(--pd-navy, #1E3A5F)' : 'var(--pd-muted, #64748B)' }}">
                    {{ $period['label'] }}
                </text>
                {{-- Sous-label --}}
                @if($period['sublabel'])
                <text x="{{ $period['x'] + 8 }}" y="42" font-size="11" fill="var(--pd-muted, #94A3B8)">
                    {{ $period['sublabel'] }}
                </text>
                @endif
                @endforeach

                {{-- Ligne "aujourd'hui" --}}
                @if(now()->between($viewStart, $viewEnd))
                @php $todayX = $xPos(now()); @endphp
                <line x1="{{ $todayX }}" y1="{{ $headerH }}" x2="{{ $todayX }}" y2="{{ $svgHeight }}"
                      stroke="#DC2626" stroke-width="2" stroke-dasharray="6,4" opacity="0.8"/>
                <rect x="{{ $todayX - 25 }}" y="4" width="50" height="18" rx="4" fill="#DC2626"/>
                <text x="{{ $todayX }}" y="16" text-anchor="middle" font-size="10" fill="#fff" font-weight="600">Aujourd'hui</text>
                @endif

                {{-- Lignes des tâches --}}
                @php $row = 0; @endphp
                @foreach($tasksForGantt as $task)
                @php
                    $y     = $headerH + $row * $rowHeight;
                    $x1    = $xPos($task->start_date);
                    $x2    = $xPos($task->due_date);
                    $barW  = max(8, $x2 - $x1);
                    $bg    = $prioBg[$task->priority] ?? '#93C5FD';
                    $stroke= $prioStroke[$task->priority] ?? '#2563EB';
                    $isDone = $task->status === 'done';
                    $row++;
                @endphp

                {{-- Fond alternance --}}
                <rect x="0" y="{{ $y }}" width="{{ $svgWidth }}" height="{{ $rowHeight }}"
                      fill="{{ $loop->odd ? 'var(--pd-surface, #f5f7fa)' : 'var(--pd-bg, #fff)' }}" opacity="0.6"/>

                {{-- Séparateur --}}
                <line x1="0" y1="{{ $y + $rowHeight }}" x2="{{ $svgWidth }}" y2="{{ $y + $rowHeight }}"
                      stroke="var(--pd-border, #E2E8F0)" stroke-width="0.5"/>

                {{-- Label tâche --}}
                <text x="12" y="{{ $y + $rowHeight / 2 + 4 }}" font-size="12"
                      fill="{{ $isDone ? 'var(--pd-muted, #64748B)' : 'var(--pd-text, #1A1A1A)' }}"
                      text-decoration="{{ $isDone ? 'line-through' : 'none' }}"
                      font-weight="{{ $task->priority === 'urgent' ? '600' : '400' }}">
                    {{ Str::limit($task->title, 30) }}
                </text>

                {{-- Barre Gantt --}}
                <g class="gantt-bar-group" data-task-id="{{ $task->id }}"
                   data-start="{{ $task->start_date->format('Y-m-d') }}"
                   data-end="{{ $task->due_date->format('Y-m-d') }}"
                   style="cursor:{{ $canEdit ? 'ew-resize' : 'pointer' }};">
                    
                    {{-- Ombre portée --}}
                    <rect x="{{ $x1 + 2 }}" y="{{ $y + $barY + 2 }}" width="{{ $barW }}" height="{{ $barH }}"
                          rx="5" fill="rgba(0,0,0,0.08)"/>
                    
                    {{-- Barre principale --}}
                    <rect x="{{ $x1 }}" y="{{ $y + $barY }}" width="{{ $barW }}" height="{{ $barH }}"
                          rx="5" fill="{{ $isDone ? '#D1FAE5' : $bg }}"
                          stroke="{{ $isDone ? '#16A34A' : $stroke }}" stroke-width="1.5"
                          opacity="{{ $isDone ? '0.8' : '1' }}"
                          class="gantt-bar"/>

                    {{-- Indicateur priorité (point coloré) --}}
                    @if($task->priority === 'urgent' || $task->priority === 'high')
                    <circle cx="{{ $x1 + 8 }}" cy="{{ $y + $barY + 8 }}" r="4" 
                            fill="{{ $stroke }}" opacity="0.9"/>
                    @endif
                </g>

                {{-- Statut dans la barre si assez large --}}
                @if($barW > 60)
                <text x="{{ $x1 + $barW / 2 }}" y="{{ $y + $barY + $barH / 2 + 4 }}"
                      text-anchor="middle" font-size="10" font-weight="500"
                      fill="{{ $isDone ? '#065F46' : $stroke }}">
                    {{ \App\Models\Tenant\Task::statusLabels()[$task->status] }}
                </text>
                @endif

                {{-- Dépendances : flèches SVG vers les tâches bloquées --}}
                @foreach($task->blocking ?? [] as $blocked)
                @php
                    $blockedTask = $tasksForGantt->firstWhere('id', $blocked->id);
                    if (!$blockedTask) continue;
                    $blockedRow = $tasksForGantt->search(fn($t) => $t->id === $blocked->id);
                    $yB = $headerH + $blockedRow * $rowHeight + $rowHeight / 2;
                    $yA = $y + $rowHeight / 2;
                    $xA = $x2;
                    $xB = $xPos($blockedTask->start_date);
                @endphp
                <path d="M{{ $xA }} {{ $yA }} C{{ $xA + 25 }} {{ $yA }}, {{ $xB - 25 }} {{ $yB }}, {{ $xB }} {{ $yB }}"
                      fill="none" stroke="#94A3B8" stroke-width="1.5" stroke-dasharray="5,3"
                      marker-end="url(#dep-arrow)" opacity="0.7"/>
                @endforeach

                @endforeach

                {{-- Jalons --}}
                @foreach($project->milestones as $milestone)
                @php
                    if (!$milestone->due_date || $milestone->due_date->lt($viewStart) || $milestone->due_date->gt($viewEnd)) continue;
                    $y   = $headerH + $row * $rowHeight;
                    $xM  = $xPos($milestone->due_date);
                    $row++;
                @endphp
                <rect x="0" y="{{ $y }}" width="{{ $svgWidth }}" height="{{ $rowHeight }}"
                      fill="#FEF3C7" opacity="0.4"/>
                <text x="12" y="{{ $y + $rowHeight / 2 + 4 }}" font-size="12" fill="#92400E" font-weight="600">
                    🏁 {{ Str::limit($milestone->title, 28) }}
                </text>
                {{-- Losange jalon --}}
                <rect x="{{ $xM - 9 }}" y="{{ $y + $rowHeight/2 - 9 }}" width="18" height="18"
                      transform="rotate(45, {{ $xM }}, {{ $y + $rowHeight/2 }})"
                      fill="{{ $milestone->isReached() ? '#16A34A' : $milestone->color }}"
                      stroke="{{ $milestone->isLate() ? '#DC2626' : 'none' }}" stroke-width="2.5"/>
                @endforeach

                {{-- Marqueur flèche dépendance --}}
                <defs>
                    <marker id="dep-arrow" viewBox="0 0 10 10" refX="8" refY="5"
                            markerWidth="6" markerHeight="6" orient="auto-start-reverse">
                        <path d="M2 1L8 5L2 9" fill="none" stroke="#94A3B8" stroke-width="1.5"
                              stroke-linecap="round" stroke-linejoin="round"/>
                    </marker>
                </defs>

            </svg>
        </div>
    </div>

    {{-- Légende priorités --}}
    <div style="display:flex;gap:16px;margin-top:12px;flex-wrap:wrap;font-size:12px;">
        @foreach(\App\Models\Tenant\Task::priorityLabels() as $prio => $label)
        <div style="display:flex;align-items:center;gap:6px;color:var(--pd-muted);">
            <div style="width:16px;height:16px;border-radius:4px;background:{{ $prioBg[$prio] }};border:1.5px solid {{ $prioStroke[$prio] }};"></div>
            {{ $label }}
        </div>
        @endforeach
        <div style="display:flex;align-items:center;gap:6px;color:var(--pd-muted);">
            <div style="width:14px;height:14px;transform:rotate(45deg);background:#EA580C;"></div>
            Jalon
        </div>
        <div style="display:flex;align-items:center;gap:6px;color:var(--pd-muted);">
            <div style="width:16px;height:0;border-top:2px dashed #DC2626;"></div>
            Aujourd'hui
        </div>
    </div>

</div>

@if($canEdit)
<script>
function ganttZoom() {
    return {
        zoom: 1,
        minZoom: 0.5,
        maxZoom: 2,
        
        init() {
            // Restaurer le zoom depuis localStorage
            const saved = localStorage.getItem('gantt_zoom');
            if (saved) {
                this.zoom = Math.max(this.minZoom, Math.min(this.maxZoom, parseFloat(saved)));
            }
        },
        
        zoomIn() {
            this.zoom = Math.min(this.maxZoom, this.zoom + 0.1);
            localStorage.setItem('gantt_zoom', this.zoom);
        },
        
        zoomOut() {
            this.zoom = Math.max(this.minZoom, this.zoom - 0.1);
            localStorage.setItem('gantt_zoom', this.zoom);
        },
        
        resetZoom() {
            this.zoom = 1;
            localStorage.removeItem('gantt_zoom');
        },
        
        handleWheel(e) {
            // Zoom avec Ctrl + molette
            if (e.ctrlKey) {
                e.preventDefault();
                if (e.deltaY < 0) {
                    this.zoomIn();
                } else {
                    this.zoomOut();
                }
            }
        }
    };
}

// Drag horizontal Alpine.js sur les barres Gantt (ADR-009)
document.querySelectorAll('.gantt-bar-group').forEach(bar => {
    let startX, origStart, origEnd, dayWidth;
    const totalDays = {{ $totalDays }};
    const barAreaW  = {{ $barArea }};
    const labelW    = {{ $labelWidth }};

    bar.addEventListener('mousedown', e => {
        // Clic sur le groupe, mais on veut le rect principal
        const rect = bar.querySelector('.gantt-bar');
        if (!rect) return;
        
        startX    = e.clientX;
        origStart = bar.dataset.start;
        origEnd   = bar.dataset.end;
        dayWidth  = barAreaW / totalDays;
        e.preventDefault();

        const onMove = mv => {
            const dx       = mv.clientX - startX;
            const daysDelta= Math.round(dx / dayWidth);
            if (daysDelta === 0) return;

            const newStart = dayjs(origStart).add(daysDelta, 'day').format('YYYY-MM-DD');
            const newEnd   = dayjs(origEnd).add(daysDelta, 'day').format('YYYY-MM-DD');
            bar.setAttribute('data-start', newStart);
            bar.setAttribute('data-end', newEnd);
        };

        const onUp = () => {
            document.removeEventListener('mousemove', onMove);
            document.removeEventListener('mouseup', onUp);

            const taskId  = bar.dataset.taskId;
            const newStart= bar.dataset.start;
            const newEnd  = bar.dataset.end;

            if (newStart === origStart) return;

            fetch(`{{ url('projects/' . $project->id . '/tasks') }}/${taskId}/dates`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ start_date: newStart, due_date: newEnd }),
            })
            .then(r => r.json())
            .then(d => { 
                if (!d.success) { 
                    bar.setAttribute('data-start', origStart); 
                    bar.setAttribute('data-end', origEnd); 
                } else {
                    // Afficher notification succès
                    if (window.showToast) {
                        window.showToast('Dates mises à jour', 'success');
                    }
                }
            });
        };

        document.addEventListener('mousemove', onMove);
        document.addEventListener('mouseup', onUp);
    });
    
    // Clic pour ouvrir le slideover
    bar.addEventListener('click', e => {
        if (e.target.classList.contains('gantt-bar')) {
            const taskId = bar.dataset.taskId;
            window.dispatchEvent(new CustomEvent('open-task', { detail: { taskId: parseInt(taskId) } }));
        }
    });
});
</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/dayjs/1.11.10/dayjs.min.js"></script>
@else
<script>
function ganttZoom() {
    return {
        zoom: 1,
        minZoom: 0.5,
        maxZoom: 2,
        
        init() {
            const saved = localStorage.getItem('gantt_zoom');
            if (saved) {
                this.zoom = Math.max(this.minZoom, Math.min(this.maxZoom, parseFloat(saved)));
            }
        },
        
        zoomIn() {
            this.zoom = Math.min(this.maxZoom, this.zoom + 0.1);
            localStorage.setItem('gantt_zoom', this.zoom);
        },
        
        zoomOut() {
            this.zoom = Math.max(this.minZoom, this.zoom - 0.1);
            localStorage.setItem('gantt_zoom', this.zoom);
        },
        
        resetZoom() {
            this.zoom = 1;
            localStorage.removeItem('gantt_zoom');
        },
        
        handleWheel(e) {
            if (e.ctrlKey) {
                e.preventDefault();
                if (e.deltaY < 0) this.zoomIn();
                else this.zoomOut();
            }
        }
    };
}
</script>
@endif
