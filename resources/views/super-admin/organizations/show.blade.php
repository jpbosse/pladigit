@extends('layouts.super-admin')
@section('title', $organization->name . ' — Super Admin')

@section('content')
<div class="max-w-4xl mx-auto px-4 py-6">

    {{-- Breadcrumb --}}
    <div class="text-sm text-gray-500 mb-4">
        <a href="{{ route('super-admin.organizations.index') }}" class="hover:underline">Organisations</a>
        <span class="mx-2">›</span>
        <span>{{ $organization->name }}</span>
    </div>

    {{-- Flash --}}
    @if(session('success'))
        <div class="bg-green-50 border border-green-300 text-green-700 rounded-lg p-3 mb-4 text-sm">
            {{ session('success') }}
        </div>
    @endif

    {{-- Header --}}
    <div class="flex justify-between items-start mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">{{ $organization->name }}</h1>
            <p class="text-gray-500 text-sm font-mono">{{ $organization->slug }}.pladigit.fr</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('super-admin.organizations.edit', $organization) }}"
               class="px-4 py-2 rounded-lg border border-gray-300 text-sm text-gray-700 hover:bg-gray-50">
                Modifier
            </a>
            @if($organization->status === 'active')
            <form method="POST" action="{{ route('super-admin.organizations.suspend', $organization) }}"
                  onsubmit="return confirm('Suspendre ?')">
                @csrf
                <button class="px-4 py-2 rounded-lg bg-red-600 text-white text-sm">Suspendre</button>
            </form>
            @else
            <form method="POST" action="{{ route('super-admin.organizations.activate', $organization) }}">
                @csrf
                <button class="px-4 py-2 rounded-lg bg-green-600 text-white text-sm">Activer</button>
            </form>
            @endif
        </div>
    </div>

    {{-- Infos --}}
    <div class="grid grid-cols-2 gap-4 mb-6">
        <div class="bg-white rounded-xl shadow p-4">
            <p class="text-xs text-gray-500 uppercase font-medium mb-1">Statut</p>
            <span class="px-2 py-1 rounded-full text-xs font-medium
                {{ $organization->status === 'active' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                {{ ['active'=>'Actif','suspended'=>'Suspendu','pending'=>'En attente','archived'=>'Archivé'][$organization->status] ?? ucfirst($organization->status) }}
            </span>
        </div>
        <div class="bg-white rounded-xl shadow p-4">
            <p class="text-xs text-gray-500 uppercase font-medium mb-1">Plan</p>
            <p class="font-semibold text-gray-800">{{ ['communautaire'=>'Communautaire','assistance'=>'Assistance','enterprise'=>'Enterprise'][$organization->plan] ?? ucfirst($organization->plan) }}</p>
        </div>
        <div class="bg-white rounded-xl shadow p-4">
            <p class="text-xs text-gray-500 uppercase font-medium mb-1">Utilisateurs</p>
            <p class="font-semibold text-gray-800">{{ $userCount }} / {{ $organization->max_users }}</p>
        </div>
        <div class="bg-white rounded-xl shadow p-4">
            <p class="text-xs text-gray-500 uppercase font-medium mb-1">Base de données</p>
            <p class="font-mono text-sm text-gray-600">{{ $organization->db_name }}</p>
        </div>
    </div>

    {{-- Créer un admin --}}
    <div class="bg-white rounded-xl shadow p-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">Créer un administrateur</h2>
        <form method="POST" action="{{ route('super-admin.organizations.create-admin', $organization) }}">
            @csrf
            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nom</label>
                    <input type="text" name="name" value="{{ old('name') }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <input type="email" name="email" value="{{ old('email') }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" required>
                </div>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Mot de passe</label>
                <input type="password" name="password"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" required>
            </div>
            @if($errors->any())
                <div class="bg-red-50 border border-red-300 text-red-700 rounded-lg p-3 mb-4 text-sm">
                    {{ $errors->first() }}
                </div>
            @endif
            <button type="submit"
                    class="px-6 py-2 rounded-lg text-white text-sm font-medium"
                    style="background-color: #1E3A5F;">
                Créer l'administrateur
            </button>
        </form>
    </div>

{{-- Configuration SMTP --}}
	<div class="bg-white rounded-xl shadow p-6 mt-6">
	    <h2 class="text-lg font-semibold text-gray-800 mb-4">Configuration SMTP</h2>
	    <form method="POST" action="{{ route('super-admin.organizations.update-smtp', $organization) }}">
	        @csrf
	        <div class="grid grid-cols-2 gap-4 mb-4">
	            <div>
	                <label class="block text-sm font-medium text-gray-700 mb-1">Serveur SMTP</label>
	                <input type="text" name="smtp_host" value="{{ old('smtp_host', $organization->smtp_host) }}"
	                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
        	    </div>
	            <div>
	                <label class="block text-sm font-medium text-gray-700 mb-1">Port</label>
	                <input type="number" name="smtp_port" value="{{ old('smtp_port', $organization->smtp_port ?? 587) }}"
	                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
        	    </div>
	            <div>
        	        <label class="block text-sm font-medium text-gray-700 mb-1">Utilisateur</label>
                	<input type="text" name="smtp_user" value="{{ old('smtp_user', $organization->smtp_user) }}"
	                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
        	    </div>
	            <div>
        	        <label class="block text-sm font-medium text-gray-700 mb-1">Mot de passe</label>
                	<input type="password" name="smtp_password" placeholder="Laisser vide pour ne pas modifier"
	                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
        	    </div>
	            <div>
        	        <label class="block text-sm font-medium text-gray-700 mb-1">Adresse expéditeur</label>
                	<input type="email" name="smtp_from_address" value="{{ old('smtp_from_address', $organization->smtp_from_address) }}"
                       		class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
	            </div>
        	    <div>
                	<label class="block text-sm font-medium text-gray-700 mb-1">Nom expéditeur</label>
	                <input type="text" name="smtp_from_name" value="{{ old('smtp_from_name', $organization->smtp_from_name) }}"
        	               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
	            </div>
	        </div>
	        <button type="submit"
	                class="px-6 py-2 rounded-lg text-white text-sm font-medium"
	                style="background-color: #1E3A5F;">
	            Sauvegarder SMTP
	        </button>
	    </form>
	</div>


{{-- Configuration LDAP --}}
<div class="bg-white rounded-xl shadow p-6 mt-6">
    <h2 class="text-lg font-semibold text-gray-800 mb-4">Configuration LDAP / Active Directory</h2>
    <form method="POST" action="{{ route('super-admin.organizations.update-ldap', $organization) }}">
        @csrf
        <div class="grid grid-cols-2 gap-4 mb-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Serveur LDAP</label>
                <input type="text" name="ldap_host" value="{{ old('ldap_host') }}"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"
                       placeholder="ldap.mondomaine.fr">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Port</label>
                <input type="number" name="ldap_port" value="{{ old('ldap_port', 636) }}"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Base DN</label>
                <input type="text" name="ldap_base_dn" value="{{ old('ldap_base_dn') }}"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"
                       placeholder="dc=mondomaine,dc=fr">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Bind DN</label>
                <input type="text" name="ldap_bind_dn" value="{{ old('ldap_bind_dn') }}"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"
                       placeholder="cn=admin,dc=mondomaine,dc=fr">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Mot de passe</label>
                <input type="password" name="ldap_bind_password" placeholder="Laisser vide pour ne pas modifier"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Intervalle synchro (heures)</label>
                <input type="number" name="ldap_sync_interval_hours" value="{{ old('ldap_sync_interval_hours', 24) }}"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
            </div>
        </div>
        <div class="flex gap-6 mb-4">
            <label class="flex items-center gap-2 text-sm text-gray-700">
                <input type="checkbox" name="ldap_use_ssl" value="1" checked>
                Utiliser SSL (LDAPS port 636)
            </label>
            <label class="flex items-center gap-2 text-sm text-gray-700">
                <input type="checkbox" name="ldap_use_tls" value="1">
                Utiliser TLS (STARTTLS port 389)
            </label>
        </div>
        <button type="submit"
                class="px-6 py-2 rounded-lg text-white text-sm font-medium"
                style="background-color: #1E3A5F;">
            Sauvegarder LDAP
        </button>
    </form>
</div>

</div>
@endsection
