{{-- _finances.blade.php --}}
@php
$bs        = $budgetSummary;
$fmt       = fn($v) => number_format($v, 0, ',', ' ').' €';
$totalPct  = $bs['total']['planned']  > 0 ? round($bs['total']['committed']  / $bs['total']['planned']  * 100) : 0;
$investPct = $bs['invest']['planned'] > 0 ? round($bs['invest']['committed'] / $bs['invest']['planned'] * 100) : 0;
$fonctPct  = $bs['fonct']['planned']  > 0 ? round($bs['fonct']['committed']  / $bs['fonct']['planned']  * 100) : 0;

$pieInvest = $bs['invest']['planned'];
$pieFonct  = $bs['fonct']['planned'];
$pieTotal  = $pieInvest + $pieFonct;

$piePath = $piePath2 = '';
if ($pieTotal > 0 && $pieInvest > 0 && $pieFonct > 0) {
    $angle    = ($pieInvest / $pieTotal) * 360;
    $rad      = deg2rad($angle - 90);
    $x        = round(60 + 50 * cos($rad), 2);
    $y        = round(60 + 50 * sin($rad), 2);
    $large    = $angle > 180 ? 1 : 0;
    $large2   = (360 - $angle) > 180 ? 1 : 0;
    $piePath  = "M60,60 L60,10 A50,50 0 {$large},1 {$x},{$y} Z";
    $piePath2 = "M60,60 L{$x},{$y} A50,50 0 {$large2},1 60,10 Z";
}
@endphp

{{-- En-tête --}}
<div class="section-hdr">
    <div>
        <div class="section-title">Finances</div>
        <div class="section-sub">Investissement &amp; fonctionnement</div>
    </div>
    @if($canManage)
    <button type="button" class="pd-btn pd-btn-primary pd-btn-sm"
            onclick="document.getElementById('modal-budget-create').classList.add('open')">
        + Ligne budgétaire
    </button>
    @endif
</div>

{{-- Alertes --}}
@foreach($budgetAlerts as $alert)
<div style="display:flex;gap:10px;padding:10px 12px;border-radius:8px;font-size:12px;margin-bottom:8px;background:#FEF3C7;border-left:3px solid #D97706;">
    <span style="font-weight:700;color:#92400E;flex-shrink:0;">⚠ Dépassement</span>
    <span style="color:#78350F;">{{ $alert->label }} — prévu {{ $fmt($alert->amount_planned) }}, engagé {{ $fmt($alert->amount_committed) }} (+{{ $fmt($alert->variance()) }})</span>
</div>
@endforeach

{{-- ── RANGÉE 1 : 3 KPI ── --}}
<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:10px;">
    <div class="stat-card" style="border-top:3px solid #1E3A5F;">
        <div class="stat-lbl">Investissement</div>
        <div class="stat-val" style="font-size:15px;">{{ $fmt($bs['invest']['planned']) }}</div>
        <div class="stat-sub">{{ $fmt($bs['invest']['committed']) }} eng. ({{ $investPct }}%)</div>
        <div class="bbar-wrap"><div class="bbar-fill" style="width:{{ min($investPct,100) }}%;background:#1E3A5F;"></div></div>
    </div>
    <div class="stat-card" style="border-top:3px solid #7C3AED;">
        <div class="stat-lbl">Fonctionnement</div>
        <div class="stat-val" style="font-size:15px;">{{ $fmt($bs['fonct']['planned']) }}</div>
        <div class="stat-sub">{{ $fmt($bs['fonct']['committed']) }} eng. ({{ $fonctPct }}%)</div>
        <div class="bbar-wrap"><div class="bbar-fill" style="width:{{ min($fonctPct,100) }}%;background:#7C3AED;"></div></div>
    </div>
    <div class="stat-card" style="border-top:3px solid {{ $totalPct>100?'#E24B4A':'#16A34A' }};">
        <div class="stat-lbl">Total engagé</div>
        <div class="stat-val" style="font-size:15px;color:{{ $totalPct>100?'var(--pd-danger)':'var(--pd-navy)' }};">{{ $totalPct }}%</div>
        <div class="stat-sub">{{ $fmt($bs['total']['committed']) }} / {{ $fmt($bs['total']['planned']) }}</div>
        <div class="bbar-wrap"><div class="bbar-fill" style="width:{{ min($totalPct,100) }}%;background:{{ $totalPct>100?'#E24B4A':'#16A34A' }};"></div></div>
    </div>
</div>

