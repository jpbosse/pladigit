<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name') }} — @yield('title', 'Tableau de bord')</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @if(app(App\Services\TenantManager::class)->hasTenant())
    <style>
        :root {
            --color-primary: {{ app(App\Services\TenantManager::class)->current()->primary_color ?? '#1E3A5F' }};
        }
    </style>
    @endif

    <style>
        /* ── Barre supérieure contextuelle ── */
        .topbar {
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 1.5rem;
            background-color: var(--color-primary, #1E3A5F);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 100;
        }
        .topbar-left {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .topbar-org {
            font-size: 0.75rem;
            font-weight: 700;
            color: rgba(255,255,255,0.95);
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }
        .topbar-dot {
            width: 5px;
            height: 5px;
            border-radius: 50%;
            background: rgba(255,255,255,0.3);
        }
        .topbar-subdomain {
            font-size: 0.7rem;
            color: rgba(255,255,255,0.45);
            font-family: monospace;
        }
        .topbar-right {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .topbar-user {
            font-size: 0.75rem;
            color: rgba(255,255,255,0.7);
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .topbar-avatar {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.65rem;
            font-weight: 700;
            color: white;
            flex-shrink: 0;
        }
        .topbar-btn {
            font-size: 0.7rem;
            color: rgba(255,255,255,0.6);
            text-decoration: none;
            transition: color 0.15s;
            background: none;
            border: none;
            cursor: pointer;
            padding: 0;
            font-family: inherit;
        }
        .topbar-btn:hover { color: rgba(255,255,255,1); }
        .topbar-sep {
            width: 1px;
            height: 12px;
            background: rgba(255,255,255,0.15);
        }

        /* ── Barre de navigation principale sticky ── */
        .navbar {
            height: 52px;
            background: white;
            border-bottom: 1px solid #e5e7eb;
            box-shadow: 0 1px 8px rgba(0,0,0,0.06);
            position: fixed;
            top: 36px;
            left: 0;
            right: 0;
            z-index: 99;
            display: flex;
            align-items: center;
            padding: 0 1.5rem;
            gap: 4px;
        }
        .nav-item {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 7px;
            font-size: 0.82rem;
            font-weight: 500;
            color: #6b7280;
            text-decoration: none;
            transition: background 0.15s, color 0.15s;
            white-space: nowrap;
        }
        .nav-item:hover {
            background: #f3f4f6;
            color: #111827;
        }
        .nav-item.active {
            background: color-mix(in srgb, var(--color-primary, #1E3A5F) 10%, white);
            color: var(--color-primary, #1E3A5F);
            font-weight: 600;
        }
        .nav-item .nav-icon {
            font-size: 1rem;
            line-height: 1;
        }
        .nav-divider {
            width: 1px;
            height: 24px;
            background: #e5e7eb;
            margin: 0 4px;
        }
        .nav-badge {
            font-size: 0.6rem;
            font-weight: 700;
            padding: 1px 5px;
            border-radius: 99px;
            background: #e5e7eb;
            color: #6b7280;
            letter-spacing: 0.03em;
            text-transform: uppercase;
        }
        .nav-badge.soon {
            background: #fef3c7;
            color: #92400e;
        }

        /* ── Contenu principal ── */
        .main-content {
            margin-top: calc(36px + 52px);
            min-height: calc(100vh - 88px);
            padding: 1.5rem 0;
        }
    </style>

    @stack('styles')
</head>
<body class="bg-gray-50">

    @php
        $tenant = app(App\Services\TenantManager::class)->current();
        $user = Auth::user();
        $host = request()->getHost();
        $currentRoute = request()->route()?->getName() ?? '';
    @endphp

    {{-- ── Barre supérieure ── --}}
    <div class="topbar">
        <div class="topbar-left">
            @if($tenant)
                <span class="topbar-org">{{ $tenant->name }}</span>
                <div class="topbar-dot"></div>
                <span class="topbar-subdomain">{{ $host }}</span>
            @else
                <span class="topbar-org">{{ config('app.name') }}</span>
            @endif
        </div>
        <div class="topbar-right">
            @auth
                <div class="topbar-user">
                    <div class="topbar-avatar">{{ strtoupper(substr($user->name, 0, 1)) }}</div>
                    <span>{{ $user->name }}</span>
                    @if($user->role)
                        <span style="color:rgba(255,255,255,0.35)">·</span>
                        <span style="color:rgba(255,255,255,0.45)">{{ $user->role instanceof \App\Enums\UserRole ? $user->role->label() : $user->role }}</span>
                    @endif
                </div>
                <div class="topbar-sep"></div>
                <a href="{{ route('profile.show') }}" class="topbar-btn">Mon profil</a>
                <div class="topbar-sep"></div>
                <form method="POST" action="{{ route('logout') }}" style="display:inline">
                    @csrf
                    <button type="submit" class="topbar-btn">Déconnexion</button>
                </form>
            @endauth
        </div>
    </div>

    {{-- ── Barre de navigation principale ── --}}
    @auth
    <nav class="navbar">
        {{-- Dashboard --}}
        <a href="{{ route('dashboard') }}"
           class="nav-item {{ str_starts_with($currentRoute, 'dashboard') ? 'active' : '' }}">
            <span class="nav-icon">🏠</span> Accueil
        </a>

        <div class="nav-divider"></div>

        {{-- Photothèque --}}
        <a href="{{ route('media.albums.index') }}"
           class="nav-item {{ str_starts_with($currentRoute, 'media.') ? 'active' : '' }}">
            <span class="nav-icon">📷</span> Photothèque
        </a>

        {{-- GED — futur --}}
        <a href="#" class="nav-item" title="Disponible Phase 5 — Oct 2026">
            <span class="nav-icon">📁</span> Documents
            <span class="nav-badge soon">Phase 5</span>
        </a>

        {{-- Projets — futur --}}
        <a href="#" class="nav-item" title="Disponible Phase 8 — Jul 2027">
            <span class="nav-icon">📋</span> Projets
            <span class="nav-badge soon">Phase 8</span>
        </a>

        {{-- Chat — futur --}}
        <a href="#" class="nav-item" title="Disponible Phase 9 — Oct 2027">
            <span class="nav-icon">💬</span> Chat
            <span class="nav-badge soon">Phase 9</span>
        </a>

        {{-- Admin --}}
        @if($user?->role === 'admin' || $user?->role instanceof \App\Enums\UserRole && $user->role->value === 'admin')
        <div class="nav-divider"></div>
        <a href="{{ route('admin.users.index') }}"
           class="nav-item {{ str_starts_with($currentRoute, 'admin.') ? 'active' : '' }}">
            <span class="nav-icon">⚙️</span> Administration
        </a>
        <a href="{{ route('admin.departments.index') }}"
           class="nav-item {{ str_starts_with($currentRoute, 'admin.departments') ? 'active' : '' }}">
            <span class="nav-icon">🏢</span> Hiérarchie
        </a>
        @endif
    </nav>
    @endauth

    {{-- ── Contenu ── --}}
    <main class="main-content">
        @yield('content')
    </main>

</body>
</html>

@auth
@if(!Auth::user()->totp_enabled && !request()->routeIs('profile.show') && !request()->routeIs('2fa.setup') && !request()->routeIs('2fa.challenge') && !request()->routeIs('2fa.verify'))
<div id="2fa-popup" style="position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:999;display:flex;align-items:center;justify-content:center;backdrop-filter:blur(3px)">
    <div style="background:white;border-radius:16px;padding:2rem;max-width:440px;width:90%;box-shadow:0 24px 64px rgba(0,0,0,0.2);position:relative">
        <div style="text-align:center;margin-bottom:1.5rem">
            <div style="font-size:3rem;margin-bottom:0.75rem">🔐</div>
            <h2 style="font-size:1.2rem;font-weight:700;color:#111827;margin-bottom:0.5rem">Sécurisez votre compte</h2>
            <p style="font-size:0.875rem;color:#6b7280;line-height:1.6">La double authentification (2FA) n'est pas activée sur votre compte. Elle protège votre accès même si votre mot de passe est compromis.</p>
        </div>
        <div style="background:#f9fafb;border-radius:10px;padding:1rem;margin-bottom:1.5rem">
            <p style="font-size:0.8rem;font-weight:600;color:#374151;margin-bottom:0.75rem">Comment l'activer :</p>
            <ol style="font-size:0.8rem;color:#6b7280;line-height:1.8;padding-left:1.25rem">
                <li>Installez <strong>Google Authenticator</strong>, <strong>Microsoft Authenticator</strong> ou <strong>Aegis</strong> sur votre téléphone</li>
                <li>Allez dans <strong>Mon profil → Sécurité</strong></li>
                <li>Scannez le QR code et entrez le code à 6 chiffres</li>
            </ol>
        </div>
        <div style="display:flex;gap:10px">
            <a href="{{ route('2fa.setup') }}" style="flex:1;display:block;text-align:center;padding:0.65rem;border-radius:8px;background:var(--color-primary,#1E3A5F);color:white;font-size:0.85rem;font-weight:600;text-decoration:none">
                Activer maintenant
            </a>
            <button onclick="document.getElementById('2fa-popup').style.display='none';localStorage.setItem('2fa_dismissed',Date.now())" style="flex:1;padding:0.65rem;border-radius:8px;background:#f3f4f6;border:none;color:#6b7280;font-size:0.85rem;font-weight:500;cursor:pointer">
                Me le rappeler plus tard
            </button>
        </div>
        <p style="font-size:0.7rem;color:#d1d5db;text-align:center;margin-top:1rem">Ce message s'affichera à chaque connexion tant que le 2FA n'est pas activé.</p>
    </div>
</div>
<script>
// Ne pas afficher si déjà fermé il y a moins de 24h
var dismissed = localStorage.getItem('2fa_dismissed');
if (dismissed && Date.now() - parseInt(dismissed) < 86400000) {
    document.getElementById('2fa-popup').style.display = 'none';
}
</script>
@endif
@endauth
