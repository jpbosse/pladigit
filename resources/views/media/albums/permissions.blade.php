@extends('layouts.app')
@section('title', 'Droits — ' . $album->name)

@section('content')
<div class="max-w-4xl mx-auto px-4 py-6">

    {{-- Fil d'Ariane --}}
    <div class="flex items-center gap-2 mb-6 text-sm text-gray-400">
        <a href="{{ route('media.albums.index') }}" class="hover:text-gray-600">📷 Photothèque</a>
        <span>/</span>
        <a href="{{ route('media.albums.show', $album) }}" class="hover:text-gray-600">{{ $album->name }}</a>
        <span>/</span>
        <span class="text-gray-600">Droits d'accès</span>
    </div>

    <div class="flex items-center justify-between mb-6">
        <h1 class="text-xl font-bold text-gray-800">🔐 Droits d'accès — {{ $album->name }}</h1>
        <a href="{{ route('media.albums.show', $album) }}"
           class="px-4 py-2 rounded-lg border border-gray-200 text-sm text-gray-600 hover:bg-gray-50">
            ← Retour à l'album
        </a>
    </div>

    @if(session('success'))
        <div class="mb-4 p-3 bg-green-50 border border-green-200 text-green-700 rounded-lg text-sm">
            ✓ {{ session('success') }}
        </div>
    @endif

    {{-- ── Droits par rôle ── --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm mb-6">
        <div class="p-5 border-b border-gray-100">
            <h2 class="text-sm font-semibold text-gray-700">Droits par rôle</h2>
            <p class="text-xs text-gray-400 mt-1">Les droits Admin, Président et DGS sont toujours complets et ne peuvent pas être modifiés.</p>
        </div>

        <form method="POST" action="{{ route('media.albums.permissions.roles', $album) }}">
            @csrf @method('PUT')

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100">
                            <th class="text-left px-5 py-3 text-xs font-medium text-gray-500 w-48">Rôle</th>
                            <th class="text-center px-4 py-3 text-xs font-medium text-gray-500">👁 Voir</th>
                            <th class="text-center px-4 py-3 text-xs font-medium text-gray-500">⬇ Télécharger</th>
                            <th class="text-center px-4 py-3 text-xs font-medium text-gray-500">⚙️ Administrer</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach(['admin' => 'Admin', 'president' => 'Président', 'dgs' => 'DGS'] as $role => $label)
                        <tr class="bg-gray-50">
                            <td class="px-5 py-3 text-gray-500 font-medium">
                                {{ $label }} <span class="text-xs text-gray-400 ml-1">(accès total)</span>
                            </td>
                            <td class="text-center px-4 py-3"><span class="text-green-500">✓</span></td>
                            <td class="text-center px-4 py-3"><span class="text-green-500">✓</span></td>
                            <td class="text-center px-4 py-3"><span class="text-green-500">✓</span></td>
                        </tr>
                        @endforeach

                        @foreach($configurableRoles as $role => $label)
                        @php $perm = $rolePerms[$role] ?? null; @endphp
                        <tr>
                            <td class="px-5 py-3 font-medium text-gray-700">{{ $label }}</td>
                            <td class="text-center px-4 py-3">
                                <input type="checkbox" name="roles[{{ $role }}][can_view]" value="1"
                                       {{ $perm?->can_view ? 'checked' : '' }}
                                       class="w-4 h-4 rounded border-gray-300 text-blue-600">
                            </td>
                            <td class="text-center px-4 py-3">
                                <input type="checkbox" name="roles[{{ $role }}][can_download]" value="1"
                                       {{ $perm?->can_download ? 'checked' : '' }}
                                       class="w-4 h-4 rounded border-gray-300 text-blue-600">
                            </td>
                            <td class="text-center px-4 py-3">
                                <input type="checkbox" name="roles[{{ $role }}][can_manage]" value="1"
                                       {{ $perm?->can_manage ? 'checked' : '' }}
                                       class="w-4 h-4 rounded border-gray-300 text-blue-600">
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="px-5 py-4 border-t border-gray-100">
                <button type="submit"
                        class="px-4 py-2 rounded-lg text-white text-sm font-medium hover:opacity-90"
                        style="background-color: var(--color-primary, #1E3A5F);">
                    Enregistrer les droits par rôle
                </button>
            </div>
        </form>
    </div>

    {{-- ── Overrides par utilisateur ── --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
        <div class="p-5 border-b border-gray-100">
            <h2 class="text-sm font-semibold text-gray-700">Overrides par utilisateur</h2>
            <p class="text-xs text-gray-400 mt-1">Ces droits sont prioritaires sur les droits par rôle.</p>
        </div>

        @if($userPerms->isNotEmpty())
        <div class="overflow-x-auto border-b border-gray-100">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100">
                        <th class="text-left px-5 py-3 text-xs font-medium text-gray-500">Utilisateur</th>
                        <th class="text-center px-4 py-3 text-xs font-medium text-gray-500">👁 Voir</th>
                        <th class="text-center px-4 py-3 text-xs font-medium text-gray-500">⬇ Télécharger</th>
                        <th class="text-center px-4 py-3 text-xs font-medium text-gray-500">⚙️ Administrer</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach($userPerms as $perm)
                    <tr>
                        <td class="px-5 py-3">
                            <div class="font-medium text-gray-700">{{ $perm->user->name }}</div>
                            <div class="text-xs text-gray-400">{{ $perm->user->email }}</div>
                        </td>
                        <td class="text-center px-4 py-3">
                            <span class="{{ $perm->can_view ? 'text-green-500' : 'text-red-400' }}">
                                {{ $perm->can_view ? '✓' : '✗' }}
                            </span>
                        </td>
                        <td class="text-center px-4 py-3">
                            <span class="{{ $perm->can_download ? 'text-green-500' : 'text-red-400' }}">
                                {{ $perm->can_download ? '✓' : '✗' }}
                            </span>
                        </td>
                        <td class="text-center px-4 py-3">
                            <span class="{{ $perm->can_manage ? 'text-green-500' : 'text-red-400' }}">
                                {{ $perm->can_manage ? '✓' : '✗' }}
                            </span>
                        </td>
                        <td class="text-right px-4 py-3">
                            <form method="POST"
                                  action="{{ route('media.albums.permissions.user.destroy', [$album, $perm]) }}"
                                  onsubmit="return confirm('Supprimer cet override ?')">
                                @csrf @method('DELETE')
                                <button class="text-red-400 hover:text-red-600 text-xs">Supprimer</button>
                            </form>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
        <div class="px-5 py-4 text-sm text-gray-400 italic">Aucun override utilisateur configuré.</div>
        @endif

        {{-- Ajouter un override --}}
        <form method="POST" action="{{ route('media.albums.permissions.user.store', $album) }}" class="p-5">
            @csrf
            <h3 class="text-xs font-semibold text-gray-600 mb-3">Ajouter un override</h3>
            <div class="flex items-end gap-3 flex-wrap">
                <div class="flex-1 min-w-48">
                    <label class="block text-xs text-gray-500 mb-1">Utilisateur</label>
                    <select name="user_id" required
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-200">
                        <option value="">-- Choisir un utilisateur --</option>
                        @foreach($users as $user)
                            <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->email }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-center gap-3 pb-2">
                    <label class="flex items-center gap-1.5 text-sm text-gray-600 cursor-pointer">
                        <input type="checkbox" name="can_view" value="1" class="w-4 h-4 rounded border-gray-300">
                        👁 Voir
                    </label>
                    <label class="flex items-center gap-1.5 text-sm text-gray-600 cursor-pointer">
                        <input type="checkbox" name="can_download" value="1" class="w-4 h-4 rounded border-gray-300">
                        ⬇ Télécharger
                    </label>
                    <label class="flex items-center gap-1.5 text-sm text-gray-600 cursor-pointer">
                        <input type="checkbox" name="can_manage" value="1" class="w-4 h-4 rounded border-gray-300">
                        ⚙️ Administrer
                    </label>
                </div>
                <button type="submit"
                        class="px-4 py-2 rounded-lg text-white text-sm font-medium hover:opacity-90"
                        style="background-color: var(--color-primary, #1E3A5F);">
                    Ajouter
                </button>
            </div>
        </form>
    </div>

</div>
@endsection