{{-- ── RANGÉE 2 : Donut + Histogramme pleine hauteur ── --}}
@if($pieTotal > 0 || $project->budgets->isNotEmpty())
<div class="pd-card" style="padding:16px;margin-bottom:10px;">
    <div style="display:grid;grid-template-columns:1fr 2fr;gap:20px;align-items:stretch;min-height:200px;">

        {{-- Donut centré, occupe toute la hauteur --}}
        <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;gap:12px;">
            @if($pieTotal > 0)
            <svg viewBox="0 0 120 120" style="width:100%;max-width:180px;height:auto;">
                @if($piePath && $piePath2)
                    <path d="{{ $piePath }}"  fill="#1E3A5F" opacity="0.85"/>
                    <path d="{{ $piePath2 }}" fill="#7C3AED" opacity="0.85"/>
                @elseif($pieInvest > 0)
                    <circle cx="60" cy="60" r="50" fill="#1E3A5F" opacity="0.85"/>
                @else
                    <circle cx="60" cy="60" r="50" fill="#7C3AED" opacity="0.85"/>
                @endif
                <circle cx="60" cy="60" r="28" fill="var(--pd-surface)"/>
                <text x="60" y="56" text-anchor="middle" font-size="14" font-weight="700" fill="var(--pd-text)">{{ $totalPct }}%</text>
                <text x="60" y="70" text-anchor="middle" font-size="9" fill="var(--pd-muted)">engagé</text>
            </svg>
            <div style="display:flex;gap:14px;font-size:11px;color:var(--pd-muted);">
                <span style="display:flex;align-items:center;gap:4px;">
                    <span style="width:10px;height:10px;border-radius:2px;background:#1E3A5F;opacity:.85;display:inline-block;"></span>
                    Invest. {{ round($pieInvest/$pieTotal*100) }}%
                </span>
                <span style="display:flex;align-items:center;gap:4px;">
                    <span style="width:10px;height:10px;border-radius:2px;background:#7C3AED;opacity:.85;display:inline-block;"></span>
                    Fonct. {{ round($pieFonct/$pieTotal*100) }}%
                </span>
            </div>
            @else
            <div style="color:var(--pd-muted);font-size:11px;">Aucune donnée</div>
            @endif
        </div>

        {{-- Histogramme qui remplit toute la hauteur dynamiquement --}}
        @if($project->budgets->isNotEmpty())
        @php
            $allB  = $project->budgets->sortBy('label');
            $n     = $allB->count();
            $maxA  = $allB->max(fn($b) => max($b->amount_planned, $b->amount_committed, $b->amount_paid));
            // Barres adaptées : plus il y a de lignes, plus les barres sont fines
            $bH    = max(8, min(20, (int)(180 / max(1, $n * 3.5))));
            $bGap  = max(2, (int)($bH * 0.25));
            $rowH  = $bH * 3 + $bGap * 2 + max(10, $bH);
            $svgH  = $n * $rowH;
            $lblW  = 130;
            $bMaxW = 280;
            $svgW  = $lblW + $bMaxW + 90;
            $fs    = max(8, min(11, $bH));
        @endphp
        <div style="display:flex;flex-direction:column;justify-content:center;height:100%;">
            <div style="font-size:10px;font-weight:600;color:var(--pd-muted);text-transform:uppercase;letter-spacing:.04em;margin-bottom:8px;">Prévu · Engagé · Mandaté par ligne</div>
            <svg viewBox="0 0 {{ $svgW }} {{ $svgH }}" width="100%" height="100%" preserveAspectRatio="xMinYMid meet" style="max-height:220px;">
                @foreach($allB as $i => $b)
                @php
                    $y0 = $i * $rowH;
                    $pW = $maxA > 0 ? max(3, round(($b->amount_planned   / $maxA) * $bMaxW)) : 3;
                    $cW = $maxA > 0 ? round(($b->amount_committed / $maxA) * $bMaxW) : 0;
                    $mW = $maxA > 0 ? round(($b->amount_paid      / $maxA) * $bMaxW) : 0;
                    $lbl = mb_strlen($b->label) > 20 ? mb_substr($b->label,0,18).'…' : $b->label;
                @endphp
                <text x="{{ $lblW - 6 }}" y="{{ $y0 + $bH }}" font-size="{{ $fs }}" fill="var(--pd-text)" text-anchor="end">{{ $lbl }}</text>
                <rect x="{{ $lblW }}" y="{{ $y0 }}"                   width="{{ $pW }}" height="{{ $bH }}" rx="2" fill="#1E3A5F" opacity="0.8"/>
                <rect x="{{ $lblW }}" y="{{ $y0 + $bH + $bGap }}"     width="{{ max(0,$cW) }}" height="{{ $bH }}" rx="2" fill="{{ $b->variance()>0?'#E24B4A':'#7C3AED' }}" opacity="0.8"/>
                <rect x="{{ $lblW }}" y="{{ $y0 + $bH*2 + $bGap*2 }}" width="{{ max(0,$mW) }}" height="{{ $bH }}" rx="2" fill="#16A34A" opacity="0.8"/>
                @if($b->amount_planned > 0)
                <text x="{{ $lblW + $pW + 4 }}" y="{{ $y0 + $bH - 1 }}" font-size="{{ max(7,$fs-2) }}" fill="var(--pd-muted)">{{ $fmt($b->amount_planned) }}</text>
                @endif
                @endforeach
            </svg>
            <div style="display:flex;gap:12px;margin-top:6px;font-size:10px;color:var(--pd-muted);">
                <span style="display:flex;align-items:center;gap:3px;"><span style="width:10px;height:5px;background:#1E3A5F;opacity:.8;display:inline-block;border-radius:1px;"></span>Prévu</span>
                <span style="display:flex;align-items:center;gap:3px;"><span style="width:10px;height:5px;background:#7C3AED;opacity:.8;display:inline-block;border-radius:1px;"></span>Engagé</span>
                <span style="display:flex;align-items:center;gap:3px;"><span style="width:10px;height:5px;background:#16A34A;opacity:.8;display:inline-block;border-radius:1px;"></span>Mandaté</span>
            </div>
        </div>
        @endif

    </div>
