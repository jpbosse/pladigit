{{-- _risques.blade.php — Freins & risques — Matrice quadrants probabilité × impact --}}
@php
$catLabels  = \App\Models\Tenant\ProjectRisk::categoryLabels();
$statLabels = \App\Models\Tenant\ProjectRisk::statusLabels();
$critColors = \App\Models\Tenant\ProjectRisk::criticalityColors();

$active = $project->risks->whereNotIn('status',['closed']);
$closed = $project->risks->where('status','closed');

// Axes de la matrice
$probAxis   = ['low' => 'Faible', 'medium' => 'Moyenne', 'high' => 'Forte'];
$impactAxis = ['critical' => 'Critique', 'high' => 'Fort', 'medium' => 'Moyen', 'low' => 'Faible'];

// Couleur de fond cellule selon score = prob×impact
$probWeight   = ['low'=>1,'medium'=>2,'high'=>3];
$impactWeight = ['low'=>1,'medium'=>2,'high'=>3,'critical'=>4];
function cellCriticality(string $prob, string $impact): string {
    $pw = ['low'=>1,'medium'=>2,'high'=>3];
    $iw = ['low'=>1,'medium'=>2,'high'=>3,'critical'=>4];
    $s  = ($pw[$prob] ?? 1) * ($iw[$impact] ?? 1);
    return match(true) { $s >= 9 => 'critique', $s >= 6 => 'élevé', $s >= 3 => 'modéré', default => 'faible' };
}
$cellBgMap = [
    'critique' => '#FEE2E2',
    'élevé'    => '#FEF3C7',
    'modéré'   => '#DBEAFE',
    'faible'   => '#F1F5F9',
];
@endphp

<div class="section-hdr">
    <div>
        <div class="section-title">Freins &amp; risques</div>
        <div class="section-sub">{{ $active->count() }} actif{{ $active->count()>1?'s':'' }} · {{ $criticalRisksCount }} critique{{ $criticalRisksCount>1?'s':'' }}</div>
    </div>
    @if($canManage)
    <button class="btn-sm btn-navy" onclick="document.getElementById('modal-risk').classList.add('open')">+ Risque</button>
    @endif
</div>

