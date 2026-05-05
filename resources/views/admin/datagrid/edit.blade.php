@extends('layouts.admin')
@section('title', 'Modifier — ' . $table->label)

@section('admin-content')
<div style="padding:32px 40px;max-width:900px;">

    <div style="margin-bottom:24px;">
        <a href="{{ route('admin.datagrid.index') }}"
           style="font-size:12px;color:var(--pd-muted);text-decoration:none;">
            ← Grilles DataGrid
        </a>
        <h1 style="font-size:20px;font-weight:700;color:var(--pd-text);margin:8px 0 0;">
            {{ $table->label }}
        </h1>
        <div style="font-family:monospace;font-size:12px;color:var(--pd-muted);margin-top:2px;">
            {{ $table->mysql_table }}
        </div>
    </div>

    @if(session('success'))
    <div style="padding:10px 16px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;
                color:#166534;font-size:13px;font-weight:600;margin-bottom:16px;">
        ✓ {{ session('success') }}
    </div>
    @endif

    {{-- Métadonnées --}}
    <div style="background:var(--pd-bg);border:1px solid var(--pd-border);border-radius:10px;padding:20px;margin-bottom:24px;">
        <div style="font-size:13px;font-weight:600;color:var(--pd-text);margin-bottom:14px;">Paramètres de la grille</div>
        <form id="form-table"
              x-data="{}"
              @submit.prevent="
                fetch('{{ route('admin.datagrid.update', $table) }}', {
                    method: 'PATCH',
                    headers: {'Content-Type':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}','Accept':'application/json'},
                    body: JSON.stringify({
                        label: $el.label.value,
                        description: $el.description.value,
                        has_rgpd: $el.has_rgpd.checked,
                    })
                }).then(r => r.json()).then(d => {
                    if (d.success) { $el.querySelector('[data-saved]').style.display = 'inline'; setTimeout(() => $el.querySelector('[data-saved]').style.display = 'none', 2000); }
                })
              ">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px;">
                <div>
                    <label style="font-size:12px;color:var(--pd-muted);display:block;margin-bottom:4px;">Label</label>
                    <input name="label" type="text" value="{{ $table->label }}"
                           style="width:100%;padding:7px 10px;border:1px solid var(--pd-border);
                                  border-radius:7px;font-size:13px;box-sizing:border-box;">
                </div>
                <div>
                    <label style="font-size:12px;color:var(--pd-muted);display:block;margin-bottom:4px;">Description</label>
                    <input name="description" type="text" value="{{ $table->description }}"
                           style="width:100%;padding:7px 10px;border:1px solid var(--pd-border);
                                  border-radius:7px;font-size:13px;box-sizing:border-box;">
                </div>
            </div>
            <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
                <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer;">
                    <input name="has_rgpd" type="checkbox" {{ $table->has_rgpd ? 'checked' : '' }}>
                    Données RGPD sensibles (active le journal d'audit)
                </label>
                <button type="submit"
                        style="padding:7px 16px;background:var(--pd-navy);color:#fff;border:none;
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
          action="{{ route('admin.datagrid.destroy', $table) }}" style="display:none;">
        @csrf @method('DELETE')
    </form>

    {{-- Colonnes --}}
    <div style="background:var(--pd-bg);border:1px solid var(--pd-border);border-radius:10px;padding:20px;">
        <div style="font-size:13px;font-weight:600;color:var(--pd-text);margin-bottom:14px;">
            Colonnes ({{ $columns->count() }})
        </div>

        @if($columns->isEmpty())
        <p style="font-size:13px;color:var(--pd-muted);">Aucune colonne.</p>
        @else
        <div style="border:1px solid var(--pd-border);border-radius:8px;overflow:hidden;">
            <table style="width:100%;border-collapse:collapse;font-size:12px;">
                <thead>
                    <tr style="background:var(--pd-bg2,#f8f9fb);">
                        <th style="padding:8px 12px;text-align:left;font-weight:600;color:var(--pd-muted);border-bottom:1px solid var(--pd-border);">Nom</th>
                        <th style="padding:8px 12px;text-align:left;font-weight:600;color:var(--pd-muted);border-bottom:1px solid var(--pd-border);">Label</th>
                        <th style="padding:8px 12px;text-align:left;font-weight:600;color:var(--pd-muted);border-bottom:1px solid var(--pd-border);">Type</th>
                        <th style="padding:8px 12px;text-align:center;font-weight:600;color:var(--pd-muted);border-bottom:1px solid var(--pd-border);">Requis</th>
                        <th style="padding:8px 12px;text-align:center;font-weight:600;color:var(--pd-muted);border-bottom:1px solid var(--pd-border);">RGPD</th>
                        <th style="padding:8px 12px;border-bottom:1px solid var(--pd-border);"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($columns as $col)
                    <tr style="border-bottom:1px solid var(--pd-border);">
                        <td style="padding:8px 12px;font-family:monospace;color:var(--pd-muted);">{{ $col->name }}</td>
                        <td style="padding:8px 12px;color:var(--pd-text);font-weight:500;">{{ $col->label }}</td>
                        <td style="padding:8px 12px;color:var(--pd-text);">{{ $col->type->value }}</td>
                        <td style="padding:8px 12px;text-align:center;">{{ $col->required ? '✓' : '' }}</td>
                        <td style="padding:8px 12px;text-align:center;">{{ $col->is_rgpd_sensitive ? '✓' : '' }}</td>
                        <td style="padding:8px 12px;text-align:right;white-space:nowrap;">
                            <a href="{{ route('admin.datagrid.columns.edit', [$table, $col]) }}"
                               style="padding:4px 10px;border:1px solid var(--pd-border);border-radius:5px;
                                      font-size:11px;color:var(--pd-text);text-decoration:none;margin-right:4px;">
                                Modifier les champs
                            </a>
                            <form method="POST"
                                  action="{{ route('datagrid.columns.destroy', [$table, $col]) }}"
                                  style="display:inline;"
                                  onsubmit="return confirm('Supprimer la colonne « {{ $col->name }} » et ses données ?')">
                                @csrf @method('DELETE')
                                <button type="submit"
                                        style="padding:4px 10px;border:1px solid #fca5a5;border-radius:5px;
                                               font-size:11px;color:#dc2626;background:#fef2f2;cursor:pointer;">
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
