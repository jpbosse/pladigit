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
        <span style="font-size:12px;color:var(--pd-muted);white-space:nowrap;">
            {{ count($row['grids']) }} grille(s)
        </span>
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

@endsection
