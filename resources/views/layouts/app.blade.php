<!DOCTYPE html>
<html lang="fr" data-theme="light" id="pd-html">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name') }} — @yield('title', 'Tableau de bord')</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @php $tenant = app(App\Services\TenantManager::class)->current(); @endphp
    @if($tenant && $tenant->primary_color)
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

<body class="pd-no-transition">

@php
    $user        = Auth::user();
    $route       = request()->route()?->getName() ?? '';
    // Injecté par DashboardController ; recalcul léger sur les autres pages
    $storageUsed = $storageUsedPct ?? 0;
    $notifCount  = $notifCount ?? 0;
@endphp

{{-- ══════════ TOPBAR ══════════ --}}
<header class="pd-topbar">

    <div class="pd-topbar-logo">
        @auth
        <a href="{{ route('dashboard') }}" style="display:flex;align-items:center;gap:10px;text-decoration:none;">
            @if($tenant?->logo_path)
                <img src="{{ asset('storage/' . $tenant->logo_path) }}" alt="{{ $tenant->name }}" class="pd-logo-img">
            @else
                <div class="pd-logo-mark">P</div>
                <span class="pd-logo-text">Pladigit</span>
            @endif
        </a>
        @else
        <div class="pd-logo-mark">P</div>
        @endauth
    </div>

    @auth
    <div class="pd-topbar-center">
        <button class="pd-search-trigger" id="pd-cmd-open" type="button">
            <svg class="pd-icon pd-icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
            <span>Rechercher ou ⌘K…</span>
            <span class="pd-search-shortcut">⌘K</span>
        </button>
        <button class="pd-btn-new" id="pd-new-btn" type="button">
            <svg class="pd-icon pd-icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
            Nouveau
        </button>
    </div>
    @endauth

    <div class="pd-topbar-right">
        @auth
        <button class="pd-storage-btn" id="pd-storage-open" type="button" title="Espace disque">
            <svg class="pd-icon pd-icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4.03 3-9 3S3 13.66 3 12"/><path d="M3 5v14c0 1.66 4.03 3 9 3s9-1.34 9-3V5"/></svg>
            <div class="pd-storage-bar"><div class="pd-storage-fill" style="width:{{ $storageUsed }}%"></div></div>
            <span class="pd-storage-pct">{{ $storageUsed }}%</span>
        </button>

        <button class="pd-health-badge" type="button" title="État système">
            <span class="pd-health-pulse ok"></span>
            <span>OK</span>
        </button>

        <button class="pd-tb-btn" id="pd-notif-open" type="button" aria-label="Notifications">
            <svg class="pd-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
            @if($notifCount > 0)<span class="pd-notif-dot"></span>@endif
        </button>

        <button class="pd-theme-toggle" id="pd-theme-toggle" type="button" aria-label="Basculer le thème">
            <div class="pd-theme-thumb" id="pd-theme-thumb">☀️</div>
        </button>

        <div style="position:relative;" id="pd-avatar-wrap">
            <button id="pd-avatar-btn" type="button"
                    title="{{ $user?->name }}"
                    style="background:#fff;
                           border-radius:10px;width:44px;height:44px;
                           display:flex;align-items:center;justify-content:center;
                           cursor:pointer;flex-shrink:0;padding:4px;border:none;
                           box-shadow:0 4px 12px rgba(30,58,95,0.20),
                                      0 1px 4px rgba(30,58,95,0.10),
                                      inset 0 0 0 1px rgba(30,58,95,0.08);
                           transition:box-shadow 0.2s,transform 0.15s;"
                    onmouseover="this.style.boxShadow='0 6px 18px rgba(30,58,95,0.28),0 2px 6px rgba(30,58,95,0.14),inset 0 0 0 1px rgba(59,154,225,0.4)';this.style.transform='translateY(-1px)'"
                    onmouseout="this.style.boxShadow='0 4px 12px rgba(30,58,95,0.20),0 1px 4px rgba(30,58,95,0.10),inset 0 0 0 1px rgba(30,58,95,0.08)';this.style.transform='translateY(0)'">
                @if($user?->avatar_path)
                    <img src="{{ asset('storage/' . $user->avatar_path) }}"
                         alt="{{ $user->name }}"
                         style="width:100%;height:100%;object-fit:cover;border-radius:7px;">
                @else
                    <img src="{{ asset('img/logo.png') }}"
                         alt="Logo"
                         style="width:36px;height:36px;object-fit:contain;display:block;">
                @endif
            </button>
            <div id="pd-avatar-menu" style="display:none;position:absolute;top:calc(100% + 8px);right:0;width:220px;background:var(--pd-surface);border:1.5px solid var(--pd-border);border-radius:12px;box-shadow:var(--pd-shadow-lg);z-index:200;overflow:hidden;">
                <div style="padding:14px 16px 12px;border-bottom:1px solid var(--pd-border);">
                    <div style="font-family:'Sora',sans-serif;font-size:13px;font-weight:600;color:var(--pd-text);">{{ $user?->name }}</div>
                    <div style="font-size:11.5px;color:var(--pd-muted);margin-top:2px;">{{ \App\Enums\UserRole::tryFrom($user?->role ?? '')?->label() ?? $user?->role }}</div>
                    @if($tenant)<div style="font-size:11px;color:var(--pd-muted);margin-top:1px;">{{ $tenant->name }}</div>@endif
                </div>
                <a href="{{ route('profile.show') }}" style="display:flex;align-items:center;gap:10px;padding:11px 16px;font-size:13px;color:var(--pd-text);text-decoration:none;" onmouseover="this.style.background='var(--pd-bg)'" onmouseout="this.style.background=''">
                    <svg class="pd-icon pd-icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    Mon profil
                </a>
                @if(\App\Enums\UserRole::tryFrom($user?->role ?? '')?->atLeast(\App\Enums\UserRole::ADMIN))
                <a href="{{ route('admin.users.index') }}" style="display:flex;align-items:center;gap:10px;padding:11px 16px;font-size:13px;color:var(--pd-text);text-decoration:none;" onmouseover="this.style.background='var(--pd-bg)'" onmouseout="this.style.background=''">
                    <svg class="pd-icon pd-icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                    Administration
                </a>
                @endif
                <div style="border-top:1px solid var(--pd-border);">
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" style="display:flex;align-items:center;gap:10px;width:100%;padding:11px 16px;background:none;border:none;font-size:13px;color:var(--pd-danger);cursor:pointer;font-family:'DM Sans',sans-serif;text-align:left;" onmouseover="this.style.background='var(--pd-bg)'" onmouseout="this.style.background=''">
                            <svg class="pd-icon pd-icon-sm" style="color:var(--pd-danger)" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                            Déconnexion
                        </button>
                    </form>
                </div>
            </div>
        </div>
        @else
        <a href="{{ route('login') }}" style="font-size:13px;font-weight:600;color:var(--pd-accent);text-decoration:none;padding:6px 14px;border:1.5px solid var(--pd-accent);border-radius:9px;">Se connecter</a>
        @endauth
    </div>
