@extends('layouts.admin')
@section('title', 'Configuration LDAP')

@section('admin-content')


    <h1 class="text-2xl font-bold text-gray-800 mb-6">Configuration LDAP / Active Directory</h1>

    @if(session('success'))
        <div class="bg-green-50 border border-green-300 text-green-700 rounded-lg p-3 mb-4 text-sm">
            {{ session('success') }}
        </div>
    @endif

    <div class="bg-white rounded-xl shadow p-6 mb-4">
        <form method="POST" action="{{ route('admin.settings.ldap.update') }}">
            @csrf @method('PUT')

            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Serveur LDAP</label>
                    <input type="text" name="ldap_host" value="{{ old('ldap_host', $settings->ldap_host) }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"
                           placeholder="192.168.1.10 ou ldap.exemple.fr">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Port</label>
                    <input type="number" name="ldap_port" value="{{ old('ldap_port', $settings->ldap_port ?? 636) }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Base DN</label>
                <input type="text" name="ldap_base_dn" value="{{ old('ldap_base_dn', $settings->ldap_base_dn) }}"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono text-xs"
                       placeholder="dc=exemple,dc=fr">
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Compte de service (Bind DN)</label>
                <input type="text" name="ldap_bind_dn" value="{{ old('ldap_bind_dn', $settings->ldap_bind_dn) }}"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono text-xs"
                       placeholder="cn=admin,dc=exemple,dc=fr">
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Mot de passe du compte de service
                    <span class="text-gray-400 font-normal">(laisser vide pour ne pas modifier)</span>
                </label>
                <input type="password" name="ldap_password"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
            </div>

            <div class="mb-6">
                <div class="flex gap-6">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="hidden" name="ldap_use_ssl" value="0">
                        <input type="checkbox" name="ldap_use_ssl" value="1"
                               {{ $settings->ldap_use_ssl ? 'checked' : '' }}
                               class="rounded">
                        <span class="text-sm text-gray-700">Utiliser SSL (LDAPS — port 636)</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="hidden" name="ldap_use_tls" value="0">
                        <input type="checkbox" name="ldap_use_tls" value="1"
                               {{ $settings->ldap_use_tls ? 'checked' : '' }}
                               class="rounded">
                        <span class="text-sm text-gray-700">Utiliser TLS (STARTTLS — port 389)</span>
                    </label>
                </div>
            </div>

            @if($errors->any())
                <div class="bg-red-50 border border-red-300 text-red-700 rounded-lg p-3 mb-4 text-sm">
                    {{ $errors->first() }}
                </div>
            @endif

            <div class="flex gap-3">
                <button type="submit"
                        class="px-6 py-2 rounded-lg text-white text-sm font-medium"
                        style="background-color: #1E3A5F;">
                    Enregistrer
                </button>
                <button type="button" id="btn-test-ldap"
                        class="px-6 py-2 rounded-lg border border-gray-300 text-sm text-gray-700 hover:bg-gray-50">
                    Tester la connexion
                </button>
            </div>
        </form>
    </div>

    <div id="ldap-test-result" class="hidden rounded-lg p-3 text-sm"></div>

@endsection

@push('scripts')
<script>
document.getElementById('btn-test-ldap').addEventListener('click', function () {
    const btn = this;
    btn.textContent = 'Test en cours…';
    btn.disabled = true;

    fetch('{{ route('admin.settings.ldap.test') }}')
        .then(r => r.json())
        .then(data => {
            const el = document.getElementById('ldap-test-result');
            el.classList.remove('hidden', 'bg-green-50', 'bg-red-50', 'text-green-700', 'text-red-700',
                                 'border-green-300', 'border-red-300');
            if (data.ok) {
                el.classList.add('bg-green-50', 'border', 'border-green-300', 'text-green-700');
            } else {
                el.classList.add('bg-red-50', 'border', 'border-red-300', 'text-red-700');
            }
            el.textContent = data.message;
        })
        .finally(() => {
            btn.textContent = 'Tester la connexion';
            btn.disabled = false;
        });
});
</script>

@endpush
