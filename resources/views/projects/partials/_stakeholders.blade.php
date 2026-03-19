{{-- _stakeholders.blade.php — Carte d'adhésion + tableau éditable --}}
@php
$adhesionCfg   = \App\Models\Tenant\ProjectStakeholder::adhesionConfig();
$influenceLbls = \App\Models\Tenant\ProjectStakeholder::influenceLabels();
$adhOrder  = ['resistant','vigilant','neutre','supporter','champion'];
$inflOrder = ['high','medium','low'];

$matrix = [];
foreach ($inflOrder as $infl) {
    foreach ($adhOrder as $adh) {
        $matrix[$infl][$adh] = $project->stakeholders
            ->where('adhesion', $adh)
            ->where('influence', $infl)
            ->values();
    }
}
@endphp

<div class="section-hdr">
    <div>
        <div class="section-title">Parties prenantes</div>
        <div class="section-sub">{{ $project->stakeholders->count() }} partie{{ $project->stakeholders->count()>1?'s':'' }} prenante{{ $project->stakeholders->count()>1?'s':'' }} · carte d'adhésion au changement</div>
    </div>
    @if($canManage)
    <button class="btn-sm btn-navy" onclick="document.getElementById('modal-sh').classList.add('open')">+ Ajouter</button>
    @endif
</div>

{{-- ── MATRICE 2D ── --}}
<div class="pd-card" style="overflow-x:auto;margin-bottom:14px;padding:0;">
    <table style="width:100%;border-collapse:collapse;table-layout:fixed;">
        <thead>
            <tr>
                <th style="width:72px;padding:10px 8px;border-bottom:1px solid var(--pd-border);border-right:1px solid var(--pd-border);font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--pd-muted);text-align:center;background:var(--pd-bg2);">Influence&nbsp;/ Adhésion</th>
                @foreach($adhOrder as $adh)
                @php $cfg = $adhesionCfg[$adh]; $count = $project->stakeholders->where('adhesion',$adh)->count(); @endphp
                <th style="padding:10px 8px;border-bottom:1px solid var(--pd-border);{{ !$loop->last ? 'border-right:1px solid var(--pd-border);' : '' }}text-align:center;">
                    <span style="display:inline-block;padding:3px 10px;border-radius:20px;font-size:10px;font-weight:700;background:{{ $cfg['bg'] }};color:{{ $cfg['text'] }};">{{ $cfg['label'] }}</span>
                    @if($count)<div style="font-size:9px;color:var(--pd-muted);margin-top:2px;">{{ $count }}</div>@endif
                </th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($inflOrder as $infl)
            <tr>
                <td style="padding:6px 8px;border-right:1px solid var(--pd-border);{{ !$loop->last ? 'border-bottom:1px solid var(--pd-border);' : '' }}text-align:center;background:var(--pd-bg2);vertical-align:middle;">
                    <div style="font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--pd-muted);">{{ $influenceLbls[$infl] }}</div>
                    @if($infl==='high')<div style="font-size:8px;color:var(--pd-muted);opacity:.7;">décisif</div>@endif
                    @if($infl==='low')<div style="font-size:8px;color:var(--pd-muted);opacity:.7;">limité</div>@endif
                </td>
                @foreach($adhOrder as $adh)
                @php
                    $items   = $matrix[$infl][$adh];
                    $cfg     = $adhesionCfg[$adh];
                    $warning = ($infl==='high' && in_array($adh,['resistant','vigilant']));
                    $target  = ($infl==='high' && in_array($adh,['supporter','champion']));
                    $cellBg  = $warning ? '#FFF5F550' : ($target ? '#F0FDF450' : 'transparent');
                @endphp
                <td style="padding:10px 6px;vertical-align:top;min-height:56px;{{ !$loop->last ? 'border-right:1px solid var(--pd-border);' : '' }}{{ !$loop->parent->last ? 'border-bottom:1px solid var(--pd-border);' : '' }}background:{{ $cellBg }};" x-data="{}">
                    @if($items->isEmpty())
                        @if($warning)<div style="text-align:center;padding:10px 0;opacity:.15;font-size:18px;">⚠</div>
                        @elseif($target)<div style="text-align:center;padding:10px 0;opacity:.1;font-size:18px;">★</div>
                        @else<div style="min-height:36px;"></div>@endif
                    @else
                    <div style="display:flex;flex-wrap:wrap;gap:6px;justify-content:center;">
                        @foreach($items as $sh)
                        <div style="position:relative;" x-data="{ open: false }">
                            <div class="sh-avatar"
                                 @mouseenter="open=true" @mouseleave="open=false"
                                 style="cursor:default;background:{{ $cfg['bg'] }};color:{{ $cfg['text'] }};border:2px solid {{ $cfg['text'] }}40;transition:transform .12s;"
                                 :style="open ? 'transform:scale(1.18);z-index:10' : ''">{{ $sh->initials() }}</div>
                            <div x-show="open" x-cloak style="position:absolute;bottom:calc(100% + 8px);left:50%;transform:translateX(-50%);white-space:nowrap;background:var(--pd-surface);border:1px solid var(--pd-border);border-radius:8px;box-shadow:0 6px 20px rgba(0,0,0,.13);padding:9px 13px;z-index:200;min-width:160px;">
                                <div style="font-size:11px;font-weight:700;color:var(--pd-text);margin-bottom:3px;">{{ $sh->displayName() }}</div>
                                <div style="font-size:10px;color:var(--pd-muted);margin-bottom:4px;">{{ $sh->role }}</div>
                                <div style="display:flex;gap:4px;flex-wrap:wrap;">
                                    <span class="pd-badge" style="background:{{ $cfg['bg'] }};color:{{ $cfg['text'] }};">{{ $cfg['label'] }}</span>
                                    <span class="pd-badge" style="background:var(--pd-bg2);color:var(--pd-muted);">{{ $influenceLbls[$sh->influence] }}</span>
                                </div>
                                @if($sh->notes)<div style="font-size:10px;color:var(--pd-muted);margin-top:6px;border-top:1px solid var(--pd-border);padding-top:5px;white-space:normal;max-width:200px;">{{ $sh->notes }}</div>@endif
                                <div style="position:absolute;bottom:-5px;left:50%;width:8px;height:8px;background:var(--pd-surface);border-right:1px solid var(--pd-border);border-bottom:1px solid var(--pd-border);transform:translateX(-50%) rotate(45deg);"></div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                    @endif
                </td>
                @endforeach
            </tr>
            @endforeach
        </tbody>
    </table>
    <div style="display:flex;gap:18px;padding:9px 14px;border-top:1px solid var(--pd-border);background:var(--pd-bg2);flex-wrap:wrap;">
        <div style="display:flex;align-items:center;gap:5px;font-size:10px;color:var(--pd-muted);"><span style="width:10px;height:10px;background:#FFF5F5;border:1px solid #FECACA;border-radius:2px;display:inline-block;"></span>Zone critique</div>
        <div style="display:flex;align-items:center;gap:5px;font-size:10px;color:var(--pd-muted);"><span style="width:10px;height:10px;background:#F0FDF4;border:1px solid #86EFAC;border-radius:2px;display:inline-block;"></span>Zone cible</div>
    </div>
</div>

{{-- ── TABLEAU LISTE (repliable) ── --}}
<details style="margin-bottom:14px;">
    <summary style="cursor:pointer;font-size:12px;color:var(--pd-muted);padding:6px 0;user-select:none;">
        ▸ Détail ({{ $project->stakeholders->count() }} entrée{{ $project->stakeholders->count()>1?'s':'' }})
    </summary>
<div class="pd-card" style="margin-top:8px;">
    @if($project->stakeholders->isEmpty())
    <div style="text-align:center;padding:30px;color:var(--pd-muted);font-size:12px;">Aucune partie prenante renseignée.</div>
    @else
    <table class="pd-table">
        <thead>
            <tr>
                <th>Nom</th>
                <th>Rôle</th>
                <th>Adhésion</th>
                <th>Influence</th>
                <th>Notes</th>
                @if($canManage)<th style="width:80px;"></th>@endif
            </tr>
        </thead>
        <tbody>
        @foreach($project->stakeholders->sortBy(fn($s)=>array_search($s->adhesion,['champion','supporter','neutre','vigilant','resistant'])) as $sh)
        @php $cfg = $adhesionCfg[$sh->adhesion]; @endphp
        <tr>
            <td>
                <div style="display:flex;align-items:center;gap:8px;">
                    <div class="sh-avatar" style="width:26px;height:26px;font-size:9px;background:{{ $cfg['bg'] }};color:{{ $cfg['text'] }};flex-shrink:0;">{{ $sh->initials() }}</div>
                    <span style="font-weight:500;font-size:12px;">{{ $sh->displayName() }}</span>
                </div>
            </td>
            <td style="color:var(--pd-muted);">{{ $sh->role }}</td>
            <td><span class="pd-badge" style="background:{{ $cfg['bg'] }};color:{{ $cfg['text'] }};">{{ $cfg['label'] }}</span></td>
            <td style="color:var(--pd-muted);">{{ $influenceLbls[$sh->influence] }}</td>
            <td style="color:var(--pd-muted);font-size:11px;">{{ Str::limit($sh->notes ?? '—', 60) }}</td>
            @if($canManage)
            <td>
                <div style="display:flex;gap:4px;">
                    <button type="button" class="btn-sm"
                        style="padding:3px 8px;font-size:10px;"
                        onclick="openEditSh({
                            action: '{{ route('projects.stakeholders.update', [$project, $sh]) }}',
                            role: {{ Js::from($sh->role) }},
                            adhesion: '{{ $sh->adhesion }}',
                            influence: '{{ $sh->influence }}',
                            notes: {{ Js::from($sh->notes ?? '') }}
                        })">✎ Éditer</button>
                    <form method="POST" action="{{ route('projects.stakeholders.destroy', [$project, $sh]) }}" onsubmit="return confirm('Supprimer ?')">
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
    @endif