{{-- ── MATRICE QUADRANTS probabilité × impact ── --}}
<div class="pd-card" style="overflow-x:auto;margin-bottom:14px;padding:0;">
    <table style="width:100%;border-collapse:collapse;table-layout:fixed;">
        <thead>
            <tr>
                <th style="width:76px;padding:10px 8px;border-bottom:1px solid var(--pd-border);border-right:1px solid var(--pd-border);font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--pd-muted);text-align:center;background:var(--pd-bg2);">
                    Impact ↕<br>Prob. →
                </th>
                @foreach($probAxis as $probVal => $probLbl)
                <th style="padding:10px 8px;border-bottom:1px solid var(--pd-border);{{ !$loop->last ? 'border-right:1px solid var(--pd-border);' : '' }}text-align:center;background:var(--pd-bg2);">
                    <div style="font-size:10px;font-weight:700;color:var(--pd-muted);">{{ $probLbl }}</div>
                    <div style="font-size:9px;color:var(--pd-muted);opacity:.7;">probabilité</div>
                </th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($impactAxis as $impactVal => $impactLbl)
            <tr>
                <td style="padding:8px;border-right:1px solid var(--pd-border);{{ !$loop->last ? 'border-bottom:1px solid var(--pd-border);' : '' }}text-align:center;background:var(--pd-bg2);vertical-align:middle;">
                    <div style="font-size:10px;font-weight:700;color:var(--pd-muted);">{{ $impactLbl }}</div>
                    <div style="font-size:9px;color:var(--pd-muted);opacity:.7;">impact</div>
                </td>
                @foreach($probAxis as $probVal => $probLbl)
                @php
                    $crit      = cellCriticality($probVal, $impactVal);
                    $cellColor = $critColors[$crit];
                    $cellItems = $active->where('probability',$probVal)->where('impact',$impactVal)->values();
                @endphp
                <td style="padding:8px;vertical-align:top;min-height:52px;{{ !$loop->last ? 'border-right:1px solid var(--pd-border);' : '' }}{{ !$loop->parent->last ? 'border-bottom:1px solid var(--pd-border);' : '' }}background:{{ $cellBgMap[$crit] }}30;"
                    x-data="{}">
                    {{-- Indicateur criticité de la cellule --}}
                    <div style="font-size:8px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:{{ $cellColor['text'] }};opacity:.5;margin-bottom:4px;">{{ $crit }}</div>

                    @if($cellItems->isEmpty())
                    <div style="min-height:28px;"></div>
                    @else
                    <div style="display:flex;flex-direction:column;gap:4px;">
                        @foreach($cellItems as $risk)
                        <div x-data="{ open: false }" style="position:relative;">
                            <div @mouseenter="open=true" @mouseleave="open=false"
                                 style="cursor:default;padding:4px 7px;border-radius:6px;background:{{ $cellColor['bg'] }};border:1px solid {{ $cellColor['text'] }}30;transition:box-shadow .12s;"
                                 :style="open ? 'box-shadow:0 3px 10px rgba(0,0,0,.12);z-index:10;' : ''">
                                <div style="font-size:10px;font-weight:600;color:{{ $cellColor['text'] }};line-height:1.3;">{{ Str::limit($risk->title, 30) }}</div>
                                <div style="font-size:9px;color:{{ $cellColor['text'] }};opacity:.7;margin-top:1px;">{{ $catLabels[$risk->category] }}</div>
                            </div>
                            {{-- Tooltip --}}
                            <div x-show="open" x-cloak
                                 style="position:absolute;bottom:calc(100% + 6px);left:0;background:var(--pd-surface);border:1px solid var(--pd-border);border-radius:8px;box-shadow:0 6px 20px rgba(0,0,0,.14);padding:10px 13px;z-index:200;min-width:200px;max-width:260px;">
                                <div style="font-size:11px;font-weight:700;color:var(--pd-text);margin-bottom:4px;">{{ $risk->title }}</div>
                                <div style="display:flex;gap:4px;flex-wrap:wrap;margin-bottom:5px;">
                                    <span class="pd-badge" style="background:{{ $cellColor['bg'] }};color:{{ $cellColor['text'] }};">{{ ucfirst($crit) }} ({{ $risk->score() }})</span>
                                    <span class="pd-badge" style="background:var(--pd-bg2);color:var(--pd-muted);">{{ $statLabels[$risk->status] }}</span>
                                </div>
                                @if($risk->mitigation_plan)
                                <div style="font-size:10px;color:var(--pd-muted);border-top:1px solid var(--pd-border);padding-top:5px;margin-top:4px;">
                                    <span style="font-weight:600;">Mitigation :</span> {{ Str::limit($risk->mitigation_plan, 100) }}
                                </div>
                                @endif
                                @if($risk->owner)
                                <div style="font-size:10px;color:var(--pd-muted);margin-top:4px;">Responsable : {{ $risk->owner->name }}</div>
                                @endif
                                <div style="position:absolute;bottom:-5px;left:16px;width:8px;height:8px;background:var(--pd-surface);border-right:1px solid var(--pd-border);border-bottom:1px solid var(--pd-border);transform:rotate(45deg);"></div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                    @endif

                    {{-- Actions rapides --}}
                    @if($canManage && $cellItems->isNotEmpty())
                    <div style="margin-top:4px;display:flex;flex-direction:column;gap:2px;">
                        @foreach($cellItems as $risk)
                        @if($risk->status !== 'closed')
                        <form method="POST" action="{{ route('projects.risks.update', [$project, $risk]) }}" style="display:inline;">
                            @csrf @method('PATCH')
                            <input type="hidden" name="status" value="closed">
                            <button type="submit" style="font-size:9px;padding:1px 5px;border-radius:4px;background:#D1FAE5;color:#065F46;border:1px solid #86EFAC;cursor:pointer;margin-top:2px;" title="Clôturer {{ $risk->title }}">✓ Clôturer</button>
                        </form>
                        @endif
                        @endforeach
                    </div>
                    @endif
                </td>
                @endforeach
            </tr>
            @endforeach
        </tbody>
    </table>

    {{-- Légende --}}
    <div style="display:flex;gap:14px;padding:9px 14px;border-top:1px solid var(--pd-border);background:var(--pd-bg2);flex-wrap:wrap;">
        @foreach(['critique'=>'Critique (≥9)','élevé'=>'Élevé (≥6)','modéré'=>'Modéré (≥3)','faible'=>'Faible (<3)'] as $c=>$lbl)
        @php $cc = $critColors[$c]; @endphp
        <div style="display:flex;align-items:center;gap:5px;font-size:10px;color:var(--pd-muted);">
            <span style="width:10px;height:10px;background:{{ $cc['bg'] }};border-radius:2px;display:inline-block;"></span>
            {{ $lbl }}
        </div>
        @endforeach
    </div>
</div>

{{-- ── Clôturés repliables ── --}}
@if($closed->count())
<details style="margin-top:4px;">
    <summary style="cursor:pointer;font-size:12px;color:var(--pd-muted);padding:6px 0;user-select:none;">
        ▸ {{ $closed->count() }} risque{{ $closed->count()>1?'s':'' }} clôturé{{ $closed->count()>1?'s':'' }}
    </summary>
    <div class="pd-card" style="margin-top:8px;opacity:.65;">
        @foreach($closed as $risk)
        <div style="font-size:12px;text-decoration:line-through;color:var(--pd-muted);padding:5px 0;border-bottom:0.5px solid var(--pd-border);">{{ $risk->title }}</div>
        @endforeach
    </div>