</header>

{{-- ══════════ SIDEBAR ══════════ --}}
@auth
<aside class="pd-sidebar" id="pd-sidebar">

    <button class="pd-sidebar-toggle" id="pd-sidebar-toggle" type="button" aria-label="Toggle navigation">
        <svg style="width:12px;height:12px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;" viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
    </button>

    <nav class="pd-sidebar-nav">

        <span class="pd-nav-section">Navigation</span>

        <a href="{{ route('dashboard') }}" class="pd-nav-item {{ str_starts_with($route, 'dashboard') ? 'active' : '' }}">
            <span class="pd-nav-icon"><svg style="width:18px;height:18px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round;" viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg></span>
            <span class="pd-nav-label">Tableau de bord</span>
            <span class="pd-nav-tip">Tableau de bord</span>
        </a>

        <span class="pd-nav-section">Modules</span>

        <a href="{{ route('media.albums.index') }}" class="pd-nav-item {{ str_starts_with($route, 'media.') ? 'active' : '' }}">
            <span class="pd-nav-icon"><svg style="width:18px;height:18px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round;" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg></span>
            <span class="pd-nav-label">Photothèque</span>
            <span class="pd-nav-tip">Photothèque</span>
        </a>

        <a href="#" class="pd-nav-item">
            <span class="pd-nav-icon"><svg style="width:18px;height:18px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;" viewBox="0 0 24 24"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg></span>
            <span class="pd-nav-label">Documents</span>
            <span class="pd-nav-badge">Phase 5</span>
            <span class="pd-nav-tip">Documents — Phase 5</span>
        </a>

        <a href="#" class="pd-nav-item">
            <span class="pd-nav-icon"><svg style="width:18px;height:18px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></span>
            <span class="pd-nav-label">Agenda</span>
            <span class="pd-nav-badge">Phase 8</span>
            <span class="pd-nav-tip">Agenda — Phase 8</span>
        </a>

        <a href="#" class="pd-nav-item">
            <span class="pd-nav-icon"><svg style="width:18px;height:18px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;" viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg></span>
            <span class="pd-nav-label">Chat</span>
            <span class="pd-nav-badge">Phase 9</span>
            <span class="pd-nav-tip">Chat — Phase 9</span>
        </a>

        <a href="#" class="pd-nav-item">
            <span class="pd-nav-icon"><svg style="width:18px;height:18px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M3 15h18M9 3v18"/></svg></span>
            <span class="pd-nav-label">ERP DataGrid</span>
            <span class="pd-nav-badge">Phase 7</span>
            <span class="pd-nav-tip">ERP — Phase 7</span>
        </a>

        @if(\App\Enums\UserRole::tryFrom($user?->role ?? '')?->atLeast(\App\Enums\UserRole::ADMIN))
        <span class="pd-nav-section">Administration</span>

        <a href="{{ route('admin.users.index') }}" class="pd-nav-item {{ str_starts_with($route, 'admin.') ? 'active' : '' }}">
            <span class="pd-nav-icon"><svg style="width:18px;height:18px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg></span>
            <span class="pd-nav-label">Administration</span>
            <span class="pd-nav-tip">Administration</span>
        </a>
        @endif

    </nav>

    <div class="pd-sidebar-footer">
        <a href="{{ route('profile.show') }}" class="pd-nav-item">
            <span class="pd-nav-icon"><svg style="width:18px;height:18px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span>
            <span class="pd-nav-label">Mon profil</span>
            <span class="pd-nav-tip">Mon profil</span>
        </a>
        <form method="POST" action="{{ route('logout') }}" style="margin:0;">
            @csrf
            <button type="submit" class="pd-nav-item" style="width:100%;background:none;border:none;cursor:pointer;text-align:left;font-family:inherit;">
                <span class="pd-nav-icon" style="color:rgba(231,76,60,0.7);"><svg style="width:18px;height:18px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round;" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg></span>
                <span class="pd-nav-label" style="color:rgba(231,76,60,0.8);">Déconnexion</span>
                <span class="pd-nav-tip">Déconnexion</span>
            </button>
        </form>

        {{-- Logo Pladigit en bas de sidebar --}}
        <div class="pd-sidebar-logo-bottom">
            <div class="pd-logo-mark">P</div>
            <span class="pd-nav-label" style="font-family:'Sora',sans-serif;font-size:11px;font-weight:700;color:rgba(255,255,255,0.3);letter-spacing:0.5px;">PLADIGIT</span>
        </div>
    </div>

