@extends('layouts.admin')
@section('title', 'Configuration NAS — Photothèque')

@section('admin-content')
<div class="max-w-2xl mx-auto">

    <div class="mb-6">
        <h1 class="text-xl font-bold text-gray-800">🖧 Configuration NAS — Photothèque</h1>
        <p class="text-sm text-gray-500 mt-1">Connexion au serveur de fichiers pour les médias de l'organisation.</p>
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

    {{-- Statut dernière sync --}}
    @if($settings->nas_photo_last_sync_at)
    <div class="mb-4 p-3 bg-blue-50 border border-blue-200 text-blue-700 rounded-lg text-sm flex items-center gap-2">
        <span>🔄</span>
        Dernière synchronisation : {{ $settings->nas_photo_last_sync_at->diffForHumans() }}
        ({{ $settings->nas_photo_last_sync_at->format('d/m/Y H:i') }})
    </div>
    @endif

    <form method="POST" action="{{ route('admin.settings.nas.update') }}" class="space-y-5" id="nasForm">
        @csrf @method('PUT')

        {{-- ── Choix du driver ── --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
            <h2 class="text-sm font-semibold text-gray-700 mb-4 pb-2 border-b">Type de connexion</h2>

            <div class="grid grid-cols-3 gap-3">
                @foreach(['local' => ['🖥', 'Local', 'Développement / test'], 'sftp' => ['🔐', 'SFTP', 'Linux / NAS SSH'], 'smb' => ['🪟', 'SMB/CIFS', 'Windows / NAS Samba']] as $val => [$icon, $label, $desc])
                <label class="cursor-pointer">
                    <input type="radio" name="nas_photo_driver" value="{{ $val }}"
                           {{ ($settings->nas_photo_driver ?? 'local') === $val ? 'checked' : '' }}
                           class="sr-only peer"
                           onchange="showDriverFields(this.value)">
                    <div class="p-3 rounded-lg border-2 border-gray-200 peer-checked:border-blue-600 peer-checked:bg-blue-50 transition-all text-center">
                        <div class="text-2xl mb-1">{{ $icon }}</div>
                        <div class="text-sm font-semibold text-gray-700">{{ $label }}</div>
                        <div class="text-xs text-gray-400">{{ $desc }}</div>
                    </div>
                </label>
                @endforeach
            </div>
        </div>

        {{-- ── Champs Local ── --}}
        <div id="fields-local" class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm"
             style="{{ ($settings->nas_photo_driver ?? 'local') !== 'local' ? 'display:none' : '' }}">
            <h2 class="text-sm font-semibold text-gray-700 mb-4 pb-2 border-b">Chemin local</h2>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Chemin absolu</label>
                <input type="text" name="nas_photo_local_path"
                       value="{{ old('nas_photo_local_path', $settings->nas_photo_local_path) }}"
                       placeholder="/var/www/pladigit/storage/app/nas_simulation"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-200 font-mono">
                <p class="text-xs text-gray-400 mt-1">Laisser vide pour utiliser le dossier de simulation par défaut.</p>
            </div>
        </div>

        {{-- ── Champs SFTP ── --}}
        <div id="fields-sftp" class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm"
             style="{{ ($settings->nas_photo_driver ?? 'local') !== 'sftp' ? 'display:none' : '' }}">
            <h2 class="text-sm font-semibold text-gray-700 mb-4 pb-2 border-b">Connexion SFTP</h2>
            <div class="space-y-3">
                <div class="grid grid-cols-3 gap-3">
                    <div class="col-span-2">
                        <label class="block text-xs font-medium text-gray-600 mb-1">Hôte <span class="text-red-500">*</span></label>
                        <input type="text" name="nas_photo_host"
                               value="{{ old('nas_photo_host', $settings->nas_photo_host) }}"
                               placeholder="192.168.1.100 ou nas.mairie.fr"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-200">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Port</label>
                        <input type="number" name="nas_photo_port"
                               value="{{ old('nas_photo_port', $settings->nas_photo_port ?? 22) }}"
                               min="1" max="65535"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-200">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Utilisateur <span class="text-red-500">*</span></label>
                        <input type="text" name="nas_photo_username"
                               value="{{ old('nas_photo_username', $settings->nas_photo_username) }}"
                               placeholder="pladigit"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-200">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Mot de passe</label>
                        <input type="password" name="nas_photo_password"
                               placeholder="{{ $settings->nas_photo_password_enc ? '••••••••' : 'Mot de passe' }}"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-200">
                        @if($settings->nas_photo_password_enc)
                            <p class="text-xs text-gray-400 mt-0.5">Laisser vide pour conserver l'actuel.</p>
                        @endif
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Chemin racine</label>
                    <input type="text" name="nas_photo_root_path"
                           value="{{ old('nas_photo_root_path', $settings->nas_photo_root_path ?? '/') }}"
                           placeholder="/home/pladigit/photos"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-200 font-mono">
                    <p class="text-xs text-gray-400 mt-1">Dossier racine sur le NAS — tous les fichiers seront relatifs à ce chemin.</p>
                </div>
            </div>
        </div>

        {{-- ── Champs SMB ── --}}
        <div id="fields-smb" class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm"
             style="{{ ($settings->nas_photo_driver ?? 'local') !== 'smb' ? 'display:none' : '' }}">
            <h2 class="text-sm font-semibold text-gray-700 mb-4 pb-2 border-b">Connexion SMB/CIFS</h2>
            <div class="p-4 bg-amber-50 border border-amber-200 rounded-lg text-sm text-amber-700">
                ⚠️ Le driver SMB sera disponible en Phase 4. Utilisez SFTP pour le moment.
            </div>
        </div>

        {{-- ── Synchronisation ── --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
            <h2 class="text-sm font-semibold text-gray-700 mb-4 pb-2 border-b">Synchronisation automatique</h2>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Intervalle (minutes)</label>
                <select name="nas_photo_sync_interval_minutes"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-200">
                    @foreach([15 => '15 minutes', 30 => '30 minutes', 60 => '1 heure', 120 => '2 heures', 360 => '6 heures', 720 => '12 heures', 1440 => '24 heures'] as $val => $label)
                        <option value="{{ $val }}" {{ ($settings->nas_photo_sync_interval_minutes ?? 60) == $val ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
                <p class="text-xs text-gray-400 mt-1">Sync légère (nouveaux fichiers par date). La sync SHA-256 tourne quotidiennement à 23h30.</p>
            </div>
        </div>

        {{-- ── Sync manuelle ── --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
            <h2 class="text-sm font-semibold text-gray-700 mb-3 pb-2 border-b">Synchronisation manuelle</h2>
            <div class="flex items-center gap-3">
                <button type="button" onclick="runSync(false)"
                        class="px-4 py-2 rounded-lg border border-gray-300 text-sm font-medium text-gray-600 hover:bg-gray-50 transition">
                    🔄 Sync légère
                </button>
                <button type="button" onclick="runSync(true)"
                        class="px-4 py-2 rounded-lg border border-gray-300 text-sm font-medium text-gray-600 hover:bg-gray-50 transition">
                    🔍 Sync SHA-256
                </button>
                <span id="syncResult" class="text-sm hidden"></span>
            </div>
            <p class="text-xs text-gray-400 mt-2">La sync légère détecte les nouveaux fichiers. La sync SHA-256 vérifie l'intégrité complète (plus lente).</p>
        </div>

        {{-- ── Actions ── --}}
        <div class="flex items-center gap-3">
            <button type="submit"
                    class="px-5 py-2.5 rounded-lg text-white text-sm font-medium hover:opacity-90 transition"
                    style="background-color: var(--color-primary, #1E3A5F);">
                Enregistrer
            </button>

            <button type="button" onclick="testNasConnection()"
                    id="testBtn"
                    class="px-5 py-2.5 rounded-lg border border-gray-300 text-sm font-medium text-gray-600 hover:bg-gray-50 transition">
                🔌 Tester la connexion
            </button>

            <span id="testResult" class="text-sm hidden"></span>
        </div>
    </form>

</div>

<script>
function showDriverFields(driver) {
    ['local', 'sftp', 'smb'].forEach(d => {
        const el = document.getElementById('fields-' + d);
        if (el) el.style.display = d === driver ? 'block' : 'none';
    });
}

async function testNasConnection() {
    const btn = document.getElementById('testBtn');
    const result = document.getElementById('testResult');
    btn.disabled = true;
    btn.textContent = '⏳ Test en cours…';
    result.className = 'text-sm hidden';

    try {
        const resp = await fetch('{{ route('media.nas.test') }}', {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await resp.json();
        result.textContent = data.ok ? '✅ ' + data.message : '❌ ' + data.message;
        result.className = 'text-sm ' + (data.ok ? 'text-green-600' : 'text-red-600');
    } catch (e) {
        result.textContent = '❌ Erreur réseau';
        result.className = 'text-sm text-red-600';
    }

    result.classList.remove('hidden');
    btn.disabled = false;
    btn.textContent = '🔌 Tester la connexion';
}

async function runSync(deep) {
    const result = document.getElementById('syncResult');
    result.textContent = '⏳ Synchronisation en cours…';
    result.className = 'text-sm text-gray-500';
    result.classList.remove('hidden');

    try {
        const resp = await fetch('{{ route('admin.settings.nas.sync') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ deep }),
        });
        const data = await resp.json();
        result.textContent = data.ok ? '✅ ' + data.message : '❌ ' + data.message;
        result.className = 'text-sm ' + (data.ok ? 'text-green-600' : 'text-red-600');
    } catch (e) {
        result.textContent = '❌ Erreur réseau';
        result.className = 'text-sm text-red-600';
    }
}
</script>
@endsection
