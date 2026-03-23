{{-- resources/views/projects/partials/_historique.blade.php --}}
{{-- Fil chronologique d'activité du projet — pagination 25/page --}}

@php
use App\Http\Controllers\Projects\ProjectHistoryController;

$actionMap  = ProjectHistoryController::actionMap();
$statusLabels = \App\Models\Tenant\Task::statusLabels();

/**
 * Construit le texte descriptif d'une entrée de log.
 * Les données métier sont dans new_values (JSON).
 */
$describe = function (\App\Models\Tenant\AuditLog $log) use ($statusLabels): string {
    $data = $log->new_values ? json_decode($log->new_values, true) : [];

    return match ($log->action) {
        'task.created'           => 'Tâche créée : « ' . ($data['task_title'] ?? '—') . ' »',
        'task.deleted'           => 'Tâche supprimée : « ' . ($data['task_title'] ?? '—') . ' »',
        'task.status_changed'    => 'Tâche #' . ($data['task_id'] ?? '?') . ' : '
            . ($statusLabels[$data['from'] ?? ''] ?? $data['from'] ?? '?')
            . ' → '
            . ($statusLabels[$data['to'] ?? ''] ?? $data['to'] ?? '?'),
        'project.created'        => 'Projet « ' . ($data['project_name'] ?? '—') . ' » créé',
        'project.updated'        => 'Informations du projet mises à jour',
        'project.duplicated'     => 'Projet dupliqué → « ' . ($data['new_name'] ?? '—') . ' »',
        'project.deleted'        => 'Projet supprimé',
        'project.member.added'   => 'Membre ajouté : ' . ($data['user_name'] ?? '—') . ' (' . ($data['role'] ?? '—') . ')',
        'project.member.removed' => 'Membre retiré : ' . ($data['user_name'] ?? '—'),
        'milestone.reached'      => 'Jalon atteint : « ' . ($data['milestone_name'] ?? '—') . ' »',
        'milestone.created'      => 'Jalon créé : « ' . ($data['milestone_name'] ?? '—') . ' »',
        'phase.created'          => 'Phase créée : « ' . ($data['phase_name'] ?? '—') . ' »',
        'project.budget.created' => 'Ligne budgétaire ajoutée',
        'project.budget.updated' => 'Budget mis à jour',
        'project.budget.deleted' => 'Ligne budgétaire supprimée',
        default => $log->action,
    };
};
@endphp

{{-- ── En-tête ── --}}
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:10px;">
    <div>
        <div style="font-size:16px;font-weight:700;color:var(--pd-navy);">📜 Historique d'activité</div>
        <div style="font-size:12px;color:var(--pd-muted);margin-top:2px;">
            @if($canSeeAll)
                Toutes les actions du projet — {{ number_format($logs->total()) }} entrée{{ $logs->total() > 1 ? 's' : '' }}
            @else
                Vos actions sur ce projet — {{ number_format($logs->total()) }} entrée{{ $logs->total() > 1 ? 's' : '' }}
            @endif
        </div>
    </div>

    {{-- Filtre par type --}}
    @if($availableActions->isNotEmpty())
    <div style="display:flex;gap:8px;align-items:center;">
        <select onchange="window.dispatchEvent(new CustomEvent('load-history', {detail:{action:this.value}}))"
                style="padding:7px 10px;border:0.5px solid var(--pd-border);border-radius:7px;
                       background:var(--pd-surface);font-size:12px;color:var(--pd-text);cursor:pointer;">
            <option value="">Tous les types</option>
            @foreach($availableActions as $act => $cnt)
            <option value="{{ $act }}" {{ $filterAction === $act ? 'selected' : '' }}>
                {{ $actionMap[$act][2] ?? $act }} ({{ $cnt }})
            </option>
            @endforeach
        </select>
    </div>
    @endif
</div>

{{-- ── Fil chronologique ── --}}
@if($logs->isEmpty())
<div style="text-align:center;padding:48px 20px;color:var(--pd-muted);">
    <div style="font-size:32px;margin-bottom:12px;opacity:.3;">📭</div>
    <div style="font-size:14px;font-weight:600;">Aucune activité enregistrée</div>
    <div style="font-size:12px;margin-top:4px;">Les actions apparaîtront ici au fil du projet.</div>
</div>
@else

{{-- Groupement par jour --}}
@php $currentDay = null; @endphp
<div style="position:relative;">

    {{-- Ligne verticale du fil --}}
    <div style="position:absolute;left:19px;top:0;bottom:0;width:2px;background:var(--pd-border);border-radius:1px;"></div>

    @foreach($logs as $log)
    @php
        $day = \Carbon\Carbon::parse($log->created_at)->locale('fr')->isoFormat('dddd D MMMM YYYY');
        $map = $actionMap[$log->action] ?? ['📌', '#94A3B8', $log->action];
        [$icon, $color, $label] = $map;
        $time = \Carbon\Carbon::parse($log->created_at)->format('H:i');
        $isNewDay = $day !== $currentDay;
        $currentDay = $day;
    @endphp

    {{-- Séparateur de jour --}}
    @if($isNewDay)
    <div style="display:flex;align-items:center;gap:10px;margin:{{ $loop->first ? '0' : '20px' }} 0 16px;position:relative;z-index:1;">
        <div style="width:40px;flex-shrink:0;"></div>
        <div style="font-size:11px;font-weight:700;color:var(--pd-muted);letter-spacing:.5px;
                    background:var(--pd-surface2);padding:3px 10px;border-radius:20px;
                    border:0.5px solid var(--pd-border);white-space:nowrap;text-transform:capitalize;">
            {{ $day }}
        </div>
    </div>
    @endif

    {{-- Entrée du fil --}}
    <div style="display:flex;align-items:flex-start;gap:12px;margin-bottom:12px;position:relative;z-index:1;">

        {{-- Icône --}}
        <div style="width:40px;flex-shrink:0;display:flex;justify-content:center;">
            <div style="width:34px;height:34px;border-radius:50%;background:{{ $color }}1a;
                        border:2px solid {{ $color }};display:flex;align-items:center;justify-content:center;
                        font-size:15px;background-color:var(--pd-surface);">
                {{ $icon }}
            </div>
        </div>

        {{-- Contenu --}}
        <div style="flex:1;background:var(--pd-surface);border:0.5px solid var(--pd-border);
                    border-radius:10px;padding:10px 14px;min-width:0;">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap;margin-bottom:4px;">
                <span style="font-size:11px;font-weight:700;color:{{ $color }};
                             background:{{ $color }}1a;padding:2px 8px;border-radius:20px;">
                    {{ $label }}
                </span>
                <span style="font-size:11px;color:var(--pd-muted);white-space:nowrap;">{{ $time }}</span>
            </div>

            <div style="font-size:13px;color:var(--pd-text);margin-bottom:6px;">
                {{ $describe($log) }}
            </div>

            <div style="font-size:11px;color:var(--pd-muted);display:flex;align-items:center;gap:6px;">
                <span>👤</span>
                <span>{{ $log->user_name ?? 'Système' }}</span>
            </div>
        </div>
    </div>
    @endforeach

</div>

{{-- ── Pagination ── --}}
@if($logs->hasPages())
<div style="margin-top:24px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;">
    <div style="font-size:12px;color:var(--pd-muted);">
        {{ $logs->firstItem() }}–{{ $logs->lastItem() }} sur {{ $logs->total() }}
    </div>
    <div style="display:flex;gap:4px;">
        @if($logs->onFirstPage())
        <span style="padding:6px 10px;border:0.5px solid var(--pd-border);border-radius:6px;font-size:12px;color:var(--pd-muted);cursor:default;">‹</span>
        @else
        <a href="{{ $logs->previousPageUrl() }}&section=historique{{ $filterAction ? '&action='.$filterAction : '' }}"
           style="padding:6px 10px;border:0.5px solid var(--pd-border);border-radius:6px;font-size:12px;color:var(--pd-text);text-decoration:none;background:var(--pd-surface);">‹</a>
        @endif

        @foreach($logs->getUrlRange(max(1,$logs->currentPage()-2), min($logs->lastPage(),$logs->currentPage()+2)) as $page => $url)
        <a href="{{ $url }}&section=historique{{ $filterAction ? '&action='.$filterAction : '' }}"
           style="padding:6px 10px;border:0.5px solid {{ $page === $logs->currentPage() ? 'var(--pd-navy)' : 'var(--pd-border)' }};
                  border-radius:6px;font-size:12px;color:{{ $page === $logs->currentPage() ? 'var(--pd-navy)' : 'var(--pd-text)' }};
                  font-weight:{{ $page === $logs->currentPage() ? '700' : '400' }};
                  text-decoration:none;background:var(--pd-surface);">{{ $page }}</a>
        @endforeach

        @if($logs->hasMorePages())
        <a href="{{ $logs->nextPageUrl() }}&section=historique{{ $filterAction ? '&action='.$filterAction : '' }}"
           style="padding:6px 10px;border:0.5px solid var(--pd-border);border-radius:6px;font-size:12px;color:var(--pd-text);text-decoration:none;background:var(--pd-surface);">›</a>
        @else
        <span style="padding:6px 10px;border:0.5px solid var(--pd-border);border-radius:6px;font-size:12px;color:var(--pd-muted);cursor:default;">›</span>
        @endif
    </div>
</div>
@endif

@endif
