@extends('layouts.admin')
@section('title', 'Configuration — Collabora Online')

@section('admin-content')
<div class="max-w-2xl mx-auto">

    <div class="mb-6">
        <h1 class="text-xl font-bold text-gray-800">Configuration Collabora Online</h1>
        <p class="text-sm text-gray-500 mt-1">Paramètres de connexion à l'instance Collabora pour l'édition de documents en ligne.</p>
    </div>

    {{-- Alertes --}}
    @if(session('success'))
        <div class="mb-4 p-3 bg-green-50 border border-green-200 text-green-800 rounded-lg text-sm flex items-center gap-2">
            <span>✓</span> {{ session('success') }}
        </div>
    @endif
    @if($errors->any())
        <div class="mb-4 p-3 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm">
            {{ $errors->first() }}
        </div>
    @endif

    {{-- Avertissement --}}
    <div class="mb-4 p-4 bg-red-50 border border-red-300 text-red-800 rounded-lg text-sm flex items-start gap-3">
        <span class="text-lg mt-0.5">⚠</span>
        <span>
            <strong class="font-bold">Attention — paramètres techniques avancés.</strong>
            Ces valeurs modifient la connexion entre Pladigit et le serveur Collabora Online.
            <strong class="font-bold">Si vous ne savez pas ce que vous faites, ne touchez à rien.</strong>
            Une mauvaise configuration peut rendre l'édition de documents inutilisable pour tous les utilisateurs.
            <br class="mt-1">
            <span class="text-red-600 text-xs">
                Laisser les champs vides conserve la configuration serveur par défaut.
                @if(config('collabora.url'))
                    URL active : <code class="font-mono bg-red-100 px-1 rounded">{{ config('collabora.url') }}</code>
                @endif
            </span>
        </span>
    </div>

    <form method="POST" action="{{ route('admin.settings.collabora.update') }}" class="space-y-5">
        @csrf @method('PUT')

        {{-- ── URL Collabora ── --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
            <h2 class="text-sm font-semibold text-gray-700 mb-4 pb-2 border-b">Instance Collabora Online</h2>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">URL de l'instance Collabora</label>
                <input type="url" name="collabora_url"
                       value="{{ old('collabora_url', $settings->collabora_url) }}"
                       placeholder="https://collabora.mairie.fr"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-200 font-mono">
                <p class="text-xs text-gray-400 mt-1">
                    URL complète sans slash final. Doit être accessible depuis le navigateur <em>et</em> depuis le serveur Pladigit.
                </p>
            </div>
        </div>

        {{-- ── URL WOPI ── --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
            <h2 class="text-sm font-semibold text-gray-700 mb-4 pb-2 border-b">URL WOPI de base</h2>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">URL WOPI (optionnel)</label>
                <input type="url" name="wopi_url"
                       value="{{ old('wopi_url', $settings->wopi_url) }}"
                       placeholder="{{ config('app.url', 'https://pladigit.fr') }}"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-200 font-mono">
                <p class="text-xs text-gray-400 mt-1">
                    URL de base pour construire <code class="font-mono">WOPISrc</code> — doit être accessible depuis le serveur Collabora.
                    Laisser vide pour utiliser <code class="font-mono">APP_URL</code>.
                </p>
            </div>
        </div>

        {{-- ── TTL Session ── --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
            <h2 class="text-sm font-semibold text-gray-700 mb-4 pb-2 border-b">Durée de session</h2>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">TTL du token WOPI (minutes)</label>
                <div class="flex items-center gap-3">
                    <input type="number" name="collabora_token_ttl_minutes"
                           value="{{ old('collabora_token_ttl_minutes', $settings->collabora_token_ttl_minutes ?? 240) }}"
                           min="5" max="10080"
                           class="w-32 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-200 text-center">
                    <span class="text-sm text-gray-500">minutes</span>
                    <span class="text-xs text-gray-400">(min : 5 min — max : 7 jours)</span>
                </div>
                <p class="text-xs text-gray-400 mt-1">
                    Durée de validité du token d'accès envoyé à Collabora. Défaut serveur : {{ round(config('collabora.token_ttl', 14400) / 60) }} min ({{ round(config('collabora.token_ttl', 14400) / 3600) }}h).
                </p>
            </div>
        </div>

        {{-- ── Actions ── --}}
        <div class="flex items-center gap-3">
            <button type="submit"
                    class="px-5 py-2.5 rounded-lg text-white text-sm font-medium hover:opacity-90 transition"
                    style="background-color: var(--color-primary, #1E3A5F);">
                Enregistrer
            </button>

            <button type="button" onclick="testCollabora()"
                    id="testBtn"
                    class="px-5 py-2.5 rounded-lg border border-gray-300 text-sm font-medium text-gray-600 hover:bg-gray-50 transition">
                Tester la connexion
            </button>

            <span id="testResult" class="text-sm hidden"></span>
        </div>

    </form>
</div>

<script>
async function testCollabora() {
    const btn    = document.getElementById('testBtn');
    const result = document.getElementById('testResult');

    btn.disabled    = true;
    btn.textContent = '⏳ Test en cours…';
    result.className = 'text-sm hidden';

    try {
        const resp = await fetch('{{ route('admin.settings.collabora.test') }}', {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await resp.json();
        result.textContent = data.ok ? '✅ ' + data.message : '❌ ' + data.message;
        result.className   = 'text-sm ' + (data.ok ? 'text-green-600' : 'text-red-600');
    } catch (e) {
        result.textContent = '❌ Erreur réseau';
        result.className   = 'text-sm text-red-600';
    }

    result.classList.remove('hidden');
    btn.disabled    = false;
    btn.textContent = 'Tester la connexion';
}
</script>
@endsection
