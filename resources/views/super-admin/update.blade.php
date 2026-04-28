@extends('layouts.super-admin')
@section('title', 'Mises à jour')

@section('content')
<div class="max-w-2xl">

    <div class="mb-6">
        <h1 style="font-size:20px;font-weight:700;color:var(--pd-text);">Mises à jour de la plateforme</h1>
        <p style="font-size:13px;color:var(--pd-muted);margin-top:4px;">
            Met à jour l'instance entière — code, dépendances, migrations, assets — pour toutes les organisations.
        </p>
    </div>

    {{-- ── Versions ── --}}
    <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm mb-5">
        <h2 class="text-sm font-semibold text-gray-700 mb-3 pb-2 border-b">Versions</h2>
        <div class="flex items-center gap-6 flex-wrap">
            <div>
                <p class="text-xs text-gray-400 mb-1">Version installée</p>
                <span class="font-mono text-sm font-semibold text-gray-800">v{{ $currentVersion }}</span>
            </div>
            <div>
                <p class="text-xs text-gray-400 mb-1">Version disponible (GitHub)</p>
                <span id="availableVersion" class="font-mono text-sm font-semibold text-gray-500">
                    {{ $settings->update_available_version ? 'v'.$settings->update_available_version : '—' }}
                </span>
            </div>
            <div class="ml-auto">
                <button type="button" onclick="checkVersion()" id="checkBtn"
                        class="px-4 py-2 rounded-lg border border-gray-300 text-sm text-gray-600 hover:bg-gray-50 transition">
                    Vérifier sur GitHub
                </button>
            </div>
        </div>
        <div id="versionMsg" class="text-xs mt-2 hidden"></div>
    </div>

    {{-- ── Statut ── --}}
    <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm mb-5" id="statusCard">
        <h2 class="text-sm font-semibold text-gray-700 mb-3 pb-2 border-b">Dernière mise à jour</h2>

        @if(! $settings->update_last_run_at)
            <p class="text-sm text-gray-400 italic">Aucune mise à jour effectuée.</p>
        @else
            <div class="flex items-center gap-3 flex-wrap">
                @if($settings->update_last_status === 'success')
                    <span class="text-green-600 font-semibold text-sm">✓ Succès</span>
                @elseif($settings->update_last_status === 'running')
                    <span class="text-blue-600 font-semibold text-sm">⏳ En cours…</span>
                @else
                    <span class="text-red-600 font-semibold text-sm">✗ Échec</span>
                @endif
                <span class="text-sm text-gray-500">{{ $settings->update_last_run_at->format('d/m/Y à H:i:s') }}</span>
                @if($settings->update_current_version)
                    <span class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full font-mono">v{{ $settings->update_current_version }}</span>
                @endif
            </div>
            @if($settings->update_last_message)
                <p class="text-xs text-gray-500 mt-1 font-mono">{{ $settings->update_last_message }}</p>
            @endif
        @endif

        <div class="mt-4 flex items-center gap-3 flex-wrap">
            <button type="button" onclick="runUpdate()" id="runBtn"
                    class="px-5 py-2.5 rounded-lg text-white text-sm font-medium hover:opacity-90 transition"
                    style="background-color:var(--sa-primary,#7B1C1C);"
                    {{ $settings->update_last_status === 'running' ? 'disabled' : '' }}>
                @if($settings->update_last_status === 'running')
                    ⏳ Mise à jour en cours…
                @elseif($settings->update_available_version)
                    ▶ Mettre à jour vers v{{ $settings->update_available_version }}
                @else
                    ▶ Lancer la mise à jour
                @endif
            </button>
            <span id="runResult" class="text-sm hidden"></span>
        </div>
    </div>

    {{-- ── Informations ── --}}
    <div class="bg-amber-50 border border-amber-200 rounded-xl p-5 text-sm text-amber-800">
        <p class="font-semibold mb-2">⚠ Avant de lancer une mise à jour</p>
        <ul class="list-disc list-inside space-y-1 text-xs">
            <li>Le site passe en <strong>mode maintenance</strong> pendant l'opération (quelques minutes).</li>
            <li>Toutes les organisations sont impactées simultanément.</li>
            <li>Les migrations sont appliquées sur toutes les bases tenant.</li>
            <li>En cas d'erreur de migration, le site reste en maintenance — une intervention SSH est requise.</li>
        </ul>
    </div>

