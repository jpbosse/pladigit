@extends('layouts.app')

@section('title', $item->file_name)

@section('content')
<div class="max-w-5xl mx-auto px-4">

    {{-- Navigation --}}
    <div class="flex items-center gap-3 mb-4 text-sm">
        <a href="{{ route('media.albums.show', $album) }}" class="text-gray-400 hover:text-gray-600">
            ← {{ $album->name }}
        </a>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">

        {{-- Visionneuse --}}
        <div class="bg-gray-900 flex items-center justify-center min-h-96 relative">

            @if($item->isImage())
                <img src="{{ route('media.items.serve', [$album, $item, 'full']) }}"
                     alt="{{ $item->caption ?? $item->file_name }}"
                     class="max-w-full max-h-screen-75 object-contain">
            @elseif($item->isVideo())
                <video controls class="max-w-full max-h-96">
                    <source src="{{ route('media.items.serve', [$album, $item, 'full']) }}"
                            type="{{ $item->mime_type }}">
                    Votre navigateur ne supporte pas la lecture vidéo.
                </video>
            @else
                <div class="text-center text-gray-400 py-16">
                    <p class="text-5xl mb-3">📄</p>
                    <p class="text-sm">{{ $item->file_name }}</p>
                </div>
            @endif

            {{-- Navigation précédent / suivant --}}
            @if($prev)
                <a href="{{ route('media.items.show', [$album, $prev]) }}"
                   class="absolute left-3 top-1/2 -translate-y-1/2 bg-black bg-opacity-40 hover:bg-opacity-60 text-white rounded-full w-10 h-10 flex items-center justify-center text-lg transition">
                    ‹
                </a>
            @endif
            @if($next)
                <a href="{{ route('media.items.show', [$album, $next]) }}"
                   class="absolute right-3 top-1/2 -translate-y-1/2 bg-black bg-opacity-40 hover:bg-opacity-60 text-white rounded-full w-10 h-10 flex items-center justify-center text-lg transition">
                    ›
                </a>
            @endif
        </div>

        {{-- Métadonnées --}}
        <div class="p-5 grid grid-cols-1 md:grid-cols-2 gap-6">

            {{-- Infos fichier --}}
            <div>
                <h2 class="text-sm font-semibold text-gray-700 mb-3">Informations</h2>
                <dl class="space-y-1.5 text-sm">
                    <div class="flex gap-2">
                        <dt class="text-gray-400 w-28 shrink-0">Nom</dt>
                        <dd class="text-gray-700 font-medium truncate">{{ $item->file_name }}</dd>
                    </div>
                    <div class="flex gap-2">
                        <dt class="text-gray-400 w-28 shrink-0">Taille</dt>
                        <dd class="text-gray-700">{{ $item->humanSize() }}</dd>
                    </div>
                    @if($item->width_px && $item->height_px)
                    <div class="flex gap-2">
                        <dt class="text-gray-400 w-28 shrink-0">Dimensions</dt>
                        <dd class="text-gray-700">{{ $item->width_px }} × {{ $item->height_px }} px</dd>
                    </div>
                    @endif
                    <div class="flex gap-2">
                        <dt class="text-gray-400 w-28 shrink-0">Type</dt>
                        <dd class="text-gray-700">{{ $item->mime_type }}</dd>
                    </div>
                    <div class="flex gap-2">
                        <dt class="text-gray-400 w-28 shrink-0">Ajouté le</dt>
                        <dd class="text-gray-700">{{ $item->created_at->format('d/m/Y à H:i') }}</dd>
                    </div>
                    @if($item->uploader)
                    <div class="flex gap-2">
                        <dt class="text-gray-400 w-28 shrink-0">Par</dt>
                        <dd class="text-gray-700">{{ $item->uploader->name }}</dd>
                    </div>
                    @endif
                </dl>
            </div>

            {{-- EXIF --}}
            @if($item->exif_data && count($item->exif_data) > 0)
            <div>
                <h2 class="text-sm font-semibold text-gray-700 mb-3">Métadonnées EXIF</h2>
                <dl class="space-y-1.5 text-sm">
                    @if($item->takenAt())
                    <div class="flex gap-2">
                        <dt class="text-gray-400 w-28 shrink-0">Prise le</dt>
                        <dd class="text-gray-700">{{ $item->takenAt()->format('d/m/Y à H:i') }}</dd>
                    </div>
                    @endif
                    @if(!empty($item->exif_data['Make']))
                    <div class="flex gap-2">
                        <dt class="text-gray-400 w-28 shrink-0">Appareil</dt>
                        <dd class="text-gray-700">{{ $item->exif_data['Make'] }} {{ $item->exif_data['Model'] ?? '' }}</dd>
                    </div>
                    @endif
                    @php $gps = $item->gpsCoordinates(); @endphp
                    @if($gps)
                    <div class="flex gap-2">
                        <dt class="text-gray-400 w-28 shrink-0">GPS</dt>
                        <dd class="text-gray-700">{{ number_format($gps['lat'], 6) }}, {{ number_format($gps['lng'], 6) }}</dd>
                    </div>
                    @endif
                </dl>
            </div>
            @endif
        </div>

        {{-- Actions --}}
        <div class="px-5 pb-5 flex items-center gap-3 border-t border-gray-100 pt-4">
            <a href="{{ route('media.items.download', [$album, $item]) }}"
               class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-white text-sm font-medium"
               style="background-color: var(--color-primary, #1E3A5F);">
                ⬇ Télécharger
            </a>

            <form method="POST" action="{{ route('media.items.destroy', [$album, $item]) }}"
                  onsubmit="return confirm('Supprimer définitivement ce fichier ?')">
                @csrf @method('DELETE')
                <button class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-red-600 border border-red-200 text-sm font-medium hover:bg-red-50">
                    🗑 Supprimer
                </button>
            </form>
        </div>

    </div>
</div>
@endsection
