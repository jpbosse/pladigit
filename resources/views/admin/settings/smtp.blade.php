@extends('layouts.admin')
@section('title', 'Configuration SMTP')

@section('admin-content')


    <h1 class="text-2xl font-bold text-gray-800 mb-2">Configuration SMTP</h1>
    <p class="text-sm text-gray-500 mb-6">Laisser vide pour utiliser le serveur SMTP mutualisé de Les Bézots.</p>

    @if(session('success'))
        <div class="bg-green-50 border border-green-300 text-green-700 rounded-lg p-3 mb-4 text-sm">
            {{ session('success') }}
        </div>
    @endif

    <div class="bg-white rounded-xl shadow p-6">
        <form method="POST" action="{{ route('admin.settings.smtp.update') }}">
            @csrf @method('PUT')

            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Serveur SMTP</label>
                    <input type="text" name="smtp_host" value="{{ old('smtp_host', $org->smtp_host) }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"
                           placeholder="smtp.exemple.fr">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Port</label>
                    <input type="number" name="smtp_port" value="{{ old('smtp_port', $org->smtp_port ?? 587) }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Utilisateur SMTP</label>
                    <input type="text" name="smtp_user" value="{{ old('smtp_user', $org->smtp_user) }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Mot de passe
                        <span class="text-gray-400 font-normal">(vide = inchangé)</span>
                    </label>
                    <input type="password" name="smtp_password"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4 mb-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Adresse expéditeur</label>
                    <input type="email" name="smtp_from_address" value="{{ old('smtp_from_address', $org->smtp_from_address) }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"
                           placeholder="noreply@cc-ile-noirmoutier.fr">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nom expéditeur</label>
                    <input type="text" name="smtp_from_name" value="{{ old('smtp_from_name', $org->smtp_from_name) }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"
                           placeholder="Communauté de Communes de l'île de Noirmoutier">
                </div>
            </div>

            @if($errors->any())
                <div class="bg-red-50 border border-red-300 text-red-700 rounded-lg p-3 mb-4 text-sm">
                    {{ $errors->first() }}
                </div>
            @endif

            <button type="submit"
                    class="px-6 py-2 rounded-lg text-white text-sm font-medium"
                    style="background-color: #1E3A5F;">
                Enregistrer
            </button>
        </form>
    </div>
</div>
@endsection
