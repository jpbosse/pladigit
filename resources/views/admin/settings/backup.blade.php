@extends('layouts.admin')
@section('title', 'Sauvegarde des données')

@section('admin-content')
<div class="max-w-2xl mx-auto">

    <div class="mb-6">
        <h1 class="text-xl font-bold text-gray-800">Sauvegarde des données</h1>
        <p class="text-sm text-gray-500 mt-1">
            Les sauvegardes sont gérées par votre administrateur Pladigit et s'exécutent automatiquement chaque nuit.
            En cas de sinistre, contactez votre prestataire Pladigit.
        </p>
    </div>

    {{-- ── Statut dernière sauvegarde ── --}}
    <div class="mb-5 bg-white rounded-xl border border-gray-200 p-5 shadow-sm" id="statusCard">
        <h2 class="text-sm font-semibold text-gray-700 mb-3 pb-2 border-b">Dernière sauvegarde</h2>
        @php
            $lastStatus = $platformSettings->backup_last_status;
            $lastRun    = $platformSettings->backup_last_run_at;
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
                @if($platformSettings->backupHumanSize())
                    <span class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full">{{ $platformSettings->backupHumanSize() }}</span>
                @endif
            </div>
            @if($platformSettings->backup_last_message)
                <p class="text-xs text-gray-500 mt-1 font-mono">{{ $platformSettings->backup_last_message }}</p>
            @endif
        @endif
    </div>

    {{-- ── Vérification d'intégrité SHA-256 ── --}}
    @if($lastRun && $lastStatus === 'success')
    <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
        <h2 class="text-sm font-semibold text-gray-700 mb-3 pb-2 border-b">Vérification d'intégrité</h2>
        <p class="text-sm text-gray-500 mb-4">
            Vous pouvez vérifier que la dernière archive n'a pas été altérée en contrôlant sa somme de contrôle SHA-256.
        </p>

        <div class="flex items-center gap-3">
            <button type="button" onclick="checkIntegrity()"
                    id="checkBtn"
                    class="px-4 py-2 rounded-lg border border-gray-300 text-sm font-medium text-gray-600 hover:bg-gray-50 transition">
                🔍 Vérifier l'intégrité
            </button>
            <span id="checkResult" class="text-sm hidden"></span>
        </div>

        <div id="checksumDetail" class="hidden mt-4 p-3 bg-gray-50 rounded-lg text-xs font-mono text-gray-600 space-y-1">
            <div><span class="text-gray-400">Attendu&nbsp;&nbsp;:</span> <span id="expectedHash"></span></div>
            <div><span class="text-gray-400">Calculé&nbsp;&nbsp;:</span> <span id="computedHash"></span></div>
        </div>
    </div>
    @endif

</div>

<script>
async function checkIntegrity() {
    const btn    = document.getElementById('checkBtn');
    const result = document.getElementById('checkResult');
    const detail = document.getElementById('checksumDetail');

    btn.disabled    = true;
    btn.textContent = '⏳ Vérification…';
    result.className = 'text-sm hidden';
    detail.classList.add('hidden');

    try {
        const resp = await fetch('{{ route('admin.settings.backup.checksum') }}', {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await resp.json();

        if (! data.ok) {
            result.textContent = '❌ ' + data.message;
            result.className   = 'text-sm text-red-600';
        } else if (data.match) {
            result.textContent = '✅ Intégrité vérifiée — archive non altérée.';
            result.className   = 'text-sm text-green-600';
        } else if (data.expected === null) {
            result.textContent = '⚠ Aucun fichier .sha256 disponible pour cette archive.';
            result.className   = 'text-sm text-amber-600';
        } else {
            result.textContent = '❌ Sommes de contrôle différentes — archive potentiellement altérée !';
            result.className   = 'text-sm text-red-600';
        }

        if (data.expected || data.computed) {
            document.getElementById('expectedHash').textContent = data.expected ?? '—';
            document.getElementById('computedHash').textContent = data.computed ?? '—';
            detail.classList.remove('hidden');
        }
    } catch (e) {
        result.textContent = '❌ Erreur réseau';
        result.className   = 'text-sm text-red-600';
    }

    result.classList.remove('hidden');
    btn.disabled    = false;
    btn.textContent = '🔍 Vérifier l\'intégrité';
}
</script>
@endsection
