{{-- _gantt.blade.php — ADR-009 révisé v3 --}}
{{--
    - Jalon actif déplié automatiquement
    - En-têtes repliés : mini timeline avec losange positionné
    - Par défaut : tâches "en cours" uniquement, toggle pour tout voir
--}}

@php
use Carbon\Carbon;

$projectStart = $project->start_date ?? now()->startOfMonth();
$projectEnd   = $project->due_date   ?? now()->addMonths(3)->endOfMonth();
$viewStart    = $projectStart->copy()->startOfMonth();
$viewEnd      = $projectEnd->copy()->endOfMonth();
$totalDays    = max(1, $viewStart->diffInDays($viewEnd));
$totalMonths  = $viewStart->diffInMonths($viewEnd);

$labelWidth = 200;
$dayWidth   = max(3, min(14, 3600 / max(1, $totalDays)));
$barArea    = max(500, $totalDays * $dayWidth);
$svgWidth   = $labelWidth + $barArea;

$xPos = fn($date) => $labelWidth + (max(0, $viewStart->diffInDays($date)) / $totalDays * $barArea);

// Mini timeline (en-tête replié) : position proportionnelle 0-100%
$xPct = fn($date) => max(0, min(100, $viewStart->diffInDays($date) / $totalDays * 100));
$todayPct = $xPct(now());

// Périodes axe temporel
$periods = [];
$cursor  = $viewStart->copy()->startOfMonth();
if ($totalMonths > 24) {
    $cursor->startOfQuarter();
    while ($cursor->lte($viewEnd)) {
        $periods[] = ['label' => 'T'.$cursor->quarter.' '.$cursor->year, 'x' => $xPos($cursor), 'major' => $cursor->quarter === 1];
        $cursor->addMonths(3);
    }
} else {
    while ($cursor->lte($viewEnd)) {
        $periods[] = ['label' => $cursor->translatedFormat('M'), 'sub' => $cursor->month === 1 ? (string)$cursor->year : '', 'x' => $xPos($cursor), 'major' => $cursor->month === 1];
        $cursor->addMonth();
    }
}

// Jalon actif à déplier
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

$prioBg     = ['urgent' => '#FCA5A5', 'high' => '#FCD34D', 'medium' => '#93C5FD', 'low' => '#86EFAC'];
$prioStroke = ['urgent' => '#DC2626', 'high' => '#D97706', 'medium' => '#2563EB', 'low' => '#16A34A'];
$rowH = 36; $barH = 20; $barY = ($rowH - $barH) / 2; $headH = 44;
$todayX = now()->between($viewStart, $viewEnd) ? $xPos(now()) : null;
@endphp

<div x-data="ganttCtrl()" x-init="init()">