</div>

<script>
async function checkVersion() {
    const btn = document.getElementById('checkBtn');
    const msg = document.getElementById('versionMsg');
    btn.disabled = true; btn.textContent = '⏳…';
    msg.className = 'text-xs mt-2 hidden';
    try {
        const r = await fetch('{{ route('super-admin.update.check-version') }}', { headers: {'X-Requested-With':'XMLHttpRequest'} });
        const d = await r.json();
        if (d.ok && d.version) {
            document.getElementById('availableVersion').textContent = 'v' + d.version;
            msg.textContent = '✓ Version récupérée depuis GitHub.';
            msg.className = 'text-xs mt-2 text-green-600';
            // Mettre à jour le libellé du bouton de mise à jour
            const runBtn = document.getElementById('runBtn');
            if (runBtn && !runBtn.disabled) {
                runBtn.textContent = '▶ Mettre à jour vers v' + d.version;
            }
        } else {
            msg.textContent = d.message || 'Impossible de récupérer la version.';
            msg.className = 'text-xs mt-2 text-red-600';
        }
    } catch {
        msg.textContent = '❌ Erreur réseau.';
        msg.className = 'text-xs mt-2 text-red-600';
    }
    msg.classList.remove('hidden');
    btn.disabled = false; btn.textContent = 'Vérifier sur GitHub';
}

async function runUpdate() {
    const available = document.getElementById('availableVersion')?.textContent?.trim();
    const label = available && available !== '—' ? available : 'la dernière version';
    if (!confirm('Lancer la mise à jour vers ' + label + ' ?\n\nLe site passera en mode maintenance pendant quelques minutes.')) return;

    const btn = document.getElementById('runBtn');
    const res = document.getElementById('runResult');
    btn.disabled = true; btn.textContent = '⏳ Lancement…';
    res.className = 'text-sm hidden';
    try {
        const r = await fetch('{{ route('super-admin.update.run') }}', {
            method: 'POST',
            headers: { 'X-Requested-With':'XMLHttpRequest', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '' }
        });
        const d = await r.json();
        res.textContent = d.ok ? '✅ ' + d.message : '❌ ' + d.message;
        res.className = 'text-sm ' + (d.ok ? 'text-green-600' : 'text-red-600');
        if (d.ok) {
            btn.textContent = '⏳ Mise à jour en cours…';
            pollStatus(180);
        } else {
            btn.disabled = false;
            btn.textContent = '▶ Lancer la mise à jour';
        }
    } catch {
        res.textContent = '❌ Erreur réseau';
        res.className = 'text-sm text-red-600';
        btn.disabled = false;
        btn.textContent = '▶ Lancer la mise à jour';
    }
    res.classList.remove('hidden');
}

let pollCount = 0;
async function pollStatus(max) {
    if (pollCount++ > max) return;
    try {
        const r = await fetch('{{ route('super-admin.update.status') }}', { headers: {'X-Requested-With':'XMLHttpRequest'} });
        const d = await r.json();
        const res = document.getElementById('runResult');
        const btn = document.getElementById('runBtn');
        if (d.status === 'running') {
            setTimeout(() => pollStatus(max), 2000);
            return;
        }
        if (d.status === 'success') {
            res.textContent = '✅ Mise à jour terminée avec succès.';
            res.className = 'text-sm text-green-600';
            btn.textContent = '▶ Lancer la mise à jour';
            btn.disabled = false;
            // Recharger la page pour afficher le nouveau statut
            setTimeout(() => window.location.reload(), 2000);
        } else if (d.status === 'error') {
            res.textContent = '❌ ' + (d.message || 'Erreur lors de la mise à jour.');
            res.className = 'text-sm text-red-600';
            btn.textContent = '▶ Lancer la mise à jour';
            btn.disabled = false;
        }
    } catch {
        setTimeout(() => pollStatus(max), 2000);
    }
    pollCount = 0;
}

// Si une mise à jour est en cours au chargement de la page, démarrer le polling
@if($settings->update_last_status === 'running')
document.addEventListener('DOMContentLoaded', () => pollStatus(180));
@endif
</script>
@endsection
