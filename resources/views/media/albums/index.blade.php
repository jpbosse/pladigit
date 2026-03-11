@extends('layouts.app')

@section('title', 'Photothèque')

@section('content')
<div class="max-w-7xl mx-auto px-4">

    {{-- En-tête --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">📷 Photothèque</h1>
            <p class="text-sm text-gray-500 mt-1">{{ $albums->total() }} album(s)</p>
        </div>
        <div class="flex items-center gap-2">
            @if(Auth::user()?->role === 'admin')
            <a href="{{ route('admin.settings.media') }}" class="text-sm text-gray-500 hover:text-gray-700 border border-gray-200 px-3 py-2 rounded-lg bg-white">⚙ Paramètres</a>
            @endif
            <a href="{{ route('media.albums.create') }}"
               class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-white text-sm font-medium"
               style="background-color: var(--color-primary, #1E3A5F);">
                + Nouvel album
            </a>
        </div>
    </div>

    {{-- Messages flash --}}
    @if(session('success'))
        <div class="mb-4 p-3 bg-green-50 border border-green-200 text-green-800 rounded-lg text-sm">
            {{ session('success') }}
        </div>
    @endif

    {{-- Grille des albums --}}
    @if($albums->isEmpty())
        <div class="text-center py-20 text-gray-400">
            <p class="text-4xl mb-3">🗂️</p>
            <p class="text-lg font-medium">Aucun album pour le moment.</p>
            <p class="text-sm mt-1">Créez votre premier album pour commencer.</p>
        </div>
    @else
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-6">
            @foreach($albums as $album)
                <div class="flex flex-col gap-2">

                    {{-- Carte album racine --}}
                    <a href="{{ route('media.albums.show', $album) }}"
                       class="group block bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-md transition">
                        <div class="aspect-square bg-gray-100 flex items-center justify-center overflow-hidden">
                            @if($album->cover_path)
                                <img src="{{ route('media.items.serve', ['album' => $album->id, 'item' => 0]) }}"
                                     alt="{{ $album->name }}"
                                     class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-200">
                            @else
                                <span class="text-4xl opacity-30">🖼️</span>
                            @endif
                        </div>
                        <div class="p-2">
                            <p class="text-xs font-semibold text-gray-800 truncate">{{ $album->name }}</p>
                            <div class="flex items-center justify-between mt-1">
                                <span class="text-xs text-gray-400">
                                    {{ $album->items_count }} photo(s)
                                    @if($album->children_count > 0)
                                        · <span class="text-blue-400">{{ $album->children_count }} sous-album(s)</span>
                                    @endif
                                </span>
                                <span class="text-xs px-1.5 py-0.5 rounded-full
                                    @if($album->visibility === 'public') bg-green-100 text-green-700
                                    @elseif($album->visibility === 'restricted') bg-yellow-100 text-yellow-700
                                    @else bg-gray-100 text-gray-600 @endif">
                                    {{ $album->visibilityLabel() }}
                                </span>
                            </div>
                        </div>
                    </a>

                </div>
            @endforeach
        </div>

        {{-- Pagination --}}
        <div class="mt-6">
            {{ $albums->links() }}
        </div>
    @endif

</div>
@endsection


