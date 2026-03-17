{{-- resources/views/projects/partials/_gantt.blade.php --}}
{{--
    Gantt — rendu SVG côté PHP, drag horizontal Alpine.js (ADR-009).
    Zéro lib JS externe. PHP calcule les positions/largeurs des barres.
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

// ── Largeur SVG adaptative selon la durée ────────────────────────────────────
// Moins de 6 mois → 900px / 6–18 mois → 1200px / 18–36 mois → 1800px / > 36 mois → 2800px
$labelWidth = 200;
if ($totalMonths <= 6) {
    $svgWidth = 900;
} elseif ($totalMonths <= 18) {
    $svgWidth = 1200;
} elseif ($totalMonths <= 36) {
    $svgWidth = 1800;
} else {
    // > 3 ans : ~60px par mois minimum
    $svgWidth = max(2400, $totalMonths * 55 + $labelWidth);
}
$barArea    = $svgWidth - $labelWidth;
$rowHeight  = 36;
$barH       = 20;
$barY       = ($rowHeight - $barH) / 2;
$headerH    = 56;

$rowCount  = $tasksForGantt->count() + $project->milestones->count();
$svgHeight = $headerH + ($rowCount * $rowHeight) + 20;

// Helper : position X d'une date
$xPos = fn($date) => $labelWidth + ($viewStart->diffInDays($date) / $totalDays * $barArea);

// Couleurs priorité
$prioBg     = ['urgent'=>'#FCA5A5','high'=>'#FCD34D','medium'=>'#93C5FD','low'=>'#86EFAC'];
$prioStroke = ['urgent'=>'#DC2626','high'=>'#D97706','medium'=>'#2563EB','low'=>'#16A34A'];

// ── Axe temporel adaptatif ───────────────────────────────────────────────────
// > 24 mois → axe par trimestres + année en sous-titre
// > 48 mois → axe par semestres
// Sinon → axe mensuel classique
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

<div style="margin-bottom:10px;display:flex;align-items:center;gap:10px;">
    <span style="font-size:13px;color:var(--pd-muted);">
        {{ $tasksForGantt->count() }} tâche{{ $tasksForGantt->count() > 1 ? 's' : '' }} planifiées
        @if($tasksForGantt->count() < ($taskStats['total'] ?? 0))
            <span style="color:var(--pd-warning);"> — {{ ($taskStats['total'] ?? 0) - $tasksForGantt->count() }} sans date</span>
        @endif
    </span>
</div>

<div style="overflow-x:auto;border:0.5px solid var(--pd-border);border-radius:10px;">
<svg width="100%" viewBox="0 0 {{ $svgWidth }} {{ $svgHeight }}"
     xmlns="http://www.w3.org/2000/svg"
     id="gantt-svg"
     style="font-family:var(--font-sans, Arial, sans-serif);">

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
    <text x="{{ $period['x'] + 5 }}" y="22"
          font-size="11" font-weight="{{ $period['major'] ? '700' : '500' }}"
          fill="{{ $period['major'] ? 'var(--pd-navy, #1E3A5F)' : 'var(--pd-muted, #64748B)' }}">
        {{ $period['label'] }}
    </text>
    {{-- Sous-label (mois de début de trimestre, ou année) --}}
    @if($period['sublabel'])
    <text x="{{ $period['x'] + 5 }}" y="38" font-size="10" fill="var(--pd-muted, #94A3B8)">
        {{ $period['sublabel'] }}
    </text>
    @endif
    @endforeach

    {{-- Ligne "aujourd'hui" --}}
    @if(now()->between($viewStart, $viewEnd))
    @php $todayX = $xPos(now()); @endphp
    <line x1="{{ $todayX }}" y1="{{ $headerH }}" x2="{{ $todayX }}" y2="{{ $svgHeight }}"
          stroke="#DC2626" stroke-width="1.5" stroke-dasharray="4,3" opacity="0.7"/>
    <text x="{{ $todayX + 3 }}" y="{{ $headerH - 4 }}" font-size="10" fill="#DC2626">Auj.</text>
    @endif

    {{-- Lignes des tâches --}}
    @php $row = 0; @endphp
    @foreach($tasksForGantt as $task)
    @php
        $y     = $headerH + $row * $rowHeight;
        $x1    = $xPos($task->start_date);
        $x2    = $xPos($task->due_date);
        $barW  = max(4, $x2 - $x1);
        $bg    = $prioBg[$task->priority] ?? '#93C5FD';
        $stroke= $prioStroke[$task->priority] ?? '#2563EB';
        $isDone = $task->status === 'done';
        $row++;
    @endphp

    {{-- Fond alternance --}}
    <rect x="0" y="{{ $y }}" width="{{ $svgWidth }}" height="{{ $rowHeight }}"
          fill="{{ $loop->odd ? 'var(--pd-surface, #f5f7fa)' : 'var(--pd-bg, #fff)' }}" opacity="0.5"/>

    {{-- Séparateur --}}
    <line x1="{{ $labelWidth }}" y1="{{ $y }}" x2="{{ $svgWidth }}" y2="{{ $y }}"
          stroke="var(--pd-border, #E2E8F0)" stroke-width="0.5"/>

    {{-- Label tâche --}}
    <text x="10" y="{{ $y + $rowHeight / 2 + 4 }}" font-size="12"
          fill="{{ $isDone ? 'var(--pd-muted, #64748B)' : 'var(--pd-text, #1A1A1A)' }}"
          text-decoration="{{ $isDone ? 'line-through' : 'none' }}">
        {{ Str::limit($task->title, 26) }}
    </text>

    {{-- Barre Gantt --}}
    <rect x="{{ $x1 }}" y="{{ $y + $barY }}" width="{{ $barW }}" height="{{ $barH }}"
          rx="4" fill="{{ $isDone ? '#D1FAE5' : $bg }}"
          stroke="{{ $isDone ? '#16A34A' : $stroke }}" stroke-width="1"
          opacity="{{ $isDone ? '0.7' : '1' }}"
          class="gantt-bar" data-task-id="{{ $task->id }}"
          data-start="{{ $task->start_date->format('Y-m-d') }}"
          data-end="{{ $task->due_date->format('Y-m-d') }}"
          style="cursor:{{ $canEdit ? 'ew-resize' : 'default' }};"/>

    {{-- Statut dans la barre si assez large --}}
    @if($barW > 50)
    <text x="{{ $x1 + $barW / 2 }}" y="{{ $y + $barY + $barH / 2 + 4 }}"
          text-anchor="middle" font-size="10"
          fill="{{ $isDone ? '#065F46' : $prioStroke[$task->priority] ?? '#2563EB' }}">
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
    <path d="M{{ $xA }} {{ $yA }} C{{ $xA + 20 }} {{ $yA }}, {{ $xB - 20 }} {{ $yB }}, {{ $xB }} {{ $yB }}"
          fill="none" stroke="#94A3B8" stroke-width="1" stroke-dasharray="4,2"
          marker-end="url(#dep-arrow)"/>
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
          fill="#FEF3C7" opacity="0.3"/>
    <text x="10" y="{{ $y + $rowHeight / 2 + 4 }}" font-size="12" fill="#92400E" font-weight="600">
        🏁 {{ Str::limit($milestone->title, 24) }}
    </text>
    {{-- Losange jalon --}}
    <rect x="{{ $xM - 7 }}" y="{{ $y + $rowHeight/2 - 7 }}" width="14" height="14"
          transform="rotate(45, {{ $xM }}, {{ $y + $rowHeight/2 }})"
          fill="{{ $milestone->isReached() ? '#16A34A' : $milestone->color }}"
          stroke="{{ $milestone->isLate() ? '#DC2626' : 'none' }}" stroke-width="2"/>
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

{{-- Légende priorités --}}
<div style="display:flex;gap:12px;margin-top:10px;flex-wrap:wrap;">
    @foreach(\App\Models\Tenant\Task::priorityLabels() as $prio => $label)
    <div style="display:flex;align-items:center;gap:5px;font-size:12px;color:var(--pd-muted);">
        <div style="width:12px;height:12px;border-radius:2px;background:{{ $prioBg[$prio] }};border:1px solid {{ $prioStroke[$prio] }};"></div>
        {{ $label }}
    </div>
    @endforeach
    <div style="display:flex;align-items:center;gap:5px;font-size:12px;color:var(--pd-muted);">
        <div style="width:12px;height:12px;transform:rotate(45deg);background:#EA580C;"></div>
        Jalon
    </div>
</div>

@if($canEdit)
<script>
// Drag horizontal Alpine.js sur les barres Gantt (ADR-009)
document.querySelectorAll('.gantt-bar').forEach(bar => {
    let startX, origStart, origEnd, dayWidth;
    const totalDays = {{ $totalDays }};
    const barAreaW  = {{ $barArea }};

    bar.addEventListener('mousedown', e => {
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
            .then(d => { if (!d.success) { bar.setAttribute('data-start', origStart); bar.setAttribute('data-end', origEnd); } });
        };

        document.addEventListener('mousemove', onMove);
        document.addEventListener('mouseup', onUp);
    });
});
</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/dayjs/1.11.10/dayjs.min.js"></script>
@endif
