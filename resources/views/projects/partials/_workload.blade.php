{{-- _workload.blade.php — Vue charge par personne --}}
@php
use Carbon\Carbon;

$today     = now()->startOfDay();

// Fenêtre centrée sur les tâches actives (in_progress en priorité)
// Récupérer d'abord les tâches pour calculer la fenêtre
$allAssigned = $project->tasks()
    ->with('assignee:id,name')
    ->whereNotNull('assigned_to')
    ->whereNotNull('due_date')
    ->whereNotIn('status', ['done'])
    ->get();

$inProgressTasks = $allAssigned->where('status', 'in_progress');
$activeTasks     = $inProgressTasks->isNotEmpty() ? $inProgressTasks : $allAssigned;

if ($activeTasks->isNotEmpty()) {
    $firstDate = $activeTasks->min(fn($t) => $t->start_date ?? $t->due_date);
    $lastDate  = $activeTasks->max('due_date');
    $viewStart = $firstDate->copy()->subWeeks(2)->startOfWeek();
    $viewEnd   = $lastDate->copy()->addWeeks(6)->endOfWeek();
} else {
    $viewStart = $today->copy()->subWeeks(2)->startOfWeek();
    $viewEnd   = $today->copy()->addWeeks(8)->endOfWeek();
}

// Toujours inclure aujourd'hui
if ($today->lt($viewStart)) $viewStart = $today->copy()->startOfWeek();
if ($today->gt($viewEnd))   $viewEnd   = $today->copy()->addWeeks(4)->endOfWeek();

$tasks = $allAssigned;

$totalWeeks = max(1, $viewStart->diffInWeeks($viewEnd) + 1);
$weekWidth  = max(50, min(110, 3000 / $totalWeeks));

$weeks = [];
$cur   = $viewStart->copy();
while ($cur->lte($viewEnd)) {
    $weeks[] = $cur->copy();
    $cur->addWeek();
}

$tasksInProgress = $tasks->where('status', 'in_progress');

$byUser  = $tasks->groupBy('assigned_to');
$members = $project->projectMembers->sortBy('user.name');
$ALERT   = 3;
@endphp

<div style="margin-bottom:10px;display:flex;align-items:center;gap:14px;flex-wrap:wrap;">
    <span style="font-size:12px;color:var(--pd-muted);">
        {{ $tasks->count() }} tâche{{ $tasks->count()>1?'s':'' }} assignée{{ $tasks->count()>1?'s':'' }}
        sur {{ $members->count() }} membre{{ $members->count()>1?'s':'' }}
    </span>
    <label style="display:flex;align-items:center;gap:6px;font-size:12px;color:var(--pd-muted);cursor:pointer;">
        <input type="checkbox" x-model="showAll" style="accent-color:var(--pd-navy);">
        Toutes les tâches
    </label>
    <div style="display:flex;gap:8px;font-size:11px;align-items:center;">
        <span style="display:inline-block;width:10px;height:10px;border-radius:2px;background:#DBEAFE;border:1px solid #93C5FD;"></span> Normal
        <span style="display:inline-block;width:10px;height:10px;border-radius:2px;background:#FEF3C7;border:1px solid #FCD34D;"></span> Chargé ({{ $ALERT }}+)
        <span style="display:inline-block;width:10px;height:10px;border-radius:2px;background:#FEE2E2;border:1px solid #FCA5A5;"></span> Surchargé ({{ $ALERT+2 }}+)
    </div>
</div>

@if($members->isEmpty())
<div style="text-align:center;padding:40px;color:var(--pd-muted);font-size:12px;">
    Aucun membre dans ce projet.
</div>
@elseif($tasks->isEmpty())
<div style="text-align:center;padding:40px;color:var(--pd-muted);font-size:12px;">
    Aucune tâche assignée avec une date d'échéance.
</div>
@else