</aside>
@endauth

{{-- ══════════ MAIN ══════════ --}}
<main class="pd-main" id="pd-main">

    @auth
    @if(\App\Enums\UserRole::tryFrom($user?->role ?? '') === \App\Enums\UserRole::ADMIN)
    @php
        $onboardingSteps = [
            ['label' => 'Authentification', 'done' => true],
            ['label' => '2FA',              'done' => (bool)$user->totp_enabled],
            ['label' => 'SMTP',             'done' => (bool)($tenant?->smtp_host)],
            ['label' => 'LDAP',             'done' => false],
            ['label' => 'Logo & couleurs',  'done' => (bool)($tenant?->logo_path)],
            ['label' => 'Structure org.',   'done' => \App\Models\Tenant\Department::on('tenant')->exists()],
        ];
        $doneCnt  = count(array_filter($onboardingSteps, fn($s) => $s['done']));
        $totalCnt = count($onboardingSteps);
        $obPct    = round($doneCnt / $totalCnt * 100);
    @endphp
    @if($doneCnt < $totalCnt)
    <div class="pd-onboarding" id="pd-onboarding">
        <span class="pd-onboarding-label">⚙️ Configuration</span>
        <div class="pd-onboarding-steps">
            @foreach($onboardingSteps as $s)
            <span class="pd-onboarding-step {{ $s['done'] ? 'done' : 'todo' }}">
                {{ $s['done'] ? '✓' : '○' }} {{ $s['label'] }}
            </span>
            @endforeach
        </div>
        <div class="pd-onboarding-progress">
            <span class="pd-onboarding-pct">{{ $obPct }}%</span>
            <div class="pd-onboarding-track">
                <div class="pd-onboarding-fill" style="width:{{ $obPct }}%"></div>
            </div>
        </div>
        <button class="pd-onboarding-dismiss" type="button"
                onclick="document.getElementById('pd-onboarding').style.display='none';localStorage.setItem('pd_onboarding_dismissed','1');">✕</button>
    </div>
    @endif
    @endif
    @endauth

    @if(session('success'))
    <div style="margin:12px 20px 0;padding:12px 18px;background:rgba(46,204,113,0.1);border:1.5px solid rgba(46,204,113,0.3);border-radius:10px;font-size:13px;color:#1a8a4a;display:flex;align-items:center;gap:10px;">
        ✓ {{ session('success') }}
    </div>
    @endif
    @if(session('error'))
    <div style="margin:12px 20px 0;padding:12px 18px;background:rgba(231,76,60,0.08);border:1.5px solid rgba(231,76,60,0.25);border-radius:10px;font-size:13px;color:#c0392b;display:flex;align-items:center;gap:10px;">
        ⚠ {{ session('error') }}
    </div>
    @endif

    @yield('content')
