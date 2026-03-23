@extends('layouts.admin')
@section('title', 'Audit — Rétention & Purge')

@section('admin-content')

@include('admin.audit._tabs', ['active' => 'retention'])

@if(session('success'))
<div style="background:#D1FAE5;border:1px solid #6EE7B7;border-radius:10px;padding:12px 16px;margin-bottom:20px;font-size:13px;color:#065F46;display:flex;align-items:center;gap:8px;">
    ✅ {{ session('success') }}
</div>
@endif

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:28px;">

    {{-- Configuration rétention --}}
    <div style="background:var(--pd-surface);border:0.5px solid var(--pd-border);border-radius:12px;padding:24px;">
        <div style="font-size:14px;font-weight:700;color:var(--pd-navy);margin-bottom:4px;">⚙ Configuration de la rétention</div>
        <div style="font-size:12px;color:var(--pd-muted);margin-bottom:20px;">Les entrées plus anciennes que cette durée sont éligibles à la purge.</div>

        <form method="POST" action="{{ route('admin.audit.retention.update') }}">
            @csrf
            @method('PATCH')
            <div style="margin-bottom:16px;">
                <label style="display:block;font-size:12px;font-weight:600;color:var(--pd-text);margin-bottom:8px;">Durée de rétention</label>
                <select name="audit_retention_months"
                        style="width:100%;padding:10px 12px;border:0.5px solid var(--pd-border);border-radius:8px;background:var(--pd-surface);font-size:13px;color:var(--pd-text);">
                    @foreach([3 => '3 mois', 6 => '6 mois', 12 => '12 mois (recommandé)', 24 => '24 mois', 36 => '36 mois'] as $val => $label)
                    <option value="{{ $val }}" {{ $retention == $val ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit"
                    style="padding:10px 20px;background:var(--pd-navy);color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;width:100%;">
                Enregistrer
            </button>
        </form>
    </div>

    {{-- État actuel --}}
    <div style="background:var(--pd-surface);border:0.5px solid var(--pd-border);border-radius:12px;padding:24px;">
        <div style="font-size:14px;font-weight:700;color:var(--pd-navy);margin-bottom:4px;">📊 État actuel</div>
        <div style="font-size:12px;color:var(--pd-muted);margin-bottom:20px;">Vue d'ensemble de la table audit_logs.</div>

        <div style="display:flex;flex-direction:column;gap:12px;">
            <div style="display:flex;justify-content:space-between;padding:10px 14px;background:var(--pd-surface2);border-radius:8px;">
                <span style="font-size:12px;color:var(--pd-muted);">Total entrées</span>
                <span style="font-size:13px;font-weight:700;color:var(--pd-navy);">{{ number_format($totalLogs) }}</span>
            </div>
            <div style="display:flex;justify-content:space-between;padding:10px 14px;background:var(--pd-surface2);border-radius:8px;">
                <span style="font-size:12px;color:var(--pd-muted);">Entrée la plus ancienne</span>
                <span style="font-size:13px;font-weight:600;color:var(--pd-text);">
                    {{ $oldestLog ? \Carbon\Carbon::parse($oldestLog)->translatedFormat('d M Y') : '—' }}
                </span>
            </div>
            <div style="display:flex;justify-content:space-between;padding:10px 14px;background:{{ $toDelete > 0 ? '#FEF3C7' : 'var(--pd-surface2)' }};border-radius:8px;border:0.5px solid {{ $toDelete > 0 ? '#FCD34D' : 'transparent' }};">
                <span style="font-size:12px;color:var(--pd-muted);">Éligibles à la purge</span>
                <span style="font-size:13px;font-weight:700;color:{{ $toDelete > 0 ? '#92400E' : 'var(--pd-muted)' }};">{{ number_format($toDelete) }}</span>
            </div>
            <div style="display:flex;justify-content:space-between;padding:10px 14px;background:var(--pd-surface2);border-radius:8px;">
                <span style="font-size:12px;color:var(--pd-muted);">Date de coupure actuelle</span>
                <span style="font-size:13px;font-weight:600;color:var(--pd-text);">{{ $cutoff->translatedFormat('d M Y') }}</span>
            </div>
        </div>
    </div>
</div>

{{-- Prévisualisation entrées à supprimer --}}
@if($toDelete > 0)
<div style="background:var(--pd-surface);border:0.5px solid #FCD34D;border-radius:12px;padding:24px;margin-bottom:28px;">
    <div style="font-size:14px;font-weight:700;color:#92400E;margin-bottom:16px;">
        🔍 Prévisualisation — {{ number_format($toDelete) }} entrée(s) à supprimer
    </div>
    <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:20px;">
        @foreach($toDeleteByAction as $row)
        <div style="padding:6px 12px;background:#FEF3C7;border:0.5px solid #FCD34D;border-radius:20px;font-size:12px;color:#92400E;">
            {{ $row->action }} <strong>{{ number_format($row->cnt) }}</strong>
        </div>
        @endforeach
    </div>

    {{-- Zone de purge --}}
    <div style="background:#FEE2E2;border:1px solid #FCA5A5;border-radius:10px;padding:20px;">
        <div style="font-size:13px;font-weight:700;color:#991B1B;margin-bottom:8px;">⚠ Zone de purge irréversible</div>
        <div style="font-size:12px;color:#B91C1C;margin-bottom:16px;">
            Cette action supprimera définitivement {{ number_format($toDelete) }} entrée(s) antérieures au {{ $cutoff->format('d/m/Y') }}.
            Cette opération est <strong>irréversible</strong>. Exportez les logs avant si nécessaire.
        </div>
        <form method="POST" action="{{ route('admin.audit.purge') }}"
              onsubmit="return confirm('Confirmer la suppression de {{ $toDelete }} entrées ? Cette action est irréversible.')">
            @csrf
            @method('DELETE')
            <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                <input type="text" name="confirm" placeholder="Tapez PURGER pour confirmer"
                       style="flex:1;min-width:200px;padding:9px 12px;border:1px solid #FCA5A5;border-radius:8px;background:#fff;font-size:13px;font-family:monospace;"
                       autocomplete="off">
                <button type="submit"
                        style="padding:9px 20px;background:#DC2626;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;white-space:nowrap;">
                    🗑 Purger maintenant
                </button>
            </div>
            @error('confirm')
            <div style="margin-top:6px;font-size:12px;color:#DC2626;">{{ $message }}</div>
            @enderror
        </form>
    </div>
</div>
@else
<div style="background:#D1FAE5;border:0.5px solid #6EE7B7;border-radius:12px;padding:20px;text-align:center;color:#065F46;font-size:13px;">
    ✅ Aucune entrée à purger — tous les logs sont dans la fenêtre de rétention de {{ $retention }} mois.
</div>
@endif

@endsection
