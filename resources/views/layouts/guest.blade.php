<!DOCTYPE html>
<html lang="fr" data-theme="light" id="pd-html">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name') }} — @yield('title', 'Connexion')</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @php $tenant = app(App\Services\TenantManager::class)->current(); @endphp
    @if($tenant?->primary_color)
    <style>
        :root {
            --pd-navy:       {{ $tenant->primary_color }};
            --pd-navy-dark:  color-mix(in srgb, {{ $tenant->primary_color }} 80%, #000);
            --pd-navy-light: color-mix(in srgb, {{ $tenant->primary_color }} 70%, #fff);
            --color-primary: {{ $tenant->primary_color }};
        }
    </style>
    @endif

    @stack('styles')
</head>

<body class="pd-no-transition" style="min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;background:var(--pd-bg);
    @if(filled($tenant?->login_bg_path)) background-image:url('{{ asset('storage/' . $tenant->login_bg_path) }}');background-size:cover;background-position:center;background-attachment:fixed; @endif">

    {{-- Overlay foncé si image de fond --}}
    @if(filled($tenant?->login_bg_path))
    <div style="position:fixed;inset:0;z-index:0;background:rgba(10,20,40,0.45);backdrop-filter:blur(1px);"></div>
    @else
    {{-- Fond décoratif (sans image) --}}
    <div style="position:fixed;inset:0;z-index:0;pointer-events:none;overflow:hidden;">
        <div style="position:absolute;top:-120px;right:-120px;width:480px;height:480px;border-radius:50%;
                    background:radial-gradient(circle,rgba(59,154,225,0.08),transparent 70%);"></div>
        <div style="position:absolute;bottom:-80px;left:-80px;width:360px;height:360px;border-radius:50%;
                    background:radial-gradient(circle,rgba(30,58,95,0.06),transparent 70%);"></div>
    </div>
    @endif

    <div style="position:relative;z-index:1;width:100%;max-width:420px;padding:16px;">

        {{-- Logo fixe public/img/logo.png --}}
        <div style="text-align:center;margin-bottom:24px;">
            <img src="{{ asset('img/logo.png') }}"
                 alt="{{ $tenant->name ?? 'Pladigit' }}"
                 style="height:120px;width:auto;max-width:320px;object-fit:contain;margin:0 auto 14px;display:block;"
                 onerror="this.style.display='none'">
            @if($tenant)
            <div style="font-family:'Sora',sans-serif;font-size:15px;font-weight:600;
                        color:{{ filled($tenant?->login_bg_path) ? '#fff' : 'var(--pd-navy)' }};
                        text-shadow:{{ filled($tenant?->login_bg_path) ? '0 1px 4px rgba(0,0,0,0.4)' : 'none' }};">
                {{ $tenant->name }}
            </div>
            @endif
        </div>

        {{-- Carte principale --}}
        <div style="background:var(--pd-surface);border-radius:18px;
                    border:1.5px solid var(--pd-border);
                    box-shadow:0 8px 32px rgba(30,58,95,0.10);
                    padding:32px 32px 28px;
                    font-family:'DM Sans',sans-serif;">
            @yield('content')
        </div>

        {{-- Footer --}}
        <div style="text-align:center;margin-top:20px;
                    font-size:11.5px;color:var(--pd-muted);
                    display:flex;align-items:center;justify-content:center;gap:10px;">
            <span>Propulsé par <strong style="color:var(--pd-navy);font-family:'Sora',sans-serif;">Pladigit</strong></span>
            <span style="width:3px;height:3px;border-radius:50%;background:var(--pd-border);display:inline-block;"></span>
            <span>Les Bézots</span>
            <span style="width:3px;height:3px;border-radius:50%;background:var(--pd-border);display:inline-block;"></span>
            <a href="#" style="color:var(--pd-muted);text-decoration:none;">Aide</a>
        </div>

    </div>

    {{-- Toggle thème discret --}}
    <button id="pd-theme-toggle"
            style="position:fixed;bottom:16px;right:16px;
                   width:34px;height:34px;border-radius:10px;
                   background:var(--pd-surface);border:1.5px solid var(--pd-border);
                   cursor:pointer;font-size:15px;
                   display:flex;align-items:center;justify-content:center;
                   box-shadow:var(--pd-shadow);z-index:10;"
            type="button" aria-label="Basculer le thème">
        <span id="pd-theme-thumb">☀️</span>
    </button>

    <script>
    (function(){
        var html  = document.getElementById('pd-html');
        var thumb = document.getElementById('pd-theme-thumb');
        var saved = localStorage.getItem('pd_theme') || 'light';
        function apply(t){ html.setAttribute('data-theme',t); if(thumb) thumb.textContent = t==='dark'?'🌙':'☀️'; }
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
