@extends('layouts.admin')
@section('title', 'Sécurité & Sessions')

@section('admin-content')

    <h1 class="text-2xl font-bold text-gray-800 mb-2">Sécurité & Sessions</h1>
    <p class="text-sm text-gray-500 mb-6">
        Ces paramètres s'appliquent à tous les utilisateurs de votre organisation.
    </p>

    @if(session('success'))
        <div class="bg-green-50 border border-green-300 text-green-700 rounded-lg p-3 mb-4 text-sm">
            {{ session('success') }}
        </div>
    @endif

    <div class="bg-white rounded-xl shadow p-6">
        <form method="POST" action="{{ route('admin.settings.security.update') }}">
            @csrf @method('PUT')

            <h2 class="text-base font-semibold text-gray-700 mb-4 pb-2 border-b border-gray-100">Sessions</h2>

            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Durée de session inactive
                    <span class="text-gray-400 font-normal">(minutes)</span>
                </label>
                <input type="number" name="session_lifetime_minutes"
                       value="{{ old('session_lifetime_minutes', $settings->session_lifetime_minutes ?? 120) }}"
                       min="5" max="10080"
                       class="w-48 border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <p class="text-xs text-gray-400 mt-1">
                    Défaut : 120 min (2h). Maximum : 10 080 min (7 jours).<br>
                    L'utilisateur est déconnecté après cette période d'inactivité.
                </p>
            </div>

            <h2 class="text-base font-semibold text-gray-700 mb-4 pb-2 border-b border-gray-100">Verrouillage de compte</h2>

            <div class="grid grid-cols-2 gap-6 mb-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tentatives max avant verrouillage</label>
                    <input type="number" name="login_max_attempts"
                           value="{{ old('login_max_attempts', $settings->login_max_attempts ?? 5) }}"
                           min="3" max="20"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <p class="text-xs text-gray-400 mt-1">Entre 3 et 20 tentatives. Défaut : 5.</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Durée de verrouillage
                        <span class="text-gray-400 font-normal">(minutes)</span>
                    </label>
                    <input type="number" name="login_lockout_minutes"
                           value="{{ old('login_lockout_minutes', $settings->login_lockout_minutes ?? 15) }}"
                           min="1" max="1440"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <p class="text-xs text-gray-400 mt-1">Entre 1 et 1 440 min (24h). Défaut : 15 min.</p>
                </div>
            </div>

            @if($errors->any())
                <div class="bg-red-50 border border-red-300 text-red-700 rounded-lg p-3 mb-4 text-sm">
                    {{ $errors->first() }}
                </div>
            @endif

            <button type="submit"
                    class="px-6 py-2 rounded-lg text-white text-sm font-medium"
                    style="background-color: #1E3A5F;">
                Enregistrer
            </button>
        </form>
    </div>

    <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 text-sm text-gray-600 mt-4">
        <strong>Paramètres actifs :</strong>
        session {{ $settings->session_lifetime_minutes ?? 120 }} min —
        verrouillage après {{ $settings->login_max_attempts ?? 5 }} tentatives —
        durée {{ $settings->login_lockout_minutes ?? 15 }} min
    </div>

@endsection
