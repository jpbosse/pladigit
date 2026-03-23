@extends('layouts.app')

@section('title', 'Nouvel album')

@section('content')
<div class="max-w-xl mx-auto px-4">

    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('media.albums.index') }}" class="text-gray-400 hover:text-gray-600 text-sm">
            ← Photothèque
        </a>
        @if($selectedParent)
            @php $parentAlbumBread = \App\Models\Tenant\MediaAlbum::find($selectedParent) @endphp
            <span class="text-gray-300">/</span>
            <a href="{{ route('media.albums.show', $selectedParent) }}" class="text-gray-400 hover:text-gray-600 text-sm">
                {{ $parentAlbumBread?->name }}
            </a>
        @endif
        <span class="text-gray-300">/</span>
        <h1 class="text-xl font-bold text-gray-800">
            {{ $selectedParent ? 'Nouveau sous-album' : 'Nouvel album' }}
        </h1>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <form method="POST" action="{{ route('media.albums.store') }}">
            @csrf

            <div class="space-y-4">
                {{-- Nom --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Nom de l'album <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="name" value="{{ old('name') }}" required
                           class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-300
                                  @error('name') border-red-400 @enderror">
                    @error('name')
                        <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Description --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea name="description" rows="3"
                              class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-300
                                     @error('description') border-red-400 @enderror">{{ old('description') }}</textarea>
                    @error('description')
                        <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Info NAS --}}
                <div class="px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg text-xs text-gray-500">
                    📁 Un dossier NAS sera créé automatiquement avec le nom de l'album.
                    Pour restreindre l'accès, configurez les permissions après création.
                </div>

                {{-- Album parent --}}
                @if($selectedParent)
                    {{-- Vient d'un clic "+ Sous-album" : parent fixé, pas de select --}}
                    @php $parentAlbum = \App\Models\Tenant\MediaAlbum::find($selectedParent) @endphp
                    <input type="hidden" name="parent_id" value="{{ $selectedParent }}">
                    <div class="flex items-center gap-2 px-3 py-2 bg-blue-50 border border-blue-100 rounded-lg text-sm text-blue-700">
                        <span>📁</span>
                        <span>Sous-album de <strong>{{ $parentAlbum?->name ?? '—' }}</strong></span>
                    </div>
                @elseif($parentAlbums->isNotEmpty())
                    {{-- Création depuis la liste : select optionnel --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Sous-album de <span class="text-gray-400 font-normal">(optionnel)</span>
                        </label>
                        <select name="parent_id"
                                class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-300">
                            <option value="">— Aucun (album racine)</option>
                            @foreach($parentAlbums as $parent)
                                <option value="{{ $parent->id }}"
                                    {{ old('parent_id') == $parent->id ? 'selected' : '' }}>
                                    {{ $parent->name }}
                                </option>
                            @endforeach
                        </select>
                        <p class="text-xs text-gray-400 mt-1">
                            Si défini, cet album héritera des droits de l'album parent.
                        </p>
                        @error('parent_id')
                            <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                @endif

                {{-- Visibilité --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Visibilité <span class="text-red-500">*</span>
                    </label>
                    <select name="visibility" required
                            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-300">
                        <option value="restricted" {{ old('visibility') === 'restricted' ? 'selected' : '' }}>
                            Restreint — visible par les membres connectés
                        </option>
                        <option value="public" {{ old('visibility') === 'public' ? 'selected' : '' }}>
                            Public — visible par tous
                        </option>
                        <option value="private" {{ old('visibility') === 'private' ? 'selected' : '' }}>
                            Privé — visible par moi uniquement
                        </option>
                    </select>
                    @error('visibility')
                        <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 mt-6 pt-4 border-t border-gray-100">
                <a href="{{ route('media.albums.index') }}"
                   class="text-sm text-gray-500 hover:text-gray-700">
                    Annuler
                </a>
                <button type="submit"
                        class="px-5 py-2 rounded-lg text-white text-sm font-medium"
                        style="background-color: var(--color-primary, #1E3A5F);">
                    {{ $selectedParent ? 'Créer le sous-album' : 'Créer l\'album' }}
                </button>
            </div>
        </form>
    </div>

</div>
@endsection
