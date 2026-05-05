@extends('layouts.admin')
@section('title', 'Grilles DataGrid')

@section('admin-content')
<div style="padding:32px 40px;">

    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;">
        <div>
            <h1 style="font-size:20px;font-weight:700;color:var(--pd-text);margin:0 0 4px;">Grilles DataGrid</h1>
            <p style="font-size:13px;color:var(--pd-muted);margin:0;">
                {{ $tables->count() }} grille{{ $tables->count() !== 1 ? 's' : '' }}
            </p>
        </div>
        <a href="{{ route('datagrid.import') }}"
           style="padding:8px 16px;background:var(--pd-navy);color:#fff;border-radius:8px;
                  font-size:13px;font-weight:600;text-decoration:none;">
            + Importer une grille
        </a>
    </div>

    @if(session('success'))
    <div style="padding:12px 16px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;
                color:#166534;font-size:13px;margin-bottom:20px;">
        {{ session('success') }}
    </div>
    @endif

    @if($tables->isEmpty())
    <div style="text-align:center;padding:64px 24px;color:var(--pd-muted);">
        <div style="font-size:40px;margin-bottom:12px;">📇</div>
        <p style="font-size:14px;font-weight:600;color:var(--pd-text);margin:0 0 6px;">Aucune grille configurée</p>
        <p style="font-size:13px;margin:0;">Importez un fichier Excel pour créer la première grille.</p>
    </div>
    @else
    <div style="border:1px solid var(--pd-border);border-radius:10px;overflow:hidden;">
        <table style="width:100%;border-collapse:collapse;font-size:13px;">
            <thead>
                <tr style="background:var(--pd-bg2,#f8f9fb);">
                    <th style="padding:10px 14px;text-align:left;font-weight:600;color:var(--pd-muted);
                                border-bottom:1px solid var(--pd-border);">Label</th>
                    <th style="padding:10px 14px;text-align:left;font-weight:600;color:var(--pd-muted);
                                border-bottom:1px solid var(--pd-border);">Table MySQL</th>
                    <th style="padding:10px 14px;text-align:center;font-weight:600;color:var(--pd-muted);
                                border-bottom:1px solid var(--pd-border);">Colonnes</th>
                    <th style="padding:10px 14px;text-align:center;font-weight:600;color:var(--pd-muted);
                                border-bottom:1px solid var(--pd-border);">RGPD</th>
                    <th style="padding:10px 14px;border-bottom:1px solid var(--pd-border);"></th>
                </tr>
            </thead>
            <tbody>
                @foreach($tables as $table)
                <tr style="border-bottom:1px solid var(--pd-border);">
                    <td style="padding:10px 14px;font-weight:600;color:var(--pd-text);">
                        {{ $table->label }}
                    </td>
                    <td style="padding:10px 14px;font-family:monospace;font-size:12px;color:var(--pd-muted);">
                        {{ $table->mysql_table }}
                    </td>
                    <td style="padding:10px 14px;text-align:center;color:var(--pd-text);">
                        {{ $table->columns_count }}
                    </td>
                    <td style="padding:10px 14px;text-align:center;">
                        @if($table->has_rgpd)
                        <span style="display:inline-block;padding:2px 8px;background:#fef3c7;color:#92400e;
                                     border-radius:20px;font-size:11px;font-weight:600;">RGPD</span>
                        @else
                        <span style="color:var(--pd-muted);font-size:12px;">—</span>
                        @endif
                    </td>
                    <td style="padding:10px 14px;text-align:right;white-space:nowrap;">
                        <a href="{{ route('admin.datagrid.edit', $table) }}"
                           style="padding:5px 12px;border:1px solid var(--pd-border);border-radius:6px;
                                  font-size:12px;font-weight:500;color:var(--pd-text);text-decoration:none;
                                  margin-right:6px;">
                            Modifier
                        </a>
                        <button type="button"
                                onclick="openDeleteModal('{{ $table->mysql_table }}', '{{ route('admin.datagrid.destroy', $table) }}')"
                                style="padding:5px 12px;border:1px solid #fca5a5;border-radius:6px;
                                       font-size:12px;font-weight:500;color:#dc2626;background:#fef2f2;
                                       cursor:pointer;">
                            Supprimer
                        </button>
                        <form id="form-del-{{ $table->id }}" method="POST"
                              action="{{ route('admin.datagrid.destroy', $table) }}" style="display:none;">
                            @csrf @method('DELETE')
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
     style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1000;">
    <div style="background:var(--pd-bg);border-radius:12px;padding:32px;max-width:480px;
                margin:10vh auto;box-shadow:0 20px 60px rgba(0,0,0,.2);">
        <h3 style="font-size:15px;font-weight:700;color:var(--pd-text);margin:0 0 10px;">Supprimer la grille</h3>
        <p style="font-size:13px;color:var(--pd-muted);margin:0 0 4px;line-height:1.5;">
            Cette action est irréversible. Saisissez le nom technique pour confirmer :
        </p>
        <code id="modal-table-name"
              style="display:block;margin:12px 0;padding:8px;
                     background:var(--pd-border);border-radius:6px;font-size:13px;font-weight:700;"></code>
        <input id="modal-confirm-input" type="text" oninput="checkDeleteInput()"
               placeholder="Nom technique…"
               style="width:100%;padding:8px 10px;border:1px solid var(--pd-border);border-radius:7px;
                      font-size:13px;box-sizing:border-box;font-family:monospace;">
        <div style="margin-top:16px;display:flex;gap:12px;">
            <button id="modal-confirm-btn" onclick="submitDelete()" disabled
                    style="padding:8px 18px;background:#dc2626;color:#fff;border:none;border-radius:7px;
                           font-size:13px;font-weight:600;cursor:pointer;opacity:.4;">
                Supprimer définitivement
            </button>
            <button onclick="closeDeleteModal()"
                    style="padding:8px 16px;border:1px solid var(--pd-border);border-radius:7px;
                           font-size:13px;color:var(--pd-text);background:#fff;cursor:pointer;">
                Annuler
            </button>
        </div>
    </div>