</div>
@endif

<div class="pd-card">
    <div class="pd-label" style="font-size:11px;margin-bottom:10px;">Détail par ligne budgétaire</div>
    @if($project->budgets->isEmpty())
    <div style="text-align:center;padding:24px;color:var(--pd-muted);font-size:12px;">
        Aucune ligne budgétaire. Utilisez le bouton + pour commencer.
    </div>
    @else
    <table class="pd-table">
        <thead>
            <tr>
                <th>Libellé</th><th>Type</th><th>Année</th>
                <th style="text-align:right;">Prévu</th>
                <th style="text-align:right;">Engagé</th>
                <th style="text-align:right;">Mandaté</th>
                <th>Taux</th>
                @if($canManage)<th style="width:56px;"></th>@endif
            </tr>
        </thead>
        <tbody>
        @foreach($project->budgets->groupBy('type') as $type => $lines)
        <tr>
            <td colspan="{{ $canManage?8:7 }}" style="background:var(--pd-surface2);font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--pd-muted);padding:5px 10px;">
                {{ \App\Models\Tenant\ProjectBudget::typeLabels()[$type] }}
            </td>
        </tr>
        @foreach($lines as $b)
        @php $bpct = $b->committedRate(); @endphp
        <tr>
            <td style="font-weight:500;">
                {{ $b->label }}
                @if($b->cofinancer)
                <span style="font-size:10px;color:var(--pd-muted);display:block;">{{ $b->cofinancer }}{{ $b->cofinancing_rate ? ' ('.$b->cofinancing_rate.'%)' : '' }}</span>
                @endif
            </td>
            <td><span class="pd-badge" style="background:{{ $type==='invest'?'#EEF2FF':'#F3EEFF' }};color:{{ $type==='invest'?'#3730A3':'#5B21B6' }};">{{ $type==='invest'?'Inv.':'Fct.' }}</span></td>
            <td>{{ $b->year }}</td>
            <td style="text-align:right;font-family:monospace;font-size:11px;">{{ $fmt($b->amount_planned) }}</td>
            <td style="text-align:right;font-family:monospace;font-size:11px;color:{{ $b->variance()>0?'var(--pd-danger)':'inherit' }};">{{ $fmt($b->amount_committed) }}</td>
            <td style="text-align:right;font-family:monospace;font-size:11px;">{{ $fmt($b->amount_paid) }}</td>
            <td style="min-width:70px;">
                <div style="display:flex;align-items:center;gap:4px;">
                    <div class="bbar-wrap" style="flex:1;"><div class="bbar-fill" style="width:{{ min($bpct,100) }}%;background:{{ $bpct>100?'#E24B4A':'#1E3A5F' }};"></div></div>
                    <span style="font-size:10px;min-width:26px;text-align:right;">{{ $bpct }}%</span>
                </div>
            </td>
            @if($canManage)
            <td style="white-space:nowrap;">
                <button type="button" class="pd-btn pd-btn-xs" style="color:var(--pd-primary);" title="Modifier"
                        onclick="openBudgetEdit({{ $b->id }},@js($b->label),'{{ $b->type }}',{{ $b->year }},{{ $b->amount_planned }},{{ $b->amount_committed }},{{ $b->amount_paid }},@js($b->cofinancer ?? ''),{{ $b->cofinancing_rate ?? 'null' }},@js($b->notes ?? ''))">✏</button>
                <form method="POST" action="{{ route('projects.budgets.destroy', [$project, $b]) }}" style="display:inline;"
                      onsubmit="return confirm('Supprimer « {{ addslashes($b->label) }} » ?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="pd-btn pd-btn-xs" style="color:var(--pd-danger);" title="Supprimer">✕</button>
                </form>
            </td>
            @endif
        </tr>
        @endforeach
        @endforeach
        </tbody>
    </table>
    @endif
