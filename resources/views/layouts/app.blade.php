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

    @vite(['resources/css/app.css', 'resources/css/pladigit.css', 'resources/js/app.js'])

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

        <button class="pd-health-badge" type="button" title="État système" onclick="window.open('/health','_blank')">
            <span class="pd-health-pulse" id="topbar-health-dot"></span>
            <span id="topbar-health-label">…</span>
        </button>

        <button class="pd-tb-btn" id="pd-notif-open" type="button" aria-label="Notifications">
            <svg class="pd-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
            @if($notifCount > 0)<span class="pd-notif-dot">{{ $notifCount > 9 ? '9+' : $notifCount }}</span>@endif
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

        @if(app(\App\Services\TenantManager::class)->current()?->hasModule(\App\Enums\ModuleKey::MEDIA))
        <a href="{{ route('media.albums.index') }}" class="pd-nav-item {{ str_starts_with($route, 'media.') ? 'active' : '' }}">
            <span class="pd-nav-icon"><svg style="width:18px;height:18px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round;" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg></span>
            <span class="pd-nav-label">Photothèque</span>
            <span class="pd-nav-tip">Photothèque</span>
        </a>
        @endif

        @if(app(\App\Services\TenantManager::class)->current()?->hasModule(\App\Enums\ModuleKey::GED))
        <a href="{{ route('ged.index') }}" class="pd-nav-item {{ str_starts_with($route, 'ged.') ? 'active' : '' }}">
            <span class="pd-nav-icon"><svg style="width:18px;height:18px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round;" viewBox="0 0 24 24"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg></span>
            <span class="pd-nav-label">GED</span>
            <span class="pd-nav-tip">GED documentaire</span>
        </a>
        @endif


@if($tenant?->hasModule(\App\Enums\ModuleKey::PROJECTS))
    <a href="{{ route('projects.index') }}"
       class="pd-nav-item {{ str_starts_with($route, 'projects.') ? 'active' : '' }}"
       style="position:relative;">
        <span class="pd-nav-icon"><svg style="width:18px;height:18px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round;" viewBox="0 0 24 24">
            <rect x="3" y="3" width="18" height="4" rx="1.5"/>
            <rect x="3" y="10" width="13" height="4" rx="1.5"/>
            <rect x="3" y="17" width="16" height="4" rx="1.5"/>
        </svg></span>
        <span class="pd-nav-label">Projets</span>
        <span class="pd-nav-tip">Projets</span>
        @php
            $alertCount = 0;
            try {
                $alertCount = \App\Models\Tenant\Project::on('tenant')
                    ->whereIn('status', ['delayed', 'at_risk'])
                    ->count();
            } catch (\Throwable) {}
        @endphp
        @if($alertCount > 0)
        <span style="position:absolute;top:6px;right:6px;min-width:16px;height:16px;background:#DC2626;color:#fff;border-radius:8px;font-size:9px;font-weight:700;display:flex;align-items:center;justify-content:center;padding:0 3px;border:2px solid var(--pd-surface);">{{ $alertCount > 9 ? '9+' : $alertCount }}</span>
        @endif
    </a>
    @endif


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
        // Préférer les étapes calculées par le contrôleur (contexte sûr)
        // Fallback minimal si le layout est rendu hors DashboardController
        if (empty($onboardingSteps ?? [])) {
            $ldapDone = false;
            try { $ldapDone = (bool)\App\Models\Tenant\TenantSettings::on('tenant')->whereNotNull('ldap_host')->value('ldap_host'); } catch (\Throwable) {}
            $onboardingSteps = [
                ['label' => 'Authentification', 'done' => true],
                ['label' => '2FA',              'done' => (bool)$user->totp_enabled],
                ['label' => 'SMTP',             'done' => (bool)($tenant?->smtp_host)],
                ['label' => 'LDAP',             'done' => $ldapDone],

                ['label' => 'Structure org.',   'done' => \App\Models\Tenant\Department::on('tenant')->exists()],
            ];
        }
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
<footer class="pd-footer" style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;padding:16px 24px;">

    {{-- Bloc gauche — infos système --}}
    <div style="display:flex;flex-direction:column;gap:5px;font-size:12px;color:var(--pd-muted);">
        <span style="font-weight:600;color:rgba(255,255,255,0.85);letter-spacing:.02em;">v1.5 · Phase 3</span>
        <span style="display:flex;align-items:center;gap:5px;">
            <span id="health-dot" class="pd-status-dot"></span>
            <a href="/health" target="_blank" id="health-label"
               style="text-decoration:none;color:inherit;">Système…</a>
        </span>
        <span>© {{ date('Y') }} Les Bézots</span>
        <a href="mailto:contact@lesbezots.fr"
           style="color:inherit;text-decoration:none;">contact@lesbezots.fr</a>
    </div>

    {{-- Bloc droite — liens légaux --}}
    <div style="display:flex;flex-direction:column;gap:5px;font-size:12px;text-align:right;">
        <a href="{{ route('legal.mentions') }}" target="_blank"
           style="color:var(--pd-muted);text-decoration:none;">Mentions légales</a>
        <a href="{{ route('legal.confidentialite') }}" target="_blank"
           style="color:var(--pd-muted);text-decoration:none;">Confidentialité</a>
        <a href="https://github.com/jpbosse/pladigit" target="_blank" rel="noopener"
           style="color:var(--pd-muted);text-decoration:none;display:flex;align-items:center;gap:4px;justify-content:flex-end;">
            <svg style="width:12px;height:12px;fill:currentColor;" viewBox="0 0 24 24"><path d="M12 0C5.37 0 0 5.37 0 12c0 5.31 3.435 9.795 8.205 11.385.6.105.825-.255.825-.57 0-.285-.015-1.23-.015-2.235-3.015.555-3.795-.735-4.035-1.41-.135-.345-.72-1.41-1.23-1.695-.42-.225-1.02-.78-.015-.795.945-.015 1.62.87 1.845 1.23 1.08 1.815 2.805 1.305 3.495.99.105-.78.42-1.305.765-1.605-2.67-.3-5.46-1.335-5.46-5.925 0-1.305.465-2.385 1.23-3.225-.12-.3-.54-1.53.12-3.18 0 0 1.005-.315 3.3 1.23.96-.27 1.98-.405 3-.405s2.04.135 3 .405c2.295-1.56 3.3-1.23 3.3-1.23.66 1.65.24 2.88.12 3.18.765.84 1.23 1.905 1.23 3.225 0 4.605-2.805 5.625-5.475 5.925.435.375.81 1.095.81 2.22 0 1.605-.015 2.895-.015 3.3 0 .315.225.69.825.57A12.02 12.02 0 0 0 24 12c0-6.63-5.37-12-12-12z"/></svg>
            AGPL-3.0
        </a>
        <a href="https://www.gnu.org/licenses/agpl-3.0.html" target="_blank" rel="noopener"
           title="Toute modification doit être publiée"
           style="color:var(--pd-muted);text-decoration:none;">Licence GNU AGPL-3.0</a>
        <a href="mailto:contact@lesbezots.fr"
           style="color:var(--pd-muted);text-decoration:none;">Aide</a>
    </div>

    {{-- Flèche haut — bloc compact --}}
    <div style="display:flex;align-items:flex-start;">
        <button class="pd-back-top" type="button"
                onclick="window.scrollTo({top:0,behavior:'smooth'})"
                style="font-size:16px;padding:6px 10px;">↑</button>
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

