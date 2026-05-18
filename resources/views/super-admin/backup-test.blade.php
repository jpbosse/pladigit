@extends('layouts.super-admin')
@section('title', 'Tester la sauvegarde')

@section('content')
<div class="max-w-3xl">

    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 style="font-size:20px;font-weight:700;color:var(--pd-text);">Tester la sauvegarde</h1>
            <p style="font-size:13px;color:var(--pd-muted);margin-top:4px;">
                Vérification SHA-256 et inspection du contenu des archives — sans restauration.
            </p>
        </div>
        <a href="{{ route('super-admin.security.dashboard') }}"
           class="text-sm text-gray-500 hover:text-gray-700 flex items-center gap-1">
            ← Tableau de bord sécurité
        </a>
    </div>

    @if(($settings->backup_driver ?? 'local') !== 'local' || empty($settings->backup_local_path))
        <div class="p-4 bg-amber-50 border border-amber-200 rounded-xl text-sm text-amber-700">
            ⚠ La destination de sauvegarde n'est pas configurée en mode local.
            <a href="{{ route('super-admin.backup') }}" class="underline ml-1">Configurer</a>
        </div>
    @else

    {{-- ── Sélection de l'archive ── --}}
    <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm mb-5">
        <h2 class="text-sm font-semibold text-gray-700 mb-3 pb-2 border-b">Sélectionner une archive</h2>
        <div class="flex gap-3 items-center">
            <select id="archiveSelect"
                    class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-red-200">
                <option value="">Chargement…</option>
            </select>
            <button type="button" onclick="runInspect()" id="inspectBtn"
                    class="px-5 py-2 rounded-lg text-white text-sm font-medium hover:opacity-90 transition"
                    style="background-color:var(--sa-primary,#7B1C1C);" disabled>
                🔍 Inspecter
            </button>
        </div>
    </div>

    {{-- ── Résultats ── --}}
    <div id="resultsCard" class="hidden space-y-4">

        {{-- SHA-256 --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
            <h2 class="text-sm font-semibold text-gray-700 mb-3 pb-2 border-b">🔒 Vérification SHA-256</h2>
            <div id="sha256Result"></div>
        </div>

        {{-- Contenu tar --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
            <h2 class="text-sm font-semibold text-gray-700 mb-3 pb-2 border-b flex items-center justify-between">
                <span>📦 Contenu de l'archive</span>
                <span id="entryCount" class="text-xs text-gray-400 font-normal"></span>
            </h2>
            <div id="tarContents"></div>
        </div>

        {{-- Enregistrement du test --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
            <h2 class="text-sm font-semibold text-gray-700 mb-3 pb-2 border-b">📝 Enregistrer ce test</h2>
            <div class="flex flex-wrap gap-2 items-start">
                <select id="restoreStatus" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-200">
                    <option value="success">✓ Succès</option>
                    <option value="failed">✗ Échec</option>
                </select>
                <input type="text" id="restoreNote" placeholder="Note (optionnelle) — ex : contenu OK, SHA-256 vérifié"
                       class="flex-1 min-w-48 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-200">
                <button type="button" onclick="recordTest()"
                        class="px-4 py-2 rounded-lg text-white text-sm font-medium hover:opacity-90 transition"
                        style="background-color:var(--sa-primary,#7B1C1C);">
                    Enregistrer
                </button>
                <span id="recordResult" class="text-sm hidden self-center"></span>
            </div>
        </div>

    </div>

    {{-- Loading --}}
    <div id="loadingCard" class="hidden bg-white rounded-xl border border-gray-200 p-8 shadow-sm text-center">
        <p class="text-sm text-gray-400">⏳ Inspection en cours…</p>
    </div>

    @endif
</div>

<script>
// ── Chargement de la liste des archives ─────────────────────────────
async function loadArchives() {
    const select = document.getElementById('archiveSelect');
    const btn    = document.getElementById('inspectBtn');
    try {
        const r = await fetch('{{ route('super-admin.backup.list') }}', { headers: {'X-Requested-With':'XMLHttpRequest'} });
        const d = await r.json();
        if (!d.ok || !d.archives.length) {
            select.innerHTML = '<option value="">Aucune archive disponible</option>';
            return;
        }
        select.innerHTML = d.archives.map(a =>
            `<option value="${a.path}">[${a.path.split('/')[0]}] ${a.name} — ${a.size_h} — ${a.date}</option>`
        ).join('');
        btn.disabled = false;
    } catch {
        select.innerHTML = '<option value="">Erreur de chargement</option>';
    }
}

// ── Inspection ───────────────────────────────────────────────────────
async function runInspect() {
    const select  = document.getElementById('archiveSelect');
    const file    = select.value;
    if (!file) return;

    document.getElementById('resultsCard').classList.add('hidden');
    document.getElementById('loadingCard').classList.remove('hidden');
    document.getElementById('inspectBtn').disabled = true;

    try {
        const url = '{{ route('super-admin.backup.inspect') }}?file=' + encodeURIComponent(file);
        const r   = await fetch(url, { headers: {'X-Requested-With':'XMLHttpRequest'} });
        const d   = await r.json();

        document.getElementById('loadingCard').classList.add('hidden');
        document.getElementById('inspectBtn').disabled = false;

        if (!d.ok) {
            document.getElementById('sha256Result').innerHTML =
                `<p class="text-sm text-red-600">❌ ${d.message}</p>`;
            document.getElementById('tarContents').innerHTML = '';
            document.getElementById('entryCount').textContent = '';
            document.getElementById('resultsCard').classList.remove('hidden');
            return;
        }

        // SHA-256
        let sha256Html = '';
        if (d.sha256.expected === null) {
            sha256Html = '<p class="text-sm text-gray-400 italic">Pas de fichier .sha256 — vérification impossible.</p>';
        } else if (d.sha256.match) {
            sha256Html = `
                <div class="flex items-center gap-2 text-sm text-green-700 font-semibold mb-2">✅ Intégrité vérifiée</div>
                <p class="text-xs font-mono text-gray-400">SHA-256 : ${d.sha256.computed}</p>`;
        } else {
            sha256Html = `
                <div class="flex items-center gap-2 text-sm text-red-700 font-semibold mb-2">❌ Hash incorrect — archive corrompue ou modifiée</div>
                <p class="text-xs font-mono text-gray-500">Attendu&nbsp;&nbsp;: ${d.sha256.expected}</p>
                <p class="text-xs font-mono text-gray-500">Calculé&nbsp;&nbsp;: ${d.sha256.computed}</p>`;
        }
        if (d.gpg_decrypted) {
            sha256Html += '<p class="text-xs text-amber-600 mt-1">🔐 Archive GPG déchiffrée temporairement pour inspection.</p>';
        }
        document.getElementById('sha256Result').innerHTML = sha256Html;

        // Contenu tar
        document.getElementById('entryCount').textContent = `${d.entry_count} fichier(s)`;
        if (d.tar_error) {
            document.getElementById('tarContents').innerHTML =
                `<p class="text-sm text-red-600">❌ Erreur tar : ${d.tar_error}</p>`;
        } else {
            // Grouper par dossier racine
            const grouped = {};
            d.entries.forEach(e => {
                const parts  = e.split('/');
                const folder = parts.length > 1 ? parts[0] : '(racine)';
                if (!grouped[folder]) grouped[folder] = [];
                grouped[folder].push(e);
            });

            let html = '<div class="space-y-2 max-h-96 overflow-y-auto">';
            Object.entries(grouped).forEach(([folder, files]) => {
                html += `
                <details class="border border-gray-100 rounded-lg">
                    <summary class="px-3 py-2 text-xs font-semibold text-gray-600 cursor-pointer hover:bg-gray-50 select-none">
                        📁 ${folder} <span class="text-gray-400 font-normal">(${files.length} fichier(s))</span>
                    </summary>
                    <div class="px-3 pb-2 space-y-0.5">
                        ${files.map(f => `<p class="text-xs font-mono text-gray-500 truncate">${f}</p>`).join('')}
                    </div>
                </details>`;
            });
            html += '</div>';
            document.getElementById('tarContents').innerHTML = html;
        }

        // Pré-remplir la note avec le résultat
        const autoNote = d.sha256.match === true
            ? `SHA-256 OK — ${d.entry_count} fichiers — ${select.options[select.selectedIndex].text.split('—')[0].trim()}`
            : (d.sha256.match === false ? 'SHA-256 incorrect ⚠' : `${d.entry_count} fichiers — pas de .sha256`);
        document.getElementById('restoreNote').value = autoNote;
        if (d.sha256.match === false) {
            document.getElementById('restoreStatus').value = 'failed';
        }

        document.getElementById('resultsCard').classList.remove('hidden');

    } catch {
        document.getElementById('loadingCard').classList.add('hidden');
        document.getElementById('inspectBtn').disabled = false;
        alert('Erreur réseau lors de l\'inspection.');
    }
}

// ── Enregistrement du test ───────────────────────────────────────────
async function recordTest() {
    const btn    = document.querySelector('[onclick="recordTest()"]');
    const result = document.getElementById('recordResult');
    btn.disabled = true; btn.textContent = '⏳…';
    result.className = 'text-sm hidden';
    try {
        const r = await fetch('{{ route('super-admin.security.record-restore-test') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
            },
            body: JSON.stringify({
                status: document.getElementById('restoreStatus').value,
                note:   document.getElementById('restoreNote').value,
            }),
        });
        const d = await r.json();
        result.textContent = d.ok ? '✅ ' + d.message : '❌ ' + d.message;
        result.className   = 'text-sm ' + (d.ok ? 'text-green-600' : 'text-red-600');
    } catch {
        result.textContent = '❌ Erreur réseau';
        result.className   = 'text-sm text-red-600';
    }
    result.classList.remove('hidden');
    btn.disabled = false; btn.textContent = 'Enregistrer';
}

document.addEventListener('DOMContentLoaded', loadArchives);
</script>
@endsection
