@extends('layouts.admin')
@section('title', 'Audit — Statistiques')

@section('admin-content')

{{-- Navigation onglets --}}
@include('admin.audit._tabs', ['active' => 'stats'])

{{-- Métriques clés --}}
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px;margin-bottom:28px;">
    <div style="background:var(--pd-surface);border:0.5px solid var(--pd-border);border-radius:12px;padding:16px 20px;">
        <div style="font-size:11px;font-weight:700;color:var(--pd-muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px;">Total entrées</div>
        <div style="font-size:28px;font-weight:700;color:var(--pd-navy);">{{ number_format($totalLogs) }}</div>
        <div style="font-size:11px;color:var(--pd-muted);margin-top:3px;">dans la base</div>
    </div>
    <div style="background:var(--pd-surface);border:0.5px solid var(--pd-border);border-radius:12px;padding:16px 20px;">
        <div style="font-size:11px;font-weight:700;color:var(--pd-muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px;">Ce mois</div>
        @php $thisMonth = $monthlyVolume[now()->format('Y-m')] ?? 0; @endphp
        <div style="font-size:28px;font-weight:700;color:var(--pd-navy);">{{ number_format($thisMonth) }}</div>
        <div style="font-size:11px;color:var(--pd-muted);margin-top:3px;">{{ now()->translatedFormat('F Y') }}</div>
    </div>
    <div style="background:var(--pd-surface);border:0.5px solid var(--pd-border);border-radius:12px;padding:16px 20px;">
        <div style="font-size:11px;font-weight:700;color:var(--pd-muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px;">Rétention</div>
        <div style="font-size:28px;font-weight:700;color:var(--pd-navy);">{{ $retention }}</div>
        <div style="font-size:11px;color:var(--pd-muted);margin-top:3px;">mois configurés</div>
    </div>
    <div style="background:var(--pd-surface);border:0.5px solid var(--pd-border);border-radius:12px;padding:16px 20px;">
        <div style="font-size:11px;font-weight:700;color:var(--pd-muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px;">Types d'actions</div>
        <div style="font-size:28px;font-weight:700;color:var(--pd-navy);">{{ $topActions->count() }}</div>
        <div style="font-size:11px;color:var(--pd-muted);margin-top:3px;">actions distinctes</div>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:28px;">

    {{-- Top actions --}}
    <div style="background:var(--pd-surface);border:0.5px solid var(--pd-border);border-radius:12px;padding:20px;">
        <div style="font-size:14px;font-weight:700;color:var(--pd-navy);margin-bottom:16px;">Top actions</div>
        @php $maxAction = $topActions->max('cnt') ?: 1; @endphp
        @foreach($topActions as $row)
        <div style="margin-bottom:10px;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;">
                <span style="font-size:12px;color:var(--pd-text);">{{ $row->action }}</span>
                <span style="font-size:12px;font-weight:600;color:var(--pd-navy);">{{ number_format($row->cnt) }}</span>
            </div>
            <div style="height:6px;background:var(--pd-surface2);border-radius:3px;overflow:hidden;">
                <div style="height:100%;width:{{ round($row->cnt / $maxAction * 100) }}%;background:var(--pd-accent);border-radius:3px;"></div>
            </div>
        </div>
        @endforeach
    </div>

    {{-- Top utilisateurs --}}
    <div style="background:var(--pd-surface);border:0.5px solid var(--pd-border);border-radius:12px;padding:20px;">
        <div style="font-size:14px;font-weight:700;color:var(--pd-navy);margin-bottom:16px;">Top utilisateurs</div>
        @php $maxUser = $topUsers->max('cnt') ?: 1; @endphp
        @foreach($topUsers as $row)
        <div style="margin-bottom:10px;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;">
                <div style="display:flex;align-items:center;gap:8px;">
                    <div style="width:22px;height:22px;border-radius:6px;background:linear-gradient(135deg,var(--pd-navy),var(--pd-accent));display:flex;align-items:center;justify-content:center;font-size:9px;font-weight:700;color:#fff;flex-shrink:0;">
                        {{ strtoupper(substr($row->user_name, 0, 2)) }}
                    </div>
                    <span style="font-size:12px;color:var(--pd-text);">{{ $row->user_name }}</span>
                </div>
                <span style="font-size:12px;font-weight:600;color:var(--pd-navy);">{{ number_format($row->cnt) }}</span>
            </div>
            <div style="height:6px;background:var(--pd-surface2);border-radius:3px;overflow:hidden;">
                <div style="height:100%;width:{{ round($row->cnt / $maxUser * 100) }}%;background:#16A34A;border-radius:3px;"></div>
            </div>
        </div>
        @endforeach
    </div>
</div>

{{-- Volume par mois --}}
<div style="background:var(--pd-surface);border:0.5px solid var(--pd-border);border-radius:12px;padding:20px;margin-bottom:28px;">
    <div style="font-size:14px;font-weight:700;color:var(--pd-navy);margin-bottom:16px;">Volume mensuel — 12 derniers mois</div>
    @php $maxMonth = $monthlyVolume->max() ?: 1; @endphp
    <div style="display:flex;align-items:flex-end;gap:8px;height:120px;">
        @foreach($monthlyVolume as $month => $cnt)
        @php $h = max(4, round($cnt / $maxMonth * 100)); @endphp
        <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:4px;">
            <div style="font-size:10px;color:var(--pd-muted);">{{ $cnt }}</div>
            <div style="width:100%;height:{{ $h }}px;background:{{ $month === now()->format('Y-m') ? 'var(--pd-navy)' : 'var(--pd-accent)' }};border-radius:4px 4px 0 0;opacity:{{ $month === now()->format('Y-m') ? '1' : '0.6' }};"></div>
            <div style="font-size:9px;color:var(--pd-muted);writing-mode:vertical-rl;transform:rotate(180deg);">{{ \Carbon\Carbon::createFromFormat('Y-m', $month)->translatedFormat('M y') }}</div>
        </div>
        @endforeach
    </div>
</div>

{{-- Volume par jour (30j) --}}
<div style="background:var(--pd-surface);border:0.5px solid var(--pd-border);border-radius:12px;padding:20px;">
    <div style="font-size:14px;font-weight:700;color:var(--pd-navy);margin-bottom:16px;">Volume quotidien — 30 derniers jours</div>
    @php $maxDay = $dailyVolume->max() ?: 1; @endphp
    <div style="display:flex;align-items:flex-end;gap:3px;height:80px;overflow-x:auto;">
        @foreach($dailyVolume as $day => $cnt)
        @php $h = max(2, round($cnt / $maxDay * 72)); @endphp
        <div style="flex-shrink:0;width:20px;display:flex;flex-direction:column;align-items:center;gap:2px;" title="{{ $day }} : {{ $cnt }} entrées">
            <div style="width:100%;height:{{ $h }}px;background:var(--pd-accent);border-radius:2px 2px 0 0;opacity:.7;"></div>
            <div style="font-size:8px;color:var(--pd-muted);">{{ \Carbon\Carbon::parse($day)->format('d') }}</div>
        </div>
        @endforeach
    </div>
</div>

@endsection
