@extends('layouts.app')
@section('title', 'Mon profil')

@section('content')
<div class="max-w-3xl mx-auto px-4 py-8">

    {{-- Retour dashboard --}}
    <a href="{{ route('dashboard') }}"
       class="inline-flex items-center gap-1 text-sm text-gray-400 hover:text-gray-600 mb-6 transition">
        ← Retour au tableau de bord
    </a>

    {{-- En-tête --}}
    <div class="flex items-center gap-4 mb-8">
        <div class="w-14 h-14 rounded-full flex items-center justify-center text-white text-2xl font-bold"
             style="background-color: var(--color-primary, #1E3A5F);">
            {{ strtoupper(substr($user->name, 0, 1)) }}
        </div>
        <div>
            <h1 class="text-2xl font-bold text-gray-800">{{ $user->name }}</h1>
            <p class="text-sm text-gray-500">
                {{ App\Enums\UserRole::tryFrom($user->role)?->label() ?? $user->role }}
                @if($user->department)
                    — {{ $user->department }}
                @endif
            </p>
        </div>
    </div>

    {{-- Alertes succès --}}
    @if(session('success_info'))
        <div class="bg-green-50 border border-green-300 text-green-700 rounded-xl p-4 mb-6 text-sm">
            ✓ {{ session('success_info') }}
        </div>
    @endif
    @if(session('success_password'))
        <div class="bg-green-50 border border-green-300 text-green-700 rounded-xl p-4 mb-6 text-sm">
            ✓ {{ session('success_password') }}
        </div>
    @endif

    {{-- Nouveaux codes de secours — affichés une seule fois --}}
    @if(session('new_backup_codes'))
        <div class="bg-yellow-50 border border-yellow-400 rounded-xl p-6 mb-6">
            <p class="text-sm font-semibold text-yellow-800 mb-1">⚠ Nouveaux codes de secours — copiez-les maintenant</p>
            <p class="text-xs text-yellow-700 mb-4">Ces codes ne seront plus affichés. Conservez-les dans un endroit sûr.</p>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-2 mb-4">
                @foreach(session('new_backup_codes') as $code)
                    <code class="bg-white border border-yellow-300 rounded px-3 py-1.5 text-sm font-mono text-center text-yellow-900">
                        {{ $code }}
                    </code>
                @endforeach
            </div>
            <button onclick="copyBackupCodes()"
                    class="text-xs px-3 py-1.5 rounded border border-yellow-400 text-yellow-700 hover:bg-yellow-100 transition">
                📋 Copier tous les codes
            </button>
            <script>
                function copyBackupCodes() {
                    const codes = @json(session('new_backup_codes'));
                    navigator.clipboard.writeText(codes.join('\n'));
                }
            </script>
        </div>
    @endif

    {{-- Section 1 : Informations personnelles --}}
    <div class="bg-white rounded-xl shadow p-6 mb-6">
        <h2 class="text-base font-semibold text-gray-800 mb-4 pb-2 border-b">
            Informations personnelles
        </h2>

        @if($errors->has('name'))
            <div class="bg-red-50 border border-red-300 text-red-700 rounded-lg p-3 mb-4 text-sm">
                {{ $errors->first('name') }}
            </div>
        @endif

        <form method="POST" action="{{ route('profile.update-info') }}">
            @csrf @method('PATCH')

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nom complet</label>
                    <input type="text" name="name" value="{{ old('name', $user->name) }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2"
                           required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <input type="email" value="{{ $user->email }}"
                           class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm bg-gray-50 text-gray-400"
                           disabled>
                    <p class="text-xs text-gray-400 mt-1">L'email ne peut pas être modifié</p>
                </div>
            </div>

            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-1">Service / Direction</label>
                <input type="text" name="department" value="{{ old('department', $user->department) }}"
                       placeholder="Ex : Direction des Services Techniques"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2">
            </div>

            <div class="flex items-center justify-between">
                <div class="text-xs text-gray-400">
                    Rôle : <span class="font-medium text-gray-600">{{ App\Enums\UserRole::tryFrom($user->role)?->label() ?? $user->role }}</span>
                    — modifiable uniquement par un administrateur
                </div>
                <button type="submit"
                        class="px-5 py-2 rounded-lg text-white text-sm font-medium hover:opacity-90 transition"
                        style="background-color: var(--color-primary, #1E3A5F);">
                    Enregistrer
                </button>
            </div>
        </form>
    </div>

    {{-- Section 2 : Mot de passe --}}
    @if(!$user->ldap_dn)
    <div class="bg-white rounded-xl shadow p-6 mb-6">
        <h2 class="text-base font-semibold text-gray-800 mb-4 pb-2 border-b">
            Changer le mot de passe
        </h2>

        {{-- Expiration --}}
        @if($passwordExpiresIn !== null)
            @if($passwordExpiresIn < 0)
                <div class="bg-red-50 border border-red-300 text-red-700 rounded-lg p-3 mb-4 text-sm">
                    ⚠ Votre mot de passe a expiré. Veuillez le changer maintenant.
                </div>
            @elseif($passwordExpiresIn <= 7)
                <div class="bg-yellow-50 border border-yellow-300 text-yellow-700 rounded-lg p-3 mb-4 text-sm">
                    ⚠ Votre mot de passe expire dans {{ $passwordExpiresIn }} jour{{ $passwordExpiresIn > 1 ? 's' : '' }}.
                </div>
            @else
                <p class="text-xs text-gray-400 mb-4">
                    Expire dans <span class="font-medium">{{ $passwordExpiresIn }} jours</span>
                    ({{ $user->password_changed_at->addDays($settings->pwd_validity_days)->locale('fr')->isoFormat('D MMMM YYYY') }})
                </p>
            @endif
        @endif

        @if($errors->has('current_password') || $errors->has('password'))
            <div class="bg-red-50 border border-red-300 text-red-700 rounded-lg p-3 mb-4 text-sm">
                @foreach(['current_password', 'password'] as $field)
                    @if($errors->has($field))
                        <p>{{ $errors->first($field) }}</p>
                    @endif
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('profile.update-password') }}">
            @csrf @method('PATCH')

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Mot de passe actuel</label>
                    <input type="password" name="current_password"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2"
                           required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nouveau mot de passe</label>
                    <input type="password" name="password" id="new_password"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2"
                           oninput="checkStrength(this.value)"
                           required>
                    {{-- Indicateur de force --}}
                    <div class="mt-2">
                        <div class="flex gap-1 mb-1">
                            <div id="bar1" class="h-1 flex-1 rounded bg-gray-200 transition-colors"></div>
                            <div id="bar2" class="h-1 flex-1 rounded bg-gray-200 transition-colors"></div>
                            <div id="bar3" class="h-1 flex-1 rounded bg-gray-200 transition-colors"></div>
                            <div id="bar4" class="h-1 flex-1 rounded bg-gray-200 transition-colors"></div>
                        </div>
                        <p id="strength_label" class="text-xs text-gray-400"></p>
                        <ul class="mt-1 text-xs text-gray-400 space-y-0.5" id="policy_hints">
                            @if($settings->pwd_min_length)
                                <li id="hint_length">○ {{ $settings->pwd_min_length }} caractères minimum</li>
                            @endif
                            @if($settings->pwd_require_uppercase)
                                <li id="hint_upper">○ Une majuscule</li>
                            @endif
                            @if($settings->pwd_require_number)
                                <li id="hint_number">○ Un chiffre</li>
                            @endif
                            @if($settings->pwd_require_special)
                                <li id="hint_special">○ Un caractère spécial</li>
                            @endif
                        </ul>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Confirmer</label>
                    <input type="password" name="password_confirmation" id="password_confirmation"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2"
                           oninput="checkMatch()"
                           required>
                    <p id="match_label" class="text-xs mt-1 text-gray-400"></p>
                </div>
            </div>

            @if($user->password_changed_at)
                <p class="text-xs text-gray-400 mb-4">
                    Dernier changement : {{ $user->password_changed_at->locale('fr')->diffForHumans() }}
                </p>
            @endif

            <div class="flex justify-end">
                <button type="submit"
                        class="px-5 py-2 rounded-lg text-white text-sm font-medium hover:opacity-90 transition"
                        style="background-color: var(--color-primary, #1E3A5F);">
                    Changer le mot de passe
                </button>
            </div>
        </form>
    </div>
    @else
    <div class="bg-blue-50 border border-blue-200 text-blue-700 rounded-xl p-4 mb-6 text-sm">
        🔒 Votre mot de passe est géré par l'annuaire LDAP de votre organisation.
    </div>
    @endif

    {{-- Section 3 : Double authentification --}}
    <div class="bg-white rounded-xl shadow p-6 mb-6">
        <h2 class="text-base font-semibold text-gray-800 mb-4 pb-2 border-b">
            Double authentification (2FA)
        </h2>

        <div class="flex items-center justify-between mb-4">
            <div>
                <p class="text-sm font-medium {{ $user->totp_enabled ? 'text-green-700' : 'text-yellow-700' }}">
                    {{ $user->totp_enabled ? '✓ Activée' : '⚠ Non activée' }}
                </p>
                <p class="text-xs text-gray-400 mt-1">
                    {{ $user->totp_enabled
                        ? 'Votre compte est protégé par une application TOTP (Google Authenticator, Authy…)'
                        : 'Activez le 2FA pour renforcer la sécurité de votre compte.' }}
                </p>
            </div>
            @if($user->totp_enabled)
                <form method="POST" action="{{ route('2fa.disable') }}">
                    @csrf
                    <div class="flex items-center gap-2">
                        <input type="password" name="password" placeholder="Mot de passe"
                               class="border border-gray-300 rounded-lg px-3 py-2 text-sm w-36 focus:outline-none"
                               required>
                        <button type="submit"
                                class="px-4 py-2 rounded-lg border border-red-300 text-red-600 text-sm hover:bg-red-50 transition">
                            Désactiver
                        </button>
                    </div>
                </form>
            @else
                <a href="{{ route('2fa.setup') }}"
                   class="px-5 py-2 rounded-lg text-white text-sm font-medium hover:opacity-90 transition"
                   style="background-color: var(--color-primary, #1E3A5F);">
                    Activer le 2FA
                </a>
            @endif
        </div>

        {{-- Codes de secours --}}
        @if($user->totp_enabled)
        <div class="border-t pt-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-700">Codes de secours</p>
                    <p class="text-xs text-gray-400 mt-0.5">
                        @if($backupCodesCount === null || $backupCodesCount === 0)
                            <span class="text-red-500">⚠ Aucun code disponible</span>
                        @elseif($backupCodesCount <= 2)
                            <span class="text-yellow-600">⚠ {{ $backupCodesCount }} code{{ $backupCodesCount > 1 ? 's' : '' }} restant{{ $backupCodesCount > 1 ? 's' : '' }} — régénérez-les bientôt</span>
                        @else
                            <span class="text-green-600">{{ $backupCodesCount }} codes disponibles</span>
                        @endif
                    </p>
                </div>
                <form method="POST" action="{{ route('profile.regenerate-backup-codes') }}">
                    @csrf
                    <div class="flex items-center gap-2">
                        <input type="password" name="password" placeholder="Mot de passe"
                               class="border border-gray-300 rounded-lg px-3 py-2 text-sm w-36 focus:outline-none"
                               required>
                        <button type="submit"
                                onclick="return confirm('Régénérer les codes invalidera les anciens. Continuer ?')"
                                class="px-4 py-2 rounded-lg border border-gray-300 text-gray-600 text-sm hover:bg-gray-50 transition">
                            Régénérer
                        </button>
                    </div>
                </form>
            </div>
            @if($errors->has('password'))
                <p class="text-xs text-red-500 mt-2">{{ $errors->first('password') }}</p>
            @endif
        </div>
        @endif
    </div>

    {{-- Section 4 : Informations de connexion --}}
    <div class="bg-white rounded-xl shadow p-6">
        <h2 class="text-base font-semibold text-gray-800 mb-4 pb-2 border-b">
            Informations de connexion
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
            <div>
                <p class="text-xs text-gray-400 uppercase font-medium mb-1">Dernière connexion</p>
                <p class="text-gray-700">
                    {{ $user->last_login_at?->locale('fr')->isoFormat('D MMMM YYYY à HH:mm') ?? 'Première connexion' }}
                </p>
                @if($user->last_login_ip)
                    <p class="text-xs text-gray-400 mt-0.5">depuis {{ $user->last_login_ip }}</p>
                @endif
            </div>
            <div>
                <p class="text-xs text-gray-400 uppercase font-medium mb-1">Compte créé</p>
                <p class="text-gray-700">
                    {{ $user->created_at?->locale('fr')->isoFormat('D MMMM YYYY') ?? '—' }}
                </p>
                @if($user->ldap_dn)
                    <p class="text-xs text-blue-500 mt-0.5">Compte LDAP — {{ $user->ldap_dn }}</p>
                @endif
            </div>
        </div>
    </div>

