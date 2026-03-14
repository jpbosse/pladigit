<!DOCTYPE html>
<html lang="fr" data-theme="light" id="pd-html">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Super Admin — @yield('title', 'Pladigit')</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    {{--
        Super Admin — palette volontairement différente des tenants.
        Rouge bordeaux pour signaler visuellement qu'on est hors organisation.
    --}}
    <style>
        :root {
            --sa-primary:   #7B1C1C;
            --sa-dark:      #4a1010;
            --sa-light:     #a83232;
            --sa-accent:    #c0392b;
            --sa-gold:      #E8A838;
            --pd-navy:      #7B1C1C;
            --pd-navy-dark: #4a1010;
            --pd-navy-light:#a83232;
            --pd-accent:    #c0392b;
        }
    </style>

    @stack('styles')
</head>

<body class="pd-no-transition">

@php
    $route = request()->route()?->getName() ?? '';

    $saNav = [
        [
            'label' => 'Organisations',
            'icon'  => '<svg style="width:15px;height:15px;flex-shrink:0;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;" viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>',
            'route' => 'super-admin.organizations.index',
            'match' => 'super-admin.organizations.*',
        ],
        [
            'label' => 'Statistiques',
            'icon'  => '<svg style="width:15px;height:15px;flex-shrink:0;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;" viewBox="0 0 24 24"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>',
            'route' => 'super-admin.stats',
            'match' => 'super-admin.stats',
        ],
    ];
@endphp

