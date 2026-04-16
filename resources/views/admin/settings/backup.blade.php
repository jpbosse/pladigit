@extends('layouts.admin')
@section('title', 'Configuration — Sauvegarde')

@section('admin-content')
<div class="max-w-2xl mx-auto">

    <div class="mb-6">
        <h1 class="text-xl font-bold text-gray-800">Sauvegarde des données</h1>
        <p class="text-sm text-gray-500 mt-1">Configure la sauvegarde automatique des bases de données, des documents GED et des médias.</p>
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

    {{-- ── Statut dernière sauvegarde ── --}}
    <div class="mb-5 bg-white rounded-xl border border-gray-200 p-5 shadow-sm" id="statusCard">
        <h2 class="text-sm font-semibold text-gray-700 mb-3 pb-2 border-b">Dernière sauvegarde</h2>
        @php
            $lastStatus = $settings->backup_last_status;
            $lastRun    = $settings->backup_last_run_at;
        @endphp

        @if(! $lastRun)
            <p class="text-sm text-gray-400 italic">Aucune sauvegarde effectuée.</p>
        @else
            <div class="flex items-center gap-3">
                @if($lastStatus === 'success')
                    <span class="text-green-600 font-semibold text-sm">✓ Succès</span>
                @elseif($lastStatus === 'running')
                    <span class="text-blue-600 font-semibold text-sm">⏳ En cours…</span>
                @else
                    <span class="text-red-600 font-semibold text-sm">✗ Échec</span>
                @endif
                <span class="text-sm text-gray-500">{{ $lastRun->format('d/m/Y à H:i:s') }}</span>
                @if($settings->backupHumanSize())
                    <span class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full">{{ $settings->backupHumanSize() }}</span>
                @endif
            </div>
            @if($settings->backup_last_message)
                <p class="text-xs text-gray-500 mt-1 font-mono">{{ $settings->backup_last_message }}</p>
            @endif
        @endif

        {{-- Bouton Lancer la sauvegarde --}}
        <div class="mt-4 flex items-center gap-3">
            <button type="button" onclick="runBackup()"
                    id="runBtn"
                    class="px-5 py-2.5 rounded-lg text-white text-sm font-medium hover:opacity-90 transition"
                    style="background-color: var(--color-primary, #1E3A5F);">
                ▶ Lancer la sauvegarde
            </button>
            <span id="runResult" class="text-sm hidden"></span>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.settings.backup.update') }}" class="space-y-5">
        @csrf @method('PUT')

        {{-- ── Contenu sauvegardé ── --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
            <h2 class="text-sm font-semibold text-gray-700 mb-3 pb-2 border-b">Contenu sauvegardé</h2>
            <ul class="space-y-1 text-sm text-gray-600">
                <li class="flex items-center gap-2"><span class="text-green-500">✓</span> Base de données platform (organisations, modules)</li>
                <li class="flex items-center gap-2"><span class="text-green-500">✓</span> Base de données tenant (utilisateurs, projets, GED, médias)</li>
                <li class="flex items-center gap-2"><span class="text-green-500">✓</span> Fichiers GED (<code class="text-xs bg-gray-100 px-1 rounded">storage/app/private/ged/</code>)</li>
                <li class="flex items-center gap-2"><span class="text-green-500">✓</span> Médias NAS (dossier de stockage local configuré)</li>
                <li class="flex items-center gap-2"><span class="text-green-500">✓</span> Fichier <code class="text-xs bg-gray-100 px-1 rounded">.env</code></li>
            </ul>
            <p class="text-xs text-gray-400 mt-3">
                L'archive créée est nommée <code class="font-mono">backup_YYYY-MM-DD_HHmmss_slug.tar.gz</code>.
            </p>
        </div>

        {{-- ── Activation et planification ── --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
            <h2 class="text-sm font-semibold text-gray-700 mb-4 pb-2 border-b">Planification</h2>

            <div class="flex items-center gap-3 mb-4">
                <input type="hidden" name="backup_enabled" value="0">
                <input type="checkbox" name="backup_enabled" value="1" id="backup_enabled"
                       {{ $settings->backup_enabled ? 'checked' : '' }}
                       class="w-4 h-4 rounded border-gray-300">
                <label for="backup_enabled" class="text-sm text-gray-700 font-medium">Activer la sauvegarde automatique</label>
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Fréquence</label>
                <select name="backup_schedule"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-200">
                    <option value="hourly" {{ ($settings->backup_schedule ?? 'daily') === 'hourly' ? 'selected' : '' }}>Toutes les heures</option>
                    <option value="daily"  {{ ($settings->backup_schedule ?? 'daily') === 'daily'  ? 'selected' : '' }}>Une fois par jour (minuit)</option>
                    <option value="weekly" {{ ($settings->backup_schedule ?? 'daily') === 'weekly' ? 'selected' : '' }}>Une fois par semaine (dimanche minuit)</option>
                </select>
            </div>
        </div>

        {{-- ── Destination ── --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
            <h2 class="text-sm font-semibold text-gray-700 mb-4 pb-2 border-b">Destination</h2>

            <div class="mb-4">
                <label class="block text-xs font-medium text-gray-600 mb-1">Type de destination</label>
                <select name="backup_driver" id="backup_driver"
                        onchange="toggleDriver(this.value)"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-200">
                    <option value="local" {{ ($settings->backup_driver ?? 'local') === 'local' ? 'selected' : '' }}>Chemin local (même serveur)</option>
                    <option value="sftp"  {{ ($settings->backup_driver ?? 'local') === 'sftp'  ? 'selected' : '' }}>SFTP — NAS distant</option>
                </select>
                <p class="text-xs text-gray-400 mt-1">
                    SFTP : idéal pour sauvegarder vers un NAS Synology, QNAP ou un serveur Linux.
                    Nécessite <code class="font-mono">php8.4-ssh2</code> sur le serveur.
                    @if(! function_exists('ssh2_connect'))
                        <span class="text-amber-600 font-semibold">⚠ Extension non installée actuellement.</span>
                        Commande : <code class="font-mono">sudo apt install php8.4-ssh2 &amp;&amp; sudo systemctl restart php8.4-fpm</code>
                    @endif
                </p>
            </div>

            {{-- Local --}}
            <div id="section_local" class="{{ ($settings->backup_driver ?? 'local') !== 'local' ? 'hidden' : '' }}">
                <label class="block text-xs font-medium text-gray-600 mb-1">Chemin de destination</label>
                <input type="text" name="backup_local_path"
                       value="{{ old('backup_local_path', $settings->backup_local_path) }}"
                       placeholder="/mnt/backup ou /srv/backups/pladigit"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-200 font-mono">
                <p class="text-xs text-gray-400 mt-1">Chemin absolu sur le serveur. Le répertoire est créé s'il n'existe pas.</p>
            </div>

            {{-- SFTP --}}
            <div id="section_sftp" class="{{ ($settings->backup_driver ?? 'local') !== 'sftp' ? 'hidden' : '' }} space-y-3">
                <div class="grid grid-cols-3 gap-3">
                    <div class="col-span-2">
                        <label class="block text-xs font-medium text-gray-600 mb-1">Hôte / IP</label>
                        <input type="text" name="backup_sftp_host"
                               value="{{ old('backup_sftp_host', $settings->backup_sftp_host) }}"
                               placeholder="192.168.1.100 ou nas.mairie.fr"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-200 font-mono">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Port</label>
                        <input type="number" name="backup_sftp_port"
                               value="{{ old('backup_sftp_port', $settings->backup_sftp_port ?? 22) }}"
                               min="1" max="65535"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-200 text-center">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Utilisateur</label>
                        <input type="text" name="backup_sftp_user"
                               value="{{ old('backup_sftp_user', $settings->backup_sftp_user) }}"
                               placeholder="backup"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-200">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Mot de passe</label>
                        <input type="password" name="backup_sftp_password"
                               placeholder="{{ $settings->backup_sftp_password_enc ? '••••••••' : 'Mot de passe' }}"
                               autocomplete="new-password"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-200">
                        <p class="text-xs text-gray-400 mt-0.5">Laisser vide pour conserver l'actuel.</p>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Chemin distant</label>
                    <input type="text" name="backup_sftp_path"
                           value="{{ old('backup_sftp_path', $settings->backup_sftp_path ?? '/backup') }}"
                           placeholder="/volume1/backup/pladigit"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-200 font-mono">
                    <p class="text-xs text-gray-400 mt-1">Chemin absolu sur le NAS. Créé automatiquement s'il n'existe pas.</p>
                </div>

                <button type="button" onclick="testSftp()"
                        id="testSftpBtn"
                        class="px-4 py-2 rounded-lg border border-gray-300 text-sm font-medium text-gray-600 hover:bg-gray-50 transition">
                    Tester la connexion SFTP
                </button>
                <span id="testSftpResult" class="text-sm hidden ml-2"></span>
            </div>
        </div>

        {{-- ── Rétention ── --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
            <h2 class="text-sm font-semibold text-gray-700 mb-4 pb-2 border-b">Rétention</h2>
            <div class="flex items-center gap-3">
                <input type="number" name="backup_retention_count"
                       value="{{ old('backup_retention_count', $settings->backup_retention_count ?? 7) }}"
                       min="1" max="90"
                       class="w-24 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-200 text-center">
                <span class="text-sm text-gray-500">archives conservées</span>
            </div>
            <p class="text-xs text-gray-400 mt-1">
                Les archives les plus anciennes au-delà de ce seuil sont supprimées automatiquement (destination locale uniquement).
            </p>
        </div>

        {{-- ── Actions ── --}}
        <div class="flex items-center gap-3">
            <button type="submit"
                    class="px-5 py-2.5 rounded-lg text-white text-sm font-medium hover:opacity-90 transition"
                    style="background-color: var(--color-primary, #1E3A5F);">
                Enregistrer
            </button>
        </div>

    </form>
</div>

<script>
function toggleDriver(value) {
    document.getElementById('section_local').classList.toggle('hidden', value !== 'local');
    document.getElementById('section_sftp').classList.toggle('hidden',  value !== 'sftp');
}

async function testSftp() {
    const btn    = document.getElementById('testSftpBtn');
    const result = document.getElementById('testSftpResult');
    btn.disabled    = true;
    btn.textContent = '⏳ Test…';
    result.className = 'text-sm hidden';

    try {
        const resp = await fetch('{{ route('admin.settings.backup.test-sftp') }}', {
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
    btn.textContent = 'Tester la connexion SFTP';
}

async function runBackup() {
    const btn    = document.getElementById('runBtn');
    const result = document.getElementById('runResult');

    btn.disabled    = true;
    btn.textContent = '⏳ Lancement…';
    result.className = 'text-sm hidden';

    try {
        const resp = await fetch('{{ route('admin.settings.backup.run') }}', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? ''
            }
        });
        const data = await resp.json();
        result.textContent = data.ok ? '✅ ' + data.message : '❌ ' + data.message;
        result.className   = 'text-sm ' + (data.ok ? 'text-green-600' : 'text-red-600');

        if (data.ok) {
            // Sondage du statut toutes les 3s pendant 5 min max
            pollStatus(100);
        }
    } catch (e) {
        result.textContent = '❌ Erreur réseau';
        result.className   = 'text-sm text-red-600';
    }

    result.classList.remove('hidden');
    btn.disabled    = false;
    btn.textContent = '▶ Lancer la sauvegarde';
}

let pollCount = 0;
async function pollStatus(maxPolls) {
    if (pollCount++ > maxPolls) return;

    const resp = await fetch('{{ route('admin.settings.backup.status') }}', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    });
    const data = await resp.json();

    // Mise à jour du bloc statut
    const card   = document.getElementById('statusCard');
    const result = document.getElementById('runResult');

    if (data.status === 'running') {
        setTimeout(() => pollStatus(maxPolls), 3000);
        return;
    }

    if (data.status === 'success') {
        result.textContent = '✅ ' + data.message + (data.size ? ' — ' + data.size : '');
        result.className   = 'text-sm text-green-600';
    } else if (data.status === 'failed') {
        result.textContent = '❌ ' + data.message;
        result.className   = 'text-sm text-red-600';
    }

    // Actualise la date et taille dans le bloc statut sans rechargement complet
    if (data.last_run) {
        const info = card.querySelector('p.text-xs.text-gray-500');
        if (data.status === 'success') {
            card.querySelector('.flex').innerHTML =
                '<span class="text-green-600 font-semibold text-sm">✓ Succès</span>'
                + '<span class="text-sm text-gray-500">' + data.last_run + '</span>'
                + (data.size ? '<span class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full">' + data.size + '</span>' : '');
        }
    }

    pollCount = 0;
}
</script>
@endsection
