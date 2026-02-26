@extends('layouts.app')
@section('title', 'Tableau de bord')
 
@section('content')
<div class="max-w-7xl mx-auto px-4 py-6">
 
    {{-- En-tête --}}
    <div class="mb-6">
        <h1 class="text-2xl font-bold" style="color: var(--color-primary, #1E3A5F);">
            Bonjour, {{ Auth::user()->name }} 👋
        </h1>
        <p class="text-gray-500 text-sm">
            {{ now()->locale('fr')->isoFormat('dddd D MMMM YYYY') }}
        </p>
    </div>
 
    {{-- Cartes d'information --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
 
        {{-- Rôle de l'utilisateur --}}
        <div class="bg-white rounded-xl shadow p-4 border-l-4"
             style="border-color: var(--color-primary, #1E3A5F);">
            <p class="text-xs text-gray-400 uppercase font-medium">Rôle</p>
            <p class="text-lg font-semibold text-gray-800 mt-1">
                {{ Str::title(str_replace('_', ' ', Auth::user()->role)) }}
            </p>
        </div>
 
        {{-- Dernier accès --}}
        <div class="bg-white rounded-xl shadow p-4 border-l-4 border-gray-200">
            <p class="text-xs text-gray-400 uppercase font-medium">Dernière connexion</p>
            <p class="text-sm font-medium text-gray-800 mt-1">
                {{ Auth::user()->last_login_at?->locale('fr')->diffForHumans() ?? 'Première connexion' }}
            </p>
        </div>
 
        {{-- Statut 2FA --}}
        <div class="bg-white rounded-xl shadow p-4 border-l-4
                    {{ Auth::user()->totp_enabled ? 'border-green-400' : 'border-yellow-400' }}">
            <p class="text-xs text-gray-400 uppercase font-medium">Double authentification</p>
            <p class="text-sm font-medium mt-1
                       {{ Auth::user()->totp_enabled ? 'text-green-600' : 'text-yellow-600' }}">
                {{ Auth::user()->totp_enabled ? '✓ Activée' : '⚠ Non activée' }}
                @unless(Auth::user()->totp_enabled)
                    — <a href="{{ route('2fa.setup') }}" class="underline">Activer</a>
                @endunless
            </p>
        </div>
    </div>
 
    {{-- Modules (placeholder Phase 1) --}}
    <div class="bg-white rounded-xl shadow p-6">
        <h2 class="text-lg font-semibold text-gray-700 mb-4">Modules disponibles</h2>
        <p class="text-gray-400 text-sm italic">
            Les modules (Photothèque, GED, Agenda, Chat…) seront disponibles
            à partir de la Phase 3. Cette interface est le socle d'accueil.
        </p>
    </div>
</div>
@endsection
