{{-- _comcom.blade.php — Plan de communication — Timeline visuelle --}}
@php
$channels = \App\Models\Tenant\ProjectCommAction::channelConfig();
$pending  = $project->commActions->filter(fn($a)=>!$a->isDone())->sortBy('planned_at');
$done     = $project->commActions->filter(fn($a)=>$a->isDone())->sortByDesc('done_at');

// Calcul fenêtre temporelle pour la timeline (today ± contexte)
$allDates = $pending->pluck('planned_at');
$minDate  = $allDates->min() ?? now();
$maxDate  = $allDates->max() ?? now()->addDays(30);

// Fenêtre : du début du mois courant jusqu'à max + 2 semaines
$timeStart = now()->startOfMonth();
$timeEnd   = \Carbon\Carbon::parse($maxDate)->addWeeks(2)->endOfMonth();
$totalDays = max($timeStart->diffInDays($timeEnd), 1);

function dayPos(\Carbon\Carbon $date, \Carbon\Carbon $start, int $total): float {
    $d = max(0, min($total, $start->diffInDays($date, false)));
    return round(($d / $total) * 100, 2);
}

// Mois pour les repères
$months = [];
$cursor = $timeStart->copy()->startOfMonth();
while ($cursor->lte($timeEnd)) {
    $months[] = [
        'label' => $cursor->translatedFormat('M Y'),
        'pos'   => dayPos($cursor, $timeStart, $totalDays),
    ];
    $cursor->addMonth();
}
@endphp

<div class="section-hdr">
    <div>
        <div class="section-title">Plan de communication</div>
        <div class="section-sub">{{ $pending->count() }} action{{ $pending->count()>1?'s':'' }} à venir · {{ $done->count() }} réalisée{{ $done->count()>1?'s':'' }}</div>
    </div>
    @if($canManage)
    <button class="btn-sm btn-navy" onclick="document.getElementById('modal-comm').classList.add('open')">+ Action</button>
    @endif
</div>

