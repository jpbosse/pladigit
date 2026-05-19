@extends('layouts.admin')
@section('title', 'Journal DataGrid')

@section('admin-content')

@include('admin.audit._tabs', ['active' => 'datagrid', 'totalLogs' => $totalLogs])

{{-- ── En-tête ── --}}
<div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:20px;gap:12px;flex-wrap:wrap;">
    <div>
        <h1 style="font-family:'Sora',sans-serif;font-size:20px;font-weight:700;color:var(--pd-text);margin:0 0 4px;">
            Journal DataGrid
        </h1>
        <p style="font-size:13px;color:var(--pd-muted);margin:0;">
            Traçabilité granulaire des actions sur les grilles — lecture, modification, suppression, export.<br>
            <span style="font-size:12px;">
                Contrairement au journal général, chaque entrée ici détaille la ligne concernée, la colonne modifiée et les valeurs avant/après.
                Ce niveau de détail est requis pour les grilles contenant des données RGPD sensibles.
            </span>
        </p>
    </div>
    <div style="font-size:12px;color:var(--pd-muted);background:var(--pd-surface);border:1.5px solid var(--pd-border);border-radius:9px;padding:6px 14px;white-space:nowrap;">
        {{ number_format($logs->total()) }} entrée{{ $logs->total() > 1 ? 's' : '' }}
    </div>
</div>

{{-- ── Filtres ── --}}
<form method="GET" action="{{ route('admin.audit.datagrid') }}"
      style="display:flex;flex-wrap:wrap;gap:8px;align-items:flex-end;margin-bottom:20px;background:var(--pd-surface);border:1px solid var(--pd-border);border-radius:10px;padding:14px 16px;">

    <div style="display:flex;flex-direction:column;gap:4px;">
        <label style="font-size:11px;font-weight:600;color:var(--pd-muted);text-transform:uppercase;">Action</label>
        <select name="action" style="border:1px solid var(--pd-border);border-radius:7px;padding:6px 10px;font-size:13px;min-width:140px;">
            <option value="">Toutes</option>
            @foreach($actions as $act => $cnt)
                <option value="{{ $act }}" {{ request('action') === $act ? 'selected' : '' }}>
                    {{ \App\Enums\DatagridAuditAction::tryFrom($act)?->label() ?? ucfirst($act) }} ({{ $cnt }})
                </option>
            @endforeach
        </select>
    </div>

    <div style="display:flex;flex-direction:column;gap:4px;">
        <label style="font-size:11px;font-weight:600;color:var(--pd-muted);text-transform:uppercase;">Grille</label>
        <select name="table" style="border:1px solid var(--pd-border);border-radius:7px;padding:6px 10px;font-size:13px;min-width:160px;">
            <option value="">Toutes</option>
            @foreach($tables as $t)
                <option value="{{ $t->id }}" {{ request('table') == $t->id ? 'selected' : '' }}>
                    {{ $t->label }}
                </option>
            @endforeach
        </select>
    </div>

    <div style="display:flex;flex-direction:column;gap:4px;">
        <label style="font-size:11px;font-weight:600;color:var(--pd-muted);text-transform:uppercase;">Utilisateur</label>
        <input type="text" name="user" value="{{ request('user') }}" placeholder="Nom…"
               style="border:1px solid var(--pd-border);border-radius:7px;padding:6px 10px;font-size:13px;width:140px;">
    </div>

    <div style="display:flex;flex-direction:column;gap:4px;">
        <label style="font-size:11px;font-weight:600;color:var(--pd-muted);text-transform:uppercase;">Du</label>
        <input type="date" name="from" value="{{ request('from') }}"
               style="border:1px solid var(--pd-border);border-radius:7px;padding:6px 10px;font-size:13px;">
    </div>

    <div style="display:flex;flex-direction:column;gap:4px;">
        <label style="font-size:11px;font-weight:600;color:var(--pd-muted);text-transform:uppercase;">Au</label>
        <input type="date" name="to" value="{{ request('to') }}"
               style="border:1px solid var(--pd-border);border-radius:7px;padding:6px 10px;font-size:13px;">
    </div>

    <div style="display:flex;gap:6px;align-items:flex-end;">
        <button type="submit"
                style="padding:7px 18px;background:var(--pd-navy);color:#fff;border:none;border-radius:7px;font-size:13px;font-weight:600;cursor:pointer;">
            Filtrer
        </button>
        @if(request()->hasAny(['action','table','user','from','to']))
        <a href="{{ route('admin.audit.datagrid') }}"
           style="padding:7px 12px;border:1px solid var(--pd-border);border-radius:7px;font-size:13px;color:var(--pd-muted);text-decoration:none;">✕</a>
        @endif
    </div>
</form>

