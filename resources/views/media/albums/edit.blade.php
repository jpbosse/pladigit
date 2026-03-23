@extends('layouts.app')

@section('title', 'Modifier ' . $album->name)

@section('content')
<div class="max-w-xl mx-auto px-4">

    <div class="flex items-center gap-3 mb-6">
        @if($album->parent)
            <a href="{{ route('media.albums.show', $album->parent) }}" class="text-gray-400 hover:text-gray-600 text-sm">
                ← {{ $album->parent->name }}
            </a>
            <span class="text-gray-300">/</span>
        @else
            <a href="{{ route('media.albums.index') }}" class="text-gray-400 hover:text-gray-600 text-sm">
                ← Photothèque
            </a>
            <span class="text-gray-300">/</span>
        @endif
        <a href="{{ route('media.albums.show', $album) }}" class="text-gray-400 hover:text-gray-600 text-sm">
            {{ $album->name }}
        </a>
        <span class="text-gray-300">/</span>
        <h1 class="text-xl font-bold text-gray-800">Modifier</h1>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <form method="POST" action="{{ route('media.albums.update', $album) }}">
            @csrf @method('PUT')

            <div class="space-y-4">
                {{-- Nom --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Nom de l'album <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="name" value="{{ old('name', $album->name) }}" required
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
                              class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-300">{{ old('description', $album->description) }}</textarea>
                </div>

                {{-- Album parent --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Sous-album de
                    </label>
                    <select name="parent_id"
                            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-300">
                        <option value="">— Aucun (album racine)</option>
                        @foreach($parentAlbums as $parent)
                            <option value="{{ $parent->id }}"
                                {{ old('parent_id', $album->parent_id) == $parent->id ? 'selected' : '' }}>
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

                {{-- Visibilité --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Visibilité</label>
                    <select name="visibility" required
                            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-300">
                        @foreach(['restricted' => 'Restreint', 'public' => 'Public', 'private' => 'Privé'] as $val => $label)
                            <option value="{{ $val }}" {{ old('visibility', $album->visibility) === $val ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 mt-6 pt-4 border-t border-gray-100">
                <a href="{{ route('media.albums.show', $album) }}"
                   class="text-sm text-gray-500 hover:text-gray-700">Annuler</a>
                <button type="submit"
                        class="px-5 py-2 rounded-lg text-white text-sm font-medium"
                        style="background-color: var(--color-primary, #1E3A5F);">
                    Enregistrer
                </button>
            </div>
        </form>
    </div>

</div>
    {{-- ── Section couverture ─────────────────────────────────────────── --}}
    @if($coverItems->isNotEmpty())
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mt-4">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h2 class="text-sm font-semibold text-gray-700">Image de couverture</h2>
                <p class="text-xs text-gray-400 mt-0.5">
                    Première image utilisée par défaut. Cliquez pour choisir une autre.
                </p>
            </div>
            @if($album->cover_item_id)
            <form method="POST" action="{{ route('media.albums.cover.reset', $album) }}"
                  onsubmit="return confirm('Réinitialiser vers la première image ?')">
                @csrf @method('DELETE')
                <button type="submit"
                        class="text-xs text-amber-600 hover:text-amber-700 border border-amber-200 rounded-lg px-3 py-1.5 hover:bg-amber-50 transition-colors">
                    ↺ Réinitialiser
                </button>
            </form>
            @endif
        </div>

        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(80px,1fr));gap:8px;">
            @foreach($coverItems as $item)
            <form method="POST" action="{{ route('media.albums.cover', [$album, $item]) }}">
                @csrf @method('PUT')
                <button type="submit"
                        title="{{ $item->caption ?? $item->file_name }}"
                        style="
                            width:100%;aspect-ratio:1;padding:0;border-radius:8px;overflow:hidden;
                            border:3px solid {{ $currentCover?->id === $item->id ? '#f59e0b' : 'transparent' }};
                            cursor:pointer;position:relative;background:none;
                            box-shadow:{{ $currentCover?->id === $item->id ? '0 0 0 1px #f59e0b' : 'none' }};
                            transition:border-color .15s, box-shadow .15s;
                        "
                        onmouseover="this.style.borderColor='#f59e0b'"
                        onmouseout="this.style.borderColor='{{ $currentCover?->id === $item->id ? '#f59e0b' : 'transparent' }}'">
                    <img src="{{ route('media.items.serve', [$album, $item, 'thumb']) }}"
                         alt="{{ $item->caption ?? $item->file_name }}"
                         style="width:100%;height:100%;object-fit:cover;display:block;">
                    @if($currentCover?->id === $item->id)
                    <span style="
                        position:absolute;top:3px;right:3px;
                        background:#f59e0b;border-radius:50%;
                        width:16px;height:16px;font-size:9px;
                        display:flex;align-items:center;justify-content:center;color:#fff;
                        line-height:1;
                    ">⭐</span>
                    @endif
                </button>
            </form>
            @endforeach
        </div>

        @if($coverItems->count() === 24)
        <p class="text-xs text-gray-400 mt-3">
            Affichage limité aux 24 premières images. Pour en choisir d'autres,
            utilisez le bouton ⭐ dans la galerie au survol d'une photo.
        </p>
        @endif
    </div>
    @endif


@endsection