{{-- ── TIMELINE VISUELLE ── --}}
<div class="pd-card" style="margin-bottom:14px;overflow-x:auto;">

    @if($pending->isEmpty())
    <div style="text-align:center;padding:40px;color:var(--pd-muted);font-size:12px;">
        <div style="font-size:28px;margin-bottom:8px;">📅</div>
        Aucune action de communication prévue.
    </div>
    @else

    {{-- Ruban des mois --}}
    <div style="position:relative;height:22px;margin-bottom:4px;min-width:500px;">
        @foreach($months as $m)
        <div style="position:absolute;left:calc({{ $m['pos'] }}% + 1px);font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--pd-muted);white-space:nowrap;">
            {{ $m['label'] }}
        </div>
        @endforeach
    </div>

    {{-- Ligne de temps avec marqueurs mois --}}
    <div style="position:relative;margin-bottom:6px;min-width:500px;">
        <div style="height:2px;background:var(--pd-border);border-radius:2px;position:relative;">
            {{-- Trait aujourd'hui --}}
            @php $todayPos = dayPos(now(), $timeStart, $totalDays); @endphp
            @if($todayPos >= 0 && $todayPos <= 100)
            <div style="position:absolute;left:{{ $todayPos }}%;top:-6px;width:2px;height:14px;background:var(--pd-navy);border-radius:1px;z-index:5;" title="Aujourd'hui"></div>
            @endif
            {{-- Tirets mois --}}
            @foreach($months as $m)
            <div style="position:absolute;left:{{ $m['pos'] }}%;top:-3px;width:1px;height:8px;background:var(--pd-border);"></div>
            @endforeach
        </div>
    </div>

    {{-- Actions sur la timeline --}}
    <div style="position:relative;min-width:500px;padding-bottom:4px;">
        @foreach($pending as $index => $action)
        @php
            $pos   = dayPos(\Carbon\Carbon::parse($action->planned_at), $timeStart, $totalDays);
            $pos   = max(0, min(96, $pos));
            $chCfg = $channels[$action->channel] ?? ['icon'=>'📢','label'=>$action->channel];
            $late  = $action->isLate();
            $row   = $index % 3; // décaler les cartes sur 3 lignes pour éviter chevauchement
        @endphp
        <div x-data="{ open: false }"
             style="position:relative;margin-bottom:6px;min-height:38px;">
            {{-- Trait vertical jusqu'à la ligne --}}
            <div style="position:absolute;left:calc({{ $pos }}% + 6px);top:0;width:1px;height:100%;background:{{ $late ? 'var(--pd-danger)' : 'var(--pd-border)' }};opacity:.5;z-index:0;"></div>
            {{-- Carte action --}}
            <div @mouseenter="open=true" @mouseleave="open=false"
                 style="position:relative;display:inline-block;margin-left:calc({{ $pos }}%);max-width:180px;z-index:5;">
                <div style="background:{{ $late ? '#FEF2F2' : 'var(--pd-surface)' }};border:1px solid {{ $late ? '#FECACA' : 'var(--pd-border)' }};border-radius:8px;padding:6px 10px;cursor:default;transition:box-shadow .12s;"
                     :style="open ? 'box-shadow:0 4px 14px rgba(0,0,0,.12);z-index:20;' : ''">
                    <div style="display:flex;align-items:center;gap:5px;margin-bottom:2px;">
                        <span style="font-size:13px;">{{ $chCfg['icon'] }}</span>
                        <span style="font-size:10px;font-weight:700;color:{{ $late ? 'var(--pd-danger)' : 'var(--pd-text)' }};white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:120px;">{{ $action->title }}</span>
                    </div>
                    <div style="font-size:9px;color:var(--pd-muted);">
                        {{ \Carbon\Carbon::parse($action->planned_at)->translatedFormat('d M') }}
                        @if($late) · <span style="color:var(--pd-danger);font-weight:600;">En retard</span>@endif
                    </div>
                </div>
                {{-- Tooltip détail --}}
                <div x-show="open" x-cloak
                     style="position:absolute;bottom:calc(100% + 8px);left:0;background:var(--pd-surface);border:1px solid var(--pd-border);border-radius:8px;box-shadow:0 6px 20px rgba(0,0,0,.13);padding:10px 14px;z-index:200;min-width:220px;max-width:280px;white-space:normal;">
                    <div style="font-size:11px;font-weight:700;color:var(--pd-text);margin-bottom:4px;">{{ $action->title }}</div>
                    <div style="font-size:10px;color:var(--pd-muted);margin-bottom:3px;">
                        {{ $chCfg['icon'] }} {{ $chCfg['label'] }} · {{ $action->target_audience }}
                    </div>
                    <div style="font-size:10px;color:var(--pd-muted);margin-bottom:3px;">
                        📅 {{ \Carbon\Carbon::parse($action->planned_at)->translatedFormat('d M Y') }}
                        @if($late) &nbsp;<span style="color:var(--pd-danger);font-weight:600;">⚠ En retard</span>@endif
                    </div>
                    @if($action->responsible)
                    <div style="font-size:10px;color:var(--pd-muted);margin-bottom:3px;">👤 {{ $action->responsible->name }}</div>
                    @endif
                    @if($action->message)
                    <div style="font-size:10px;color:var(--pd-muted);border-top:1px solid var(--pd-border);padding-top:5px;margin-top:4px;">{{ Str::limit($action->message, 100) }}</div>
                    @endif
                    @if($canManage)
                    <div style="display:flex;gap:6px;margin-top:8px;border-top:1px solid var(--pd-border);padding-top:6px;">
                        <form method="POST" action="{{ route('projects.comm_actions.update', [$project, $action]) }}" style="display:inline;">
                            @csrf @method('PATCH')
                            <input type="hidden" name="done_at" value="{{ now()->toDateString() }}">
                            <button type="submit" style="font-size:9px;padding:2px 7px;border-radius:5px;background:#D1FAE5;color:#065F46;border:1px solid #86EFAC;cursor:pointer;">✓ Marquer fait</button>
                        </form>
                        <form method="POST" action="{{ route('projects.comm_actions.destroy', [$project, $action]) }}" onsubmit="return confirm('Supprimer ?')">
                            @csrf @method('DELETE')
                            <button type="submit" style="font-size:9px;padding:2px 7px;border-radius:5px;background:var(--pd-bg2);color:var(--pd-muted);border:1px solid var(--pd-border);cursor:pointer;">Supprimer</button>
                        </form>
                    </div>
                    @endif
                    <div style="position:absolute;bottom:-5px;left:16px;width:8px;height:8px;background:var(--pd-surface);border-right:1px solid var(--pd-border);border-bottom:1px solid var(--pd-border);transform:rotate(45deg);"></div>
                </div>
            </div>
        </div>
        @endforeach
    </div>

    {{-- Label "Aujourd'hui" --}}
    @if($todayPos >= 0 && $todayPos <= 100)
    <div style="position:relative;min-width:500px;height:14px;">
        <div style="position:absolute;left:calc({{ $todayPos }}%);transform:translateX(-50%);font-size:8px;font-weight:700;color:var(--pd-navy);text-transform:uppercase;letter-spacing:.04em;white-space:nowrap;">
            Aujourd'hui
        </div>
    </div>
    @endif

    @endif
</div>

{{-- ── Tableau compact à venir ── --}}
@if($pending->isNotEmpty())
<div class="pd-card" style="margin-bottom:14px;">
    <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--pd-muted);margin-bottom:12px;">Liste — À réaliser</div>
    <table class="pd-table">
        <thead><tr><th>Action</th><th>Canal</th><th>Audience</th><th>Responsable</th><th>Prévu le</th><th>Statut</th>@if($canManage)<th></th>@endif</tr></thead>
        <tbody>
        @foreach($pending as $action)
        <tr>
            <td style="font-weight:500;">{{ $action->title }}</td>
            <td><span style="font-size:11px;">{{ $channels[$action->channel]['icon'] ?? '' }} {{ $channels[$action->channel]['label'] }}</span></td>
            <td style="color:var(--pd-muted);">{{ $action->target_audience }}</td>
            <td style="color:var(--pd-muted);">{{ $action->responsible?->name ?? '—' }}</td>
            <td style="color:{{ $action->isLate() ? 'var(--pd-danger)' : 'var(--pd-muted)' }};font-size:11px;">
                {{ $action->planned_at->translatedFormat('d M Y') }}
                @if($action->isLate()) · En retard @endif
            </td>
            <td>
                @if($canManage)
                <form method="POST" action="{{ route('projects.comm_actions.update', [$project, $action]) }}" style="display:inline;">
                    @csrf @method('PATCH')
                    <input type="hidden" name="done_at" value="{{ now()->toDateString() }}">
                    <button type="submit" class="btn-sm" style="font-size:10px;padding:3px 8px;background:#D1FAE5;color:#065F46;border-color:#86EFAC;">✓ Fait</button>
                </form>
                @else
                <span class="pd-badge" style="background:#FEF3C7;color:#92400E;">À faire</span>
                @endif
            </td>
            @if($canManage)
            <td>
                <form method="POST" action="{{ route('projects.comm_actions.destroy', [$project, $action]) }}" onsubmit="return confirm('Supprimer ?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn-sm" style="padding:3px 7px;color:var(--pd-muted);">✕</button>
                </form>
            </td>
            @endif
        </tr>
        @endforeach
        </tbody>
    </table>
</div>
@endif

{{-- ── Réalisées repliables ── --}}
@if($done->count())
<details>
    <summary style="cursor:pointer;font-size:12px;color:var(--pd-muted);padding:6px 0;user-select:none;">
        ▸ {{ $done->count() }} action{{ $done->count()>1?'s':'' }} réalisée{{ $done->count()>1?'s':'' }}
    </summary>
    <div class="pd-card" style="margin-top:8px;opacity:.7;">
        <table class="pd-table">
            <thead><tr><th>Action</th><th>Canal</th><th>Réalisée le</th></tr></thead>
            <tbody>
            @foreach($done as $action)
            <tr>
                <td style="text-decoration:line-through;color:var(--pd-muted);">{{ $action->title }}</td>
                <td style="color:var(--pd-muted);">{{ $channels[$action->channel]['label'] ?? $action->channel }}</td>
                <td style="color:var(--pd-muted);">{{ $action->done_at->translatedFormat('d M Y') }}</td>
            </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</details>
@endif

{{-- ── Modal ajout ── --}}
@if($canManage)
<div id="modal-comm" class="pd-modal-overlay">
    <div class="pd-modal pd-modal-md">
        <div class="pd-modal-header">
            <div>
                <div class="pd-modal-title">Nouvelle action de communication</div>
                <div class="pd-modal-subtitle">Ajoutée automatiquement à la timeline</div>
            </div>
            <button class="pd-modal-close" onclick="document.getElementById('modal-comm').classList.remove('open')">×</button>
        </div>
        <div class="pd-modal-body">
            <form id="form-comm" method="POST" action="{{ route('projects.comm_actions.store', $project) }}">
                @csrf
                <div class="pd-form-group">
                    <label class="pd-label pd-label-req">Titre de l'action</label>
                    <input type="text" name="title" class="pd-input" required>
                </div>
                <div class="pd-form-row-2">
                    <div class="pd-form-group">
                        <label class="pd-label pd-label-req">Audience cible</label>
                        <input type="text" name="target_audience" class="pd-input" placeholder="Agents, Élus, Usagers..." required>
                    </div>
                    <div class="pd-form-group">
                        <label class="pd-label pd-label-req">Canal</label>
                        <select name="channel" class="pd-input" required>
                            @foreach($channels as $val => $cfg)
                            <option value="{{ $val }}">{{ $cfg['icon'] }} {{ $cfg['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="pd-form-row-2">
                    <div class="pd-form-group">
                        <label class="pd-label pd-label-req">Date prévue</label>
                        <input type="date" name="planned_at" class="pd-input" required>
                    </div>
                    <div class="pd-form-group">
                        <label class="pd-label">Responsable</label>
                        <select name="responsible_id" class="pd-input">
                            <option value="">—</option>
                            @foreach($tenantUsers as $u)
                            <option value="{{ $u->id }}">{{ $u->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="pd-form-group">
                    <label class="pd-label">Message / points clés</label>
                    <textarea name="message" class="pd-input" rows="3"></textarea>
                </div>
            </form>
        </div>
        <div class="pd-modal-footer">
            <button type="button" class="pd-btn pd-btn-secondary" onclick="document.getElementById('modal-comm').classList.remove('open')">Annuler</button>
            <button type="submit" form="form-comm" class="pd-btn pd-btn-primary">Enregistrer</button>
        </div>
    </div>
</div>
@endif