</div>

<script>
(function () {
    var _deleteUrl  = null;
    var _tableName  = null;

    window.openDeleteModal = function (name, url) {
        _tableName  = name;
        _deleteUrl  = url;
        document.getElementById('modal-table-name').textContent = name;
        document.getElementById('modal-confirm-input').value = '';
        var btn = document.getElementById('modal-confirm-btn');
        btn.disabled = true;
        btn.style.opacity = '.4';
        document.getElementById('delete-modal').style.display = 'block';
        document.getElementById('modal-confirm-input').focus();
    };

    window.closeDeleteModal = function () {
        document.getElementById('delete-modal').style.display = 'none';
    };

    window.checkDeleteInput = function () {
        var ok  = document.getElementById('modal-confirm-input').value === _tableName;
        var btn = document.getElementById('modal-confirm-btn');
        btn.disabled = !ok;
        btn.style.opacity = ok ? '1' : '.4';
    };

    window.submitDelete = function () {
        var form = document.createElement('form');
        form.method = 'POST';
        form.action = _deleteUrl;
        form.style.display = 'none';

        var csrf = document.createElement('input');
        csrf.type = 'hidden';
        csrf.name = '_token';
        csrf.value = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        var method = document.createElement('input');
        method.type = 'hidden';
        method.name = '_method';
        method.value = 'DELETE';

        form.appendChild(csrf);
        form.appendChild(method);
        document.body.appendChild(form);
        form.submit();
    };

    document.getElementById('modal-confirm-input').addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !document.getElementById('modal-confirm-btn').disabled) {
            window.submitDelete();
        }
        if (e.key === 'Escape') window.closeDeleteModal();
    });

    document.getElementById('delete-modal').addEventListener('click', function (e) {
        if (e.target === this) window.closeDeleteModal();
    });
}());
</script>
@endsection
