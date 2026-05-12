@extends('layouts.super-admin')
@section('title', 'Grille — ' . $table->label)

@section('content')
<div style="margin-bottom:20px;">
    <a href="{{ route('super-admin.datagrids.index') }}"
       style="font-size:12px;color:var(--pd-muted);text-decoration:none;">
        ← Toutes les grilles
    </a>
    <div style="display:flex;align-items:baseline;gap:14px;margin-top:8px;">
        <h1 style="font-family:'Sora',sans-serif;font-size:20px;font-weight:700;color:var(--pd-text);margin:0;">
            {{ $table->label }}
        </h1>
        <span style="font-family:monospace;font-size:12px;color:var(--pd-muted);">{{ $org->name }}</span>
    </div>
    <div style="font-family:monospace;font-size:12px;color:var(--pd-muted);margin-top:2px;">
        {{ $table->mysql_table }}
    </div>
</div>

@if(session('success'))
<div style="padding:10px 16px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;
            color:#166534;font-size:13px;margin-bottom:18px;">
    {{ session('success') }}
</div>
@endif

{{-- Métadonnées --}}
<div style="background:var(--pd-surface);border:1px solid var(--pd-border);border-radius:12px;padding:20px;margin-bottom:20px;">
    <div style="font-size:13px;font-weight:600;color:var(--pd-text);margin-bottom:14px;">Paramètres</div>
    <form id="form-table"
          onsubmit="
            event.preventDefault();
            var f = this;
            fetch('{{ route('super-admin.datagrids.update', [$org, $table->id]) }}', {
                method: 'PATCH',
                headers: {'Content-Type':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}','Accept':'application/json'},
                body: JSON.stringify({label: f.label.value, description: f.description.value, has_rgpd: f.has_rgpd.checked})
            }).then(r => r.json()).then(d => {
                if (d.success) { var s = f.querySelector('[data-saved]'); s.style.display='inline'; setTimeout(() => s.style.display='none', 2000); }
            });
          ">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px;">
            <div>
                <label style="font-size:12px;color:var(--pd-muted);display:block;margin-bottom:4px;">Label</label>
                <input name="label" type="text" value="{{ $table->label }}"
                       style="width:100%;padding:7px 10px;border:1px solid var(--pd-border);border-radius:7px;font-size:13px;box-sizing:border-box;">
            </div>
            <div>
                <label style="font-size:12px;color:var(--pd-muted);display:block;margin-bottom:4px;">Description</label>
                <input name="description" type="text" value="{{ $table->description }}"
                       style="width:100%;padding:7px 10px;border:1px solid var(--pd-border);border-radius:7px;font-size:13px;box-sizing:border-box;">
            </div>
        </div>
        <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
            <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer;">
                <input name="has_rgpd" type="checkbox" {{ $table->has_rgpd ? 'checked' : '' }}>
                Données RGPD (active le journal d'audit)
            </label>
            <button type="submit"
                    style="padding:7px 16px;background:var(--sa-primary,#1e3a5f);color:#fff;border:none;
                           border-radius:7px;font-size:13px;font-weight:600;cursor:pointer;">
                Enregistrer
            </button>
            <span data-saved style="display:none;font-size:12px;color:#16a34a;font-weight:600;">✓ Sauvegardé</span>
            <button type="button"
                    onclick="showDeleteModal('{{ $table->mysql_table }}', 'form-delete-grid')"
                    style="margin-left:auto;padding:7px 14px;border:1px solid #fca5a5;border-radius:7px;
                           font-size:13px;font-weight:600;color:#dc2626;background:#fef2f2;cursor:pointer;">
                Supprimer cette grille
            </button>
        </div>
    </form>
</div>

<form id="form-delete-grid" method="POST"
      action="{{ route('super-admin.datagrids.destroy', [$org, $table->id]) }}" style="display:none;">
    @csrf @method('DELETE')
</form>

{{-- Colonnes --}}
<div style="background:var(--pd-surface);border:1px solid var(--pd-border);border-radius:12px;padding:20px;">
    <div style="font-size:13px;font-weight:600;color:var(--pd-text);margin-bottom:14px;">
        Colonnes ({{ $columns->count() }})
    </div>

    @if($columns->isEmpty())
    <p style="font-size:13px;color:var(--pd-muted);">Aucune colonne.</p>
    @else
    <div style="border:1px solid var(--pd-border);border-radius:8px;overflow:hidden;">
        <table style="width:100%;border-collapse:collapse;font-size:12px;">
            <thead>
                <tr style="background:var(--pd-bg,#f8f9fb);">
                    <th style="padding:9px 14px;text-align:left;font-weight:600;color:var(--pd-muted);border-bottom:1px solid var(--pd-border);font-size:11px;text-transform:uppercase;letter-spacing:.4px;">#</th>
                    <th style="padding:9px 14px;text-align:left;font-weight:600;color:var(--pd-muted);border-bottom:1px solid var(--pd-border);font-size:11px;text-transform:uppercase;letter-spacing:.4px;">Nom technique</th>
                    <th style="padding:9px 14px;text-align:left;font-weight:600;color:var(--pd-muted);border-bottom:1px solid var(--pd-border);font-size:11px;text-transform:uppercase;letter-spacing:.4px;">Label</th>
                    <th style="padding:9px 14px;text-align:left;font-weight:600;color:var(--pd-muted);border-bottom:1px solid var(--pd-border);font-size:11px;text-transform:uppercase;letter-spacing:.4px;">Type</th>
                    <th style="padding:9px 14px;text-align:center;font-weight:600;color:var(--pd-muted);border-bottom:1px solid var(--pd-border);font-size:11px;text-transform:uppercase;letter-spacing:.4px;">Onglet</th>
                    <th style="padding:9px 14px;text-align:center;font-weight:600;color:var(--pd-muted);border-bottom:1px solid var(--pd-border);font-size:11px;text-transform:uppercase;letter-spacing:.4px;">Requis</th>
                    <th style="padding:9px 14px;text-align:center;font-weight:600;color:var(--pd-muted);border-bottom:1px solid var(--pd-border);font-size:11px;text-transform:uppercase;letter-spacing:.4px;">Visible</th>
                    <th style="padding:9px 14px;border-bottom:1px solid var(--pd-border);"></th>
                </tr>
            </thead>
            <tbody>
                @foreach($columns as $col)
                @php $tab = $col->tab ?? 'main'; @endphp
                <tr style="border-bottom:1px solid var(--pd-border);transition:background .12s;"
                    onmouseover="this.style.background='var(--pd-bg)'"
                    onmouseout="this.style.background=''">
                    <td style="padding:9px 14px;color:var(--pd-muted);font-size:11px;">{{ $col->sort_order }}</td>
                    <td style="padding:9px 14px;font-family:monospace;color:var(--sa-primary,#1e3a5f);font-weight:600;font-size:12px;">{{ $col->name }}</td>
                    <td style="padding:9px 14px;color:var(--pd-text);font-weight:500;">{{ $col->label }}</td>
                    <td style="padding:9px 14px;">
                        <span style="display:inline-flex;align-items:center;padding:2px 8px;border-radius:20px;
                                     font-size:11px;font-weight:500;background:var(--pd-bg2);color:var(--pd-muted);">
                            {{ $col->type->label() }}
                        </span>
                    </td>
                    <td style="padding:9px 14px;text-align:center;">
                        @if($tab === 'extra')
                            <span style="display:inline-flex;align-items:center;padding:2px 8px;border-radius:20px;
                                         font-size:11px;font-weight:600;background:#ede9fe;color:#7c3aed;">
                                Complémentaires
                            </span>
                        @else
                            <span style="display:inline-flex;align-items:center;padding:2px 8px;border-radius:20px;
                                         font-size:11px;font-weight:500;background:#f0f9ff;color:#0369a1;">
                                Données
                            </span>
                        @endif
                    </td>
                    <td style="padding:9px 14px;text-align:center;color:{{ $col->required ? '#16a34a' : 'var(--pd-muted)' }};font-size:13px;">
                        {{ $col->required ? '✓' : '—' }}
                    </td>
                    <td style="padding:9px 14px;text-align:center;color:{{ $col->visible_by_default ? '#16a34a' : 'var(--pd-muted)' }};font-size:13px;">
                        {{ $col->visible_by_default ? '✓' : '—' }}
                    </td>
                    <td style="padding:9px 14px;text-align:right;white-space:nowrap;">
                        <a href="{{ route('super-admin.datagrids.columns.edit', [$org, $table->id, $col->id]) }}"
                           style="padding:5px 12px;background:var(--sa-primary,#1e3a5f);color:#fff;border-radius:6px;
                                  font-size:11px;font-weight:600;text-decoration:none;margin-right:4px;">
                            ✎ Modifier
                        </a>
                        <form method="POST"
                              action="{{ route('super-admin.datagrids.columns.destroy', [$org, $table->id, $col->id]) }}"
                              style="display:inline;"
                              onsubmit="return confirm('Supprimer la colonne « {{ $col->name }} » ?')">
                            @csrf @method('DELETE')
                            <button type="submit"
                                    style="padding:5px 10px;border:1px solid #fca5a5;border-radius:6px;
                                           font-size:11px;font-weight:500;color:#dc2626;background:#fef2f2;cursor:pointer;">
                                Supprimer
                            </button>
                        </form>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>

{{-- Modale suppression grille --}}
<div id="delete-modal"
     style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:9999;
            align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:12px;padding:28px;max-width:420px;width:90%;box-shadow:0 20px 60px rgba(0,0,0,.2);">
        <h3 style="font-size:15px;font-weight:700;color:var(--pd-text);margin:0 0 10px;">Supprimer la grille</h3>
        <p style="font-size:13px;color:var(--pd-muted);margin:0 0 16px;line-height:1.5;">
            Cette action est irréversible. Tapez le nom de la table pour confirmer :
        </p>
        <code id="modal-target-name"
              style="display:block;font-size:13px;font-weight:700;color:var(--pd-text);
                     background:#f8f9fb;border:1px solid var(--pd-border);border-radius:6px;
                     padding:6px 10px;margin-bottom:12px;"></code>
        <input id="modal-confirm-input" type="text" placeholder="Saisir le nom de la table…"
               style="width:100%;padding:8px 10px;border:1px solid var(--pd-border);border-radius:7px;
                      font-size:13px;box-sizing:border-box;margin-bottom:16px;font-family:monospace;">
        <div style="display:flex;gap:10px;justify-content:flex-end;">
            <button type="button" onclick="closeDeleteModal()"
                    style="padding:7px 16px;border:1px solid var(--pd-border);border-radius:7px;
                           font-size:13px;color:var(--pd-text);background:#fff;cursor:pointer;">
                Annuler
            </button>
            <button type="button" onclick="submitDeleteModal()"
                    style="padding:7px 16px;background:#dc2626;color:#fff;border:none;
                           border-radius:7px;font-size:13px;font-weight:600;cursor:pointer;">
                Supprimer définitivement
            </button>
        </div>
    </div>
</div>

<script>
(function () {
    var _formId = null;

    window.showDeleteModal = function (tableName, formId) {
        _formId = formId;
        document.getElementById('modal-target-name').textContent = tableName;
        document.getElementById('modal-confirm-input').value = '';
        document.getElementById('modal-confirm-input').style.borderColor = '';
        document.getElementById('delete-modal').style.display = 'flex';
        document.getElementById('modal-confirm-input').focus();
    };

    window.closeDeleteModal = function () {
        document.getElementById('delete-modal').style.display = 'none';
    };

    window.submitDeleteModal = function () {
        var input    = document.getElementById('modal-confirm-input');
        var expected = document.getElementById('modal-target-name').textContent;
        if (input.value === expected) {
            document.getElementById(_formId).submit();
        } else {
            input.style.borderColor = '#dc2626';
        }
    };

    document.getElementById('modal-confirm-input').addEventListener('keydown', function (e) {
        if (e.key === 'Enter') window.submitDeleteModal();
        if (e.key === 'Escape') window.closeDeleteModal();
    });

    document.getElementById('delete-modal').addEventListener('click', function (e) {
        if (e.target === this) window.closeDeleteModal();
    });
}());
</script>
@endsection