</main>

{{-- ══════════ FOOTER ══════════ --}}
<footer class="pd-footer">
    <div class="pd-footer-left">
        <span class="pd-version">v1.4 · Phase 2</span>
        <span style="display:flex;align-items:center;gap:6px;">
            <span class="pd-status-dot"></span>
            <span>Système opérationnel</span>
        </span>
        <span>© {{ date('Y') }} Les Bézots</span>
    </div>
    <div class="pd-footer-right">
        <a href="#">Mentions légales</a>
        <a href="#">Confidentialité</a>
        <a href="#">Aide</a>
        <button class="pd-back-top" type="button" onclick="window.scrollTo({top:0,behavior:'smooth'})">↑</button>
    </div>
</footer>

{{-- ══════════ DRAWER NOTIFICATIONS ══════════ --}}
@auth
<aside class="pd-notif-drawer" id="pd-notif-drawer">
    <div class="pd-notif-header">
        <h3>Notifications</h3>
        <span class="pd-notif-count">{{ $notifCount }}</span>
        <button class="pd-notif-mark-all" type="button">Tout lire</button>
        <button class="pd-notif-close" id="pd-notif-close" type="button">✕</button>
    </div>
    <div class="pd-notif-body">
        @stack('notifications')
        @if(isset($notifications) && $notifications->count())
            @foreach($notifications as $notif)
            <div class="pd-notif-item {{ $notif->read ? '' : 'unread' }}">
                <div class="pd-notif-ico" style="background:{{ $notif->iconBg() }};">{{ $notif->icon() }}</div>
                <div style="flex:1;min-width:0;">
                    <div class="pd-notif-title">{{ $notif->title }}</div>
                    @if($notif->body)<div class="pd-notif-desc">{{ $notif->body }}</div>@endif
                    @if($notif->link)<a href="{{ $notif->link }}" class="pd-notif-action">Voir →</a>@endif
                    <div class="pd-notif-time">{{ $notif->created_at->locale('fr')->diffForHumans() }}</div>
                </div>
            </div>
            @endforeach
        @else
        <div style="padding:32px 20px;text-align:center;color:var(--pd-muted);">
            <div style="font-size:2rem;margin-bottom:8px;">🔔</div>
            <div style="font-size:13px;">Aucune notification</div>
        </div>
        @endif
    </div>
</aside>
@endauth

