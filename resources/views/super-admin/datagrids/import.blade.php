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

{{-- ── Layout deux colonnes ───────────────────────────────────────── --}}
<div class="dg-import-flex" style="display:flex;gap:24px;align-items:flex-start;">

    {{-- Sidebar gauche — grilles existantes --}}
    <div class="dg-import-sidebar" style="width:280px;flex-shrink:0;position:sticky;top:24px;">
        <div style="background:var(--pd-surface);border:0.5px solid var(--pd-border);
                    border-radius:12px;overflow:hidden;">
            <div style="padding:11px 16px;border-bottom:0.5px solid var(--pd-border);
                        display:flex;align-items:center;justify-content:space-between;">
                <span style="font-size:11px;font-weight:600;color:var(--pd-muted);
                             text-transform:uppercase;letter-spacing:.05em;">
                    Grilles existantes
                </span>
                <span style="font-size:11px;background:var(--pd-bg2);color:var(--pd-muted);
                             padding:1px 8px;border-radius:10px;">
                    {{ $grids->count() }}
                </span>
            </div>
            <div style="max-height:62vh;overflow-y:auto;">
                @forelse($grids as $grid)
                <div style="padding:9px 16px;border-bottom:0.5px solid var(--pd-border);">
                    <div style="font-size:12px;font-weight:600;color:var(--pd-text);
                                 white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                        {{ $grid->label }}
                    </div>
                    <div style="font-size:10px;color:var(--pd-muted);font-family:monospace;
                                 margin-top:2px;">
                        {{ $grid->name }}
                    </div>
                </div>
                @empty
                <div style="padding:20px;text-align:center;font-size:12px;
                             color:var(--pd-muted);font-style:italic;">
                    Aucune grille définie
                </div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Zone wizard droite --}}
    <div style="flex:1;min-width:0;">
        @livewire('super-admin.datagrid.import-wizard', ['organizationId' => $org->id])
    </div>

</div>

<style>
@media (max-width: 800px) {
    .dg-import-flex { flex-direction: column !important; }
    .dg-import-sidebar { width: 100% !important; position: static !important; }
}
</style>

@endsection