{{-- ══════════ DRAWER ESPACE DE STOCKAGE ══════════ --}}
@auth
@include('partials.storage-drawer')
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
            @if(app(\App\Services\TenantManager::class)->current()?->hasModule(\App\Enums\ModuleKey::MEDIA))
            <a href="{{ route('media.albums.index') }}" class="pd-cmd-item">
                <div class="pd-cmd-item-icon" style="background:rgba(46,204,113,0.12);">📷</div>
                <div><div class="pd-cmd-title">Photothèque</div><div class="pd-cmd-sub">Gérer les albums et médias</div></div>
                <span class="pd-cmd-shortcut">G P</span>
            </a>
            @endif
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

    // Notifications drawer — chargement AJAX
    var notifDrawer = document.getElementById('pd-notif-drawer');
    var notifBody   = notifDrawer?.querySelector('.pd-notif-body');
    var notifBadge  = document.querySelector('.pd-notif-count');
    var notifDot    = document.querySelector('.pd-notif-dot');
    var notifLoaded = false;

    function openNotif() {
        notifDrawer?.classList.add('open');
        if (!notifLoaded) loadNotifications();
    }
    function closeNotif() { notifDrawer?.classList.remove('open'); }

    function loadNotifications() {
        if (!notifBody) return;
        notifBody.innerHTML = '<div style="padding:32px;text-align:center;color:var(--pd-muted);">Chargement…</div>';
        fetch('{{ route("notifications.index") }}', {
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content }
        })
        .then(r => r.json())
        .then(data => {
            notifLoaded = true;
            renderNotifications(data.notifications, data.unread_count);
        })
        .catch(() => {
            notifBody.innerHTML = '<div style="padding:24px;text-align:center;color:var(--pd-muted);">Erreur de chargement.</div>';
        });
    }

    function renderNotifications(notifications, unreadCount) {
        if (!notifBody) return;

        // Mise à jour badge
        if (notifBadge) notifBadge.textContent = unreadCount;
        if (notifDot) notifDot.style.display = unreadCount > 0 ? 'block' : 'none';

        if (!notifications || notifications.length === 0) {
            notifBody.innerHTML = `
                <div style="padding:32px 20px;text-align:center;color:var(--pd-muted);">
                    <div style="font-size:2rem;margin-bottom:8px;">🔔</div>
                    <div style="font-size:13px;">Aucune notification</div>
                </div>`;
            return;
        }

        const html = notifications.map(n => `
            <div class="pd-notif-item ${n.read ? '' : 'unread'}" data-id="${n.id}">
                <div class="pd-notif-ico" style="background:${notifIconBg(n.type)};">${notifIcon(n.type)}</div>
                <div style="flex:1;min-width:0;">
                    <div class="pd-notif-title">${escHtml(n.title)}</div>
                    ${n.body ? `<div class="pd-notif-desc">${escHtml(n.body)}</div>` : ''}
                    ${n.link ? `<a href="${n.link}" class="pd-notif-action">Voir →</a>` : ''}
                    <div class="pd-notif-time">${n.created_at_diff || ''}</div>
                </div>
                <button onclick="deleteNotif(${n.id})" style="background:none;border:none;cursor:pointer;color:var(--pd-muted);font-size:14px;padding:2px 4px;flex-shrink:0;" title="Supprimer">×</button>
            </div>`).join('');

        notifBody.innerHTML = html;

        // Marquer comme lu au clic
        notifBody.querySelectorAll('.pd-notif-item.unread').forEach(item => {
            item.addEventListener('click', function() {
                const id = this.dataset.id;
                this.classList.remove('unread');
                fetch(`{{ url('notifications') }}/${id}`, {
                    method: 'PATCH',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content }
                });
                const remaining = notifBody.querySelectorAll('.pd-notif-item.unread').length;
                if (notifBadge) notifBadge.textContent = remaining;
                if (notifDot && remaining === 0) notifDot.style.display = 'none';
            });
        });
    }

    function deleteNotif(id) {
        const item = notifBody?.querySelector(`[data-id="${id}"]`);
        item?.remove();
        fetch(`{{ url('notifications') }}/${id}`, {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content }
        });
    }
    window.deleteNotif = deleteNotif;

    // Tout marquer comme lu
    notifDrawer?.querySelector('.pd-notif-mark-all')?.addEventListener('click', function() {
        fetch('{{ route("notifications.read-all") }}', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content }
        }).then(() => {
            notifBody?.querySelectorAll('.pd-notif-item.unread').forEach(el => el.classList.remove('unread'));
            if (notifBadge) notifBadge.textContent = '0';
            if (notifDot) notifDot.style.display = 'none';
        });
    });

    function notifIcon(type) {
        if (type?.startsWith('agenda'))   return '📅';
        if (type?.startsWith('project'))  return '✅';
        if (type?.startsWith('document')) return '📄';
        if (type?.startsWith('storage'))  return '💾';
        if (type?.startsWith('chat'))     return '💬';
        return '🔔';
    }
    function notifIconBg(type) {
        if (type?.startsWith('agenda'))   return 'rgba(155,89,182,0.12)';
        if (type?.startsWith('project'))  return 'rgba(46,204,113,0.12)';
        if (type?.startsWith('document')) return 'rgba(59,154,225,0.12)';
        if (type?.startsWith('storage'))  return 'rgba(232,168,56,0.12)';
        return 'rgba(107,114,128,0.12)';
    }
    function escHtml(s) {
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    document.getElementById('pd-notif-open')?.addEventListener('click', openNotif);
    document.getElementById('pd-notif-close')?.addEventListener('click', closeNotif);

    // Storage drawer
    var storageDrawer  = document.getElementById('pd-storage-drawer');
    var storageOverlay = document.getElementById('pd-storage-overlay');
    function openStorage(){
        storageDrawer?.classList.add('open');
        storageOverlay?.classList.add('open');
    }
    function closeStorage(){
        storageDrawer?.classList.remove('open');
        storageOverlay?.classList.remove('open');
    }
    document.getElementById('pd-storage-open')?.addEventListener('click', openStorage);
    // Exposer globalement pour les onclick="" inline du drawer
    window.openStorage  = openStorage;
    window.closeStorage = closeStorage;

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
        if(e.key==='Escape'){ closeCmd(); closeNotif(); closeStorage(); }
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

<script>
(function() {
    const topDot = document.getElementById('topbar-health-dot');
    const topLbl = document.getElementById('topbar-health-label');
    const ftrDot = document.getElementById('health-dot');
    const ftrLbl = document.getElementById('health-label');

    fetch('/health', { headers: { 'Accept': 'application/json' } })
        .then(r => r.json())
        .then(data => {
            const ok = data.status === 'ok';
            if (topDot) { topDot.className = 'pd-health-pulse ' + (ok ? 'ok' : 'err'); }
            if (topLbl) { topLbl.textContent = ok ? 'OK' : 'Dégradé'; }
            if (ftrDot) { ftrDot.style.background = ok ? '#2ECC71' : '#E74C3C'; }
            if (ftrLbl) { ftrLbl.textContent = ok ? 'Système opérationnel' : 'Système dégradé'; }
        })
        .catch(() => {
            if (topDot) { topDot.className = 'pd-health-pulse err'; }
            if (topLbl) { topLbl.textContent = 'KO'; }
            if (ftrDot) { ftrDot.style.background = '#E74C3C'; }
            if (ftrLbl) { ftrLbl.textContent = 'Système injoignable'; }
        });
})();
</script>
</body>
</html>