{{-- ══════════ COMMAND PALETTE ══════════ --}}
@auth
<div class="pd-cmd-overlay" id="pd-cmd-overlay">
    <div class="pd-cmd-box">
        <div class="pd-cmd-input-row">
            <svg class="pd-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
            <input type="text" id="pd-cmd-input" placeholder="Rechercher, naviguer, créer…" autocomplete="off">
            <span class="pd-cmd-esc">Échap</span>
        </div>
        <div id="pd-cmd-content">
            <div class="pd-cmd-group">Actions rapides</div>
            <a href="{{ route('media.albums.index') }}" class="pd-cmd-item">
                <div class="pd-cmd-item-icon" style="background:rgba(46,204,113,0.12);">📷</div>
                <div><div class="pd-cmd-title">Photothèque</div><div class="pd-cmd-sub">Gérer les albums et médias</div></div>
                <span class="pd-cmd-shortcut">G P</span>
            </a>
            @if(\App\Enums\UserRole::tryFrom($user?->role ?? '')?->atLeast(\App\Enums\UserRole::ADMIN))
            <a href="{{ route('admin.users.create') }}" class="pd-cmd-item">
                <div class="pd-cmd-item-icon" style="background:rgba(59,154,225,0.12);">👤</div>
                <div><div class="pd-cmd-title">Inviter un utilisateur</div><div class="pd-cmd-sub">Administration → Utilisateurs</div></div>
                <span class="pd-cmd-shortcut">N U</span>
            </a>
            @endif
            <div class="pd-cmd-group">Navigation</div>
            <a href="{{ route('dashboard') }}" class="pd-cmd-item">
                <div class="pd-cmd-item-icon" style="background:var(--pd-bg);">🏠</div>
                <div><div class="pd-cmd-title">Tableau de bord</div></div>
                <span class="pd-cmd-shortcut">G D</span>
            </a>
            <a href="{{ route('profile.show') }}" class="pd-cmd-item">
                <div class="pd-cmd-item-icon" style="background:var(--pd-bg);">👤</div>
                <div><div class="pd-cmd-title">Mon profil</div></div>
            </a>
        </div>
        <div class="pd-cmd-footer">
            <span><kbd>↑↓</kbd> naviguer</span>
            <span><kbd>↵</kbd> ouvrir</span>
            <span><kbd>Échap</kbd> fermer</span>
        </div>
    </div>
</div>
@endauth

{{-- ══════════ POPUP 2FA ══════════ --}}
@auth
@if(!$user->totp_enabled && !request()->routeIs('profile.show', '2fa.*'))
<div id="pd-2fa-popup" style="position:fixed;inset:0;background:rgba(10,20,40,0.6);z-index:400;display:flex;align-items:center;justify-content:center;backdrop-filter:blur(4px);">
    <div style="background:var(--pd-surface);border-radius:18px;padding:2rem;max-width:420px;width:90%;box-shadow:0 24px 60px rgba(0,0,0,0.3);border:1.5px solid var(--pd-border);">
        <div style="text-align:center;margin-bottom:1.5rem;">
            <div style="font-size:2.5rem;margin-bottom:.75rem;">🔐</div>
            <h2 style="font-family:'Sora',sans-serif;font-size:1.15rem;font-weight:700;color:var(--pd-text);margin-bottom:.5rem;">Sécurisez votre compte</h2>
            <p style="font-size:.85rem;color:var(--pd-muted);line-height:1.6;">La double authentification (2FA) n'est pas activée. Elle protège votre accès même si votre mot de passe est compromis.</p>
        </div>
        <div style="display:flex;gap:10px;">
            <a href="{{ route('2fa.setup') }}" style="flex:1;display:block;text-align:center;padding:.7rem;border-radius:10px;background:var(--pd-accent);color:white;font-size:.875rem;font-weight:600;text-decoration:none;">Activer maintenant</a>
            <button onclick="document.getElementById('pd-2fa-popup').style.display='none';localStorage.setItem('pd_2fa_dismissed',Date.now())" style="flex:1;padding:.7rem;border-radius:10px;background:var(--pd-bg);border:1.5px solid var(--pd-border);color:var(--pd-muted);font-size:.875rem;font-weight:500;cursor:pointer;font-family:'DM Sans',sans-serif;">Me rappeler plus tard</button>
        </div>
    </div>
</div>
<script>
(function(){var d=localStorage.getItem('pd_2fa_dismissed');if(d&&Date.now()-parseInt(d)<86400000){var el=document.getElementById('pd-2fa-popup');if(el)el.style.display='none';}})();
</script>
@endif
@endauth

