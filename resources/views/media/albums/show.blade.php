@extends('layouts.app')

@section('title', $album->name)

@push('styles')
<style>
    .media-grid {
        display: grid;
        gap: 12px;
    }
    .media-grid[data-cols="1"] { grid-template-columns: repeat(1, 1fr); }
    .media-grid[data-cols="2"] { grid-template-columns: repeat(2, 1fr); }
    .media-grid[data-cols="3"] { grid-template-columns: repeat(3, 1fr); }
    .media-grid[data-cols="4"] { grid-template-columns: repeat(4, 1fr); }
    .media-grid[data-cols="5"] { grid-template-columns: repeat(5, 1fr); }
    .media-grid[data-cols="6"] { grid-template-columns: repeat(6, 1fr); }

    .media-card {
        position: relative;
        aspect-ratio: 1;
        border-radius: 10px;
        overflow: hidden;
        background: #f3f4f6;
        cursor: pointer;
        box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        transition: box-shadow 0.2s, transform 0.2s;
    }
    .media-card:hover {
        box-shadow: 0 8px 24px rgba(0,0,0,0.14);
        transform: translateY(-2px);
    }
    .media-card img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.3s ease;
    }
    .media-card:hover img { transform: scale(1.04); }

    .media-card .overlay {
        position: absolute;
        inset: 0;
        background: linear-gradient(to top, rgba(0,0,0,0.65) 0%, transparent 55%);
        opacity: 0;
        transition: opacity 0.2s;
        display: flex;
        flex-direction: column;
        justify-content: flex-end;
        padding: 10px;
	pointer-events: none;
    }
    .media-card:hover .overlay { opacity: 1; }

    .media-card .filename {
        color: #fff;
        font-size: 0.72rem;
        font-weight: 500;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        margin-bottom: 6px;
        text-shadow: 0 1px 2px rgba(0,0,0,0.5);
    }
    .media-card .actions { display: flex; gap: 6px; }
    .media-card .btn-action {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 30px;
        height: 30px;
        border-radius: 6px;
        background: rgba(255,255,255,0.2);
        backdrop-filter: blur(4px);
        border: 1px solid rgba(255,255,255,0.3);
        color: #fff;
        font-size: 0.75rem;
        transition: background 0.15s;
        text-decoration: none;
        cursor: pointer;
	pointer-events: auto;
    }
    .media-card .btn-action:hover { background: rgba(255,255,255,0.35); }
    .media-card .btn-action.danger:hover { background: rgba(239,68,68,0.7); }

    .media-card .type-badge {
        position: absolute;
        top: 8px;
        right: 8px;
        background: rgba(0,0,0,0.45);
        backdrop-filter: blur(4px);
        color: #fff;
        font-size: 0.65rem;
        font-weight: 600;
        padding: 2px 6px;
        border-radius: 4px;
        letter-spacing: 0.04em;
        text-transform: uppercase;
    }

    .video-thumb {
        width: 100%; height: 100%;
        display: flex; align-items: center; justify-content: center;
        background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
    }
    .play-icon {
        width: 48px; height: 48px;
        background: rgba(255,255,255,0.15);
        border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        backdrop-filter: blur(4px);
        border: 2px solid rgba(255,255,255,0.3);
        transition: background 0.2s;
    }
    .media-card:hover .play-icon { background: rgba(255,255,255,0.25); }

    .pdf-thumb {
        width: 100%; height: 100%;
        display: flex; flex-direction: column;
        align-items: center; justify-content: center;
        background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
        gap: 6px;
    }

    /* Sélecteur de colonnes */
    .cols-selector {
        display: flex;
        align-items: center;
        gap: 4px;
        background: #f3f4f6;
        border-radius: 8px;
        padding: 4px;
    }
    .cols-btn {
        width: 32px; height: 28px;
        border-radius: 5px;
        border: none;
        background: transparent;
        cursor: pointer;
        display: flex; align-items: center; justify-content: center;
        transition: background 0.15s;
        color: #6b7280;
        font-size: 0.7rem;
        font-weight: 600;
    }
    .cols-btn:hover { background: #e5e7eb; color: #374151; }
    .cols-btn.active {
        background: var(--color-primary, #1E3A5F);
        color: #fff;
    }

    .dropzone {
        border: 2px dashed #d1d5db;
        border-radius: 14px;
        padding: 32px 24px;
        text-align: center;
        transition: border-color 0.2s, background 0.2s;
        background: #fff;
        margin-bottom: 28px;
    }
    .dropzone.dragging {
        border-color: var(--color-primary, #1E3A5F);
        background: #eff6ff;
    }

    .progress-bar {
        width: 100%; background: #e5e7eb;
        border-radius: 99px; height: 6px;
        overflow: hidden; margin-top: 12px;
    }
    .progress-fill {
        height: 100%; border-radius: 99px;
        background: var(--color-primary, #1E3A5F);
        transition: width 0.3s ease;
    }
</style>
@endpush

@section('content')
<div class="max-w-7xl mx-auto px-4"
     x-data="albumPage('{{ route('media.items.store', $album) }}', '{{ csrf_token() }}', {{ $userCols }}, '{{ route('media.prefs.cols') }}')"
     @dragover.prevent="dragging = true"
     @dragleave.prevent="dragging = false"
     @drop.prevent="handleDrop($event)">

    {{-- En-tête --}}
    <div class="flex items-center gap-3 mb-4 flex-wrap">
        <a href="{{ route('media.albums.index') }}" class="text-gray-400 hover:text-gray-600 text-sm">
            ← Photothèque
        </a>
        <span class="text-gray-300">/</span>
        <h1 class="text-xl font-bold text-gray-800">{{ $album->name }}</h1>

        <span class="text-xs px-2.5 py-1 rounded-full font-medium
            @if($album->visibility === 'public') bg-green-100 text-green-700
            @elseif($album->visibility === 'restricted') bg-amber-100 text-amber-700
            @else bg-gray-100 text-gray-500 @endif">
            {{ $album->visibilityLabel() }}
        </span>

        <span class="text-xs text-gray-400 bg-gray-100 px-2.5 py-1 rounded-full font-medium">
            {{ $items->total() }} fichier{{ $items->total() > 1 ? 's' : '' }}
        </span>

        {{-- Sélecteur de colonnes --}}
        <div class="cols-selector ml-2" title="Nombre de colonnes">
            @foreach([1, 2, 3, 4, 5, 6] as $c)
                <button class="cols-btn"
                        :class="{ active: cols === {{ $c }} }"
                        @click="setCols({{ $c }})">
                    {{ $c }}
                </button>
            @endforeach
        </div>

        <div class="ml-auto flex items-center gap-2">
            
            @can('manage', $album)
            <a href="{{ route('media.albums.permissions.edit', $album) }}"
               class="text-xs text-gray-500 hover:text-gray-700 border border-gray-200 px-3 py-1.5 rounded-lg bg-white">
                🔐 Droits
            </a>
            @endcan
            <a href="{{ route('media.albums.edit', $album) }}"
               class="text-xs text-gray-500 hover:text-gray-700 border border-gray-200 px-3 py-1.5 rounded-lg bg-white">
                ✏️ Modifier
            </a>
            <form method="POST" action="{{ route('media.albums.destroy', $album) }}"
                  onsubmit="return confirm('Supprimer cet album et tous ses médias ?')">
                @csrf @method('DELETE')
                <button class="text-xs text-red-500 hover:text-red-700 border border-red-200 px-3 py-1.5 rounded-lg bg-white">
                    🗑 Supprimer
                </button>
            </form>
        </div>
    </div>

    @if($album->description)
        <p class="text-sm text-gray-500 mb-5">{{ $album->description }}</p>
    @endif

    {{-- Messages flash --}}
    @if(session('success'))
        <div class="mb-4 p-3 bg-green-50 border border-green-200 text-green-800 rounded-lg text-sm">
            ✅ {{ session('success') }}
        </div>
    @endif
    @if(session('upload_errors'))
        <div class="mb-4 p-3 bg-red-50 border border-red-200 text-red-800 rounded-lg text-sm">
            <p class="font-semibold mb-1">⚠️ Certains fichiers n'ont pas pu être importés :</p>
            <ul class="list-disc list-inside space-y-0.5">
                @foreach(session('upload_errors') as $err)<li>{{ $err }}</li>@endforeach
            </ul>
        </div>
    @endif

    {{-- Zone drag & drop --}}
    <div class="dropzone" :class="{ dragging: dragging }">
        <div class="text-3xl mb-2">📤</div>
        <p class="text-gray-500 text-sm mb-3">Glissez vos fichiers ici ou</p>
        <label class="cursor-pointer inline-flex items-center gap-2 px-5 py-2.5 rounded-lg text-white text-sm font-medium shadow-sm hover:opacity-90 transition-opacity"
               style="background-color: var(--color-primary, #1E3A5F);">
            Choisir des fichiers
            <input type="file" multiple class="hidden" @change="handleFileInput($event)">
        </label>
        <p class="text-xs text-gray-400 mt-3">JPEG · PNG · WEBP · GIF · MP4 · MOV · PDF — 200 Mo max</p>
        <div x-show="uploading" class="mt-4 max-w-xs mx-auto">
            <div class="progress-bar">
                <div class="progress-fill" :style="'width:' + progress + '%'"></div>
            </div>
            <p class="text-xs text-gray-500 mt-2" x-text="statusText"></p>
        </div>
    </div>

    {{-- Grille --}}
    @if($items->isEmpty())
        <div class="text-center py-20 text-gray-400">
            <div class="text-5xl mb-3">🖼️</div>
            <p class="text-base font-medium text-gray-500 mb-1">Cet album est vide</p>
            <p class="text-sm">Uploadez vos premiers fichiers ci-dessus.</p>
        </div>
    @else
        <div class="media-grid" :style="`grid-template-columns: repeat(${cols}, 1fr)`">
            @foreach($items as $item)
                <div class="media-card">
                    @if($item->isVideo())
                        <span class="type-badge">Vidéo</span>
                    @elseif(!$item->isImage())
                        <span class="type-badge">PDF</span>
                    @endif

                    <a href="{{ route('media.items.show', [$album, $item]) }}" class="block w-full h-full">
                        @if($item->isImage())
                            <img src="{{ route('media.items.serve', [$album, $item, 'thumb']) }}"
                                 alt="{{ $item->caption ?? $item->file_name }}" loading="lazy">
                        @elseif($item->isVideo())
                            <div class="video-thumb">
                                <div class="play-icon">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="white"><path d="M8 5v14l11-7z"/></svg>
                                </div>
                            </div>
                        @else
                            <div class="pdf-thumb">
                                <span class="text-4xl">📄</span>
                                <span class="text-xs font-medium text-amber-800 text-center px-2 truncate w-full">{{ $item->file_name }}</span>
                            </div>
                        @endif
                    </a>

                    <div class="overlay">
                        <div class="filename">{{ $item->caption ?? $item->file_name }}</div>
                        <div class="actions">
                            <a href="{{ route('media.items.download', [$album, $item]) }}"
                               title="Télécharger" class="btn-action">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                    <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M7 10l5 5 5-5M12 15V3"/>
                                </svg>
                            </a>
                            <form method="POST" action="{{ route('media.items.destroy', [$album, $item]) }}"
                                  onsubmit="return confirm('Supprimer ce fichier ?')" style="display:inline">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn-action danger" title="Supprimer">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                        <polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4h6v2"/>
                                    </svg>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        @if($items->hasPages())
            <div class="mt-8">{{ $items->links() }}</div>
        @endif
    @endif

</div>

@endsection
