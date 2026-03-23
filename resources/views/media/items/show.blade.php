{{-- resources/views/media/items/show.blade.php --}}
@extends('layouts.app')

@section('title', $item->caption ?? $item->file_name)

@push('styles')
<style>
.caption-display { cursor: pointer; }
.caption-display:hover { background: #f3f4f6; border-radius: 6px; }
.exif-badge {
    display: inline-flex; align-items: center; gap: 4px;
    background: #f3f4f6; border-radius: 6px;
    padding: 3px 8px; font-size: 0.72rem; color: #4b5563;
    font-weight: 500;
}
</style>
@endpush

@section('content')
<div class="max-w-5xl mx-auto px-4"
     x-data="mediaDetail()"
     @keydown.arrow-left.window="{{ $prev ? 'window.location=\''.route('media.items.show', [$album, $prev]).'\'' : '' }}"
     @keydown.arrow-right.window="{{ $next ? 'window.location=\''.route('media.items.show', [$album, $next]).'\'' : '' }}">

    {{-- Navigation fil d'Ariane --}}
    <div class="flex items-center gap-2 mb-4 text-sm text-gray-400">
        <a href="{{ route('media.albums.index') }}" class="hover:text-gray-600">📷 Photothèque</a>
        <span>/</span>
        <a href="{{ route('media.albums.show', $album) }}" class="hover:text-gray-600">{{ $album->name }}</a>
        <span>/</span>
        <span class="text-gray-600 truncate max-w-xs">{{ $item->file_name }}</span>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">

        {{-- ── Visionneuse ── --}}
        <div class="bg-gray-900 flex items-center justify-center relative" style="min-height: 400px; max-height: 70vh;">

            @if($item->isImage())
                <img src="{{ route('media.items.serve', [$album, $item, 'full']) }}"
                     alt="{{ $item->caption ?? $item->file_name }}"
                     class="max-w-full object-contain"
                     style="max-height: 70vh;">
            @elseif($item->isVideo())
                <video controls class="max-w-full" style="max-height: 70vh;">
                    <source src="{{ route('media.items.serve', [$album, $item, 'full']) }}"
                            type="{{ $item->mime_type }}">
                    Votre navigateur ne supporte pas la lecture vidéo.
                </video>
            @else
                <div class="text-center text-gray-400 py-16">
                    <p class="text-5xl mb-3">📄</p>
                    <p class="text-sm">{{ $item->file_name }}</p>
                    <a href="{{ route('media.items.download', [$album, $item]) }}"
                       class="mt-4 inline-flex items-center gap-2 px-4 py-2 rounded-lg text-white text-sm"
                       style="background-color:#1E3A5F;">
                        ⬇ Télécharger le PDF
                    </a>
                </div>
            @endif

            {{-- Navigation précédent / suivant --}}
            @if($prev)
                <a href="{{ route('media.items.show', [$album, $prev]) }}"
                   title="Précédent (←)"
                   class="absolute left-3 top-1/2 -translate-y-1/2 bg-black bg-opacity-40 hover:bg-opacity-70 text-white rounded-full w-11 h-11 flex items-center justify-center text-xl transition">
                    ‹
                </a>
            @endif
            @if($next)
                <a href="{{ route('media.items.show', [$album, $next]) }}"
                   title="Suivant (→)"
                   class="absolute right-3 top-1/2 -translate-y-1/2 bg-black bg-opacity-40 hover:bg-opacity-70 text-white rounded-full w-11 h-11 flex items-center justify-center text-xl transition">
                    ›
                </a>
            @endif

            {{-- Compteur position --}}
            @if($position && $total)
            <div class="absolute bottom-3 right-3 bg-black bg-opacity-50 text-white text-xs px-2.5 py-1 rounded-full">
                {{ $position }} / {{ $total }}
            </div>
            @endif
        </div>

        {{-- ── Description éditable ── --}}
        <div class="px-5 pt-4 pb-2 border-b border-gray-100">
            <div x-show="!editing" class="caption-display px-2 py-1.5 -mx-2 group flex items-center gap-2"
                 @click="startEdit()">
                <span class="text-gray-700 text-sm flex-1">
                    {{ $item->caption ?? '' }}
                    <span x-show="{{ $item->caption ? 'false' : 'true' }}" class="text-gray-400 italic">
                        Ajouter une description…
                    </span>
                </span>
                <span class="text-gray-300 group-hover:text-gray-500 text-xs opacity-0 group-hover:opacity-100 transition">✏️</span>
            </div>

            <div x-show="editing" x-cloak class="flex items-center gap-2">
                <input type="text"
                       x-ref="captionInput"
                       x-model="caption"
                       @keydown.enter="saveCaption()"
                       @keydown.escape="cancelEdit()"
                       placeholder="Description du média…"
                       class="flex-1 border border-blue-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-200">
                <button @click="saveCaption()"
                        class="px-3 py-1.5 rounded-lg text-white text-xs font-medium"
                        style="background-color:#1E3A5F;">
                    ✓
                </button>
                <button @click="cancelEdit()"
                        class="px-3 py-1.5 rounded-lg border border-gray-200 text-gray-500 text-xs">
                    ✕
                </button>
            </div>

            <div x-show="saved" x-cloak class="text-xs text-green-600 mt-1">✓ Description sauvegardée</div>
            <div x-show="error" x-cloak class="text-xs text-red-500 mt-1" x-text="error"></div>
        </div>

        {{-- ── Infos + EXIF ── --}}
        <div class="p-5 grid grid-cols-1 md:grid-cols-2 gap-6">

            {{-- Infos fichier --}}
            <div>
                <h2 class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-3">Fichier</h2>
                <dl class="space-y-2 text-sm">
                    <div class="flex gap-2">
                        <dt class="text-gray-400 w-28 shrink-0">Nom</dt>
                        <dd class="text-gray-700 font-medium truncate" title="{{ $item->file_name }}">{{ $item->file_name }}</dd>
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
                    @if($item->sha256_hash)
                    <div class="flex gap-2">
                        <dt class="text-gray-400 w-28 shrink-0">SHA-256</dt>
                        <dd class="text-gray-400 font-mono text-xs truncate" title="{{ $item->sha256_hash }}">{{ substr($item->sha256_hash, 0, 16) }}…</dd>
                    </div>
                    @endif
                </dl>
            </div>

            {{-- EXIF --}}
            @php
                $exif = $item->exif_data ?? [];
                $takenAt = $item->takenAt();
                $gps = $item->gpsCoordinates();
            @endphp

            @if($item->isImage())
            <div>
                <h2 class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-3">
                    Métadonnées EXIF
                    @if(empty($exif))
                        <span class="text-gray-300 font-normal normal-case">(non disponibles)</span>
                    @endif
                </h2>

                @if(!empty($exif))
                <dl class="space-y-2 text-sm">

                    @if($takenAt)
                    <div class="flex gap-2">
                        <dt class="text-gray-400 w-28 shrink-0">📅 Prise le</dt>
                        <dd class="text-gray-700">{{ $takenAt->format('d/m/Y à H:i:s') }}</dd>
                    </div>
                    @endif

                    @if(!empty($exif['Make']))
                    <div class="flex gap-2">
                        <dt class="text-gray-400 w-28 shrink-0">📷 Appareil</dt>
                        <dd class="text-gray-700">{{ $exif['Make'] }} {{ $exif['Model'] ?? '' }}</dd>
                    </div>
                    @endif

                    @if(!empty($exif['Software']))
                    <div class="flex gap-2">
                        <dt class="text-gray-400 w-28 shrink-0">💾 Logiciel</dt>
                        <dd class="text-gray-700 truncate">{{ $exif['Software'] }}</dd>
                    </div>
                    @endif

                    {{-- Paramètres de prise de vue --}}
                    @php
                        $shootingParams = [];
                        if (!empty($exif['ExposureTime'])) {
                            $exp = $exif['ExposureTime'];
                            $shootingParams[] = is_float($exp) && $exp < 1
                                ? '1/' . round(1/$exp) . 's'
                                : $exp . 's';
                        }
                        if (!empty($exif['FNumber'])) {
                            $shootingParams[] = 'f/' . number_format($exif['FNumber'], 1);
                        }
                        if (!empty($exif['ISOSpeedRatings'])) {
                            $iso = is_array($exif['ISOSpeedRatings']) ? $exif['ISOSpeedRatings'][0] : $exif['ISOSpeedRatings'];
                            $shootingParams[] = 'ISO ' . $iso;
                        }
                        if (!empty($exif['FocalLength'])) {
                            $fl = $exif['FocalLength'];
                            $flStr = (floor($fl) == $fl ? (int)$fl : round($fl, 1)) . 'mm';
                            if (!empty($exif['FocalLengthIn35mmFilm'])) {
                                $flStr .= ' (' . $exif['FocalLengthIn35mmFilm'] . 'mm eq.)';
                            }
                            $shootingParams[] = $flStr;
                        }
                    @endphp

                    @if(!empty($shootingParams))
                    <div class="flex gap-2">
                        <dt class="text-gray-400 w-28 shrink-0">⚙️ Réglages</dt>
                        <dd class="flex flex-wrap gap-1">
                            @foreach($shootingParams as $param)
                                <span class="exif-badge">{{ $param }}</span>
                            @endforeach
                        </dd>
                    </div>
                    @endif

                    @if(isset($exif['Flash']))
                    <div class="flex gap-2">
                        <dt class="text-gray-400 w-28 shrink-0">⚡ Flash</dt>
                        <dd class="text-gray-700">{{ ($exif['Flash'] & 1) ? 'Déclenché' : 'Non déclenché' }}</dd>
                    </div>
                    @endif

                    @if(isset($exif['MeteringMode']))
                    @php
                        $meteringLabels = [0=>'Inconnu',1=>'Moyenne',2=>'Pondérée centrale',3=>'Spot',4=>'Multi-spot',5=>'Multi-zones',6=>'Partielle'];
                    @endphp
                    <div class="flex gap-2">
                        <dt class="text-gray-400 w-28 shrink-0">🎯 Mesure</dt>
                        <dd class="text-gray-700">{{ $meteringLabels[$exif['MeteringMode']] ?? $exif['MeteringMode'] }}</dd>
                    </div>
                    @endif

                    @if(isset($exif['WhiteBalance']))
                    <div class="flex gap-2">
                        <dt class="text-gray-400 w-28 shrink-0">🌡 Balance</dt>
                        <dd class="text-gray-700">{{ $exif['WhiteBalance'] == 0 ? 'Automatique' : 'Manuel' }}</dd>
                    </div>
                    @endif

                    @if(isset($exif['ExposureMode']))
                    @php
                        $exposureModeLabels = [0=>'Auto',1=>'Manuel',2=>'Auto bracketing'];
                    @endphp
                    <div class="flex gap-2">
                        <dt class="text-gray-400 w-28 shrink-0">📊 Exposition</dt>
                        <dd class="text-gray-700">{{ $exposureModeLabels[$exif['ExposureMode']] ?? $exif['ExposureMode'] }}</dd>
                    </div>
                    @endif

                    @if($gps)
                    <div class="flex gap-2">
                        <dt class="text-gray-400 w-28 shrink-0">📍 GPS</dt>
                        <dd>
                            <a href="https://www.openstreetmap.org/?mlat={{ $gps['lat'] }}&mlon={{ $gps['lng'] }}&zoom=15"
                               target="_blank"
                               class="text-blue-600 hover:underline text-sm">
                                {{ number_format(abs($gps['lat']), 5) }}°{{ $gps['lat'] >= 0 ? 'N' : 'S' }}
                                {{ number_format(abs($gps['lng']), 5) }}°{{ $gps['lng'] >= 0 ? 'E' : 'O' }} ↗
                            </a>
                            @if(!empty($exif['GPSAltitude']))
                            <span class="text-gray-400 text-xs ml-1">· {{ round($exif['GPSAltitude']) }} m</span>
                            @endif
                        </dd>
                    </div>
                    @endif

                    @if(!empty($exif['Orientation']) && $exif['Orientation'] != 1)
                    @php
                        $orientationLabels = [1=>'Normal',2=>'Miroir H',3=>'Rotation 180°',4=>'Miroir V',5=>'Miroir H + 270°',6=>'Rotation 90°',7=>'Miroir H + 90°',8=>'Rotation 270°'];
                    @endphp
                    <div class="flex gap-2">
                        <dt class="text-gray-400 w-28 shrink-0">🔄 Orientation</dt>
                        <dd class="text-gray-700">{{ $orientationLabels[$exif['Orientation']] ?? $exif['Orientation'] }}</dd>
                    </div>
                    @endif

                </dl>
                @endif
            </div>
            @endif

        </div>

        {{-- ── Actions ── --}}
        <div class="px-5 pb-5 flex items-center gap-3 border-t border-gray-100 pt-4 flex-wrap">
            <a href="{{ route('media.items.download', [$album, $item]) }}"
               class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-white text-sm font-medium hover:opacity-90 transition"
               style="background-color: var(--color-primary, #1E3A5F);">
                ⬇ Télécharger
            </a>

            @if($prev)
            <a href="{{ route('media.items.show', [$album, $prev]) }}"
               class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-gray-200 text-gray-600 text-sm hover:bg-gray-50">
                ‹ Précédent
            </a>
            @endif

            @if($next)
            <a href="{{ route('media.items.show', [$album, $next]) }}"
               class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-gray-200 text-gray-600 text-sm hover:bg-gray-50">
                Suivant ›
            </a>
            @endif

            <div class="ml-auto flex items-center gap-2">
                @can('manage', $item)
                <a href="{{ route('media.items.shares.edit', $item) }}"
                   class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-gray-600 border border-gray-200 text-sm font-medium hover:bg-gray-50">
                    🔐 Partager
                </a>
                @endcan
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

    {{-- Hint navigation clavier --}}
    @if($prev || $next)
    <p class="text-center text-xs text-gray-400 mt-3">
        Utilisez les touches ← → pour naviguer entre les médias
    </p>
    @endif

</div>

<script>
function mediaDetail() {
    return {
        editing: false,
        caption: @json($item->caption ?? ''),
        originalCaption: @json($item->caption ?? ''),
        saved: false,
        error: null,

        startEdit() {
            this.editing = true;
            this.saved = false;
            this.error = null;
            this.$nextTick(() => this.$refs.captionInput.focus());
        },

        cancelEdit() {
            this.editing = false;
            this.caption = this.originalCaption;
        },

        async saveCaption() {
            this.error = null;
            try {
                const resp = await fetch('{{ route('media.items.updateCaption', [$album, $item]) }}', {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ caption: this.caption }),
                });

                if (!resp.ok) throw new Error('Erreur serveur');

                this.originalCaption = this.caption;
                this.editing = false;
                this.saved = true;
                setTimeout(() => this.saved = false, 3000);
            } catch (e) {
                this.error = 'Impossible de sauvegarder.';
            }
        }
    };
}
</script>
@endsection
