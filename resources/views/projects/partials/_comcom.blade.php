{{-- _comcom.blade.php — Plan de communication — Timeline Gantt-style --}}
@php
use Carbon\Carbon;

$channels = \App\Models\Tenant\ProjectCommAction::channelConfig();
$pending  = $project->commActions->filter(fn($a)=>!$a->isDone())->sortBy('planned_at');
$done     = $project->commActions->filter(fn($a)=>$a->isDone())->sortByDesc('done_at');

// ── Fenêtre temporelle adaptative (même logique que Gantt) ──────────────
$allDates  = $pending->pluck('planned_at')->map(fn($d) => Carbon::parse($d));
$minDate   = $allDates->min() ?? now();
$maxDate   = $allDates->max() ?? now()->addMonths(2);

// Fenêtre : du début du mois de la 1re action jusqu'à fin du mois de la dernière + 1 mois tampon
$viewStart   = Carbon::parse($minDate)->startOfMonth();
$viewEnd     = Carbon::parse($maxDate)->addMonth()->endOfMonth();
// Si aujourd'hui est avant viewStart, on l'inclut
if (now()->lt($viewStart)) { $viewStart = now()->startOfMonth(); }

$totalDays   = max(1, $viewStart->diffInDays($viewEnd));
$totalMonths = $viewStart->diffInMonths($viewEnd);

// dayWidth adaptatif — même algo que Gantt
$dayWidth  = max(3, min(14, 3600 / max(1, $totalDays)));
$svgWidth  = max(600, $totalDays * $dayWidth);

$xPos = fn($date) => max(0, $viewStart->diffInDays(Carbon::parse($date)) / $totalDays * $svgWidth);

// Périodes axe : trimestriel si > 24 mois, mensuel sinon
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
        $periods[] = [
            'label' => $cursor->translatedFormat('M'),
            'sub'   => $cursor->month === 1 ? (string)$cursor->year : '',
            'x'     => $xPos($cursor),
            'major' => $cursor->month === 1,
        ];
        $cursor->addMonth();
    }
}

$todayX = now()->between($viewStart, $viewEnd) ? $xPos(now()) : null;

// Hauteur SVG : 1 ligne par action (30px) + header (44px)
$rowH   = 34;
$headH  = 44;
$svgH   = $headH + max(1, $pending->count()) * $rowH + 10;
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