{{-- ══════════ TOPBAR SUPER-ADMIN ══════════ --}}
<header style="position:fixed;top:0;left:0;right:0;height:58px;z-index:100;
               background:linear-gradient(135deg,var(--sa-dark),var(--sa-primary));
               display:flex;align-items:center;padding:0 24px;gap:16px;
               box-shadow:0 2px 12px rgba(74,16,16,0.3);">

    {{-- Logo + badge SA --}}
    <div style="display:flex;align-items:center;gap:10px;flex-shrink:0;">
        <div style="width:36px;height:36px;border-radius:8px;
                    background:rgba(255,255,255,0.95);
                    display:flex;align-items:center;justify-content:center;
                    padding:4px;">
            <img src="{{ asset('img/logo.png') }}" style="width:100%;height:100%;object-fit:contain;display:block;">
        </div>
        <div>
            <div style="font-family:'Sora',sans-serif;font-size:14px;font-weight:700;color:#fff;line-height:1.2;">
                Pladigit
            </div>
            <div style="font-size:10px;font-weight:600;text-transform:uppercase;
                        letter-spacing:1px;color:rgba(255,255,255,0.5);line-height:1.2;">
                Super Admin
            </div>
        </div>
    </div>

    {{-- Badge d'alerte niveau plateforme --}}
    <div style="background:rgba(255,255,255,0.12);border:1px solid rgba(255,255,255,0.2);
                border-radius:20px;padding:4px 12px;
                font-size:11px;font-weight:600;color:rgba(255,255,255,0.8);
                display:flex;align-items:center;gap:6px;">
        <span style="width:6px;height:6px;border-radius:50%;background:#ff6b6b;
                     box-shadow:0 0 6px #ff6b6b;animation:pd-pulse 2s infinite;flex-shrink:0;"></span>
        Accès plateforme — hors organisation
    </div>

    <div style="flex:1;"></div>

    {{-- Actions droite --}}
    <div style="display:flex;align-items:center;gap:8px;">

        {{-- Toggle thème --}}
        <button class="pd-theme-toggle" id="pd-theme-toggle" type="button"
                style="background:rgba(255,255,255,0.12);border-color:rgba(255,255,255,0.2);"
                aria-label="Basculer le thème">
            <div class="pd-theme-thumb" id="pd-theme-thumb">☀️</div>
        </button>

        {{-- Déconnexion --}}
        <form method="POST" action="{{ route('super-admin.logout') }}" style="margin:0;">
            @csrf
            <button type="submit"
                    style="display:flex;align-items:center;gap:7px;
                           background:rgba(255,255,255,0.12);
                           border:1px solid rgba(255,255,255,0.2);
                           border-radius:9px;padding:7px 14px;
                           color:rgba(255,255,255,0.85);
                           font-family:'DM Sans',sans-serif;
                           font-size:13px;font-weight:500;cursor:pointer;
                           transition:background 0.2s;"
                    onmouseover="this.style.background='rgba(255,255,255,0.2)'"
                    onmouseout="this.style.background='rgba(255,255,255,0.12)'">
                <svg style="width:14px;height:14px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;" viewBox="0 0 24 24">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                    <polyline points="16 17 21 12 16 7"/>
                    <line x1="21" y1="12" x2="9" y2="12"/>
                </svg>
                Déconnexion
            </button>
        </form>
    </div>
</header>

{{-- ══════════ BARRE NAV SECONDAIRE ══════════ --}}
<div style="position:fixed;top:58px;left:0;right:0;z-index:90;
            background:var(--pd-surface);border-bottom:1px solid var(--pd-border);
            box-shadow:0 1px 8px rgba(0,0,0,0.05);">
    <div style="max-width:1400px;margin:0 auto;padding:0 24px;
                display:flex;align-items:center;gap:2px;">

        @foreach($saNav as $item)
        @php $isActive = request()->routeIs($item['match']); @endphp
        <a href="{{ route($item['route']) }}"
           style="display:flex;align-items:center;gap:7px;
                  padding:12px 16px;font-size:13px;
                  font-weight:{{ $isActive ? '600' : '500' }};
                  color:{{ $isActive ? 'var(--sa-primary)' : 'var(--pd-muted)' }};
                  text-decoration:none;white-space:nowrap;
                  border-bottom:2px solid {{ $isActive ? 'var(--sa-accent)' : 'transparent' }};
                  transition:color 0.15s,border-color 0.15s;"
           onmouseover="if(!{{ $isActive ? 'true' : 'false' }}){this.style.color='var(--pd-text)'}"
           onmouseout="if(!{{ $isActive ? 'true' : 'false' }}){this.style.color='var(--pd-muted)'}">
            {!! $item['icon'] !!}
            {{ $item['label'] }}
        </a>
        @endforeach

    </div>
</div>

{{-- ══════════ CONTENU ══════════ --}}
<main style="margin-top:calc(58px + 44px);min-height:calc(100vh - 58px - 44px - 44px);
             padding:28px 24px;">
    <div style="max-width:1400px;margin:0 auto;">

        {{-- Flash messages --}}
        @if(session('success'))
        <div style="margin-bottom:20px;padding:12px 18px;
                    background:rgba(46,204,113,0.1);
                    border:1.5px solid rgba(46,204,113,0.3);
                    border-radius:10px;font-size:13px;color:#1a8a4a;
                    display:flex;align-items:center;gap:10px;">
            ✓ {{ session('success') }}
        </div>
        @endif
        @if(session('error'))
        <div style="margin-bottom:20px;padding:12px 18px;
                    background:rgba(231,76,60,0.08);
                    border:1.5px solid rgba(231,76,60,0.25);
                    border-radius:10px;font-size:13px;color:#c0392b;
                    display:flex;align-items:center;gap:10px;">
            ⚠ {{ session('error') }}
        </div>
        @endif

        @yield('content')
    </div>
</main>

{{-- ══════════ FOOTER ══════════ --}}
<footer style="height:44px;background:var(--pd-surface);
               border-top:1px solid var(--pd-border);
               display:flex;align-items:center;justify-content:space-between;
               padding:0 24px;font-size:12px;color:var(--pd-muted);">
    <div style="display:flex;align-items:center;gap:14px;">
        <span style="background:var(--pd-bg);border:1px solid var(--pd-border);
                     border-radius:5px;padding:2px 8px;
                     font-size:11px;font-weight:600;color:var(--sa-primary);
                     font-family:'Sora',sans-serif;">
            v1.4 · Super Admin
        </span>
        <span style="display:flex;align-items:center;gap:5px;">
            <span style="width:6px;height:6px;border-radius:50%;
                         background:var(--pd-success);
                         box-shadow:0 0 5px var(--pd-success);"></span>
            Plateforme opérationnelle
        </span>
    </div>
    <span>© {{ date('Y') }} Les Bézots</span>
</footer>

{{-- ══════════ JS ══════════ --}}
<script>
(function(){
    var html  = document.getElementById('pd-html');
    var thumb = document.getElementById('pd-theme-thumb');
    var saved = localStorage.getItem('pd_theme') || 'light';
    function apply(t){ html.setAttribute('data-theme',t); if(thumb) thumb.textContent=t==='dark'?'🌙':'☀️'; }
    apply(saved);
    document.getElementById('pd-theme-toggle')?.addEventListener('click',function(){
        var next = html.getAttribute('data-theme')==='dark'?'light':'dark';
        localStorage.setItem('pd_theme',next); apply(next);
    });
    requestAnimationFrame(function(){ document.body.classList.remove('pd-no-transition'); });
})();
</script>

@stack('scripts')
</body>
</html>
