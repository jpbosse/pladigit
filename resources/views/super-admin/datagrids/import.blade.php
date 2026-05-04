@extends('layouts.super-admin')
@section('title', 'DataGrid — Import — ' . $org->name)

@section('content')

<div style="margin-bottom:28px;">
    {{-- Fil d'Ariane --}}
    <div style="font-size:12px;color:var(--pd-muted);margin-bottom:12px;">
        <a href="{{ route('super-admin.datagrids.index') }}" style="color:var(--pd-muted);text-decoration:none;">DataGrid</a>
        <span style="margin:0 6px;">›</span>
        <span style="color:var(--pd-text);">Import — {{ $org->name }}</span>
    </div>

    <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
        <div>
            <h1 style="font-family:'Sora',sans-serif;font-size:22px;font-weight:700;color:var(--pd-text);margin-bottom:4px;">
                Importer une grille DataGrid
            </h1>
            @php
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
            <p style="font-size:13px;color:var(--pd-muted);display:flex;align-items:center;gap:8px;">
                <span style="font-family:'Sora',sans-serif;font-weight:600;color:var(--pd-text);">{{ $org->name }}</span>
                <span style="font-family:monospace;font-size:11px;">{{ $org->slug }}</span>
                <span style="display:inline-flex;align-items:center;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:600;
                             background:{{ $statusColor }}20;color:{{ $statusColor }};">
                    {{ $statusLabel }}
                </span>
            </p>
        </div>
        <a href="{{ route('super-admin.datagrids.index') }}"
           style="padding:8px 16px;background:var(--pd-bg2);color:var(--pd-muted);
                  border:0.5px solid var(--pd-border);border-radius:9px;
                  font-size:13px;text-decoration:none;white-space:nowrap;">
            ← Retour à la liste
        </a>
    </div>
</div>

{{-- ── Grilles existantes (contexte) ──────────────────────────── --}}
@if($grids->isNotEmpty())
<div style="background:var(--pd-surface);border:1px solid var(--pd-border);border-radius:14px;overflow:hidden;margin-bottom:28px;">
    <div style="padding:12px 20px;border-bottom:1px solid var(--pd-border);display:flex;align-items:center;gap:8px;">
        <span style="font-size:13px;font-weight:600;color:var(--pd-text);">Grilles existantes</span>
        <span style="font-size:11px;color:var(--pd-muted);">{{ $grids->count() }} grille(s) déjà présente(s) dans ce tenant</span>
    </div>
    <div style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;font-size:12px;">
            <thead>
                <tr style="background:var(--pd-bg);border-bottom:1px solid var(--pd-border);">
                    <th style="text-align:left;padding:8px 16px;color:var(--pd-muted);font-weight:600;font-size:11px;text-transform:uppercase;letter-spacing:.4px;">Nom technique</th>
                    <th style="text-align:left;padding:8px 16px;color:var(--pd-muted);font-weight:600;font-size:11px;text-transform:uppercase;letter-spacing:.4px;">Libellé</th>
                    <th style="text-align:left;padding:8px 16px;color:var(--pd-muted);font-weight:600;font-size:11px;text-transform:uppercase;letter-spacing:.4px;">Table MySQL</th>
                </tr>
            </thead>
            <tbody>
                @foreach($grids as $grid)
                <tr style="border-bottom:0.5px solid var(--pd-border);">
                    <td style="padding:8px 16px;font-family:monospace;color:var(--sa-primary);font-weight:600;">{{ $grid->name }}</td>
                    <td style="padding:8px 16px;color:var(--pd-text);">{{ $grid->label }}</td>
                    <td style="padding:8px 16px;font-family:monospace;color:var(--pd-muted);">{{ $grid->mysql_table }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

{{-- ── Wizard d'import ─────────────────────────────────────────── --}}
@livewire('super-admin.datagrid.import-wizard', ['organizationId' => $org->id])

@endsection