</div>

{{-- Indicateur de force JS --}}
<script>
const minLength  = {{ $settings->pwd_min_length ?? 8 }};
const reqUpper   = {{ $settings->pwd_require_uppercase ? 'true' : 'false' }};
const reqNumber  = {{ $settings->pwd_require_number ? 'true' : 'false' }};
const reqSpecial = {{ $settings->pwd_require_special ? 'true' : 'false' }};

function checkStrength(val) {
    let score = 0;
    const okLength  = val.length >= minLength;
    const okUpper   = !reqUpper  || /[A-Z]/.test(val);
    const okNumber  = !reqNumber || /[0-9]/.test(val);
    const okSpecial = !reqSpecial|| /[\W_]/.test(val);

    if (okLength)  score++;
    if (okUpper)   score++;
    if (okNumber)  score++;
    if (okSpecial) score++;
    if (val.length >= minLength + 4) score = Math.min(4, score + 1);

    const colors = ['', 'bg-red-400', 'bg-orange-400', 'bg-yellow-400', 'bg-green-500'];
    const labels = ['', 'Très faible', 'Faible', 'Moyen', 'Fort'];

    for (let i = 1; i <= 4; i++) {
        const bar = document.getElementById('bar' + i);
        bar.className = 'h-1 flex-1 rounded transition-colors ' + (i <= score ? colors[score] : 'bg-gray-200');
    }
    const lbl = document.getElementById('strength_label');
    lbl.textContent = val.length > 0 ? labels[score] || '' : '';
    lbl.className   = 'text-xs ' + (score >= 3 ? 'text-green-600' : score >= 2 ? 'text-yellow-600' : 'text-red-500');

    // Mettre à jour les hints
    updateHint('hint_length',  okLength);
    updateHint('hint_upper',   okUpper);
    updateHint('hint_number',  okNumber);
    updateHint('hint_special', okSpecial);
}

function updateHint(id, ok) {
    const el = document.getElementById(id);
    if (!el) return;
    el.textContent = el.textContent.replace(/^[○✓]/, ok ? '✓' : '○');
    el.className   = ok ? 'text-green-600' : '';
}

function checkMatch() {
    const pwd    = document.getElementById('new_password').value;
    const conf   = document.getElementById('password_confirmation').value;
    const label  = document.getElementById('match_label');
    if (conf.length === 0) { label.textContent = ''; return; }
    if (pwd === conf) {
        label.textContent = '✓ Les mots de passe correspondent';
        label.className   = 'text-xs mt-1 text-green-600';
    } else {
        label.textContent = '✗ Les mots de passe ne correspondent pas';
        label.className   = 'text-xs mt-1 text-red-500';
    }
}
</script>
@endsection