{{-- ── TIMELINE SVG GANTT-STYLE ── --}}
<div x-data="comcomCtrl()" x-init="init()">

    @if($pending->isEmpty())
    <div class="pd-card" style="text-align:center;padding:40px;color:var(--pd-muted);font-size:12px;margin-bottom:14px;">
        <div style="font-size:28px;margin-bottom:8px;">📅</div>
        Aucune action de communication prévue.
    </div>
    @else

    {{-- Barre d'outils zoom --}}
    <div style="display:flex;align-items:center;justify-content:flex-end;gap:8px;margin-bottom:8px;flex-wrap:wrap;">
        <span style="font-size:11px;color:var(--pd-muted);">Hauteur</span>
        <button @click="vScale=Math.max(0.5,vScale-0.25)" class="pd-btn pd-btn-xs pd-btn-secondary">↕−</button>
        <span style="font-size:11px;font-weight:600;min-width:36px;text-align:center;" x-text="Math.round(vScale*100)+'%'"></span>
        <button @click="vScale=Math.min(4,vScale+0.25)" class="pd-btn pd-btn-xs pd-btn-secondary">↕+</button>
        <span style="color:var(--pd-border);font-size:11px;">|</span>
        <span style="font-size:11px;color:var(--pd-muted);">Zoom</span>
        <button @click="zoom=Math.max(0.4,zoom-0.15)" class="pd-btn pd-btn-xs pd-btn-secondary">−</button>
        <span style="font-size:11px;font-weight:600;min-width:36px;text-align:center;" x-text="Math.round(zoom*100)+'%'"></span>
        <button @click="zoom=Math.min(3,zoom+0.15)" class="pd-btn pd-btn-xs pd-btn-secondary">+</button>
        <button @click="zoom=1;vScale=1" class="pd-btn pd-btn-xs pd-btn-secondary">↺</button>
    </div>

    <div style="overflow-x:auto;">
    <div :style="'width:'+Math.round({{ $svgWidth }}*zoom)+'px'">

    {{-- ── Axe temporel (header fixe — pas de scaleY) ── --}}
    <svg width="{{ $svgWidth }}" height="{{ $headH }}"
         :style="'transform:scaleX('+zoom+');transform-origin:left top;width:'+Math.round({{ $svgWidth }}*zoom)+'px'"
         style="display:block;background:var(--pd-surface2);border-radius:8px 8px 0 0;border:0.5px solid var(--pd-border);">
        @foreach($periods as $p)
        <line x1="{{ $p['x'] }}" y1="0" x2="{{ $p['x'] }}" y2="{{ $headH }}"
              stroke="var(--pd-border)" stroke-width="{{ ($p['major']??false) ? '1' : '0.5' }}"/>
        <text x="{{ $p['x']+5 }}" y="18" font-size="11"
              font-weight="{{ ($p['major']??false) ? '700' : '500' }}"
              fill="{{ ($p['major']??false) ? 'var(--pd-navy)' : 'var(--pd-muted)' }}">{{ $p['label'] }}</text>
        @if(!empty($p['sub']))
        <text x="{{ $p['x']+5 }}" y="33" font-size="10" fill="var(--pd-muted)">{{ $p['sub'] }}</text>
        @endif
        @endforeach
        @if($todayX)
        <rect x="{{ $todayX - 18 }}" y="26" width="36" height="14" rx="4" fill="#DC2626"/>
        <text x="{{ $todayX }}" y="36" text-anchor="middle" font-size="9" fill="#fff" font-weight="700">Auj.</text>
        @endif
    </svg>

    {{-- ── Corps timeline — une ligne par action ── --}}
    <div :style="'height:'+Math.round({{ $pending->count() * $rowH + 10 }}*vScale)+'px;overflow:hidden;'">
    <svg width="{{ $svgWidth }}" height="{{ $pending->count() * $rowH + 10 }}"
         :style="'transform:scaleX('+zoom+') scaleY('+vScale+');transform-origin:left top;width:'+Math.round({{ $svgWidth }}*zoom)+'px'"
         style="display:block;border:0.5px solid var(--pd-border);border-top:none;border-radius:0 0 8px 8px;background:var(--pd-surface);">

        {{-- Lignes de grille verticales --}}
        @foreach($periods as $p)
        <line x1="{{ $p['x'] }}" y1="0" x2="{{ $p['x'] }}" y2="{{ $pending->count() * $rowH + 10 }}"
              stroke="var(--pd-border)" stroke-width="0.5" stroke-dasharray="3 3"/>
        @endforeach

        {{-- Trait aujourd'hui --}}
        @if($todayX)
        <line x1="{{ $todayX }}" y1="0" x2="{{ $todayX }}" y2="{{ $pending->count() * $rowH + 10 }}"
              stroke="#DC2626" stroke-width="1.5" stroke-dasharray="4 3" opacity="0.5"/>
        @endif

        {{-- Actions --}}
        @foreach($pending as $index => $action)
        @php
            $ax    = $xPos($action->planned_at);
            $cy    = $index * $rowH + $rowH / 2;
            $late  = $action->isLate();
            $chCfg = $channels[$action->channel] ?? ['icon'=>'📢','label'=>$action->channel];
            $dotColor = $late ? '#DC2626' : 'var(--pd-navy)';
            $dateLabel = \Carbon\Carbon::parse($action->planned_at)->translatedFormat('d M Y');
            $lateLabel = $late ? ' · En retard' : '';
            // Texte à droite ou à gauche si trop proche du bord
            $textAnchor = ($ax > $svgWidth - 180) ? 'end' : 'start';
            $textX      = ($ax > $svgWidth - 180) ? ($ax - 14) : ($ax + 14);
        @endphp

        {{-- Ligne horizontale de fond --}}
        @if($index % 2 === 0)
        <rect x="0" y="{{ $index * $rowH }}" width="{{ $svgWidth }}" height="{{ $rowH }}" fill="var(--pd-bg2)" opacity="0.4"/>
        @endif

        {{-- Losange (marqueur date) --}}
        <polygon points="{{ $ax }},{{ $cy - 8 }} {{ $ax + 7 }},{{ $cy }} {{ $ax }},{{ $cy + 8 }} {{ $ax - 7 }},{{ $cy }}"
                 fill="{{ $late ? '#FEE2E2' : 'var(--pd-bg2)' }}"
                 stroke="{{ $dotColor }}"
                 stroke-width="1.5"/>

        {{-- Icône canal --}}
        <text x="{{ $ax }}" y="{{ $cy + 4 }}" text-anchor="middle" font-size="10">{{ $chCfg['icon'] }}</text>

        {{-- Label titre --}}
        <text x="{{ $textX }}" y="{{ $cy - 4 }}" font-size="11" font-weight="600"
              fill="{{ $late ? '#DC2626' : 'var(--pd-text)' }}"
              text-anchor="{{ $textAnchor }}">{{ Str::limit($action->title, 35) }}</text>

        {{-- Label date --}}
        <text x="{{ $textX }}" y="{{ $cy + 10 }}" font-size="9"
              fill="{{ $late ? '#DC2626' : 'var(--pd-muted)' }}"
              text-anchor="{{ $textAnchor }}">{{ $dateLabel }}{{ $lateLabel }}</text>

        @endforeach
    </svg>
    </div>{{-- /vScale height wrapper --}}

    </div>{{-- /zoom wrapper --}}
    </div>{{-- /overflow --}}

    @endif
</div>{{-- /x-data --}}

{{-- ── Tableau compact à venir ── --}}
@if($pending->isNotEmpty())
<div class="pd-card" style="margin-bottom:14px;">
    <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--pd-muted);margin-bottom:12px;">Liste — À réaliser</div>
    <table class="pd-table">
        <thead><tr><th>Action</th><th>Canal</th><th>Audience</th><th>Responsable</th><th>Prévu le</th><th>Ressources</th><th>Statut</th>@if($canManage)<th style="width:100px;"></th>@endif</tr></thead>
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
            <td style="font-size:11px;color:var(--pd-muted);">
                @if($action->resources_needed)
                <span title="{{ $action->resources_needed }}" style="cursor:help;">
                    🔧 {{ Str::limit($action->resources_needed, 40) }}
                </span>
                @else
                <span style="opacity:.4;">—</span>
                @endif
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
                <div style="display:flex;gap:4px;">
                    <button type="button" class="btn-sm" style="padding:3px 8px;font-size:10px;"
                        onclick="openEditComm({
                            action: '{{ route('projects.comm_actions.update', [$project, $action]) }}',
                            title: {{ Js::from($action->title) }},
                            target_audience: {{ Js::from($action->target_audience) }},
                            channel: '{{ $action->channel }}',
                            planned_at: '{{ $action->planned_at->toDateString() }}',
                            responsible_id: '{{ $action->responsible_id ?? '' }}',
                            message: {{ Js::from($action->message ?? '') }},
                            resources_needed: {{ Js::from($action->resources_needed ?? '') }}
                        })">✎ Éditer</button>
                    <form method="POST" action="{{ route('projects.comm_actions.destroy', [$project, $action]) }}" onsubmit="return confirm('Supprimer ?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn-sm" style="padding:3px 7px;color:var(--pd-muted);">✕</button>
                    </form>
                </div>
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
    <div class="pd-card" style="margin-top:8px;opacity:.8;">
        <table class="pd-table">
            <thead><tr><th>Action</th><th>Canal</th><th>Réalisée le</th>@if($canManage)<th style="width:130px;"></th>@endif</tr></thead>
            <tbody>
            @foreach($done as $action)
            <tr>
                <td style="text-decoration:line-through;color:var(--pd-muted);">{{ $action->title }}</td>
                <td style="color:var(--pd-muted);">{{ $channels[$action->channel]['label'] ?? $action->channel }}</td>
                <td style="color:var(--pd-muted);">{{ $action->done_at->translatedFormat('d M Y') }}</td>
                @if($canManage)
                <td>
                    <form method="POST" action="{{ route('projects.comm_actions.update', [$project, $action]) }}" style="display:inline;">
                        @csrf @method('PATCH')
                        <input type="hidden" name="done_at" value="">
                        <button type="submit" class="btn-sm" style="font-size:10px;padding:3px 8px;background:#FEF3C7;color:#92400E;border-color:#FCD34D;" title="Remettre cette action dans la liste À réaliser">↩ À faire</button>
                    </form>
                </td>
                @endif
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
        <div class="pd-modal-header pd-modal-header--colored pd-modal-header--navy">
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
                <div class="pd-form-group">
                    <label class="pd-label">Ressources nécessaires</label>
                    <textarea name="resources_needed" class="pd-input" rows="2" placeholder="Ex : Salle du conseil, vidéoprojecteur, impression 50 exemplaires..."></textarea>
                </div>
            </form>
        </div>
        <div class="pd-modal-footer">
            <button type="button" class="pd-btn pd-btn-secondary" onclick="document.getElementById('modal-comm').classList.remove('open')">Annuler</button>
            <button type="submit" form="form-comm" class="pd-btn pd-btn-primary">Enregistrer</button>
        </div>
    </div>
</div>

{{-- ── Modal ÉDITION action comm ── --}}
<div id="modal-comm-edit" class="pd-modal-overlay">
    <div class="pd-modal pd-modal-md">
        <div class="pd-modal-header pd-modal-header--colored pd-modal-header--navy">
            <div><div class="pd-modal-title">Modifier l'action de communication</div></div>
            <button class="pd-modal-close" onclick="document.getElementById('modal-comm-edit').classList.remove('open')">×</button>
        </div>
        <div class="pd-modal-body">
            <form id="form-comm-edit" method="POST">
                @csrf @method('PATCH')
                <div class="pd-form-group">
                    <label class="pd-label pd-label-req">Titre de l'action</label>
                    <input type="text" id="ce-title" name="title" class="pd-input" required>
                </div>
                <div class="pd-form-row-2">
                    <div class="pd-form-group">
                        <label class="pd-label pd-label-req">Audience cible</label>
                        <input type="text" id="ce-audience" name="target_audience" class="pd-input" required>
                    </div>
                    <div class="pd-form-group">
                        <label class="pd-label pd-label-req">Canal</label>
                        <select id="ce-channel" name="channel" class="pd-input" required>
                            @foreach($channels as $val => $cfg)
                            <option value="{{ $val }}">{{ $cfg['icon'] }} {{ $cfg['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="pd-form-row-2">
                    <div class="pd-form-group">
                        <label class="pd-label pd-label-req">Date prévue</label>
                        <input type="date" id="ce-planned-at" name="planned_at" class="pd-input" required>
                    </div>
                    <div class="pd-form-group">
                        <label class="pd-label">Responsable</label>
                        <select id="ce-responsible" name="responsible_id" class="pd-input">
                            <option value="">—</option>
                            @foreach($tenantUsers as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach
                        </select>
                    </div>
                </div>
                <div class="pd-form-group">
                    <label class="pd-label">Message / points clés</label>
                    <textarea id="ce-message" name="message" class="pd-input" rows="3"></textarea>
                </div>
                <div class="pd-form-group">
                    <label class="pd-label">Ressources nécessaires</label>
                    <textarea id="ce-resources" name="resources_needed" class="pd-input" rows="2" placeholder="Ex : Salle du conseil, vidéoprojecteur, impression 50 exemplaires..."></textarea>
                </div>
            </form>
        </div>
        <div class="pd-modal-footer">
            <button type="button" class="pd-btn pd-btn-secondary" onclick="document.getElementById('modal-comm-edit').classList.remove('open')">Annuler</button>
            <button type="submit" form="form-comm-edit" class="pd-btn pd-btn-primary">Enregistrer</button>
        </div>
    </div>
</div>

<script>
window.openEditComm = function(data) {
    var form = document.getElementById('form-comm-edit');
    form.action = data.action;
    document.getElementById('ce-title').value       = data.title;
    document.getElementById('ce-audience').value    = data.target_audience;
    document.getElementById('ce-channel').value     = data.channel;
    document.getElementById('ce-planned-at').value  = data.planned_at;
    document.getElementById('ce-responsible').value = data.responsible_id;
    document.getElementById('ce-message').value     = data.message;
    document.getElementById('ce-resources').value   = data.resources_needed;
    document.getElementById('modal-comm-edit').classList.add('open');
};

function comcomCtrl() {
    return {
        zoom: 1,
        vScale: 1,
        init() {
            const z = localStorage.getItem('comcom_zoom_v1');
            if (z) this.zoom = Math.max(0.4, Math.min(3, parseFloat(z)));
            const v = localStorage.getItem('comcom_vscale_v1');
            if (v) this.vScale = Math.max(0.5, Math.min(4, parseFloat(v)));
            this.$watch('zoom',   val => localStorage.setItem('comcom_zoom_v1',   val));
            this.$watch('vScale', val => localStorage.setItem('comcom_vscale_v1', val));
        },
    };
}
</script>
@endif
