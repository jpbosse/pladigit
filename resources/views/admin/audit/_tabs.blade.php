{{-- resources/views/admin/audit/_tabs.blade.php --}}
{{-- Usage : @include('admin.audit._tabs', ['active' => 'journal|stats|retention|export']) --}}
@php
$tabs = [
    'journal'   => ['route' => 'admin.audit.index',          'label' => '📋 Journal',          'count' => $totalLogs ?? null],
    'stats'     => ['route' => 'admin.audit.stats',          'label' => '📊 Statistiques'],
    'retention' => ['route' => 'admin.audit.retention.index','label' => '⚙ Rétention & Purge'],
    'export'    => ['route' => 'admin.audit.export.form',    'label' => '📥 Export'],
];
@endphp
<div style="display:flex;gap:4px;margin-bottom:24px;border-bottom:1px solid var(--pd-border);padding-bottom:0;">
    @foreach($tabs as $key => $tab)
    @php $isActive = ($active ?? '') === $key; @endphp
    <a href="{{ route($tab['route']) }}"
       style="display:flex;align-items:center;gap:6px;padding:8px 16px;font-size:13px;font-weight:{{ $isActive ? '700' : '500' }};
              color:{{ $isActive ? 'var(--pd-navy)' : 'var(--pd-muted)' }};text-decoration:none;
              border-bottom:2px solid {{ $isActive ? 'var(--pd-navy)' : 'transparent' }};
              margin-bottom:-1px;transition:color .15s;">
        {{ $tab['label'] }}
        @if(isset($tab['count']) && $tab['count'] !== null)
        <span style="background:var(--pd-surface2);border:0.5px solid var(--pd-border);border-radius:10px;padding:1px 7px;font-size:11px;font-weight:600;color:var(--pd-muted);">
            {{ number_format($tab['count']) }}
        </span>
        @endif
    </a>
    @endforeach
</div>
