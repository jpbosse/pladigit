@extends('layouts.app')

@section('title', 'Modifier ' . $album->name)

@section('content')
<div class="max-w-xl mx-auto px-4">

    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('media.albums.show', $album) }}" class="text-gray-400 hover:text-gray-600 text-sm">
            ← {{ $album->name }}
        </a>
        <span class="text-gray-300">/</span>
        <h1 class="text-xl font-bold text-gray-800">Modifier l'album</h1>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <form method="POST" action="{{ route('media.albums.update', $album) }}">
            @csrf @method('PUT')

            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Nom de l'album <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="name" value="{{ old('name', $album->name) }}" required
                           class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-300">
                    @error('name')
                        <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea name="description" rows="3"
                              class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-300">{{ old('description', $album->description) }}</textarea>
                </div>

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
@endsection
