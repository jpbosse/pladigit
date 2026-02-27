@extends('layouts.app')
@section('title', 'Modifier ' . $user->name)

@section('content')
<div class="max-w-2xl mx-auto px-4 py-6">

    <div class="text-sm text-gray-500 mb-4">
        <a href="{{ route('admin.users.index') }}" class="hover:underline">Utilisateurs</a>
        <span class="mx-2">›</span><span>{{ $user->name }}</span>
    </div>

    <div class="bg-white rounded-xl shadow p-6">
        <h1 class="text-xl font-bold text-gray-800 mb-2">Modifier {{ $user->name }}</h1>
        @if($user->ldap_dn)
            <p class="text-sm text-blue-600 mb-6">⚠ Compte LDAP — certains champs sont gérés par l'annuaire</p>
        @endif

        <form method="POST" action="{{ route('admin.users.update', $user) }}">
            @csrf @method('PUT')

            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nom complet</label>
                    <input type="text" name="name" value="{{ old('name', $user->name) }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <input type="email" value="{{ $user->email }}"
                           class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm bg-gray-50" disabled>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Rôle</label>
                    <select name="role" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        @foreach(['user','resp_service','resp_direction','dgs','president','admin'] as $role)
                            <option value="{{ $role }}" {{ $user->role === $role ? 'selected' : '' }}>
                                {{ ucfirst(str_replace('_', ' ', $role)) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Statut</label>
                    <select name="status" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        @foreach(['active','inactive','locked'] as $status)
                            <option value="{{ $status }}" {{ $user->status === $status ? 'selected' : '' }}>
                                {{ ucfirst($status) }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Service / Direction</label>
                <input type="text" name="department" value="{{ old('department', $user->department) }}"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
            </div>

            @if(!$user->ldap_dn)
            <div class="border-t pt-4 mb-4">
                <p class="text-sm font-medium text-gray-700 mb-3">Changer le mot de passe (laisser vide pour ne pas modifier)</p>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm text-gray-600 mb-1">Nouveau mot de passe</label>
                        <input type="password" name="password"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm text-gray-600 mb-1">Confirmer</label>
                        <input type="password" name="password_confirmation"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    </div>
                </div>
            </div>
            @endif

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
                <a href="{{ route('admin.users.index') }}"
                   class="px-6 py-2 rounded-lg border border-gray-300 text-sm text-gray-700 hover:bg-gray-50">
                    Annuler
                </a>
            </div>
        </form>
    </div>
</div>
@endsection