</details>
@endif

{{-- ── Modal ── --}}
@if($canManage)
<div id="modal-risk" class="pd-modal-overlay">
    <div class="pd-modal pd-modal-md">
        <div class="pd-modal-header">
            <div>
                <div class="pd-modal-title">Nouveau risque / frein</div>
                <div class="pd-modal-subtitle">Positionné automatiquement dans la matrice</div>
            </div>
            <button class="pd-modal-close" onclick="document.getElementById('modal-risk').classList.remove('open')">×</button>
        </div>
        <div class="pd-modal-body">
            <form id="form-risk" method="POST" action="{{ route('projects.risks.store', $project) }}">
                @csrf
                <div class="pd-form-group">
                    <label class="pd-label pd-label-req">Titre du risque</label>
                    <input type="text" name="title" class="pd-input" required>
                </div>
                <div class="pd-form-group">
                    <label class="pd-label">Description</label>
                    <textarea name="description" class="pd-input" rows="2"></textarea>
                </div>
                <div class="pd-form-row-3">
                    <div class="pd-form-group">
                        <label class="pd-label pd-label-req">Catégorie</label>
                        <select name="category" class="pd-input" required>
                            @foreach($catLabels as $val => $lbl)
                            <option value="{{ $val }}">{{ $lbl }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="pd-form-group">
                        <label class="pd-label pd-label-req">Probabilité</label>
                        <select name="probability" class="pd-input" required>
                            <option value="low">Faible</option>
                            <option value="medium" selected>Moyenne</option>
                            <option value="high">Forte</option>
                        </select>
                    </div>
                    <div class="pd-form-group">
                        <label class="pd-label pd-label-req">Impact</label>
                        <select name="impact" class="pd-input" required>
                            <option value="low">Faible</option>
                            <option value="medium" selected>Moyen</option>
                            <option value="high">Fort</option>
                            <option value="critical">Critique</option>
                        </select>
                    </div>
                </div>
                {{-- Prévisualisation score --}}
                <div id="risk-score-preview" style="padding:8px 12px;border-radius:8px;background:var(--pd-bg2);font-size:11px;color:var(--pd-muted);text-align:center;margin-bottom:4px;">
                    Score : <strong id="risk-score-val">2</strong> — <span id="risk-score-label">Modéré</span>
                </div>
                <div class="pd-form-group">
                    <label class="pd-label">Plan de mitigation</label>
                    <textarea name="mitigation_plan" class="pd-input" rows="2"></textarea>
                </div>
                <div class="pd-form-group">
                    <label class="pd-label">Responsable du suivi</label>
                    <select name="owner_id" class="pd-input">
                        <option value="">—</option>
                        @foreach($tenantUsers as $u)
                        <option value="{{ $u->id }}">{{ $u->name }}</option>
                        @endforeach
                    </select>
                </div>
            </form>
        </div>
        <div class="pd-modal-footer">
            <button type="button" class="pd-btn pd-btn-secondary" onclick="document.getElementById('modal-risk').classList.remove('open')">Annuler</button>
            <button type="submit" form="form-risk" class="pd-btn pd-btn-primary">Enregistrer</button>
        </div>
    </div>
</div>

<script>
(function() {
    const probW = { low:1, medium:2, high:3 };
    const impW  = { low:1, medium:2, high:3, critical:4 };
    const critLabels = { critique:'Critique', 'élevé':'Élevé', 'modéré':'Modéré', faible:'Faible' };
    const critBg = { critique:'#FEE2E2', 'élevé':'#FEF3C7', 'modéré':'#DBEAFE', faible:'#F1F5F9' };
    const critText = { critique:'#991B1B', 'élevé':'#92400E', 'modéré':'#1E40AF', faible:'#475569' };

    function criticality(s) {
        if(s>=9) return 'critique';
        if(s>=6) return 'élevé';
        if(s>=3) return 'modéré';
        return 'faible';
    }

    function updateScore() {
        const form = document.getElementById('form-risk');
        if (!form) return;
        const prob = form.querySelector('[name=probability]')?.value || 'medium';
        const imp  = form.querySelector('[name=impact]')?.value || 'medium';
        const score = (probW[prob]||1) * (impW[imp]||1);
        const crit  = criticality(score);
        const preview = document.getElementById('risk-score-preview');
        document.getElementById('risk-score-val').textContent   = score;
        document.getElementById('risk-score-label').textContent = critLabels[crit];
        if(preview) {
            preview.style.background = critBg[crit];
            preview.style.color      = critText[crit];
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('form-risk');
        if (!form) return;
        ['probability','impact'].forEach(function(n) {
            form.querySelector('[name='+n+']')?.addEventListener('change', updateScore);
        });
        updateScore();
    });
})();
</script>
@endif
