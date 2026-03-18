{{-- _finances.blade.php --}}
@php
$bs       = $budgetSummary;
$fmt      = fn($v) => number_format($v, 0, ',', ' ').' €';
$totalPct  = $bs['total']['planned']  > 0 ? round($bs['total']['committed']  / $bs['total']['planned']  * 100) : 0;
$investPct = $bs['invest']['planned'] > 0 ? round($bs['invest']['committed'] / $bs['invest']['planned'] * 100) : 0;
$fonctPct  = $bs['fonct']['planned']  > 0 ? round($bs['fonct']['committed']  / $bs['fonct']['planned']  * 100) : 0;
@endphp

<div class="section-hdr">
    <div>
        <div class="section-title">Finances</div>
        <div class="section-sub">Investissement &amp; fonctionnement</div>
    </div>
    @if($canManage)
    <button type="button" class="pd-btn pd-btn-primary pd-btn-sm"
            onclick="document.getElementById('modal-budget').classList.add('open')">
        + Ligne budgétaire
    </button>
    @endif
</div>

@foreach($budgetAlerts as $alert)
<div style="display:flex;gap:10px;padding:10px 12px;border-radius:8px;font-size:12px;margin-bottom:8px;background:#FEF3C7;border-left:3px solid #D97706;">
    <span style="font-weight:700;color:#92400E;flex-shrink:0;">Dépassement</span>
    <span style="color:#78350F;">{{ $alert->label }} — prévu {{ $fmt($alert->amount_planned) }}, engagé {{ $fmt($alert->amount_committed) }} (+{{ $fmt($alert->variance()) }})</span>
</div>
@endforeach

<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:16px;">
    <div class="stat-card" style="border-top:3px solid #1E3A5F;">
        <div class="stat-lbl">Investissement prévu</div>
        <div class="stat-val" style="font-size:17px;">{{ $fmt($bs['invest']['planned']) }}</div>
        <div class="stat-sub">{{ $fmt($bs['invest']['committed']) }} engagés ({{ $investPct }}%)</div>
        <div class="bbar-wrap"><div class="bbar-fill" style="width:{{ min($investPct,100) }}%;background:#1E3A5F;"></div></div>
    </div>
    <div class="stat-card" style="border-top:3px solid #7C3AED;">
        <div class="stat-lbl">Fonctionnement prévu</div>
        <div class="stat-val" style="font-size:17px;">{{ $fmt($bs['fonct']['planned']) }}</div>
        <div class="stat-sub">{{ $fmt($bs['fonct']['committed']) }} engagés ({{ $fonctPct }}%)</div>
        <div class="bbar-wrap"><div class="bbar-fill" style="width:{{ min($fonctPct,100) }}%;background:#7C3AED;"></div></div>
    </div>
    <div class="stat-card" style="border-top:3px solid {{ $totalPct>100?'#E24B4A':'#16A34A' }};">
        <div class="stat-lbl">Total engagé</div>
        <div class="stat-val" style="font-size:17px;color:{{ $totalPct>100?'var(--pd-danger)':'var(--pd-navy)' }};">{{ $totalPct }}%</div>
        <div class="stat-sub">{{ $fmt($bs['total']['committed']) }} / {{ $fmt($bs['total']['planned']) }}</div>
        <div class="bbar-wrap"><div class="bbar-fill" style="width:{{ min($totalPct,100) }}%;background:{{ $totalPct>100?'#E24B4A':'#16A34A' }};"></div></div>
    </div>
</div>

<div class="pd-card">
    <div class="pd-label" style="margin-bottom:12px;">Détail par ligne budgétaire</div>
    @if($project->budgets->isEmpty())
    <div style="text-align:center;padding:30px;color:var(--pd-muted);font-size:12px;">
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
                @if($canManage)<th></th>@endif
            </tr>
        </thead>
        <tbody>
        @foreach($project->budgets->groupBy('type') as $type => $lines)
        <tr>
            <td colspan="{{ $canManage?8:7 }}" style="background:var(--pd-surface2);font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--pd-muted);padding:6px 10px;">
                {{ \App\Models\Tenant\ProjectBudget::typeLabels()[$type] }}
            </td>
        </tr>
        @foreach($lines as $b)
        @php $bpct = $b->committedRate(); @endphp
        <tr>
            <td style="font-weight:500;">{{ $b->label }}</td>
            <td><span class="pd-badge" style="background:{{ $type==='invest'?'#EEF2FF':'#F3EEFF' }};color:{{ $type==='invest'?'#3730A3':'#5B21B6' }};">{{ $type==='invest'?'Invest.':'Fonct.' }}</span></td>
            <td>{{ $b->year }}</td>
            <td style="text-align:right;font-family:monospace;font-size:11px;">{{ $fmt($b->amount_planned) }}</td>
            <td style="text-align:right;font-family:monospace;font-size:11px;color:{{ $b->variance()>0?'var(--pd-danger)':'inherit' }};">{{ $fmt($b->amount_committed) }}</td>
            <td style="text-align:right;font-family:monospace;font-size:11px;">{{ $fmt($b->amount_paid) }}</td>
            <td style="min-width:80px;">
                <div style="display:flex;align-items:center;gap:5px;">
                    <div class="bbar-wrap" style="flex:1;"><div class="bbar-fill" style="width:{{ min($bpct,100) }}%;background:{{ $bpct>100?'#E24B4A':'#1E3A5F' }};"></div></div>
                    <span style="font-size:10px;min-width:28px;text-align:right;">{{ $bpct }}%</span>
                </div>
            </td>
            @if($canManage)
            <td>
                <form method="POST" action="{{ route('projects.budgets.destroy',[$project,$b]) }}" onsubmit="return confirm('Supprimer cette ligne ?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="pd-btn pd-btn-xs" style="color:var(--pd-muted);">✕</button>
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

@if($canManage)
<div id="modal-budget" class="pd-modal-overlay" onclick="if(event.target===this)this.classList.remove('open')">
    <div class="pd-modal pd-modal-md">
        <div class="pd-modal-header">
            <div>
                <div class="pd-modal-title">Nouvelle ligne budgétaire</div>
                <div class="pd-modal-subtitle">Investissement ou fonctionnement</div>
            </div>
            <button type="button" class="pd-modal-close"
                    onclick="document.getElementById('modal-budget').classList.remove('open')">×</button>
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
                    <input type="text" name="label" class="pd-input" placeholder="Ex: Prestations externes…" required>
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
                <div class="pd-form-group">
                    <label class="pd-label">Co-financement</label>
                    <input type="text" name="cofinancer" class="pd-input" placeholder="DETR, Région, FEDER…">
                </div>
                <div class="pd-form-group" style="margin-bottom:0;">
                    <label class="pd-label">Notes</label>
                    <textarea name="notes" class="pd-input" rows="2"></textarea>
                </div>
            </div>
            <div class="pd-modal-footer">
                <button type="button" class="pd-btn pd-btn-secondary pd-btn-sm"
                        onclick="document.getElementById('modal-budget').classList.remove('open')">Annuler</button>
                <button type="submit" class="pd-btn pd-btn-primary pd-btn-sm">Enregistrer</button>
            </div>
        </form>
    </div>
</div>
@endif