{{-- ── Tableau ── --}}
<div style="background:var(--pd-surface);border:1px solid var(--pd-border);border-radius:12px;overflow:hidden;">
    <table style="width:100%;border-collapse:collapse;font-size:12px;">
        <thead>
            <tr style="background:var(--pd-surface2);">
                <th style="padding:10px 12px;text-align:left;font-weight:600;color:var(--pd-muted);text-transform:uppercase;font-size:11px;">Date</th>
                <th style="padding:10px 12px;text-align:left;font-weight:600;color:var(--pd-muted);text-transform:uppercase;font-size:11px;">Utilisateur</th>
                <th style="padding:10px 12px;text-align:left;font-weight:600;color:var(--pd-muted);text-transform:uppercase;font-size:11px;">Grille</th>
                <th style="padding:10px 12px;text-align:left;font-weight:600;color:var(--pd-muted);text-transform:uppercase;font-size:11px;">Action</th>
                <th style="padding:10px 12px;text-align:left;font-weight:600;color:var(--pd-muted);text-transform:uppercase;font-size:11px;">Ligne</th>
                <th style="padding:10px 12px;text-align:left;font-weight:600;color:var(--pd-muted);text-transform:uppercase;font-size:11px;">Colonne / Format</th>
                <th style="padding:10px 12px;text-align:left;font-weight:600;color:var(--pd-muted);text-transform:uppercase;font-size:11px;">Avant → Après</th>
                <th style="padding:10px 12px;text-align:left;font-weight:600;color:var(--pd-muted);text-transform:uppercase;font-size:11px;">IP</th>
            </tr>
        </thead>
        <tbody>
            @forelse($logs as $log)
            @php
                $actionColors = [
                    'read'             => ['bg' => '#EFF6FF', 'color' => '#1D4ED8'],
                    'create'           => ['bg' => '#ECFDF5', 'color' => '#065F46'],
                    'write'            => ['bg' => '#F0FDF4', 'color' => '#15803D'],
                    'delete'           => ['bg' => '#FEF2F2', 'color' => '#B91C1C'],
                    'export'           => ['bg' => '#FFF7ED', 'color' => '#C2410C'],
                    'import'           => ['bg' => '#F5F3FF', 'color' => '#6D28D9'],
                    'structure_create' => ['bg' => '#F0F9FF', 'color' => '#0369A1'],
                    'structure_drop'   => ['bg' => '#FFF1F2', 'color' => '#9F1239'],
                    'column_update'    => ['bg' => '#FEFCE8', 'color' => '#854D0E'],
                    'column_drop'      => ['bg' => '#FFF7ED', 'color' => '#9A3412'],
                    'column_reorder'   => ['bg' => '#F8FAFC', 'color' => '#475569'],
                ];
                $ac = $actionColors[$log->action] ?? ['bg' => '#F9FAFB', 'color' => '#374151'];
            @endphp
            <tr style="border-bottom:0.5px solid var(--pd-border);">
                <td style="padding:8px 12px;color:var(--pd-muted);white-space:nowrap;">
                    {{ \Carbon\Carbon::parse($log->created_at)->format('d/m/Y H:i') }}
                </td>
                <td style="padding:8px 12px;font-weight:500;color:var(--pd-text);">
                    {{ $log->user_name ?? '—' }}
                </td>
                <td style="padding:8px 12px;color:var(--pd-text);">
                    {{ $log->table_label }}
                </td>
                <td style="padding:8px 12px;">
                    <span style="display:inline-block;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:600;
                          background:{{ $ac['bg'] }};color:{{ $ac['color'] }};">
                        {{ \App\Enums\DatagridAuditAction::tryFrom($log->action)?->label() ?? ucfirst($log->action) }}
                    </span>
                </td>
                <td style="padding:8px 12px;font-family:monospace;color:var(--pd-muted);">
                    {{ $log->row_id ?? '—' }}
                </td>
                <td style="padding:8px 12px;font-family:monospace;color:var(--pd-text);max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                    title="{{ $log->column_name }}">
                    {{ $log->column_name ?? '—' }}
                </td>
                <td style="padding:8px 12px;max-width:200px;">
                    @if($log->old_value !== null || $log->new_value !== null)
                        <span style="font-family:monospace;font-size:11px;color:#B91C1C;word-break:break-all;">{{ Str::limit($log->old_value ?? '', 40) }}</span>
                        @if($log->old_value !== null && $log->new_value !== null)
                            <span style="color:var(--pd-muted);margin:0 4px;">→</span>
                        @endif
                        <span style="font-family:monospace;font-size:11px;color:#15803D;word-break:break-all;">{{ Str::limit($log->new_value ?? '', 40) }}</span>
                    @else
                        <span style="color:var(--pd-muted);">—</span>
                    @endif
                </td>
                <td style="padding:8px 12px;font-family:monospace;color:var(--pd-muted);white-space:nowrap;">
                    {{ $log->ip_address ?? '—' }}
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="8" style="padding:48px 16px;text-align:center;color:var(--pd-muted);">
                    <svg style="width:28px;height:28px;fill:none;stroke:currentColor;stroke-width:1.5;margin:0 auto 10px;display:block;opacity:0.3;" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                    Aucune entrée trouvée.
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

{{ $logs->links() }}

@endsection

