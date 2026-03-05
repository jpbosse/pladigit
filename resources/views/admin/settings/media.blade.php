@extends('layouts.app')

@section('title', 'Paramètres Photothèque')

@section('content')
<div class="max-w-2xl mx-auto px-4">

    <div class="mb-6">
        <h1 class="text-xl font-bold text-gray-800">Paramètres Photothèque</h1>
        <p class="text-sm text-gray-500 mt-1">Configuration de l'affichage par défaut pour tous les utilisateurs.</p>
    </div>

    @if(session('success'))
        <div class="mb-4 p-3 bg-green-50 border border-green-200 text-green-800 rounded-lg text-sm">
            ✅ {{ session('success') }}
        </div>
    @endif

    <form method="POST" action="{{ route('admin.settings.media.update') }}" class="bg-white rounded-xl border border-gray-200 p-6 shadow-sm">
        @csrf @method('PUT')

        <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">
                Nombre de colonnes par défaut
            </label>
            <p class="text-xs text-gray-500 mb-3">
                Valeur initiale pour tous les utilisateurs. Chaque utilisateur peut modifier ce réglage depuis l'album (mémorisé dans son navigateur).
            </p>

            <div class="flex gap-3">
                @foreach([1 => '1 colonne', 2 => '2 colonnes', 3 => '3 colonnes', 4 => '4 colonnes', 5 => '5 colonnes', 6 => '6 colonnes'] as $val => $label)
                    <label class="flex flex-col items-center gap-2 cursor-pointer">
                        <input type="radio" name="media_default_cols" value="{{ $val }}"
                               {{ ($settings->media_default_cols ?? 3) == $val ? 'checked' : '' }}
                               class="sr-only peer">
                        <div class="w-16 h-14 rounded-lg border-2 border-gray-200 peer-checked:border-blue-600 peer-checked:bg-blue-50 flex items-center justify-center transition-all">
                            {{-- Miniature grille --}}
                            <div class="grid gap-0.5" style="grid-template-columns: repeat({{ min($val, 3) }}, 1fr); width: 36px;">
                                @for($i = 0; $i < min($val * 2, 9); $i++)
                                    <div class="bg-gray-300 peer-checked:bg-blue-300 rounded-sm" style="height: 10px;"></div>
                                @endfor
                            </div>
                        </div>
                        <span class="text-xs text-gray-600 font-medium">{{ $label }}</span>
                    </label>
                @endforeach
            </div>

            @error('media_default_cols')
                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex justify-end">
            <button type="submit"
                    class="px-5 py-2 rounded-lg text-white text-sm font-medium hover:opacity-90 transition-opacity"
                    style="background-color: var(--color-primary, #1E3A5F);">
                Enregistrer
            </button>
        </div>
    </form>

</div>
@endsection
