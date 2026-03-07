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
            <p class="text-xs text-gray-400 mt-1">Président et DGS ont toujours accès total. L'Admin est soumis aux droits comme les autres rôles.</p>
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
                            <th class="text-center px-4 py-3 text-xs font-medium text-gray-500">✏️ Éditer</th>
                            <th class="text-center px-4 py-3 text-xs font-medium text-gray-500">⚙️ Gérer</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach(['president' => 'Président', 'dgs' => 'DGS'] as $role => $label)
                        <tr class="bg-gray-50">
                            <td class="px-5 py-3 text-gray-400 font-medium">{{ $label }} <span class="text-xs">(accès total)</span></td>
                            <td class="text-center px-4 py-3 text-green-500">✓</td>
                            <td class="text-center px-4 py-3 text-green-500">✓</td>
                            <td class="text-center px-4 py-3 text-green-500">✓</td>
                            <td class="text-center px-4 py-3 text-green-500">✓</td>
                        </tr>
                        @endforeach

                        @foreach($configurableRoles as $role => $label)
                        @php $s = $roleShares[$role] ?? null; @endphp
                        <tr>
                            <td class="px-5 py-3 font-medium text-gray-700">{{ $label }}</td>
                            <td class="text-center px-4 py-3">
                                <input type="checkbox" name="roles[{{ $role }}][can_view]" value="1"
                                       {{ $s?->can_view ? 'checked' : '' }}
                                       class="w-4 h-4 rounded border-gray-300 text-blue-600">
                            </td>
                            <td class="text-center px-4 py-3">
                                <input type="checkbox" name="roles[{{ $role }}][can_download]" value="1"
                                       {{ $s?->can_download ? 'checked' : '' }}
                                       class="w-4 h-4 rounded border-gray-300 text-blue-600">
                            </td>
                            <td class="text-center px-4 py-3">
                                <input type="checkbox" name="roles[{{ $role }}][can_edit]" value="1"
                                       {{ $s?->can_edit ? 'checked' : '' }}
                                       class="w-4 h-4 rounded border-gray-300 text-blue-600">
                            </td>
                            <td class="text-center px-4 py-3">
                                <input type="checkbox" name="roles[{{ $role }}][can_manage]" value="1"
                                       {{ $s?->can_manage ? 'checked' : '' }}
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

    {{-- ── Partages existants (dept + user) ── --}}
    @if($deptShares->isNotEmpty() || $userShares->isNotEmpty())
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm mb-6">
        <div class="p-5 border-b border-gray-100">
            <h2 class="text-sm font-semibold text-gray-700">Partages individuels</h2>
            <p class="text-xs text-gray-400 mt-1">Prioritaires sur les droits par rôle.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100">
                        <th class="text-left px-5 py-3 text-xs font-medium text-gray-500">Destinataire</th>
                        <th class="text-center px-4 py-3 text-xs font-medium text-gray-500">👁</th>
                        <th class="text-center px-4 py-3 text-xs font-medium text-gray-500">⬇</th>
                        <th class="text-center px-4 py-3 text-xs font-medium text-gray-500">✏️</th>
                        <th class="text-center px-4 py-3 text-xs font-medium text-gray-500">⚙️</th>
                        <th class="px-4 py-3"></th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach($deptShares->merge($userShares) as $share)
                    <tr>
                        <td class="px-5 py-3">
                            @if($share->shared_with_type === 'department')
                                <span class="text-xs bg-blue-50 text-blue-600 px-2 py-0.5 rounded mr-1">Direction/Service</span>
                                {{ $share->sharedWithDepartment?->name ?? '—' }}
                            @else
                                <span class="text-xs bg-purple-50 text-purple-600 px-2 py-0.5 rounded mr-1">Utilisateur</span>
                                <span class="font-medium">{{ $share->sharedWithUser?->name ?? '—' }}</span>
                                <span class="text-xs text-gray-400 ml-1">{{ $share->sharedWithUser?->email }}</span>
                            @endif
                        </td>
                        {{-- Formulaire de modification inline --}}
                        <form method="POST" action="{{ route('media.albums.permissions.update', [$album, $share]) }}" class="contents">
                        @csrf @method('PATCH')
                        <td class="text-center px-4 py-3">
                            <input type="checkbox" name="can_view" value="1" {{ $share->can_view ? 'checked' : '' }}
                                   class="rounded border-gray-300 text-blue-600">
                        </td>
                        <td class="text-center px-4 py-3">
                            <input type="checkbox" name="can_download" value="1" {{ $share->can_download ? 'checked' : '' }}
                                   class="rounded border-gray-300 text-blue-600">
                        </td>
                        <td class="text-center px-4 py-3">
                            <input type="checkbox" name="can_edit" value="1" {{ $share->can_edit ? 'checked' : '' }}
                                   class="rounded border-gray-300 text-blue-600">
                        </td>
                        <td class="text-center px-4 py-3">
                            <input type="checkbox" name="can_manage" value="1" {{ $share->can_manage ? 'checked' : '' }}
                                   class="rounded border-gray-300 text-blue-600">
                        </td>
                        <td class="text-right px-4 py-3 space-x-2">
                            <button type="submit" class="text-blue-500 hover:text-blue-700 text-xs font-medium">Enregistrer</button>
                        </td>
                        </form>
                        <td class="px-2 py-3">
                            <form method="POST" action="{{ route('media.albums.permissions.destroy', [$album, $share]) }}"
                                  onsubmit="return confirm('Supprimer ce partage ?')">
                                @csrf @method('DELETE')
                                <button class="text-red-400 hover:text-red-600 text-xs">✕</button>
                            </form>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- ── Ajouter un partage ── --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
        <div class="p-5 border-b border-gray-100">
            <h2 class="text-sm font-semibold text-gray-700">Ajouter un partage</h2>
            <p class="text-xs text-gray-400 mt-1">Partager avec un utilisateur ou une direction/service.</p>
        </div>
        <form method="POST" action="{{ route('media.albums.permissions.store', $album) }}" class="p-5"
              x-data="{ type: 'user' }">
            @csrf
            <div class="flex items-end gap-3 flex-wrap">

                {{-- Type --}}
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Type</label>
                    <select name="shared_with_type" x-model="type"
                            class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-200">
                        <option value="user">Utilisateur</option>
                        <option value="department">Direction / Service</option>
                    </select>
                </div>

                {{-- Destinataire --}}
                <div class="flex-1 min-w-48">
                    <label class="block text-xs text-gray-500 mb-1">Destinataire</label>
                    <select x-show="type === 'user'" name="shared_with_id"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-200">
                        <option value="">-- Choisir un utilisateur --</option>
                        @foreach($users as $u)
                            <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->email }})</option>
                        @endforeach
                    </select>
                    <select x-show="type === 'department'" name="shared_with_id"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-200">
                        <option value="">-- Choisir une direction/service --</option>
                        @foreach($departments as $dept)
                            <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Droits --}}
                <div class="flex items-center gap-3 pb-2">
                    <label class="flex items-center gap-1.5 text-sm text-gray-600 cursor-pointer">
                        <input type="checkbox" name="can_view" value="1" class="w-4 h-4 rounded border-gray-300"> 👁 Voir
                    </label>
                    <label class="flex items-center gap-1.5 text-sm text-gray-600 cursor-pointer">
                        <input type="checkbox" name="can_download" value="1" class="w-4 h-4 rounded border-gray-300"> ⬇ Télécharger
                    </label>
                    <label class="flex items-center gap-1.5 text-sm text-gray-600 cursor-pointer">
                        <input type="checkbox" name="can_edit" value="1" class="w-4 h-4 rounded border-gray-300"> ✏️ Éditer
                    </label>
                    <label class="flex items-center gap-1.5 text-sm text-gray-600 cursor-pointer">
                        <input type="checkbox" name="can_manage" value="1" class="w-4 h-4 rounded border-gray-300"> ⚙️ Gérer
                    </label>
                </div>

                <button type="submit"
                        class="px-4 py-2 rounded-lg text-white text-sm font-medium hover:opacity-90"
                        style="background-color: var(--color-primary, #1E3A5F);">
                    Partager
                </button>
            </div>
        </form>
    </div>

</div>
@endsection
