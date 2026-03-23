@extends('layouts.admin')
@section('title', 'Audit — Export')

@section('admin-content')

@include('admin.audit._tabs', ['active' => 'export'])

<div style="max-width:600px;">
    <div style="background:var(--pd-surface);border:0.5px solid var(--pd-border);border-radius:12px;padding:28px;">
        <div style="font-size:14px;font-weight:700;color:var(--pd-navy);margin-bottom:4px;">📥 Exporter les logs d'audit</div>
        <div style="font-size:12px;color:var(--pd-muted);margin-bottom:24px;">
            Sélectionnez une période et un format. L'export inclut toutes les colonnes (ID, date, utilisateur, action, détails, IP, user agent).
        </div>

        <form method="GET" action="{{ route('admin.audit.export') }}">

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:16px;">
                <div>
                    <label style="display:block;font-size:12px;font-weight:600;color:var(--pd-text);margin-bottom:6px;">Du</label>
                    <input type="date" name="from"
                           style="width:100%;padding:9px 12px;border:0.5px solid var(--pd-border);border-radius:8px;background:var(--pd-surface);font-size:13px;color:var(--pd-text);">
                </div>
                <div>
                    <label style="display:block;font-size:12px;font-weight:600;color:var(--pd-text);margin-bottom:6px;">Au</label>
                    <input type="date" name="to" value="{{ now()->format('Y-m-d') }}"
                           style="width:100%;padding:9px 12px;border:0.5px solid var(--pd-border);border-radius:8px;background:var(--pd-surface);font-size:13px;color:var(--pd-text);">
                </div>
            </div>

            <div style="margin-bottom:16px;">
                <label style="display:block;font-size:12px;font-weight:600;color:var(--pd-text);margin-bottom:6px;">Action (optionnel)</label>
                <select name="action"
                        style="width:100%;padding:9px 12px;border:0.5px solid var(--pd-border);border-radius:8px;background:var(--pd-surface);font-size:13px;color:var(--pd-text);">
                    <option value="">Toutes les actions</option>
                    @foreach(\App\Models\Tenant\AuditLog::on('tenant')->selectRaw('action, COUNT(*) as cnt')->groupBy('action')->orderByDesc('cnt')->get() as $row)
                    <option value="{{ $row->action }}">{{ $row->action }} ({{ $row->cnt }})</option>
                    @endforeach
                </select>
            </div>

            <div style="margin-bottom:24px;">
                <label style="display:block;font-size:12px;font-weight:600;color:var(--pd-text);margin-bottom:8px;">Format</label>
                <div style="display:flex;gap:10px;">
                    <label style="display:flex;align-items:center;gap:8px;padding:10px 16px;border:0.5px solid var(--pd-border);border-radius:8px;cursor:pointer;flex:1;background:var(--pd-surface2);">
                        <input type="radio" name="format" value="csv" checked style="accent-color:var(--pd-navy);">
                        <div>
                            <div style="font-size:13px;font-weight:600;color:var(--pd-text);">CSV</div>
                            <div style="font-size:11px;color:var(--pd-muted);">Excel compatible (BOM UTF-8)</div>
                        </div>
                    </label>
                    <label style="display:flex;align-items:center;gap:8px;padding:10px 16px;border:0.5px solid var(--pd-border);border-radius:8px;cursor:pointer;flex:1;background:var(--pd-surface2);">
                        <input type="radio" name="format" value="json" style="accent-color:var(--pd-navy);">
                        <div>
                            <div style="font-size:13px;font-weight:600;color:var(--pd-text);">JSON</div>
                            <div style="font-size:11px;color:var(--pd-muted);">Intégration / archivage</div>
                        </div>
                    </label>
                </div>
            </div>

            <button type="submit"
                    style="width:100%;padding:12px;background:var(--pd-navy);color:#fff;border:none;border-radius:8px;font-size:14px;font-weight:700;cursor:pointer;">
                ⬇ Télécharger l'export
            </button>
        </form>
    </div>

    <div style="margin-top:16px;padding:14px 16px;background:var(--pd-surface2);border-radius:10px;font-size:12px;color:var(--pd-muted);">
        💡 Conseil : exportez les données avant une purge pour les archiver conformément à votre politique RGPD.
        La rétention légale recommandée pour les logs de sécurité est de 12 mois minimum.
    </div>
</div>

@endsection
