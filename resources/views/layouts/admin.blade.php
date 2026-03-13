@extends('layouts.app')

@section('content')
@php
    $route = request()->route()?->getName() ?? '';

    $adminNav = [
        [
            'label' => 'Utilisateurs',
            'icon'  => '<svg style="width:15px;height:15px;flex-shrink:0;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
            'route' => 'admin.users.index',
            'match' => 'admin.users.*',
        ],
        [
            'label' => 'Hiérarchie',
            'icon'  => '<svg style="width:15px;height:15px;flex-shrink:0;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;" viewBox="0 0 24 24"><rect x="2" y="7" width="6" height="14"/><rect x="9" y="3" width="6" height="18"/><rect x="16" y="10" width="6" height="11"/></svg>',
            'route' => 'admin.departments.index',
            'match' => 'admin.departments.*',
        ],
        [
            'label' => 'Personnalisation',
            'icon'  => '<svg style="width:15px;height:15px;flex-shrink:0;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M8 12s1.5-2 4-2 4 2 4 2"/><line x1="9" y1="9" x2="9.01" y2="9"/><line x1="15" y1="9" x2="15.01" y2="9"/></svg>',
            'route' => 'admin.settings.branding',
            'match' => 'admin.settings.branding*',
        ],
        [
            'label' => 'NAS',
            'icon'  => '<svg style="width:15px;height:15px;flex-shrink:0;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;" viewBox="0 0 24 24"><rect x="2" y="2" width="20" height="8" rx="2"/><rect x="2" y="14" width="20" height="8" rx="2"/><line x1="6" y1="6" x2="6.01" y2="6"/><line x1="6" y1="18" x2="6.01" y2="18"/></svg>',
            'route' => 'admin.settings.nas',
            'match' => 'admin.settings.nas*',
        ],
        [
            'label' => 'Photothèque',
            'icon'  => '<svg style="width:15px;height:15px;flex-shrink:0;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>',
            'route' => 'admin.settings.media',
            'match' => 'admin.settings.media*',
        ],
        [
            'label' => 'LDAP',
            'icon'  => '<svg style="width:15px;height:15px;flex-shrink:0;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>',
            'route' => 'admin.settings.ldap',
            'match' => 'admin.settings.ldap*',
        ],
        [
            'label' => 'SMTP',
            'icon'  => '<svg style="width:15px;height:15px;flex-shrink:0;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;" viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>',
            'route' => 'admin.settings.smtp',
            'match' => 'admin.settings.smtp*',
        ],
        [
            'label' => 'Sécurité',
            'icon'  => '<svg style="width:15px;height:15px;flex-shrink:0;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>',
            'route' => 'admin.settings.security',
            'match' => 'admin.settings.security*',
        ],
        [
            'label' => 'Audit',
            'icon'  => '<svg style="width:15px;height:15px;flex-shrink:0;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>',
            'route' => 'admin.audit.index',
            'match' => 'admin.audit.*',
        ],
    ];

    // Titre de la section active
    $activeLabel = collect($adminNav)
        ->first(fn($item) => request()->routeIs($item['match']));
@endphp

{{-- ── Barre de navigation admin ─────────────────────────── --}}
<div style="background:var(--pd-surface);border-bottom:1px solid var(--pd-border);
            position:sticky;top:var(--pd-topbar-h);z-index:80;
            box-shadow:0 2px 8px rgba(30,58,95,0.06);">
    <div style="max-width:1400px;margin:0 auto;padding:0 24px;
                display:flex;align-items:center;gap:2px;overflow-x:auto;
                scrollbar-width:none;">

        {{-- Retour dashboard --}}
        <a href="{{ route('dashboard') }}"
           style="display:flex;align-items:center;gap:6px;
                  padding:12px 14px;
                  font-size:12.5px;font-weight:500;
                  color:var(--pd-muted);text-decoration:none;
                  border-bottom:2px solid transparent;
                  white-space:nowrap;flex-shrink:0;
                  transition:color 0.15s;"
           onmouseover="this.style.color='var(--pd-text)'" onmouseout="this.style.color='var(--pd-muted)'">
            <svg style="width:14px;height:14px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;" viewBox="0 0 24 24">
                <polyline points="15 18 9 12 15 6"/>
            </svg>
            Tableau de bord
        </a>

        <div style="width:1px;height:20px;background:var(--pd-border);margin:0 6px;flex-shrink:0;"></div>

        {{-- Items navigation --}}
        @foreach($adminNav as $item)
        @php $isActive = request()->routeIs($item['match']); @endphp
        <a href="{{ route($item['route']) }}"
           style="display:flex;align-items:center;gap:7px;
                  padding:12px 14px;
                  font-size:13px;font-weight:{{ $isActive ? '600' : '500' }};
                  color:{{ $isActive ? 'var(--pd-navy)' : 'var(--pd-muted)' }};
                  text-decoration:none;white-space:nowrap;flex-shrink:0;
                  border-bottom:2px solid {{ $isActive ? 'var(--pd-accent)' : 'transparent' }};
                  transition:color 0.15s,border-color 0.15s;"
           onmouseover="if(!{{ $isActive ? 'true' : 'false' }}){this.style.color='var(--pd-text)'}"
           onmouseout="if(!{{ $isActive ? 'true' : 'false' }}){this.style.color='var(--pd-muted)'}">
            {!! $item['icon'] !!}
            {{ $item['label'] }}
        </a>
        @endforeach

    </div>
</div>

{{-- ── Contenu admin ─────────────────────────────────────── --}}
<div style="max-width:1400px;margin:0 auto;padding:28px 24px;">

    {{-- Breadcrumb --}}
    <div style="display:flex;align-items:center;gap:8px;margin-bottom:20px;">
        <span style="font-size:12px;color:var(--pd-muted);">Administration</span>
        @if($activeLabel)
        <svg style="width:12px;height:12px;fill:none;stroke:var(--pd-muted);stroke-width:2;" viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
        <span style="font-size:12px;font-weight:600;color:var(--pd-text);">{{ $activeLabel['label'] }}</span>
        @endif
    </div>

    @yield('admin-content')

</div>

@endsection
