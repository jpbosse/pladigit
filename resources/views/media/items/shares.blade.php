@extends('layouts.app')

@section('title', 'Partages — ' . $item->original_name)

@section('content')
<div class="max-w-3xl mx-auto px-4 py-8">

    {{-- En-tête --}}
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('media.items.show', [$item->album_id, $item]) }}"
           class="text-gray-400 hover:text-gray-600 text-sm">← Retour au média</a>
    </div>

    <h1 class="text-xl font-bold text-gray-800 mb-1">Partages individuels</h1>
    <p class="text-sm text-gray-500 mb-6">
        Partagez <span class="font-medium">{{ $item->original_name }}</span> avec des utilisateurs ou directions/services spécifiques,
        indépendamment des droits de l'album.
    </p>

    @if(session('success'))
    <div class="mb-4 px-4 py-3 bg-green-50 border border-green-200 rounded-lg text-green-700 text-sm">
        {{ session('success') }}
    </div>
    @endif

    {{-- Partages existants --}}
    @if($deptShares->isNotEmpty() || $userShares->isNotEmpty())
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm mb-6">
        <div class="p-5 border-b border-gray-100">
            <h2 class="text-sm font-semibold text-gray-700">Partages actifs</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100">
                        <th class="text-left px-5 py-3 text-xs font-medium text-gray-500">Destinataire</th>
                        <th class="text-center px-4 py-3 text-xs font-medium text-gray-500">👁 Voir</th>
                        <th class="text-center px-4 py-3 text-xs font-medium text-gray-500">⬇ Télécharger</th>
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
                        <form id="item-share-{{ $share->id }}"
                              method="POST"
                              action="{{ route('media.items.shares.update', [$item, $share]) }}"
                              style="display:none">
                            @csrf @method('PATCH')
                        </form>
                        <td class="text-center px-4 py-3">
                            <input type="checkbox" name="can_view" value="1"
                                   form="item-share-{{ $share->id }}"
                                   {{ $share->can_view ? 'checked' : '' }}
                                   class="rounded border-gray-300 text-blue-600">
                        </td>
                        <td class="text-center px-4 py-3">
                            <input type="checkbox" name="can_download" value="1"
                                   form="item-share-{{ $share->id }}"
                                   {{ $share->can_download ? 'checked' : '' }}
                                   class="rounded border-gray-300 text-blue-600">
                        </td>
                        <td class="px-4 py-3">
                            <button type="submit"
                                    form="item-share-{{ $share->id }}"
                                    class="text-blue-500 hover:text-blue-700 text-xs font-medium">Enregistrer</button>
                        </td>
                        <td class="px-2 py-3">
                            <form method="POST" action="{{ route('media.items.shares.destroy', [$item, $share]) }}"
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
    @else
    <p class="text-sm text-gray-400 mb-6">Aucun partage individuel — accès via les droits de l'album uniquement.</p>
    @endif

    {{-- Ajouter un partage --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
        <div class="p-5 border-b border-gray-100">
            <h2 class="text-sm font-semibold text-gray-700">Ajouter un partage</h2>
        </div>
        <div class="p-5">
            <form method="POST" action="{{ route('media.items.shares.store', $item) }}"
                  x-data="{ type: 'user' }">
                @csrf
                <div class="grid grid-cols-1 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Type</label>
                        <select name="shared_with_type" x-model="type"
                                class="w-full rounded-lg border-gray-300 text-sm">
                            <option value="user">Utilisateur</option>
                            <option value="department">Direction / Service</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Destinataire</label>
                        <select x-show="type === 'user'" name="shared_with_id" x-bind:disabled="type !== 'user'"
                                class="w-full rounded-lg border-gray-300 text-sm">
                            <option value="">-- Choisir un utilisateur --</option>
                            @foreach($users as $u)
                                <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->email }})</option>
                            @endforeach
                        </select>
                        <select x-show="type === 'department'" name="shared_with_id" x-bind:disabled="type !== 'department'"
                                class="w-full rounded-lg border-gray-300 text-sm">
                            <option value="">-- Choisir une direction/service --</option>
                            @foreach($departments as $dept)
                                <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex gap-6">
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" name="can_view" value="1" checked
                                   class="rounded border-gray-300 text-blue-600">
                            Voir
                        </label>
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" name="can_download" value="1"
                                   class="rounded border-gray-300 text-blue-600">
                            Télécharger
                        </label>
                    </div>
                    <div>
                        <button type="submit"
                                class="px-4 py-2 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700">
                            Partager
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