</div>

{{-- ════════ MODALE CRÉER ════════ --}}
@if($canManage)
<div id="modal-budget-create" class="pd-modal-overlay" onclick="if(event.target===this)this.classList.remove('open')">
    <div class="pd-modal pd-modal-md">
        <div class="pd-modal-header pd-modal-header--colored pd-modal-header--navy">
            <div>
                <div class="pd-modal-title">Nouvelle ligne budgétaire</div>
                <div class="pd-modal-subtitle">Investissement ou fonctionnement</div>
            </div>
            <button type="button" class="pd-modal-close" onclick="document.getElementById('modal-budget-create').classList.remove('open')">×</button>
        </div>
        <form method="POST" action="{{ route('projects.budgets.store', $project) }}">
            @csrf
            <div class="pd-modal-body">
                <div class="pd-form-row pd-form-row-2">
                    <div class="pd-form-group" style="margin-bottom:0;">
                        <label class="pd-label pd-label-req">Type</label>
                        <select name="type" class="pd-input" required>
                            <option value="invest">Investissement</option>
                            <option value="fonct">Fonctionnement</option>
                        </select>
                    </div>
                    <div class="pd-form-group" style="margin-bottom:0;">
                        <label class="pd-label pd-label-req">Année</label>
                        <input type="number" name="year" class="pd-input" value="{{ now()->year }}" min="2000" max="2099" required>
                    </div>
                </div>
                <div class="pd-form-group">
                    <label class="pd-label pd-label-req">Libellé</label>
                    <input type="text" name="label" class="pd-input" placeholder="Ex : Prestations externes…" required>
                </div>
                <div class="pd-form-row pd-form-row-3">
                    <div class="pd-form-group" style="margin-bottom:0;">
                        <label class="pd-label pd-label-req">Prévu (€)</label>
                        <input type="number" name="amount_planned" class="pd-input" min="0" step="0.01" placeholder="0.00" required>
                    </div>
                    <div class="pd-form-group" style="margin-bottom:0;">
                        <label class="pd-label">Engagé (€)</label>
                        <input type="number" name="amount_committed" class="pd-input" min="0" step="0.01" value="0">
                    </div>
                    <div class="pd-form-group" style="margin-bottom:0;">
                        <label class="pd-label">Mandaté (€)</label>
                        <input type="number" name="amount_paid" class="pd-input" min="0" step="0.01" value="0">
                    </div>
                </div>
                <div class="pd-form-row pd-form-row-2">
                    <div class="pd-form-group" style="margin-bottom:0;">
                        <label class="pd-label">Co-financement</label>
                        <input type="text" name="cofinancer" class="pd-input" placeholder="DETR, Région, FEDER…">
                    </div>
                    <div class="pd-form-group" style="margin-bottom:0;">
                        <label class="pd-label">Taux co-fin. (%)</label>
                        <input type="number" name="cofinancing_rate" class="pd-input" min="0" max="100" step="0.1" placeholder="0">
                    </div>
                </div>
                <div class="pd-form-group" style="margin-bottom:0;">
                    <label class="pd-label">Notes</label>
                    <textarea name="notes" class="pd-input" rows="2"></textarea>
                </div>
            </div>
            <div class="pd-modal-footer">
                <button type="button" class="pd-btn pd-btn-secondary pd-btn-sm" onclick="document.getElementById('modal-budget-create').classList.remove('open')">Annuler</button>
                <button type="submit" class="pd-btn pd-btn-primary pd-btn-sm">Enregistrer</button>
            </div>
        </form>
    </div>
</div>

