@extends('layouts.super-admin')
@section('title', 'Sauvegarde plateforme')

@section('content')
<div class="max-w-2xl">

    <div class="mb-6">
        <h1 style="font-size:20px;font-weight:700;color:var(--pd-text);">Sauvegarde de la plateforme</h1>
        <p style="font-size:13px;color:var(--pd-muted);margin-top:4px;">
            Sauvegarde toutes les organisations actives en une seule opération.
        </p>
    </div>

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

    {{-- ── Organisations couvertes ── --}}
    <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm mb-5">
        <h2 class="text-sm font-semibold text-gray-700 mb-3 pb-2 border-b">Organisations sauvegardées</h2>
        <div class="space-y-1">
            @foreach($orgs as $org)
            <div class="flex items-center gap-3 text-sm">
                <span class="text-green-500">✓</span>
                <span class="font-medium text-gray-700">{{ $org->name }}</span>
                <span class="text-xs text-gray-400 font-mono">{{ $org->slug }}</span>
                <span class="text-xs text-gray-400">→ DB : {{ $org->db_name }}</span>
            </div>
            @endforeach
        </div>
        <p class="text-xs text-gray-400 mt-3">Chaque organisation génère une archive distincte : <code class="font-mono">backup_YYYY-MM-DD_HHmmss_{slug}.tar.gz</code></p>
    </div>

    {{-- ── Statut ── --}}
    <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm mb-5" id="statusCard">
        <h2 class="text-sm font-semibold text-gray-700 mb-3 pb-2 border-b">Dernière sauvegarde</h2>

        @if(! $settings->backup_last_run_at)
            <p class="text-sm text-gray-400 italic">Aucune sauvegarde effectuée.</p>
        @else
            <div class="flex items-center gap-3">
                @if($settings->backup_last_status === 'success')
                    <span class="text-green-600 font-semibold text-sm">✓ Succès</span>
                @elseif($settings->backup_last_status === 'running')
                    <span class="text-blue-600 font-semibold text-sm">⏳ En cours…</span>
                @else
                    <span class="text-red-600 font-semibold text-sm">✗ Échec</span>
                @endif
                <span class="text-sm text-gray-500">{{ $settings->backup_last_run_at->format('d/m/Y à H:i:s') }}</span>
                @if($settings->backupHumanSize())
                    <span class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full">{{ $settings->backupHumanSize() }}</span>
                @endif
            </div>
            @if($settings->backup_last_message)
                <p class="text-xs text-gray-500 mt-1 font-mono">{{ $settings->backup_last_message }}</p>
            @endif
        @endif

        <div class="mt-4 flex items-center gap-3">
            <button type="button" onclick="runBackup()" id="runBtn"
                    class="px-5 py-2.5 rounded-lg text-white text-sm font-medium hover:opacity-90 transition"
                    style="background-color:var(--sa-primary,#7B1C1C);">
                ▶ Lancer la sauvegarde complète
            </button>
            <span id="runResult" class="text-sm hidden"></span>
        </div>
    </div>

    <form method="POST" action="{{ route('super-admin.backup.update') }}" class="space-y-5">
        @csrf @method('PUT')

        {{-- ── Planification ── --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
            <h2 class="text-sm font-semibold text-gray-700 mb-4 pb-2 border-b">Planification automatique</h2>
            <div class="flex items-center gap-3 mb-4">
                <input type="hidden" name="backup_enabled" value="0">
                <input type="checkbox" name="backup_enabled" value="1" id="backup_enabled"
                       {{ $settings->backup_enabled ? 'checked' : '' }}
                       class="w-4 h-4 rounded border-gray-300">
                <label for="backup_enabled" class="text-sm text-gray-700 font-medium">Activer la sauvegarde automatique de toutes les organisations</label>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Fréquence</label>
                <select name="backup_schedule"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-200">
                    <option value="hourly" {{ ($settings->backup_schedule ?? 'daily') === 'hourly' ? 'selected' : '' }}>Toutes les heures</option>
                    <option value="daily"  {{ ($settings->backup_schedule ?? 'daily') === 'daily'  ? 'selected' : '' }}>Une fois par jour (minuit)</option>
                    <option value="weekly" {{ ($settings->backup_schedule ?? 'daily') === 'weekly' ? 'selected' : '' }}>Une fois par semaine (dimanche minuit)</option>
                </select>
            </div>
        </div>

        {{-- ── Destination ── --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
            <h2 class="text-sm font-semibold text-gray-700 mb-4 pb-2 border-b">Destination commune</h2>

            <div class="mb-4">
                <label class="block text-xs font-medium text-gray-600 mb-1">Type</label>
                <select name="backup_driver" id="backup_driver" onchange="toggleDriver(this.value)"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-200">
                    <option value="local" {{ ($settings->backup_driver ?? 'local') === 'local' ? 'selected' : '' }}>Chemin local (même serveur)</option>
                    <option value="sftp"  {{ ($settings->backup_driver ?? 'local') === 'sftp'  ? 'selected' : '' }}>SFTP — NAS distant</option>
                </select>
                @if(! function_exists('ssh2_connect'))
                <p class="text-xs text-amber-600 mt-1">
                    ⚠ SFTP nécessite <code class="font-mono">php8.4-ssh2</code> :
                    <code class="font-mono">sudo apt install php8.4-ssh2 && sudo systemctl restart php8.4-fpm</code>
                </p>
                @endif
            </div>

            <div id="section_local" class="{{ ($settings->backup_driver ?? 'local') !== 'local' ? 'hidden' : '' }}">
                <label class="block text-xs font-medium text-gray-600 mb-1">Chemin de destination</label>
                <input type="text" name="backup_local_path"
                       value="{{ old('backup_local_path', $settings->backup_local_path) }}"
                       placeholder="/mnt/backup ou /srv/backups/pladigit"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-200 font-mono">
                <p class="text-xs text-gray-400 mt-1">Une archive par organisation sera créée dans ce répertoire.</p>
            </div>

            <div id="section_sftp" class="{{ ($settings->backup_driver ?? 'local') !== 'sftp' ? 'hidden' : '' }} space-y-3">
                <div class="grid grid-cols-3 gap-3">
                    <div class="col-span-2">
                        <label class="block text-xs font-medium text-gray-600 mb-1">Hôte / IP</label>
                        <input type="text" name="backup_sftp_host"
                               value="{{ old('backup_sftp_host', $settings->backup_sftp_host) }}"
                               placeholder="192.168.1.100"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-red-200">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Port</label>
                        <input type="number" name="backup_sftp_port"
                               value="{{ old('backup_sftp_port', $settings->backup_sftp_port ?? 22) }}"
                               min="1" max="65535"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm text-center focus:outline-none focus:ring-2 focus:ring-red-200">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Utilisateur</label>
                        <input type="text" name="backup_sftp_user"
                               value="{{ old('backup_sftp_user', $settings->backup_sftp_user) }}"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-200">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Mot de passe</label>
                        <input type="password" name="backup_sftp_password"
                               placeholder="{{ $settings->backup_sftp_password_enc ? '••••••••' : '' }}"
                               autocomplete="new-password"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-200">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Chemin distant</label>
                    <input type="text" name="backup_sftp_path"
                           value="{{ old('backup_sftp_path', $settings->backup_sftp_path ?? '/backup') }}"
                           placeholder="/volume1/backup/pladigit"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-red-200">
                </div>
                <div class="flex items-center gap-3">
                    <button type="button" onclick="testSftp()" id="testSftpBtn"
                            class="px-4 py-2 rounded-lg border border-gray-300 text-sm text-gray-600 hover:bg-gray-50 transition">
                        Tester la connexion SFTP
                    </button>
                    <span id="testSftpResult" class="text-sm hidden"></span>
                </div>
            </div>
        </div>

        {{-- ── Rétention ── --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
            <h2 class="text-sm font-semibold text-gray-700 mb-4 pb-2 border-b">Rétention</h2>
            <div class="flex items-center gap-3">
                <input type="number" name="backup_retention_count"
                       value="{{ old('backup_retention_count', $settings->backup_retention_count ?? 7) }}"
                       min="1" max="90"
                       class="w-24 border border-gray-300 rounded-lg px-3 py-2 text-sm text-center focus:outline-none focus:ring-2 focus:ring-red-200">
                <span class="text-sm text-gray-500">archives conservées par organisation</span>
            </div>
        </div>

        <div>
            <button type="submit"
                    class="px-5 py-2.5 rounded-lg text-white text-sm font-medium hover:opacity-90 transition"
                    style="background-color:var(--sa-primary,#7B1C1C);">
                Enregistrer
            </button>
        </div>
    </form>

    {{-- ── Archives disponibles (driver local uniquement) ── --}}
    @if(($settings->backup_driver ?? 'local') === 'local' && filled($settings->backup_local_path))
    <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm mt-5" id="archivesCard">
        <div class="flex items-center justify-between mb-3 pb-2 border-b">
            <h2 class="text-sm font-semibold text-gray-700">Archives disponibles</h2>
            <button type="button" onclick="loadArchives()"
                    class="text-xs px-3 py-1.5 rounded-lg border border-gray-300 text-gray-600 hover:bg-gray-50 transition">
                ↻ Actualiser
            </button>
        </div>
        <div id="archivesList">
            <p class="text-sm text-gray-400 italic">Chargement…</p>
        </div>
    </div>
    @endif
</div>

<script>
function toggleDriver(v) {
    document.getElementById('section_local').classList.toggle('hidden', v !== 'local');
    document.getElementById('section_sftp').classList.toggle('hidden',  v !== 'sftp');
}

async function testSftp() {
    const btn = document.getElementById('testSftpBtn');
    const res = document.getElementById('testSftpResult');
    btn.disabled = true; btn.textContent = '⏳…';
    res.className = 'text-sm hidden';
    try {
        const r = await fetch('{{ route('super-admin.backup.test-sftp') }}', { headers: {'X-Requested-With':'XMLHttpRequest'} });
        const d = await r.json();
        res.textContent = d.ok ? '✅ '+d.message : '❌ '+d.message;
        res.className = 'text-sm '+(d.ok ? 'text-green-600' : 'text-red-600');
    } catch { res.textContent = '❌ Erreur réseau'; res.className = 'text-sm text-red-600'; }
    res.classList.remove('hidden');
    btn.disabled = false; btn.textContent = 'Tester la connexion SFTP';
}

async function runBackup() {
    const btn = document.getElementById('runBtn');
    const res = document.getElementById('runResult');
    btn.disabled = true; btn.textContent = '⏳ Lancement…';
    res.className = 'text-sm hidden';
    try {
        const r = await fetch('{{ route('super-admin.backup.run') }}', {
            method: 'POST',
            headers: { 'X-Requested-With':'XMLHttpRequest', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '' }
        });
        const d = await r.json();
        res.textContent = d.ok ? '✅ '+d.message : '❌ '+d.message;
        res.className = 'text-sm '+(d.ok ? 'text-green-600' : 'text-red-600');
        if (d.ok) pollStatus(100);
    } catch { res.textContent = '❌ Erreur réseau'; res.className = 'text-sm text-red-600'; }
    res.classList.remove('hidden');
    btn.disabled = false; btn.textContent = '▶ Lancer la sauvegarde complète';
}

let pollCount = 0;
async function pollStatus(max) {
    if (pollCount++ > max) return;
    const r = await fetch('{{ route('super-admin.backup.status') }}', { headers: {'X-Requested-With':'XMLHttpRequest'} });
    const d = await r.json();
    const res = document.getElementById('runResult');
    if (d.status === 'running') { setTimeout(() => pollStatus(max), 3000); return; }
    if (d.status === 'success') { res.textContent = '✅ '+d.message+(d.size ? ' — '+d.size : ''); res.className = 'text-sm text-green-600'; }
    else if (d.status === 'failed') { res.textContent = '❌ '+d.message; res.className = 'text-sm text-red-600'; }
    pollCount = 0;
}

async function loadArchives() {
    const container = document.getElementById('archivesList');
    if (!container) return;
    container.innerHTML = '<p class="text-sm text-gray-400 italic">Chargement…</p>';
    try {
        const r = await fetch('{{ route('super-admin.backup.list') }}', { headers: {'X-Requested-With':'XMLHttpRequest'} });
        const d = await r.json();
        if (!d.ok || !d.archives.length) {
            container.innerHTML = '<p class="text-sm text-gray-400 italic">Aucune archive trouvée.</p>';
            return;
        }
        let html = '<div class="space-y-2">';
        d.archives.forEach(a => {
            const slug = a.path.includes('/') ? a.path.split('/')[0] : '—';
            const tenantBadge = `<span class="text-xs bg-red-50 text-red-700 border border-red-200 px-2 py-0.5 rounded-full font-mono">${slug}</span>`;
            const gpgBadge = a.gpg ? '<span class="text-xs bg-yellow-100 text-yellow-700 px-1.5 py-0.5 rounded font-mono">GPG</span>' : '';
            const sha256Preview = a.sha256
                ? `<span class="font-mono text-xs text-gray-400" title="${a.sha256}">${a.sha256.substring(0,16)}…</span>`
                : '<span class="text-xs text-gray-300 italic">pas de .sha256</span>';
            const verifyBtn = a.sha256
                ? `<button type="button" onclick="verifyChecksum('${a.path}', '${a.name}', this)"
                       class="text-xs px-2.5 py-1 rounded border border-gray-300 text-gray-600 hover:bg-gray-50 transition">
                       Vérifier
                   </button>`
                : '';
            html += `
            <div class="flex flex-wrap items-center gap-2 p-3 bg-gray-50 rounded-lg border border-gray-100 text-sm">
                ${tenantBadge}
                <span class="font-mono font-medium text-gray-700 flex-1 min-w-0 truncate" title="${a.name}">${a.name}</span>
                ${gpgBadge}
                <span class="text-xs text-gray-400">${a.size_h}</span>
                <span class="text-xs text-gray-400">${a.date}</span>
                <span class="flex items-center gap-1">${sha256Preview}</span>
                ${verifyBtn}
                <span class="verify-result-${a.name.replace(/[^a-z0-9]/gi,'_')} text-xs hidden"></span>
            </div>`;
        });
        html += '</div>';
        container.innerHTML = html;
    } catch {
        container.innerHTML = '<p class="text-sm text-red-500">Erreur lors du chargement des archives.</p>';
    }
}

async function verifyChecksum(filepath, filename, btn) {
    const safeKey = filename.replace(/[^a-z0-9]/gi,'_');
    const resultEl = document.querySelector(`.verify-result-${safeKey}`);
    btn.disabled = true; btn.textContent = '⏳';
    if (resultEl) { resultEl.className = 'text-xs hidden'; }
    try {
        const url = '{{ route('super-admin.backup.checksum') }}?file='+encodeURIComponent(filepath);
        const r = await fetch(url, { headers: {'X-Requested-With':'XMLHttpRequest'} });
        const d = await r.json();
        if (!d.ok) {
            if (resultEl) { resultEl.textContent = '❌ '+d.message; resultEl.className = 'text-xs text-red-600'; }
        } else if (d.match) {
            if (resultEl) { resultEl.textContent = '✅ Intégrité OK'; resultEl.className = 'text-xs text-green-600 font-medium'; }
        } else {
            if (resultEl) {
                resultEl.innerHTML = '❌ Hash incorrect<br>'
                    +'<span class="font-mono">attendu&nbsp;: '+d.expected+'</span><br>'
                    +'<span class="font-mono">calculé&nbsp;: '+d.computed+'</span>';
                resultEl.className = 'text-xs text-red-600';
            }
        }
    } catch {
        if (resultEl) { resultEl.textContent = '❌ Erreur réseau'; resultEl.className = 'text-xs text-red-600'; }
    }
    if (resultEl) resultEl.classList.remove('hidden');
    btn.disabled = false; btn.textContent = 'Vérifier';
}

// Chargement automatique au démarrage de la page
document.addEventListener('DOMContentLoaded', () => { if (document.getElementById('archivesList')) loadArchives(); });
</script>
@endsection
