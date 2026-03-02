@extends('layouts.app')
@section('title', 'Tableau de bord')

@section('content')
<div class="max-w-7xl mx-auto px-4 py-6">

    {{-- En-tête --}}
    <div class="flex justify-between items-start mb-6">
        <div>
            <h1 class="text-2xl font-bold" style="color: var(--color-primary, #1E3A5F);">
                Bonjour, {{ Auth::user()->name }} 👋
            </h1>
            <p class="text-gray-500 text-sm mt-1">
                {{ now()->locale('fr')->isoFormat('dddd D MMMM YYYY') }}
                — {{ $org->name }}
            </p>
        </div>
        @if(Auth::user()->role === 'admin')
        <a href="{{ route('admin.users.index') }}"
           class="px-4 py-2 rounded-lg text-white text-sm font-medium flex items-center gap-2"
           style="background-color: #1E3A5F;">
            ⚙ Administration
        </a>
        @endif
    </div>

    {{-- Alertes --}}
    @unless(Auth::user()->totp_enabled)
    <div class="bg-yellow-50 border border-yellow-300 text-yellow-800 rounded-xl p-4 mb-6 flex justify-between items-center">
        <div>
            <p class="font-medium text-sm">⚠ Double authentification non activée</p>
            <p class="text-xs mt-1 text-yellow-700">Renforcez la sécurité de votre compte en activant le 2FA.</p>
        </div>
        <a href="{{ route('2fa.setup') }}"
           class="px-4 py-2 rounded-lg bg-yellow-600 text-white text-sm hover:bg-yellow-700">
            Activer le 2FA
        </a>
    </div>
    @endunless

    {{-- Cartes stats (admin uniquement) --}}
    @if(Auth::user()->role === 'admin')
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-xl shadow p-4 border-l-4" style="border-color: #1E3A5F;">
            <p class="text-xs text-gray-400 uppercase font-medium">Utilisateurs total</p>
            <p class="text-3xl font-bold mt-1" style="color: #1E3A5F;">{{ $totalUsers }}</p>
            <p class="text-xs text-gray-400 mt-1">/ {{ $org->max_users }} max</p>
        </div>
        <div class="bg-white rounded-xl shadow p-4 border-l-4 border-green-400">
            <p class="text-xs text-gray-400 uppercase font-medium">Actifs</p>
            <p class="text-3xl font-bold text-green-600 mt-1">{{ $activeUsers }}</p>
            <p class="text-xs text-gray-400 mt-1">comptes actifs</p>
        </div>
        <div class="bg-white rounded-xl shadow p-4 border-l-4 border-blue-400">
            <p class="text-xs text-gray-400 uppercase font-medium">LDAP / AD</p>
            <p class="text-3xl font-bold text-blue-600 mt-1">{{ $ldapUsers }}</p>
            <p class="text-xs text-gray-400 mt-1">comptes annuaire</p>
        </div>
        <div class="bg-white rounded-xl shadow p-4 border-l-4 border-purple-400">
            <p class="text-xs text-gray-400 uppercase font-medium">Administrateurs</p>
            <p class="text-3xl font-bold text-purple-600 mt-1">{{ $adminUsers }}</p>
            <p class="text-xs text-gray-400 mt-1">comptes admin</p>
        </div>
    </div>
    @endif

    {{-- Infos utilisateur --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="bg-white rounded-xl shadow p-4 border-l-4" style="border-color: var(--color-primary, #1E3A5F);">
            <p class="text-xs text-gray-400 uppercase font-medium">Rôle</p>
            <p class="text-lg font-semibold text-gray-800 mt-1">
                {{ App\Enums\UserRole::tryFrom(Auth::user()->role)?->label() ?? Auth::user()->role }}
            </p>
            @if(Auth::user()->department)
            <p class="text-xs text-gray-400 mt-1">{{ Auth::user()->department }}</p>
            @endif
        </div>

        <div class="bg-white rounded-xl shadow p-4 border-l-4 border-gray-200">
            <p class="text-xs text-gray-400 uppercase font-medium">Dernière connexion</p>
            <p class="text-sm font-medium text-gray-800 mt-1">
                {{ Auth::user()->last_login_at?->locale('fr')->diffForHumans() ?? 'Première connexion' }}
            </p>
            @if(Auth::user()->last_login_ip)
            <p class="text-xs text-gray-400 mt-1">depuis {{ Auth::user()->last_login_ip }}</p>
            @endif
        </div>

        <div class="bg-white rounded-xl shadow p-4 border-l-4
                    {{ Auth::user()->totp_enabled ? 'border-green-400' : 'border-yellow-400' }}">
            <p class="text-xs text-gray-400 uppercase font-medium">Double authentification</p>
            <p class="text-sm font-medium mt-1 {{ Auth::user()->totp_enabled ? 'text-green-600' : 'text-yellow-600' }}">
                {{ Auth::user()->totp_enabled ? '✓ Activée' : '⚠ Non activée' }}
            </p>
            <a href="{{ route('2fa.setup') }}" class="text-xs underline text-gray-400 mt-1 block">
                {{ Auth::user()->totp_enabled ? 'Gérer' : 'Activer' }}
            </a>
        </div>
    </div>

    {{-- Modules --}}
    <div class="mb-2">
        <h2 class="text-lg font-semibold text-gray-700 mb-4">Modules</h2>
    </div>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">

        @php
        $modules = [
            ['icon' => '📁', 'name' => 'GED', 'desc' => 'Gestion documentaire', 'phase' => 3, 'color' => '#1E3A5F'],
            ['icon' => '🖼', 'name' => 'Photothèque', 'desc' => 'Médias & albums', 'phase' => 3, 'color' => '#2563EB'],
            ['icon' => '✅', 'name' => 'Projets', 'desc' => 'Tâches & suivi', 'phase' => 5, 'color' => '#16A34A'],
            ['icon' => '📅', 'name' => 'Agenda', 'desc' => 'Événements', 'phase' => 6, 'color' => '#9333EA'],
            ['icon' => '💬', 'name' => 'Chat', 'desc' => 'Messagerie temps réel', 'phase' => 7, 'color' => '#EA580C'],
            ['icon' => '📊', 'name' => 'Sondages', 'desc' => 'Formulaires & votes', 'phase' => 8, 'color' => '#0891B2'],
            ['icon' => '🗄', 'name' => 'ERP', 'desc' => 'Données métier', 'phase' => 9, 'color' => '#B45309'],
            ['icon' => '📰', 'name' => 'Actualités', 'desc' => 'Flux RSS', 'phase' => 10, 'color' => '#475569'],
        ];
        @endphp

        @foreach($modules as $module)
        <div class="bg-white rounded-xl shadow p-4 opacity-60 cursor-not-allowed relative overflow-hidden">
            <div class="absolute top-2 right-2">
                <span class="text-xs bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full">
                    Phase {{ $module['phase'] }}
                </span>
            </div>
            <div class="text-3xl mb-2">{{ $module['icon'] }}</div>
            <p class="font-semibold text-gray-700 text-sm">{{ $module['name'] }}</p>
            <p class="text-xs text-gray-400 mt-0.5">{{ $module['desc'] }}</p>
            <p class="text-xs text-gray-300 mt-2">À venir</p>
        </div>
        @endforeach
    </div>

    {{-- Plan --}}
    <div class="mt-6 bg-white rounded-xl shadow p-4 flex justify-between items-center">
        <div>
            <p class="text-xs text-gray-400 uppercase font-medium">Plan actuel</p>
            <p class="font-semibold text-gray-800 mt-1">{{ ucfirst($org->plan) }}</p>
        </div>
        <div class="text-right">
            <p class="text-xs text-gray-400">Utilisateurs</p>
            <p class="text-sm font-medium text-gray-700">{{ $activeUsers }} / {{ $org->max_users }}</p>
            <div class="w-32 bg-gray-200 rounded-full h-1.5 mt-1">
                <div class="h-1.5 rounded-full" style="background-color:#1E3A5F; width: {{ min(100, ($activeUsers / $org->max_users) * 100) }}%"></div>
            </div>
        </div>
    </div>

</div>
@endsection
