@extends('layouts.admin')
@section('title', 'Configuration SMTP')

@section('admin-content')

    <h1 class="text-2xl font-bold text-gray-800 mb-2">Configuration SMTP</h1>
    <p class="text-sm text-gray-500 mb-6">
        Laisser vide pour utiliser le serveur SMTP mutualisé de Les Bézots.<br>
        Quand configuré, tous les emails de cette organisation sont envoyés via ce serveur.
    </p>

    @if(session('success'))
        <div class="bg-green-50 border border-green-300 text-green-700 rounded-lg p-3 mb-4 text-sm">
            {{ session('success') }}
        </div>
    @endif

    <div class="bg-white rounded-xl shadow p-6 mb-4">
        <form method="POST" action="{{ route('admin.settings.smtp.update') }}">
            @csrf @method('PUT')

            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Serveur SMTP</label>
                    <input type="text" name="smtp_host" value="{{ old('smtp_host', $org->smtp_host) }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"
                           placeholder="smtp.exemple.fr">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Port</label>
                    <input type="number" name="smtp_port" value="{{ old('smtp_port', $org->smtp_port ?? 587) }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Chiffrement</label>
                <select name="smtp_encryption" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="tls"   {{ ($org->smtp_encryption ?? 'tls') === 'tls'   ? 'selected' : '' }}>STARTTLS — port 587 (recommandé)</option>
                    <option value="smtps" {{ ($org->smtp_encryption ?? '') === 'smtps' ? 'selected' : '' }}>SSL/TLS — port 465</option>
                    <option value="none"  {{ ($org->smtp_encryption ?? '') === 'none'  ? 'selected' : '' }}>Aucun chiffrement (déconseillé)</option>
                </select>
            </div>

            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Utilisateur SMTP</label>
                    <input type="text" name="smtp_user" value="{{ old('smtp_user', $org->smtp_user) }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Mot de passe
                        <span class="text-gray-400 font-normal">(vide = inchangé)</span>
                    </label>
                    <input type="password" name="smtp_password"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"
                           autocomplete="new-password">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4 mb-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Adresse expéditeur</label>
                    <input type="email" name="smtp_from_address" value="{{ old('smtp_from_address', $org->smtp_from_address) }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"
                           placeholder="noreply@mairie-exemple.fr">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nom expéditeur</label>
                    <input type="text" name="smtp_from_name" value="{{ old('smtp_from_name', $org->smtp_from_name) }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"
                           placeholder="{{ $org->name }}">
                </div>
            </div>

            @if($errors->any())
                <div class="bg-red-50 border border-red-300 text-red-700 rounded-lg p-3 mb-4 text-sm">
                    {{ $errors->first() }}
                </div>
            @endif

            <div class="flex items-center gap-3">
                <button type="submit"
                        class="px-6 py-2 rounded-lg text-white text-sm font-medium"
                        style="background-color: #1E3A5F;">
                    Enregistrer
                </button>
                @if($org->smtp_host)
                    <button type="button" id="btn-test-smtp"
                            class="px-4 py-2 rounded-lg border border-gray-300 text-gray-700 text-sm font-medium hover:bg-gray-50">
                        Tester la connexion
                    </button>
                    <span id="smtp-test-result" class="text-sm hidden"></span>
                @endif
            </div>
        </form>
    </div>

    <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 text-sm text-gray-600">
        @if($org->smtp_host)
            <span class="inline-flex items-center gap-1 text-green-700 font-medium">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                SMTP personnalisé actif
            </span>
            — Emails envoyés via <strong>{{ $org->smtp_host }}:{{ $org->smtp_port }}</strong>.
        @else
            <span class="text-gray-500">⚙ SMTP mutualisé Les Bézots (par défaut)</span>
        @endif
    </div>

@endsection

@push('scripts')
<script>
document.getElementById('btn-test-smtp')?.addEventListener('click', async function () {
    const btn = this;
    const result = document.getElementById('smtp-test-result');
    btn.disabled = true;
    btn.textContent = 'Test en cours…';
    result.textContent = '';
    result.classList.remove('hidden');
    try {
        const res = await fetch('{{ route('admin.settings.smtp.test') }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            },
        });
        const data = await res.json();
        result.textContent = data.message;
        result.className = 'text-sm ' + (data.ok ? 'text-green-600' : 'text-red-600');
    } catch (e) {
        result.textContent = 'Erreur réseau.';
        result.className = 'text-sm text-red-600';
    } finally {
        btn.disabled = false;
        btn.textContent = 'Tester la connexion';
    }
});
</script>
@endpush
