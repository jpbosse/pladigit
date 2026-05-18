@extends('layouts.super-admin')
@section('title', 'Tableau de bord sécurité')

@section('content')
<div class="max-w-3xl">

    <div class="mb-6">
        <h1 style="font-size:20px;font-weight:700;color:var(--pd-text);">Tableau de bord sécurité</h1>
        <p style="font-size:13px;color:var(--pd-muted);margin-top:4px;">
            État de santé de la plateforme — supervision en lecture seule.
        </p>
    </div>

    <div class="space-y-5">

        {{-- ── GPG ── --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
            <h2 class="text-sm font-semibold text-gray-700 mb-3 pb-2 border-b flex items-center gap-2">
                🔐 Chiffrement GPG
            </h2>
            <div class="flex items-center gap-3">
                @if($settings->backup_gpg_enabled && filled($settings->backup_gpg_passphrase_enc))
                    <span class="inline-flex items-center gap-1.5 text-sm font-semibold text-green-700 bg-green-50 border border-green-200 px-3 py-1 rounded-full">✓ Activé</span>
                    <span class="text-sm text-gray-500">Passphrase configurée</span>
                @elseif($settings->backup_gpg_enabled && empty($settings->backup_gpg_passphrase_enc))
                    <span class="inline-flex items-center gap-1.5 text-sm font-semibold text-amber-700 bg-amber-50 border border-amber-200 px-3 py-1 rounded-full">⚠ Activé sans passphrase</span>
                @else
                    <span class="inline-flex items-center gap-1.5 text-sm font-semibold text-red-700 bg-red-50 border border-red-200 px-3 py-1 rounded-full">✗ Désactivé</span>
                    <span class="text-xs text-gray-400">Les archives ne sont pas chiffrées.</span>
                @endif
            </div>
        </div>

        {{-- ── Sauvegarde ── --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
            <h2 class="text-sm font-semibold text-gray-700 mb-3 pb-2 border-b flex items-center gap-2">
                💾 Dernière sauvegarde
            </h2>
            @if($settings->backup_last_run_at)
                <div class="flex flex-wrap items-center gap-3 text-sm">
                    @if($settings->backup_last_status === 'success')
                        <span class="font-semibold text-green-600">✓ Succès</span>
                    @elseif($settings->backup_last_status === 'running')
                        <span class="font-semibold text-blue-600">⏳ En cours</span>
                    @else
                        <span class="font-semibold text-red-600">✗ Échec</span>
                    @endif
                    <span class="text-gray-500">{{ $settings->backup_last_run_at->format('d/m/Y à H:i:s') }}</span>
                    @if($settings->backupHumanSize())
                        <span class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full">{{ $settings->backupHumanSize() }}</span>
                    @endif
                    <span class="text-xs text-gray-400">Fréquence : {{ $settings->backup_schedule ?? 'daily' }}</span>
                </div>
                @if($settings->backup_last_message)
                    <p class="text-xs text-gray-400 mt-1 font-mono">{{ $settings->backup_last_message }}</p>
                @endif
            @else
                <p class="text-sm text-gray-400 italic">Aucune sauvegarde enregistrée.</p>
            @endif

            {{-- Espace disque --}}
            @if($diskInfo)
            <div class="mt-4 pt-3 border-t">
                <p class="text-xs font-medium text-gray-500 mb-1">Espace disque — <span class="font-mono">{{ $diskInfo['path'] }}</span></p>
                <div class="flex items-center gap-3">
                    <div class="flex-1 bg-gray-200 rounded-full h-2">
                        <div class="h-2 rounded-full {{ $diskInfo['used_pct'] >= 90 ? 'bg-red-500' : ($diskInfo['used_pct'] >= 70 ? 'bg-amber-400' : 'bg-green-500') }}"
                             style="width: {{ $diskInfo['used_pct'] }}%"></div>
                    </div>
                    <span class="text-xs text-gray-500 whitespace-nowrap">
                        {{ $diskInfo['free_h'] }} libres / {{ $diskInfo['total_h'] }} ({{ $diskInfo['used_pct'] }}% utilisé)
                    </span>
                </div>
            </div>
            @endif

            <div class="mt-4 pt-3 border-t">
                <a href="{{ route('super-admin.backup.test') }}"
                   class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-white text-sm font-medium hover:opacity-90 transition"
                   style="background-color:var(--sa-primary,#7B1C1C);">
                    🔍 Tester la sauvegarde
                </a>
                <p class="text-xs text-gray-400 mt-1">Vérifier SHA-256 et inspecter le contenu d'une archive sans restaurer.</p>
            </div>
        </div>

        {{-- ── Test de restauration ── --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
            <h2 class="text-sm font-semibold text-gray-700 mb-3 pb-2 border-b flex items-center gap-2">
                🔁 Dernier test de restauration
            </h2>
            @if($settings->security_restore_tested_at)
                <div class="flex flex-wrap items-center gap-3 text-sm mb-2">
                    @if($settings->security_restore_test_status === 'success')
                        <span class="font-semibold text-green-600">✓ Succès</span>
                    @else
                        <span class="font-semibold text-red-600">✗ Échec</span>
                    @endif
                    <span class="text-gray-500">{{ $settings->security_restore_tested_at->format('d/m/Y à H:i:s') }}</span>
                    @php
                        $daysAgo = $settings->security_restore_tested_at->diffInDays(now());
                    @endphp
                    @if($daysAgo > 90)
                        <span class="text-xs font-medium text-red-600 bg-red-50 border border-red-200 px-2 py-0.5 rounded-full">⚠ Il y a {{ $daysAgo }} jours — à renouveler</span>
                    @elseif($daysAgo > 30)
                        <span class="text-xs font-medium text-amber-600 bg-amber-50 border border-amber-200 px-2 py-0.5 rounded-full">{{ $daysAgo }} jours</span>
                    @else
                        <span class="text-xs text-gray-400">Il y a {{ $daysAgo }} jour(s)</span>
                    @endif
                </div>
                @if($settings->security_restore_test_note)
                    <p class="text-xs text-gray-500 font-mono bg-gray-50 px-3 py-2 rounded">{{ $settings->security_restore_test_note }}</p>
                @endif
            @else
                <p class="text-sm text-red-600 font-medium">⚠ Aucun test de restauration enregistré.</p>
                <p class="text-xs text-gray-400 mt-1">Effectuez un test sur un VPS de test et enregistrez le résultat ci-dessous.</p>
            @endif

            {{-- Formulaire enregistrement test --}}
            <div class="mt-4 pt-3 border-t">
                <p class="text-xs font-medium text-gray-600 mb-2">Enregistrer un test effectué manuellement</p>
                <div class="flex flex-wrap gap-2 items-start">
                    <select id="restoreStatus" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-200">
                        <option value="success">✓ Succès</option>
                        <option value="failed">✗ Échec</option>
                    </select>
                    <input type="text" id="restoreNote" placeholder="Note (optionnelle)"
                           class="flex-1 min-w-48 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-200">
                    <button type="button" onclick="recordRestoreTest()"
                            class="px-4 py-2 rounded-lg text-white text-sm font-medium hover:opacity-90 transition"
                            style="background-color:var(--sa-primary,#7B1C1C);">
                        Enregistrer
                    </button>
                    <span id="restoreResult" class="text-sm hidden self-center"></span>
                </div>
            </div>
        </div>

        {{-- ── Workers ── --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
            <h2 class="text-sm font-semibold text-gray-700 mb-3 pb-2 border-b flex items-center gap-2">
                ⚙️ Workers (Supervisor)
            </h2>
            @if(empty($workers))
                <p class="text-sm text-gray-400 italic">Supervisorctl non disponible ou aucun worker configuré.</p>
            @else
                <div class="space-y-1">
                    @foreach($workers as $w)
                    <div class="flex items-center gap-3 text-sm">
                        @if($w['ok'])
                            <span class="text-green-600 font-semibold">●</span>
                        @else
                            <span class="text-red-600 font-semibold">●</span>
                        @endif
                        <span class="font-mono text-gray-700">{{ $w['name'] }}</span>
                        <span class="text-xs px-2 py-0.5 rounded-full font-mono
                            {{ $w['ok'] ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-700 border border-red-200' }}">
                            {{ $w['status'] }}
                        </span>
                    </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- ── PHP / Laravel ── --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
            <h2 class="text-sm font-semibold text-gray-700 mb-3 pb-2 border-b flex items-center gap-2">
                🛠 Environnement
            </h2>
            <div class="grid grid-cols-2 gap-3 text-sm">
                <div class="flex items-center gap-2">
                    <span class="text-gray-500 w-28">PHP</span>
                    <span class="font-mono bg-gray-100 px-2 py-0.5 rounded text-gray-700">{{ $phpVersion }}</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-gray-500 w-28">Laravel</span>
                    <span class="font-mono bg-gray-100 px-2 py-0.5 rounded text-gray-700">{{ $laravelVersion }}</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-gray-500 w-28">Environnement</span>
                    <span class="font-mono bg-gray-100 px-2 py-0.5 rounded text-gray-700">{{ app()->environment() }}</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-gray-500 w-28">Debug</span>
                    @if(config('app.debug'))
                        <span class="font-mono bg-amber-50 text-amber-700 border border-amber-200 px-2 py-0.5 rounded">true ⚠</span>
                    @else
                        <span class="font-mono bg-green-50 text-green-700 border border-green-200 px-2 py-0.5 rounded">false ✓</span>
                    @endif
                </div>
            </div>
        </div>

        {{-- ── Clés SSH ── --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
            <h2 class="text-sm font-semibold text-gray-700 mb-3 pb-2 border-b flex items-center gap-2">
                🔑 Clés SSH autorisées
            </h2>
            @if(empty($sshKeys))
                <p class="text-sm text-gray-400 italic">Aucune clé trouvée (fichier absent ou vide).</p>
            @else
                <div class="space-y-1">
                    @foreach($sshKeys as $key)
                    @php
                        $parts    = explode(' ', $key, 3);
                        $keyType  = $parts[0] ?? '';
                        $keyShort = isset($parts[1]) ? substr($parts[1], 0, 24).'…' : '';
                        $keyLabel = $parts[2] ?? '';
                    @endphp
                    <div class="flex items-center gap-2 text-xs font-mono bg-gray-50 px-3 py-2 rounded border border-gray-100">
                        <span class="text-gray-400">{{ $keyType }}</span>
                        <span class="text-gray-500 flex-1 truncate">{{ $keyShort }}</span>
                        @if($keyLabel)
                            <span class="text-gray-400 italic">{{ $keyLabel }}</span>
                        @endif
                    </div>
                    @endforeach
                    <p class="text-xs text-gray-400 mt-1">{{ count($sshKeys) }} clé(s) autorisée(s)</p>
                </div>
            @endif
        </div>

        {{-- ── Rétention max audit (RGPD) ── --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
            <h2 class="text-sm font-semibold text-gray-700 mb-3 pb-2 border-b flex items-center gap-2">
                🛡 Rétention maximale audit_logs (RGPD)
            </h2>
            <p class="text-xs text-gray-500 mb-4">
                Plafond absolu appliqué à tous les tenants, indépendamment de leur propre configuration.
                Si un tenant a configuré une rétention supérieure, ce plafond s'applique.
            </p>
            <form method="POST" action="{{ route('super-admin.security.update-audit-retention') }}" class="flex items-center gap-3">
                @csrf @method('PUT')
                <select name="audit_max_retention_months"
                        class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-200">
                    @foreach([12 => '12 mois (1 an)', 24 => '24 mois (2 ans)', 36 => '36 mois (3 ans)', 60 => '60 mois (5 ans — défaut)', 84 => '84 mois (7 ans)'] as $val => $label)
                        <option value="{{ $val }}" {{ ($settings->audit_max_retention_months ?? 60) == $val ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
                <button type="submit"
                        class="px-4 py-2 rounded-lg text-white text-sm font-medium hover:opacity-90 transition"
                        style="background-color:var(--sa-primary,#7B1C1C);">
                    Enregistrer
                </button>
                @if(session('success_audit_retention'))
                    <span class="text-sm text-green-600">✅ {{ session('success_audit_retention') }}</span>
                @endif
            </form>
        </div>

    </div>
</div>

<script>
async function recordRestoreTest() {
    const btn    = document.querySelector('[onclick="recordRestoreTest()"]');
    const result = document.getElementById('restoreResult');
    const status = document.getElementById('restoreStatus').value;
    const note   = document.getElementById('restoreNote').value;

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
            body: JSON.stringify({ status, note }),
        });
        const d = await r.json();
        result.textContent = d.ok ? '✅ ' + d.message : '❌ ' + d.message;
        result.className   = 'text-sm ' + (d.ok ? 'text-green-600' : 'text-red-600');
        if (d.ok) setTimeout(() => location.reload(), 1200);
    } catch {
        result.textContent = '❌ Erreur réseau';
        result.className   = 'text-sm text-red-600';
    }
    result.classList.remove('hidden');
    btn.disabled = false; btn.textContent = 'Enregistrer';
}
</script>
@endsection