{{-- ══════════ JS PRINCIPAL ══════════ --}}
<script>
(function(){
    'use strict';

    var html  = document.getElementById('pd-html');
    var thumb = document.getElementById('pd-theme-thumb');
    var saved = localStorage.getItem('pd_theme') || 'light';

    function applyTheme(t) {
        html.setAttribute('data-theme', t);
        if (thumb) thumb.textContent = t === 'dark' ? '🌙' : '☀️';
    }
    applyTheme(saved);
    document.getElementById('pd-theme-toggle')?.addEventListener('click', function(){
        var next = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
        localStorage.setItem('pd_theme', next);
        applyTheme(next);
    });

    requestAnimationFrame(function(){ document.body.classList.remove('pd-no-transition'); });

    // Sidebar
    var sidebarOpen = localStorage.getItem('pd_sidebar') === '1';
    function applySidebar(){ document.body.classList.toggle('pd-sidebar-open', sidebarOpen); }
    applySidebar();
    document.getElementById('pd-sidebar-toggle')?.addEventListener('click', function(){
        sidebarOpen = !sidebarOpen;
        localStorage.setItem('pd_sidebar', sidebarOpen ? '1' : '0');
        applySidebar();
    });

    // Avatar menu
    var avatarBtn = document.getElementById('pd-avatar-btn');
    var avatarMenu = document.getElementById('pd-avatar-menu');
    if (avatarBtn && avatarMenu) {
        avatarBtn.addEventListener('click', function(e){
            e.stopPropagation();
            var isOpen = avatarMenu.style.display === 'block';
            avatarMenu.style.display = isOpen ? 'none' : 'block';
        });
        // Fermer si clic en dehors — mais pas si clic sur un lien du menu (laisser naviguer)
        document.addEventListener('click', function(e){
            if (!avatarMenu.contains(e.target) && e.target !== avatarBtn) {
                avatarMenu.style.display = 'none';
            }
        });
        // Fermer après navigation (clic sur un lien ou bouton dans le menu)
        avatarMenu.querySelectorAll('a, button[type="submit"]').forEach(function(el){
            el.addEventListener('click', function(){ avatarMenu.style.display = 'none'; });
        });
    }

    // Notifications drawer
    var notifDrawer = document.getElementById('pd-notif-drawer');
    function openNotif(){ notifDrawer?.classList.add('open'); }
    function closeNotif(){ notifDrawer?.classList.remove('open'); }
    document.getElementById('pd-notif-open')?.addEventListener('click', openNotif);
    document.getElementById('pd-notif-close')?.addEventListener('click', closeNotif);

    // Command palette
    var cmdOverlay = document.getElementById('pd-cmd-overlay');
    var cmdInput   = document.getElementById('pd-cmd-input');
    function openCmd(){ cmdOverlay?.classList.add('open'); cmdInput?.focus(); cmdInput && (cmdInput.value=''); }
    function closeCmd(){ cmdOverlay?.classList.remove('open'); }
    document.getElementById('pd-cmd-open')?.addEventListener('click', openCmd);
    document.getElementById('pd-new-btn')?.addEventListener('click', openCmd);
    cmdOverlay?.addEventListener('click', function(e){ if(e.target===cmdOverlay) closeCmd(); });

    document.addEventListener('keydown', function(e){
        if((e.metaKey||e.ctrlKey)&&e.key==='k'){ e.preventDefault(); cmdOverlay?.classList.contains('open') ? closeCmd() : openCmd(); }
        if(e.key==='Escape'){ closeCmd(); closeNotif(); }
    });

    // Onboarding dismiss
    if(localStorage.getItem('pd_onboarding_dismissed')==='1'){
        var ob = document.getElementById('pd-onboarding');
        if(ob) ob.style.display='none';
    }

    // Drawer tabs stockage
    document.querySelectorAll('.pd-drawer-tab').forEach(function(tab){
        tab.addEventListener('click', function(){
            document.querySelectorAll('.pd-drawer-tab').forEach(function(t){ t.classList.remove('active'); });
            document.querySelectorAll('.pd-drawer-panel').forEach(function(p){ p.classList.remove('active'); });
            this.classList.add('active');
            var panel = document.getElementById('pd-panel-'+this.dataset.tab);
            if(panel) panel.classList.add('active');
        });
    });

})();
</script>

@stack('scripts')
</body>
</html>
