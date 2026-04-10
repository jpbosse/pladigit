@extends('layouts.admin')
@section('title', 'Configuration stockage — GED')

@section('admin-content')
<div class="max-w-2xl mx-auto">

    <div class="mb-6">
        <h1 class="text-xl font-bold text-gray-800">🗄 Configuration stockage — GED</h1>
        <p class="text-sm text-gray-500 mt-1">Emplacement de stockage des documents de la Gestion Électronique de Documents.</p>
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

    {{-- Info --}}
    <div class="mb-4 p-3 bg-blue-50 border border-blue-200 text-blue-700 rounded-lg text-sm flex items-start gap-2">
        <span class="mt-0.5">ℹ</span>
        <span>
            Le stockage GED est indépendant du NAS de la photothèque.
            Vous pouvez utiliser le même serveur ou un emplacement distinct.
            Les fichiers sont organisés sous <code class="font-mono bg-blue-100 px-1 rounded">ged/{organisation}/{année}/{mois}/</code>.
        </span>
    </div>

    <form method="POST" action="{{ route('admin.settings.ged.update') }}" class="space-y-5" id="gedForm">
        @csrf @method('PUT')

        {{-- ── Choix du driver ── --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
            <h2 class="text-sm font-semibold text-gray-700 mb-4 pb-2 border-b">Type de stockage</h2>

            <div class="grid grid-cols-3 gap-3">
                @foreach(['local' => ['🖥', 'Local', 'Serveur Pladigit'], 'sftp' => ['🔐', 'SFTP', 'Linux / NAS SSH'], 'smb' => ['🪟', 'SMB/CIFS', 'Windows / NAS Samba']] as $val => [$icon, $label, $desc])
                <label class="cursor-pointer">
                    <input type="radio" name="nas_ged_driver" value="{{ $val }}"
                           {{ ($settings->nas_ged_driver ?? 'local') === $val ? 'checked' : '' }}
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
             style="{{ ($settings->nas_ged_driver ?? 'local') !== 'local' ? 'display:none' : '' }}">
            <h2 class="text-sm font-semibold text-gray-700 mb-4 pb-2 border-b">Stockage local</h2>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Chemin absolu</label>
                <input type="text" name="nas_ged_local_path"
                       value="{{ old('nas_ged_local_path', $settings->nas_ged_local_path) }}"
                       placeholder="/var/www/pladigit/storage/app/private"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-200 font-mono">
                <p class="text-xs text-gray-400 mt-1">
                    Laisser vide pour utiliser le dossier par défaut
                    (<code class="font-mono">storage/app/private</code>).
                </p>
            </div>
        </div>

        {{-- ── Champs SFTP ── --}}
        <div id="fields-sftp" class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm"
             style="{{ ($settings->nas_ged_driver ?? 'local') !== 'sftp' ? 'display:none' : '' }}">
            <h2 class="text-sm font-semibold text-gray-700 mb-4 pb-2 border-b">Connexion SFTP</h2>
            <div class="space-y-3">
                <div class="grid grid-cols-3 gap-3">
                    <div class="col-span-2">
                        <label class="block text-xs font-medium text-gray-600 mb-1">Hôte <span class="text-red-500">*</span></label>
                        <input type="text" name="nas_ged_host"
                               value="{{ old('nas_ged_host', $settings->nas_ged_host) }}"
                               placeholder="192.168.1.100 ou nas.mairie.fr"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-200">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Port</label>
                        <input type="number" name="nas_ged_port"
                               value="{{ old('nas_ged_port', $settings->nas_ged_port ?? 22) }}"
                               min="1" max="65535"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-200">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Utilisateur <span class="text-red-500">*</span></label>
                        <input type="text" name="nas_ged_username"
                               value="{{ old('nas_ged_username', $settings->nas_ged_username) }}"
                               placeholder="pladigit"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-200">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Mot de passe</label>
                        <input type="password" name="nas_ged_password"
                               placeholder="{{ $settings->nas_ged_password_enc ? '••••••••' : 'Mot de passe' }}"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-200">
                        @if($settings->nas_ged_password_enc)
                            <p class="text-xs text-gray-400 mt-0.5">Laisser vide pour conserver l'actuel.</p>
                        @endif
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Chemin racine</label>
                    <input type="text" name="nas_ged_root_path"
                           value="{{ old('nas_ged_root_path', $settings->nas_ged_root_path ?? '/') }}"
                           placeholder="/home/pladigit/ged"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-200 font-mono">
                    <p class="text-xs text-gray-400 mt-1">Dossier racine sur le serveur — tous les documents seront relatifs à ce chemin.</p>
                </div>
            </div>
        </div>

        {{-- ── Champs SMB ── --}}
        <div id="fields-smb" class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm"
             style="{{ ($settings->nas_ged_driver ?? 'local') !== 'smb' ? 'display:none' : '' }}">
            <h2 class="text-sm font-semibold text-gray-700 mb-4 pb-2 border-b">Connexion SMB/CIFS</h2>
            <div class="space-y-3">
                <div class="grid grid-cols-3 gap-3">
                    <div class="col-span-2">
                        <label class="block text-xs font-medium text-gray-600 mb-1">Hôte <span class="text-red-500">*</span></label>
                        <input type="text" name="nas_ged_host"
                               value="{{ old('nas_ged_host', $settings->nas_ged_host) }}"
                               placeholder="192.168.1.100 ou nas.mairie.fr"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-200">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Port</label>
                        <input type="number" name="nas_ged_port"
                               value="{{ old('nas_ged_port', $settings->nas_ged_port ?? 445) }}"
                               min="1" max="65535"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-200">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Utilisateur <span class="text-red-500">*</span></label>
                        <input type="text" name="nas_ged_username"
                               value="{{ old('nas_ged_username', $settings->nas_ged_username) }}"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-200">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Mot de passe</label>
                        <input type="password" name="nas_ged_password"
                               placeholder="{{ $settings->nas_ged_password_enc ? '••••••••' : 'Mot de passe' }}"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-200">
                        @if($settings->nas_ged_password_enc)
                            <p class="text-xs text-gray-400 mt-0.5">Laisser vide pour conserver l'actuel.</p>
                        @endif
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Partage réseau <span class="text-red-500">*</span></label>
                        <input type="text" name="nas_ged_share"
                               value="{{ old('nas_ged_share', $settings->nas_ged_share) }}"
                               placeholder="Documents"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-200 font-mono">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Chemin racine</label>
                        <input type="text" name="nas_ged_root_path"
                               value="{{ old('nas_ged_root_path', $settings->nas_ged_root_path ?? '') }}"
                               placeholder="GED"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-200 font-mono">
                    </div>
                </div>
            </div>
        </div>

        {{-- ── Actions ── --}}
        <div class="flex items-center gap-3">
            <button type="submit"
                    class="px-5 py-2.5 rounded-lg text-white text-sm font-medium hover:opacity-90 transition"
                    style="background-color: var(--color-primary, #1E3A5F);">
                Enregistrer
            </button>

            <button type="button" onclick="testGedConnection()"
                    id="testBtn"
                    class="px-5 py-2.5 rounded-lg border border-gray-300 text-sm font-medium text-gray-600 hover:bg-gray-50 transition">
                🔌 Tester la connexion
            </button>

            <span id="testResult" class="text-sm hidden"></span>
        </div>

    </form>
</div>

{{-- ── Section Synchronisation NAS ── --}}
<div class="mt-8 bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
    <h2 class="text-sm font-semibold text-gray-700 mb-4 pb-2 border-b">Synchronisation NAS → GED</h2>

    <div class="flex items-center gap-4 mb-4">
        <button type="button" onclick="syncGed()"
                id="syncGedBtn"
                class="px-5 py-2.5 rounded-lg border border-gray-300 text-sm font-medium text-gray-600 hover:bg-gray-50 transition">
            🔄 Synchroniser maintenant
        </button>
        <span id="syncGedResult" class="text-sm hidden"></span>
    </div>

    @if($settings->nas_ged_last_sync_at)
        <p class="text-xs text-gray-500 mb-3">
            Dernière synchronisation :
            <span class="font-medium text-gray-700">
                {{ $settings->nas_ged_last_sync_at->format('d/m/Y à H:i') }}
            </span>
        </p>
    @endif

    @if(!empty($settings->nas_ged_last_sync_errors))
        <div class="mt-3">
            <p class="text-xs font-medium text-orange-700 mb-2">
                ⚠ {{ count($settings->nas_ged_last_sync_errors) }} fichier(s) ignoré(s) lors de la dernière sync :
            </p>
            <div class="overflow-x-auto rounded border border-orange-200">
                <table class="w-full text-xs">
                    <thead class="bg-orange-50 text-orange-700">
                        <tr>
                            <th class="px-3 py-2 text-left font-medium">Fichier</th>
                            <th class="px-3 py-2 text-left font-medium">Raison</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-orange-100">
                        @foreach($settings->nas_ged_last_sync_errors as $err)
                            <tr>
                                <td class="px-3 py-1.5 font-mono text-gray-600">{{ $err['path'] ?? '—' }}</td>
                                <td class="px-3 py-1.5 text-orange-600">{{ $err['reason'] ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>

<script>
async function syncGed() {
    const btn    = document.getElementById('syncGedBtn');
    const result = document.getElementById('syncGedResult');

    btn.disabled    = true;
    btn.textContent = '⏳ Lancement…';
    result.className = 'text-sm hidden';

    try {
        const resp = await fetch('{{ route('admin.settings.ged.sync') }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                'Accept': 'application/json',
            },
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
    btn.textContent = '🔄 Synchroniser maintenant';
}
</script>

<script>
function showDriverFields(driver) {
    ['local', 'sftp', 'smb'].forEach(d => {
        const el = document.getElementById('fields-' + d);
        if (el) el.style.display = d === driver ? 'block' : 'none';
    });
}

async function testGedConnection() {
    const btn    = document.getElementById('testBtn');
    const result = document.getElementById('testResult');

    btn.disabled    = true;
    btn.textContent = '⏳ Test en cours…';
    result.className = 'text-sm hidden';

    try {
        const resp = await fetch('{{ route('admin.settings.ged.test') }}', {
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
    btn.textContent = '🔌 Tester la connexion';
}
</script>
@endsection