{{-- ── Barre d'outils ── --}}
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;flex-wrap:wrap;gap:8px;">
    <span style="font-size:12px;color:var(--pd-muted);">
        {{ $project->tasks()->whereNotNull('start_date')->whereNotNull('due_date')->count() }} tâches planifiées
        @php $unplanned = $taskStats['total'] - $project->tasks()->whereNotNull('start_date')->whereNotNull('due_date')->count(); @endphp
        @if($unplanned > 0)<span style="color:var(--pd-muted);"> · {{ $unplanned }} sans date</span>@endif
    </span>
    <div style="display:flex;align-items:center;gap:8px;">
        <label style="display:flex;align-items:center;gap:6px;font-size:12px;color:var(--pd-muted);cursor:pointer;">
            <input type="checkbox" x-model="showAll" style="accent-color:var(--pd-navy);">
            Toutes les tâches
        </label>
        <span style="color:var(--pd-border);">|</span>
        <span style="font-size:11px;color:var(--pd-muted);">Zoom</span>
        <button @click="zoom=Math.max(0.5,zoom-0.15)" class="pd-btn pd-btn-xs pd-btn-secondary">−</button>
        <span style="font-size:11px;font-weight:600;min-width:36px;text-align:center;" x-text="Math.round(zoom*100)+'%'"></span>
        <button @click="zoom=Math.min(2.5,zoom+0.15)" class="pd-btn pd-btn-xs pd-btn-secondary">+</button>
        <button @click="zoom=1" class="pd-btn pd-btn-xs pd-btn-secondary">↺</button>
    </div>
</div>

<div style="overflow-x:auto;">
<div :style="'width:'+Math.round({{ $svgWidth }}*zoom)+'px'">

{{-- ── Axe temporel (header commun) ── --}}
<svg width="{{ $svgWidth }}" height="{{ $headH }}"
     :style="'transform:scaleX('+zoom+');transform-origin:left top;width:'+Math.round({{ $svgWidth }}*zoom)+'px'"
     style="display:block;font-family:\'DM Sans\',sans-serif;background:var(--pd-surface2);border-radius:8px 8px 0 0;border:0.5px solid var(--pd-border);">
    <rect x="0" y="0" width="{{ $labelWidth }}" height="{{ $headH }}" fill="var(--pd-surface2)"/>
    <text x="12" y="{{ $headH/2+4 }}" font-size="10" font-weight="700" fill="var(--pd-muted)" letter-spacing="1">TÂCHE</text>
    @foreach($periods as $p)
    <line x1="{{ $p['x'] }}" y1="0" x2="{{ $p['x'] }}" y2="{{ $headH }}"
          stroke="var(--pd-border)" stroke-width="{{ ($p['major']??false)?'1':'0.5' }}"/>
    <text x="{{ $p['x']+6 }}" y="20" font-size="11"
          font-weight="{{ ($p['major']??false)?'700':'500' }}"
          fill="{{ ($p['major']??false)?'var(--pd-navy)':'var(--pd-muted)' }}">{{ $p['label'] }}</text>
    @if(!empty($p['sub']))
    <text x="{{ $p['x']+6 }}" y="36" font-size="10" fill="var(--pd-muted)">{{ $p['sub'] }}</text>
    @endif
    @endforeach
    @if($todayX)
    <rect x="{{ $todayX-18 }}" y="26" width="36" height="14" rx="4" fill="#DC2626"/>
    <text x="{{ $todayX }}" y="36" text-anchor="middle" font-size="9" fill="#fff" font-weight="700">Auj.</text>
    @endif
</svg>

{{-- ── Groupes ── --}}
@foreach($tasksByMilestone as $group)
@php
    $milestone    = $group['milestone'];
    $children     = $group['children'] ?? collect();
    $isPhaseGroup = $milestone && $milestone->isPhase() && $children->isNotEmpty();
    // Pour les phases : agréger toutes les tâches des enfants pour les compteurs
    $allGroupTasks = $isPhaseGroup
        ? $children->flatMap(fn($c) => $c['tasks'])
        : $group['tasks'];
    $allTasks   = $allGroupTasks->filter(fn($t) => $t->start_date && $t->due_date)->values();
    $inProgress = $allTasks->where('status','in_progress')->values();
    $isReached  = $milestone && $milestone->isReached();
    $isLate     = $milestone && $milestone->isLate();
    $isOpen     = $milestone && $milestone->id === $nextActiveMs;
    $msColor    = $isReached ? '#16A34A' : ($isLate ? '#DC2626' : ($milestone->color ?? '#EA580C'));
    $msPct      = $milestone?->due_date ? $xPct($milestone->due_date) : null;
    $activeCount = $allGroupTasks->where('status','!=','done')->count();
    $doneCount   = $allGroupTasks->where('status','done')->count();
    if ($isReached)  { $hdrBg='#F0FDF4'; $hdrBdr='#86EFAC'; }
    elseif ($isLate) { $hdrBg='#FFF5F5'; $hdrBdr='#FCA5A5'; }
    else             { $hdrBg='var(--pd-surface)'; $hdrBdr='var(--pd-border)'; }
@endphp

<div x-data="{ open: {{ $isOpen ? 'true' : 'false' }} }"
     style="border:0.5px solid {{ $hdrBdr }};border-radius:8px;overflow:hidden;margin-bottom:6px;">

    {{-- ── En-tête (avec mini timeline si replié) ── --}}
    <div @click="open=!open" style="cursor:pointer;user-select:none;background:{{ $hdrBg }};">

        {{-- Ligne principale --}}
        <div style="display:flex;align-items:center;gap:10px;padding:8px 12px;">
            @if($isReached)
            <div style="width:18px;height:18px;border-radius:50%;background:#16A34A;color:#fff;font-size:10px;display:flex;align-items:center;justify-content:center;font-weight:700;flex-shrink:0;">✓</div>
            @elseif($isLate)
            <div style="width:18px;height:18px;border-radius:50%;background:#FEE2E2;border:1.5px solid #E24B4A;color:#E24B4A;font-size:10px;display:flex;align-items:center;justify-content:center;font-weight:700;flex-shrink:0;">!</div>
            @else
            <div style="width:18px;height:18px;border-radius:50%;background:{{ $milestone->color ?? '#94A3B8' }};flex-shrink:0;"></div>
            @endif
            <div style="flex:1;font-size:13px;font-weight:700;color:{{ $isReached ? '#065F46' : 'var(--pd-text)' }};">
                @if($isPhaseGroup)
                <span style="font-size:9px;font-weight:700;background:var(--pd-navy);color:#fff;padding:1px 5px;border-radius:4px;margin-right:5px;letter-spacing:.04em;">PHASE</span>
                @endif
                {{ $milestone ? $milestone->title : 'Sans jalon' }}
            </div>
            @if($milestone?->due_date)
            <span style="font-size:11px;color:{{ $isLate ? '#E24B4A' : 'var(--pd-muted)' }};">
                @if($milestone->start_date){{ $milestone->start_date->format('d/m') }} → @endif
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

        {{-- ── Mini timeline (visible uniquement si replié) ── --}}
        <div x-show="!open" style="padding:0 12px 8px;">
            <div style="position:relative;height:20px;background:var(--pd-bg2);border-radius:4px;overflow:visible;">
                {{-- Barre de progression globale du jalon --}}
                @if($allTasks->isNotEmpty())
                @php
                    $msStart = $allTasks->min('start_date') ?? $viewStart;
                    $msEnd   = $milestone?->due_date ?? $allTasks->max('due_date') ?? $viewEnd;
                    $startPct = $xPct($msStart);
                    $endPct   = $xPct($msEnd);
                    $width    = max(1, $endPct - $startPct);
                    $donePct  = $allTasks->count() > 0 ? ($allTasks->where('status','done')->count() / $allTasks->count() * $width) : 0;
                @endphp
                {{-- Fond de la durée --}}
                <div style="position:absolute;top:4px;height:12px;left:{{ $startPct }}%;width:{{ $width }}%;background:{{ $isReached ? '#86EFAC' : 'color-mix(in srgb,'.($milestone->color??'#94A3B8').' 30%,#fff)' }};border-radius:3px;"></div>
                {{-- Progression done --}}
                <div style="position:absolute;top:4px;height:12px;left:{{ $startPct }}%;width:{{ $donePct }}%;background:{{ $isReached ? '#16A34A' : ($milestone->color??'#94A3B8') }};border-radius:3px;opacity:.7;"></div>
                @endif

                {{-- Tâches en cours : petits points bleus --}}
                @foreach($inProgress as $t)
                @php $tPct = $xPct($t->due_date); @endphp
                <div style="position:absolute;top:3px;left:calc({{ $tPct }}% - 7px);width:14px;height:14px;border-radius:50%;background:#3B82F6;border:2px solid #fff;z-index:2;"
                     title="{{ $t->title }}"></div>
                @endforeach

                {{-- Aujourd'hui --}}
                @if(now()->between($viewStart, $viewEnd))
                <div style="position:absolute;top:0;left:{{ $todayPct }}%;width:2px;height:20px;background:#DC2626;border-radius:1px;z-index:3;"></div>
                @endif

                {{-- Losange jalon --}}
                @if($msPct !== null)
                <div style="position:absolute;top:50%;left:{{ $msPct }}%;transform:translate(-50%,-50%) rotate(45deg);width:12px;height:12px;background:{{ $msColor }};border:2px solid #fff;z-index:4;border-radius:1px;"
                     title="{{ $milestone->title }}"></div>
                @endif
            </div>
        </div>

    </div>

    {{-- ── Corps SVG (visible si déplié) ── --}}
    <div x-show="open"
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         style="border-top:0.5px solid {{ $hdrBdr }};">

    @if($isPhaseGroup)
    {{-- Phase : jalons enfants chacun avec son SVG --}}
    @foreach($children as $child)
    @php
        $childMs    = $child['milestone'];
        $childTasks = $child['tasks']->filter(fn($t) => $t->start_date && $t->due_date)->values();
        $childReach = $childMs->isReached();
        $childLate  = $childMs->isLate();
        $childColor = $childMs->color ?? ($milestone->color ?? '#EA580C');
        $childMsClr = $childReach ? '#16A34A' : ($childLate ? '#DC2626' : $childColor);
        $cSvgH      = max(40, ($childTasks->count()+1) * $rowH);
        if ($childReach)  { $cHdrBg='#F0FDF4'; $cHdrBdr='#86EFAC'; }
        elseif ($childLate) { $cHdrBg='#FFF5F5'; $cHdrBdr='#FCA5A5'; }
        else              { $cHdrBg='var(--pd-surface2)'; $cHdrBdr='var(--pd-border)'; }
    @endphp
    <div style="margin:6px 10px;border:0.5px solid {{ $cHdrBdr }};border-radius:8px;overflow:hidden;"
         x-data="{ childOpen: {{ !$childReach ? 'true' : 'false' }} }">
        <div @click="childOpen=!childOpen"
             style="display:flex;align-items:center;gap:8px;padding:7px 12px;cursor:pointer;background:{{ $cHdrBg }};">
            <div style="width:8px;height:8px;border-radius:50%;background:{{ $childColor }};"></div>
            <span style="font-size:11px;font-weight:600;flex:1;">🏁 {{ $childMs->title }}</span>
            <span style="font-size:10px;color:{{ $childLate ? 'var(--pd-danger)' : 'var(--pd-muted)' }};">
                {{ $childMs->due_date?->format('d/m/Y') }}
                @if($childReach) ✓ @elseif($childLate) · Retard @endif
            </span>
            <span :style="childOpen?'':'transform:rotate(-90deg)'" style="transition:transform .15s;font-size:12px;">▾</span>
        </div>
        <div x-show="childOpen" style="border-top:0.5px solid {{ $cHdrBdr }};">
        @if($childTasks->isEmpty())
        <div style="padding:10px 14px;font-size:12px;color:var(--pd-muted);">Aucune tâche planifiée.</div>
        @else
        <svg width="{{ $svgWidth }}" height="{{ $cSvgH }}"
             :style="'transform:scaleX('+zoom+');transform-origin:left top;width:'+Math.round({{ $svgWidth }}*zoom)+'px'"
             style="display:block;" xmlns="http://www.w3.org/2000/svg">
            @foreach($periods as $p)
            <line x1="{{ $p['x'] }}" y1="0" x2="{{ $p['x'] }}" y2="{{ $cSvgH }}"
                  stroke="var(--pd-border)" stroke-width="{{ ($p['major']??false)?'0.8':'0.4' }}"
                  stroke-dasharray="{{ ($p['major']??false)?'':'4,4' }}"/>
            @endforeach
            @if($todayX)<line x1="{{ $todayX }}" y1="0" x2="{{ $todayX }}" y2="{{ $cSvgH }}" stroke="#DC2626" stroke-width="1.5" stroke-dasharray="5,3" opacity="0.7"/>@endif
            @foreach($childTasks as $ti => $task)
            @php
                $y=$ti*$rowH; $x1=$xPos($task->start_date); $x2=$xPos($task->due_date); $bw=max(8,$x2-$x1);
                $bg=$task->status==='done'?'#D1FAE5':($prioBg[$task->priority]??'#93C5FD');
                $str=$task->status==='done'?'#16A34A':($prioStroke[$task->priority]??'#2563EB');
                $isLateTask=$task->due_date->isPast()&&$task->status!=='done';
                $isInProgress=$task->status==='in_progress';
            @endphp
            <g :class="showAll||{{ $isInProgress?'true':'false' }}?'':'gantt-hidden'" style="transition:opacity .15s;">
                <rect x="0" y="{{ $y }}" width="{{ $svgWidth }}" height="{{ $rowH }}" fill="{{ $ti%2===0?'var(--pd-surface)':'var(--pd-surface2)' }}" opacity="0.7"/>
                <line x1="0" y1="{{ $y+$rowH }}" x2="{{ $svgWidth }}" y2="{{ $y+$rowH }}" stroke="var(--pd-border)" stroke-width="0.4"/>
                @if($isInProgress)<rect x="0" y="{{ $y }}" width="3" height="{{ $rowH }}" fill="#3B82F6"/>@endif
                <text x="10" y="{{ $y+$rowH/2+4 }}" font-size="11" fill="{{ $task->status==='done'?'var(--pd-muted)':'var(--pd-text)' }}" text-decoration="{{ $task->status==='done'?'line-through':'none' }}">{{ Str::limit($task->title,28) }}</text>
                <rect x="{{ $x1 }}" y="{{ $y+$barY }}" width="{{ $bw }}" height="{{ $barH }}" rx="4" fill="{{ $bg }}" stroke="{{ $isLateTask?'#DC2626':$str }}" stroke-width="{{ $isLateTask?'2':'1' }}" style="cursor:pointer;" onclick="window.dispatchEvent(new CustomEvent('open-task',{detail:{taskId:{{ $task->id }}}}))"/>
                @if($bw>50)<text x="{{ $x1+$bw/2 }}" y="{{ $y+$barY+$barH/2+4 }}" text-anchor="middle" font-size="9" font-weight="600" fill="{{ $task->status==='done'?'#065F46':$str }}">{{ \App\Models\Tenant\Task::statusLabels()[$task->status] }}</text>@endif
            </g>
            @endforeach
            @if($childMs->due_date)
            @php $yMs=$childTasks->count()*$rowH; $xMs=$xPos($childMs->due_date); @endphp
            <rect x="0" y="{{ $yMs }}" width="{{ $svgWidth }}" height="{{ $rowH }}" fill="{{ $childReach?'#F0FDF4':'#FEF3C7' }}" opacity="0.6"/>
            <text x="10" y="{{ $yMs+$rowH/2+4 }}" font-size="11" font-weight="700" fill="{{ $childReach?'#065F46':'#92400E' }}">{{ Str::limit($childMs->title,28) }}</text>
            <line x1="{{ $xMs }}" y1="0" x2="{{ $xMs }}" y2="{{ $yMs }}" stroke="{{ $childMsClr }}" stroke-width="1" stroke-dasharray="3,3" opacity="0.5"/>
            <polygon points="{{ $xMs }},{{ $yMs+4 }} {{ $xMs+10 }},{{ $yMs+$rowH/2 }} {{ $xMs }},{{ $yMs+$rowH-4 }} {{ $xMs-10 }},{{ $yMs+$rowH/2 }}" fill="{{ $childMsClr }}" stroke="#fff" stroke-width="1.5"/>
            @endif
        </svg>
        @endif
        </div>
    </div>
    @endforeach

    @else
    {{-- Jalon autonome : SVG classique --}}
    @php $svgTaskCount=$allTasks->count()+($milestone?1:0); $svgHeight=max(40,$svgTaskCount*$rowH); @endphp
    @if($allTasks->isEmpty())
    <div style="padding:14px 16px;font-size:12px;color:var(--pd-muted);">Aucune tâche avec des dates de début et de fin.</div>
    @else
    <svg width="{{ $svgWidth }}" height="{{ $svgHeight }}"
         :style="'transform:scaleX('+zoom+');transform-origin:left top;width:'+Math.round({{ $svgWidth }}*zoom)+'px'"
         style="display:block;font-family:'DM Sans',sans-serif;" xmlns="http://www.w3.org/2000/svg">
        @foreach($periods as $p)
        <line x1="{{ $p['x'] }}" y1="0" x2="{{ $p['x'] }}" y2="{{ $svgHeight }}" stroke="var(--pd-border)" stroke-width="{{ ($p['major']??false)?'0.8':'0.4' }}" stroke-dasharray="{{ ($p['major']??false)?'':'4,4' }}"/>
        @endforeach
        @if($todayX)<line x1="{{ $todayX }}" y1="0" x2="{{ $todayX }}" y2="{{ $svgHeight }}" stroke="#DC2626" stroke-width="1.5" stroke-dasharray="5,3" opacity="0.7"/>@endif
        @foreach($allTasks as $ti => $task)
        @php
            $y=$ti*$rowH; $x1=$xPos($task->start_date); $x2=$xPos($task->due_date); $bw=max(8,$x2-$x1);
            $bg=$task->status==='done'?'#D1FAE5':($prioBg[$task->priority]??'#93C5FD');
            $str=$task->status==='done'?'#16A34A':($prioStroke[$task->priority]??'#2563EB');
            $isLateTask=$task->due_date->isPast()&&$task->status!=='done';
            $isInProgress=$task->status==='in_progress';
        @endphp
        <g :class="showAll||{{ $isInProgress?'true':'false' }}?'':'gantt-hidden'" style="transition:opacity .15s;">
            <rect x="0" y="{{ $y }}" width="{{ $svgWidth }}" height="{{ $rowH }}" fill="{{ $ti%2===0?'var(--pd-surface)':'var(--pd-surface2)' }}" opacity="0.7"/>
            <line x1="0" y1="{{ $y+$rowH }}" x2="{{ $svgWidth }}" y2="{{ $y+$rowH }}" stroke="var(--pd-border)" stroke-width="0.4"/>
            @if($isInProgress)<rect x="0" y="{{ $y }}" width="3" height="{{ $rowH }}" fill="#3B82F6"/>@endif
            <text x="10" y="{{ $y+$rowH/2+4 }}" font-size="11" fill="{{ $task->status==='done'?'var(--pd-muted)':'var(--pd-text)' }}" text-decoration="{{ $task->status==='done'?'line-through':'none' }}">{{ Str::limit($task->title,28) }}</text>
            <rect x="{{ $x1 }}" y="{{ $y+$barY }}" width="{{ $bw }}" height="{{ $barH }}" rx="4" fill="{{ $bg }}" stroke="{{ $isLateTask?'#DC2626':$str }}" stroke-width="{{ $isLateTask?'2':'1' }}" style="cursor:pointer;" onclick="window.dispatchEvent(new CustomEvent('open-task',{detail:{taskId:{{ $task->id }}}}))"/>
            @if($bw>50)<text x="{{ $x1+$bw/2 }}" y="{{ $y+$barY+$barH/2+4 }}" text-anchor="middle" font-size="9" font-weight="600" fill="{{ $task->status==='done'?'#065F46':$str }}">{{ \App\Models\Tenant\Task::statusLabels()[$task->status] }}</text>@endif
        </g>
        @endforeach
        @if($milestone && $milestone->due_date)
        @php $yMs=$allTasks->count()*$rowH; $xMs=$xPos($milestone->due_date); $msClr=$isReached?'#16A34A':($isLate?'#DC2626':($milestone->color??'#EA580C')); @endphp
        <rect x="0" y="{{ $yMs }}" width="{{ $svgWidth }}" height="{{ $rowH }}" fill="{{ $isReached?'#F0FDF4':'#FEF3C7' }}" opacity="0.6"/>
        <text x="10" y="{{ $yMs+$rowH/2+4 }}" font-size="11" font-weight="700" fill="{{ $isReached?'#065F46':'#92400E' }}">{{ Str::limit($milestone->title,28) }}</text>
        <line x1="{{ $xMs }}" y1="0" x2="{{ $xMs }}" y2="{{ $yMs }}" stroke="{{ $msClr }}" stroke-width="1" stroke-dasharray="3,3" opacity="0.5"/>
        <polygon points="{{ $xMs }},{{ $yMs+4 }} {{ $xMs+10 }},{{ $yMs+$rowH/2 }} {{ $xMs }},{{ $yMs+$rowH-4 }} {{ $xMs-10 }},{{ $yMs+$rowH/2 }}" fill="{{ $msClr }}" stroke="#fff" stroke-width="1.5"/>
        @endif
    </svg>
    @endif
    @endif
    </div>

</div>
@endforeach

</div>
</div>

{{-- Légende --}}
<div style="display:flex;gap:14px;margin-top:12px;flex-wrap:wrap;font-size:11px;color:var(--pd-muted);align-items:center;">
    @foreach(\App\Models\Tenant\Task::priorityLabels() as $p => $l)
    <div style="display:flex;align-items:center;gap:5px;">
        <div style="width:14px;height:10px;border-radius:3px;background:{{ $prioBg[$p] }};border:1px solid {{ $prioStroke[$p] }};"></div>{{ $l }}
    </div>
    @endforeach
    <div style="display:flex;align-items:center;gap:5px;">
        <div style="width:3px;height:14px;background:#3B82F6;border-radius:1px;"></div> En cours
    </div>
    <div style="display:flex;align-items:center;gap:5px;">
        <div style="width:10px;height:10px;transform:rotate(45deg);background:#EA580C;border-radius:1px;"></div> Jalon
    </div>
    <div style="display:flex;align-items:center;gap:5px;">
        <div style="width:16px;height:2px;background:#DC2626;"></div> Aujourd'hui
    </div>
</div>

</div>

<style>
.gantt-hidden { display: none; }
</style>

<script>
function ganttCtrl() {
    return {
        zoom: 1,
        showAll: false,
        init() {
            const s = localStorage.getItem('gantt_zoom_v3');
            if (s) this.zoom = Math.max(0.5, Math.min(2.5, parseFloat(s)));
        },
    };
}
</script>