{{-- ════════ MODALE MODIFIER ════════ --}}
<div id="modal-budget-edit" class="pd-modal-overlay" onclick="if(event.target===this)this.classList.remove('open')">
    <div class="pd-modal pd-modal-md">
        <div class="pd-modal-header pd-modal-header--colored pd-modal-header--navy">
            <div>
                <div class="pd-modal-title">Modifier la ligne budgétaire</div>
                <div class="pd-modal-subtitle" id="edit-modal-subtitle">—</div>
            </div>
            <button type="button" class="pd-modal-close" onclick="document.getElementById('modal-budget-edit').classList.remove('open')">×</button>
        </div>
        <form id="form-budget-edit" method="POST" action="">
            @csrf @method('PATCH')
            <div class="pd-modal-body">
                <div class="pd-form-row pd-form-row-2">
                    <div class="pd-form-group" style="margin-bottom:0;">
                        <label class="pd-label pd-label-req">Type</label>
                        <select name="type" id="edit-type" class="pd-input" required>
                            <option value="invest">Investissement</option>
                            <option value="fonct">Fonctionnement</option>
                        </select>
                    </div>
                    <div class="pd-form-group" style="margin-bottom:0;">
                        <label class="pd-label pd-label-req">Année</label>
                        <input type="number" name="year" id="edit-year" class="pd-input" min="2000" max="2099" required>
                    </div>
                </div>
                <div class="pd-form-group">
                    <label class="pd-label pd-label-req">Libellé</label>
                    <input type="text" name="label" id="edit-label" class="pd-input" required>
                </div>
                <div class="pd-form-row pd-form-row-3">
                    <div class="pd-form-group" style="margin-bottom:0;">
                        <label class="pd-label pd-label-req">Prévu (€)</label>
                        <input type="number" name="amount_planned" id="edit-planned" class="pd-input" min="0" step="0.01" required>
                    </div>
                    <div class="pd-form-group" style="margin-bottom:0;">
                        <label class="pd-label">Engagé (€)</label>
                        <input type="number" name="amount_committed" id="edit-committed" class="pd-input" min="0" step="0.01">
                    </div>
                    <div class="pd-form-group" style="margin-bottom:0;">
                        <label class="pd-label">Mandaté (€)</label>
                        <input type="number" name="amount_paid" id="edit-paid" class="pd-input" min="0" step="0.01">
                    </div>
                </div>
                <div class="pd-form-row pd-form-row-2">
                    <div class="pd-form-group" style="margin-bottom:0;">
                        <label class="pd-label">Co-financement</label>
                        <input type="text" name="cofinancer" id="edit-cofinancer" class="pd-input" placeholder="DETR, Région, FEDER…">
                    </div>
                    <div class="pd-form-group" style="margin-bottom:0;">
                        <label class="pd-label">Taux co-fin. (%)</label>
                        <input type="number" name="cofinancing_rate" id="edit-cofinancing-rate" class="pd-input" min="0" max="100" step="0.1">
                    </div>
                </div>
                <div class="pd-form-group" style="margin-bottom:0;">
                    <label class="pd-label">Notes</label>
                    <textarea name="notes" id="edit-notes" class="pd-input" rows="2"></textarea>
                </div>
            </div>
            <div class="pd-modal-footer">
                <button type="button" class="pd-btn pd-btn-secondary pd-btn-sm" onclick="document.getElementById('modal-budget-edit').classList.remove('open')">Annuler</button>
                <button type="submit" class="pd-btn pd-btn-primary pd-btn-sm">Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<script>
function openBudgetEdit(id, label, type, year, planned, committed, paid, cofinancer, cofinancingRate, notes) {
    var baseUrl = '{{ rtrim(route('projects.budgets.update', [$project, 0]), '0') }}' + id;
    document.getElementById('form-budget-edit').action             = baseUrl;
    document.getElementById('edit-modal-subtitle').textContent     = label;
    document.getElementById('edit-label').value                    = label;
    document.getElementById('edit-type').value                     = type;
    document.getElementById('edit-year').value                     = year;
    document.getElementById('edit-planned').value                  = planned;
    document.getElementById('edit-committed').value                = committed;
    document.getElementById('edit-paid').value                     = paid;
    document.getElementById('edit-cofinancer').value               = cofinancer || '';
    document.getElementById('edit-cofinancing-rate').value         = cofinancingRate !== null ? cofinancingRate : '';
    document.getElementById('edit-notes').value                    = notes || '';
    document.getElementById('modal-budget-edit').classList.add('open');
}
</script>
@endif