<div x-data="{ showAll: false }" style="overflow-x:auto;">
<div style="min-width:{{ 180 + count($weeks) * $weekWidth }}px;">

    {{-- En-tête semaines --}}
    <div style="display:flex;margin-left:180px;margin-bottom:2px;">
        @foreach($weeks as $week)
        @php $isNow = $week->isSameWeek(now()); @endphp
        <div style="width:{{ $weekWidth }}px;flex-shrink:0;text-align:center;padding:4px 2px;font-size:10px;color:{{ $isNow ? 'var(--pd-navy)' : 'var(--pd-muted)' }};font-weight:{{ $isNow ? '700' : '400' }};border-right:0.5px solid var(--pd-border);background:{{ $isNow ? 'color-mix(in srgb,var(--pd-navy) 6%,transparent)' : 'transparent' }};">
            S{{ $week->weekOfYear }}<br>
            <span style="font-size:9px;">{{ $week->translatedFormat('d M') }}</span>
        </div>
        @endforeach
    </div>

    {{-- Lignes par membre --}}
    @foreach($members as $pm)
    @php
    $user      = $pm->user;
    $userTasks = $byUser->get($user->id, collect());

    // Charge par semaine
    $weekLoads = [];
    foreach ($weeks as $wi => $week) {
        $wEnd = $week->copy()->endOfWeek();
        $weekLoads[$wi] = $userTasks->filter(function($t) use ($week, $wEnd) {
            $start = $t->start_date ?? $t->due_date->copy()->subDays(1);
            return $start->lte($wEnd) && $t->due_date->gte($week);
        })->count();
    }
    @endphp

    <div style="border-top:0.5px solid var(--pd-border);padding-top:8px;margin-bottom:4px;">

        {{-- Cellules charge --}}
        <div style="display:flex;">
            <div style="width:180px;flex-shrink:0;display:flex;align-items:center;gap:8px;padding-right:12px;padding-bottom:4px;">
                <div style="width:30px;height:30px;border-radius:50%;background:var(--pd-bg2);color:var(--pd-navy);font-size:11px;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    {{ strtoupper(substr($user->name,0,2)) }}
                </div>
                <div>
                    <div style="font-size:12px;font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:125px;">{{ $user->name }}</div>
                    <div style="font-size:10px;color:var(--pd-muted);">{{ $userTasks->count() }} tâche{{ $userTasks->count()>1?'s':'' }}</div>
                </div>
            </div>
            @foreach($weeks as $wi => $week)
            @php
            $load = $weekLoads[$wi];
            $bg   = $load === 0 ? 'transparent' : ($load >= $ALERT+2 ? '#FEE2E2' : ($load >= $ALERT ? '#FEF3C7' : '#DBEAFE'));
            $tc   = $load === 0 ? 'var(--pd-border)' : ($load >= $ALERT+2 ? '#991B1B' : ($load >= $ALERT ? '#92400E' : '#1E40AF'));
            $isNow = $week->isSameWeek(now());
            @endphp
            <div style="width:{{ $weekWidth }}px;flex-shrink:0;height:36px;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:{{ $load>0?'700':'400' }};color:{{ $tc }};background:{{ $bg }};border:0.5px solid {{ $load>0 ? $tc : 'var(--pd-border)' }};border-radius:4px;margin-right:2px;{{ $isNow ? 'outline:2px solid var(--pd-navy);outline-offset:-2px;' : '' }}"
                 title="{{ $load > 0 ? $load.' tâche'.($load>1?'s':'').' sem. '.$week->weekOfYear : 'Aucune tâche' }}">
                {{ $load > 0 ? $load : '' }}
            </div>
            @endforeach
        </div>

        {{-- Barres de tâches --}}
        <div style="position:relative;margin-left:180px;min-height:{{ $userTasks->count() * 22 }}px;">
            @foreach($userTasks->sortBy('due_date') as $idx => $task)
            @php
            $tStart   = ($task->start_date ?? $task->due_date->copy()->subDay())->copy()->max($viewStart);
            $tEnd     = $task->due_date->copy()->min($viewEnd);
            $leftDays = $viewStart->diffInDays($tStart);
            $durDays  = max(1, $tStart->diffInDays($tEnd) + 1);
            $leftPx   = ($leftDays / 7) * $weekWidth;
            $widthPx  = max(20, ($durDays / 7) * $weekWidth - 3);
            $isLate   = $task->due_date->isPast();
            $isInProg = $task->status === 'in_progress';
            $barBg    = $isInProg ? '#3B82F6' : ($isLate ? '#FCA5A5' : '#93C5FD');
            $barText  = $isInProg ? '#fff' : ($isLate ? '#7F1D1D' : '#1E3A5F');
            @endphp
            <div x-show="showAll || {{ $isInProg ? 'true' : 'false' }}"
                 style="position:absolute;top:{{ $idx * 22 }}px;left:{{ $leftPx }}px;width:{{ $widthPx }}px;height:18px;background:{{ $barBg }};border-radius:4px;display:flex;align-items:center;padding:0 6px;overflow:hidden;"
                 title="{{ $task->title }} — {{ $tStart->format('d/m') }} → {{ $task->due_date->format('d/m') }}">
                <span style="font-size:10px;color:{{ $barText }};white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                    {{ $task->title }}
                </span>
            </div>
            @endforeach
        </div>

    </div>
    @endforeach

</div>
</div>
@endif