</div>
</details>

{{-- ── Modal AJOUT ── --}}
@if($canManage)
<div id="modal-sh" class="pd-modal-overlay">
    <div class="pd-modal pd-modal-md">
        <div class="pd-modal-header pd-modal-header--colored pd-modal-header--navy">
            <div><div class="pd-modal-title">Nouvelle partie prenante</div><div class="pd-modal-subtitle">Positionnée automatiquement sur la carte</div></div>
            <button class="pd-modal-close" onclick="document.getElementById('modal-sh').classList.remove('open')">×</button>
        </div>
        <div class="pd-modal-body">
            <form id="form-sh" method="POST" action="{{ route('projects.stakeholders.store', $project) }}">
                @csrf
                <div class="pd-form-group">
                    <label class="pd-label">Utilisateur Pladigit (optionnel)</label>
                    <select name="user_id" class="pd-input">
                        <option value="">— Personne externe —</option>
                        @foreach($tenantUsers as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach
                    </select>
                </div>
                <div class="pd-form-group">
                    <label class="pd-label">Nom (si externe)</label>
                    <input type="text" name="name" class="pd-input" placeholder="Ex: Direction générale">
                </div>
                <div class="pd-form-group">
                    <label class="pd-label pd-label-req">Rôle dans le projet</label>
                    <input type="text" name="role" class="pd-input" placeholder="Ex: Commanditaire" required>
                </div>
                <div class="pd-form-row-2">
                    <div class="pd-form-group">
                        <label class="pd-label pd-label-req">Adhésion</label>
                        <select name="adhesion" class="pd-input" required>
                            @foreach($adhesionCfg as $val => $cfg)<option value="{{ $val }}">{{ $cfg['label'] }}</option>@endforeach
                        </select>
                    </div>
                    <div class="pd-form-group">
                        <label class="pd-label pd-label-req">Influence</label>
                        <select name="influence" class="pd-input" required>
                            @foreach($influenceLbls as $val => $lbl)<option value="{{ $val }}">{{ $lbl }}</option>@endforeach
                        </select>
                    </div>
                </div>
                <div class="pd-form-group">
                    <label class="pd-label">Notes</label>
                    <textarea name="notes" class="pd-input" rows="2"></textarea>
                </div>
            </form>
        </div>
        <div class="pd-modal-footer">
            <button type="button" class="pd-btn pd-btn-secondary" onclick="document.getElementById('modal-sh').classList.remove('open')">Annuler</button>
            <button type="submit" form="form-sh" class="pd-btn pd-btn-primary">Enregistrer</button>
        </div>
    </div>
</div>

{{-- ── Modal ÉDITION ── --}}
<div id="modal-sh-edit" class="pd-modal-overlay">
    <div class="pd-modal pd-modal-md">
        <div class="pd-modal-header pd-modal-header--colored pd-modal-header--navy">
            <div><div class="pd-modal-title">Modifier la partie prenante</div></div>
            <button class="pd-modal-close" onclick="document.getElementById('modal-sh-edit').classList.remove('open')">×</button>
        </div>
        <div class="pd-modal-body">
            <form id="form-sh-edit" method="POST">
                @csrf @method('PATCH')
                <div class="pd-form-group">
                    <label class="pd-label pd-label-req">Rôle dans le projet</label>
                    <input type="text" id="sh-edit-role" name="role" class="pd-input" required>
                </div>
                <div class="pd-form-row-2">
                    <div class="pd-form-group">
                        <label class="pd-label pd-label-req">Adhésion</label>
                        <select id="sh-edit-adhesion" name="adhesion" class="pd-input" required>
                            @foreach($adhesionCfg as $val => $cfg)<option value="{{ $val }}">{{ $cfg['label'] }}</option>@endforeach
                        </select>
                    </div>
                    <div class="pd-form-group">
                        <label class="pd-label pd-label-req">Influence</label>
                        <select id="sh-edit-influence" name="influence" class="pd-input" required>
                            @foreach($influenceLbls as $val => $lbl)<option value="{{ $val }}">{{ $lbl }}</option>@endforeach
                        </select>
                    </div>
                </div>
                <div class="pd-form-group">
                    <label class="pd-label">Notes</label>
                    <textarea id="sh-edit-notes" name="notes" class="pd-input" rows="3"></textarea>
                </div>
            </form>
        </div>
        <div class="pd-modal-footer">
            <button type="button" class="pd-btn pd-btn-secondary" onclick="document.getElementById('modal-sh-edit').classList.remove('open')">Annuler</button>
            <button type="submit" form="form-sh-edit" class="pd-btn pd-btn-primary">Enregistrer</button>
        </div>
    </div>
</div>

<script>
window.openEditSh = function(data) {
    var form = document.getElementById('form-sh-edit');
    form.action = data.action;
    document.getElementById('sh-edit-role').value      = data.role;
    document.getElementById('sh-edit-adhesion').value  = data.adhesion;
    document.getElementById('sh-edit-influence').value = data.influence;
    document.getElementById('sh-edit-notes').value     = data.notes;
    document.getElementById('modal-sh-edit').classList.add('open');
};
</script>
@endif
