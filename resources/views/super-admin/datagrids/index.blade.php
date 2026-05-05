@extends('layouts.super-admin')
@section('title', 'DataGrid — toutes les grilles')

@section('content')
@php
    $totalGrids = array_sum(array_map(fn ($r) => count($r['grids']), $rows));
    $totalTenants = count($rows);
@endphp

<div style="margin-bottom:28px;">
    <h1 style="font-family:'Sora',sans-serif;font-size:22px;font-weight:700;color:var(--pd-text);margin-bottom:4px;">
        DataGrid — toutes les grilles
    </h1>
    <p style="font-size:13px;color:var(--pd-muted);">
        {{ $totalGrids }} grille(s) sur {{ $totalTenants }} organisation(s)
    </p>
</div>

@forelse($rows as $row)
@php
    $org = $row['org'];
    $statusColor = match($org->status) {
        'active'    => '#2ECC71',
        'suspended' => '#e74c3c',
        default     => '#E8A838',
    };
    $statusLabel = match($org->status) {
        'active'    => 'Active',
        'suspended' => 'Suspendue',
        default     => 'En attente',
    };
@endphp
<div style="background:var(--pd-surface);border:1px solid var(--pd-border);border-radius:14px;overflow:hidden;margin-bottom:20px;">

    {{-- En-tête de carte --}}
    <div style="padding:14px 20px;border-bottom:1px solid var(--pd-border);display:flex;align-items:center;justify-content:space-between;gap:12px;">
        <div style="display:flex;align-items:center;gap:10px;">
            <span style="font-family:'Sora',sans-serif;font-size:15px;font-weight:700;color:var(--pd-text);">
                {{ $org->name }}
            </span>
            <span style="font-size:11px;color:var(--pd-muted);font-family:monospace;">
                {{ $org->slug }}
            </span>
            <span style="display:inline-flex;align-items:center;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:600;
                         background:{{ $statusColor }}20;color:{{ $statusColor }};">
                {{ $statusLabel }}
            </span>
        </div>
        <div style="display:flex;align-items:center;gap:12px;">
            <span style="font-size:12px;color:var(--pd-muted);white-space:nowrap;">
                {{ count($row['grids']) }} grille(s)
            </span>
            @if($org->status === 'active')
            <a href="{{ route('super-admin.datagrids.import', $org) }}"
               style="padding:5px 12px;background:var(--pd-navy);color:#fff;border-radius:8px;
                      font-size:12px;font-weight:600;text-decoration:none;white-space:nowrap;">
                + Importer
            </a>
            @endif
        </div>
    </div>

    @if($row['error'])
        <div style="padding:16px 20px;font-size:13px;color:#e74c3c;background:#fef2f2;">
            Base de données inaccessible pour cette organisation.
        </div>
    @elseif(empty($row['grids']))
        <div style="padding:20px;font-size:13px;color:var(--pd-muted);font-style:italic;text-align:center;">
            Aucune grille définie
        </div>
    @else
        <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;font-size:13px;">
                <thead>
                    <tr style="background:var(--pd-bg);border-bottom:1px solid var(--pd-border);">
                        <th style="text-align:left;padding:10px 16px;color:var(--pd-muted);font-weight:600;font-size:11px;text-transform:uppercase;letter-spacing:.4px;">Nom technique</th>
                        <th style="text-align:left;padding:10px 16px;color:var(--pd-muted);font-weight:600;font-size:11px;text-transform:uppercase;letter-spacing:.4px;">Libellé</th>
                        <th style="text-align:left;padding:10px 16px;color:var(--pd-muted);font-weight:600;font-size:11px;text-transform:uppercase;letter-spacing:.4px;">Table MySQL</th>
                        <th style="text-align:right;padding:10px 16px;color:var(--pd-muted);font-weight:600;font-size:11px;text-transform:uppercase;letter-spacing:.4px;">Colonnes</th>
                        <th style="text-align:right;padding:10px 16px;color:var(--pd-muted);font-weight:600;font-size:11px;text-transform:uppercase;letter-spacing:.4px;">Lignes</th>
                        <th style="text-align:center;padding:10px 16px;color:var(--pd-muted);font-weight:600;font-size:11px;text-transform:uppercase;letter-spacing:.4px;">Statut</th>
                        <th style="padding:10px 16px;border-bottom:0;"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($row['grids'] as $grid)
                    <tr style="border-bottom:1px solid var(--pd-border);transition:background .15s;"
                        onmouseover="this.style.background='var(--pd-bg)'"
                        onmouseout="this.style.background=''">
                        <td style="padding:12px 16px;font-family:monospace;font-size:12px;color:var(--sa-primary);font-weight:600;">
                            {{ $grid['name'] }}
                        </td>
                        <td style="padding:12px 16px;color:var(--pd-text);font-weight:500;">
                            {{ $grid['label'] }}
                        </td>
                        <td style="padding:12px 16px;font-family:monospace;font-size:12px;color:var(--pd-muted);">
                            {{ $grid['mysql_table'] }}
                        </td>
                        <td style="padding:12px 16px;text-align:right;color:var(--pd-text);">
                            {{ $grid['nb_colonnes'] }}
                        </td>
                        <td style="padding:12px 16px;text-align:right;color:var(--pd-text);">
                            {{ $grid['nb_lignes'] !== null ? number_format($grid['nb_lignes']) : '—' }}
                        </td>
                        <td style="padding:12px 16px;text-align:center;">
                            @if($grid['supprimee'])
                                <span style="display:inline-flex;align-items:center;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:600;background:#f3f4f6;color:#6b7280;">
                                    Supprimée
                                </span>
                            @else
                                <span style="display:inline-flex;align-items:center;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:600;background:#d1fae5;color:#065f46;">
                                    Active
                                </span>
                            @endif
                        </td>
                        <td style="padding:12px 16px;text-align:right;white-space:nowrap;">
                            @if(!$grid['supprimee'])
                            <a href="{{ route('super-admin.datagrids.edit', [$org, $grid['id']]) }}"
                               style="padding:4px 10px;border:1px solid var(--pd-border);border-radius:6px;
                                      font-size:11px;font-weight:500;color:var(--pd-text);text-decoration:none;
                                      margin-right:6px;">
                                Modifier
                            </a>
                            <button type="button"
                                    onclick="showDeleteModal('{{ $grid['mysql_table'] }}', 'form-del-{{ $org->id }}-{{ $grid['id'] }}')"
                                    style="padding:4px 10px;border:1px solid #fca5a5;border-radius:6px;
                                           font-size:11px;font-weight:500;color:#dc2626;background:#fef2f2;cursor:pointer;">
                                Supprimer
                            </button>
                            <form id="form-del-{{ $org->id }}-{{ $grid['id'] }}" method="POST"
                                  action="{{ route('super-admin.datagrids.destroy', [$org, $grid['id']]) }}"
                                  style="display:none;">
                                @csrf @method('DELETE')
                            </form>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

</div>
@empty
<div style="text-align:center;padding:60px 20px;color:var(--pd-muted);">
    <div style="font-size:2.5rem;margin-bottom:12px;">📋</div>
    <p style="font-size:14px;">Aucune organisation hébergée.</p>
</div>
@endforelse

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
