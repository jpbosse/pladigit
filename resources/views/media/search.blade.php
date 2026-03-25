@extends('layouts.app')
@section('title', 'Recherche — Photothèque')

@push('styles')
<style>
#ph-wrap {
    display: flex;
    height: calc(100vh - var(--pd-topbar-h) - var(--pd-footer-h));
    overflow: hidden;
}
#ph-sidebar {
    width: 220px; flex-shrink: 0;
    border-right: 1px solid var(--pd-border);
    display: flex; flex-direction: column;
    background: var(--pd-surface2); overflow: hidden;
}
.ph-sidebar-header { padding: 12px 14px 10px; border-bottom: 1px solid var(--pd-border); }
.ph-upload-btn {
    width: 100%; padding: 7px 12px;
    background: var(--pd-navy); color: #fff;
    border: none; border-radius: 8px; font-size: 12px; cursor: pointer;
    display: flex; align-items: center; justify-content: center; gap: 6px;
    text-decoration: none; transition: background .15s;
}
.ph-upload-btn:hover { background: var(--pd-navy-light); }
.ph-nav { flex: 1; overflow-y: auto; padding: 6px 0; }
.ph-nav-section { padding: 8px 14px 3px; font-size: 10px; font-weight: 600; color: var(--pd-muted); text-transform: uppercase; letter-spacing: .5px; }
.ph-nav-item {
    display: flex; align-items: center; gap: 7px;
    padding: 5px 14px; cursor: pointer; color: var(--pd-muted);
    font-size: 12px; text-decoration: none;
    transition: background .1s, color .1s;
    border-right: 2px solid transparent;
}
.ph-nav-item:hover { background: var(--pd-surface); color: var(--pd-text); }
.ph-nav-item.active { background: var(--pd-surface); color: var(--pd-navy); font-weight: 600; border-right-color: var(--pd-accent); }
.ph-nav-count { margin-left: auto; font-size: 10px; background: var(--pd-border); padding: 1px 6px; border-radius: 10px; color: var(--pd-muted); }
.ph-storage { padding: 10px 14px; border-top: 1px solid var(--pd-border); font-size: 11px; color: var(--pd-muted); }
.ph-storage-bar { height: 4px; background: var(--pd-border); border-radius: 2px; margin: 5px 0 3px; overflow: hidden; }
.ph-storage-fill { height: 100%; background: var(--pd-accent); border-radius: 2px; }

#ph-main { flex: 1; display: flex; flex-direction: column; min-width: 0; overflow: hidden; }
#ph-header {
    height: 46px; flex-shrink: 0;
    border-bottom: 1px solid var(--pd-border);
    display: flex; align-items: center; padding: 0 16px; gap: 12px;
    background: var(--pd-surface);
}
.ph-hbtn {
    width: 28px; height: 28px;
    border: 1px solid var(--pd-border); border-radius: 7px;
    background: transparent; cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    color: var(--pd-muted); font-size: 12px; transition: all .15s;
    text-decoration: none;
}
.ph-hbtn:hover { background: var(--pd-bg); color: var(--pd-text); }
#ph-content { flex: 1; overflow-y: auto; padding: 20px; }

/* Formulaire de recherche */
.sr-form {
    display: flex; flex-wrap: wrap; gap: 8px; align-items: flex-end;
    background: var(--pd-surface); border: 1px solid var(--pd-border);
    border-radius: 10px; padding: 14px 16px; margin-bottom: 20px;
}
.sr-field { display: flex; flex-direction: column; gap: 4px; }
.sr-label { font-size: 10px; font-weight: 600; color: var(--pd-muted); text-transform: uppercase; letter-spacing: .4px; }
.sr-input {
    padding: 6px 10px; font-size: 12px;
    border: 1px solid var(--pd-border); border-radius: 7px;
    background: var(--pd-bg); color: var(--pd-text); outline: none;
}
.sr-input:focus { border-color: var(--pd-accent); }
.sr-q { min-width: 240px; flex: 1; }
.sr-btn {
    padding: 7px 18px; border-radius: 8px; font-size: 12px; font-weight: 600;
    background: var(--pd-navy); color: #fff; border: none; cursor: pointer;
    transition: opacity .15s; align-self: flex-end;
}
.sr-btn:hover { opacity: .85; }
.sr-reset { background: var(--pd-bg); color: var(--pd-muted); border: 1px solid var(--pd-border); padding: 6px 12px; border-radius: 8px; font-size: 12px; cursor: pointer; text-decoration: none; }

/* Grille résultats */
.sr-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
    gap: 12px;
}
.sr-card {
    border-radius: 10px; overflow: hidden; border: 1px solid var(--pd-border);
    background: var(--pd-surface); text-decoration: none;
    transition: box-shadow .15s, transform .15s; display: block;
}
.sr-card:hover { box-shadow: 0 6px 20px rgba(0,0,0,.1); transform: translateY(-2px); }
.sr-thumb {
    aspect-ratio: 1; overflow: hidden; background: var(--pd-bg);
    display: flex; align-items: center; justify-content: center; font-size: 30px;
}
.sr-thumb img { width: 100%; height: 100%; object-fit: cover; transition: transform .3s; }
.sr-card:hover .sr-thumb img { transform: scale(1.04); }
.sr-info { padding: 7px 9px; }
.sr-name { font-size: 11px; font-weight: 600; color: var(--pd-text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.sr-album { font-size: 10px; color: var(--pd-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-top: 2px; }
.sr-date  { font-size: 10px; color: var(--pd-muted); margin-top: 1px; }

.sr-empty { text-align: center; padding: 60px 0; color: var(--pd-muted); }
.sr-count { font-size: 12px; color: var(--pd-muted); margin-bottom: 14px; }
</style>
@endpush

@section('content')
@php
    $quotaMb  = app(\App\Services\TenantManager::class)->current()?->storage_quota_mb ?? 10240;
    $usedBytes = \App\Models\Tenant\MediaItem::sum('file_size_bytes');
    $usedMb   = round($usedBytes / 1024 / 1024, 1);
    $usedPct  = $quotaMb > 0 ? min(100, round($usedMb / $quotaMb * 100)) : 0;
@endphp

<div id="ph-wrap">

    {{-- Sidebar --}}
    <aside id="ph-sidebar">
        <div class="ph-sidebar-header">
            <a href="{{ route('media.albums.create') }}" class="ph-upload-btn">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                Nouvel album
            </a>
        </div>

        <nav class="ph-nav">
            <div class="ph-nav-section">Navigation</div>
            <a href="{{ route('media.albums.index') }}" class="ph-nav-item">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                Tous les albums
            </a>
            <a href="{{ route('media.search') }}" class="ph-nav-item active">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                Recherche
            </a>

            <div class="ph-nav-section">Albums</div>
            @include('media._sidebar_tree', [
                'albumTree'     => $albumTree,
                'activeAlbumId' => null,
                'ancestorIds'   => [],
            ])
        </nav>

        <div class="ph-storage">
            {{ $usedMb }} Mo / {{ $quotaMb >= 1024 ? round($quotaMb/1024,1).' Go' : $quotaMb.' Mo' }}
            <div class="ph-storage-bar">
                <div class="ph-storage-fill" style="width:{{ $usedPct }}%"></div>
            </div>
            {{ $usedPct }}% utilisé
        </div>
    </aside>

    {{-- Main --}}
    <div id="ph-main">
        <div id="ph-header">
            <span style="font-size:13px;font-weight:600;color:var(--pd-text);">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" style="vertical-align:middle;margin-right:4px;"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                Recherche dans la photothèque
            </span>
            <div style="margin-left:auto;">
                <a href="{{ route('media.albums.index') }}" class="ph-hbtn" title="Retour aux albums">←</a>
            </div>
        </div>

        <div id="ph-content">

            <p style="font-size:12px;color:var(--pd-muted);margin:0 0 12px;">
                Recherchez parmi tous vos fichiers par nom ou légende · filtrez par type et par date de prise de vue (EXIF) ou, à défaut, par date d'import dans Pladigit.
            </p>

            {{-- Formulaire --}}
            <form method="GET" action="{{ route('media.search') }}" class="sr-form">
                <div class="sr-field" style="flex:1;min-width:200px;">
                    <label class="sr-label">Mot-clé</label>
                    <div style="position:relative;">
                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                             style="position:absolute;left:9px;top:50%;transform:translateY(-50%);color:var(--pd-muted);pointer-events:none;">
                            <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                        </svg>
                        <input type="text" name="q" value="{{ $q }}"
                               class="sr-input" style="padding-left:28px;width:100%;box-sizing:border-box;"
                               placeholder="Nom de fichier, légende…" autofocus>
                    </div>
                </div>

                <div class="sr-field">
                    <label class="sr-label">Type</label>
                    <select name="type" class="sr-input">
                        <option value=""        {{ $type === ''         ? 'selected' : '' }}>Tous</option>
                        <option value="image"   {{ $type === 'image'   ? 'selected' : '' }}>Images</option>
                        <option value="video"   {{ $type === 'video'   ? 'selected' : '' }}>Vidéos</option>
                        <option value="document"{{ $type === 'document'? 'selected' : '' }}>Documents</option>
                    </select>
                </div>

                <div class="sr-field">
                    <label class="sr-label">Du</label>
                    <input type="date" name="from" value="{{ $from }}" class="sr-input">
                </div>
                <div class="sr-field">
                    <label class="sr-label">Au</label>
                    <input type="date" name="to" value="{{ $to }}" class="sr-input">
                </div>

                <button type="submit" class="sr-btn">Rechercher</button>
                @if($q || $type || $from || $to)
                    <a href="{{ route('media.search') }}" class="sr-reset">Effacer</a>
                @endif
            </form>

            {{-- Résultats --}}
            @if($results === null)
                <div class="sr-empty">
                    <div style="font-size:40px;margin-bottom:12px;">🔍</div>
                    <p style="font-size:14px;font-weight:500;color:var(--pd-text);">Lancez une recherche</p>
                    <p style="font-size:12px;margin-top:4px;">Cherchez par nom de fichier, légende, type ou date de prise de vue.</p>
                </div>

            @elseif($results->isEmpty())
                <div class="sr-empty">
                    <div style="font-size:40px;margin-bottom:12px;">😶</div>
                    <p style="font-size:14px;font-weight:500;color:var(--pd-text);">Aucun résultat</p>
                    <p style="font-size:12px;margin-top:4px;">Essayez d'autres mots-clés ou retirez des filtres.</p>
                </div>

            @else
                <div class="sr-count">
                    {{ number_format($results->total()) }} résultat{{ $results->total() > 1 ? 's' : '' }}
                    @if($q) pour « {{ $q }} » @endif
                    @if($type) · {{ ['image'=>'Images','video'=>'Vidéos','document'=>'Documents'][$type] ?? '' }} @endif
                    @if($from || $to) · {{ $from ?: '…' }} → {{ $to ?: '…' }} @endif
                </div>

                <div class="sr-grid">
                    @foreach($results as $item)
                        @php
                            $albumUrl = route('media.albums.show', $item->album);
                            $thumbUrl = $item->thumb_path
                                ? route('media.items.serve', [$item->album, $item, 'thumb'])
                                : null;
                            $breadcrumb = $item->album->parent
                                ? $item->album->parent->name . ' › ' . $item->album->name
                                : $item->album->name;
                            $dateLabel = $item->exif_taken_at
                                ? $item->exif_taken_at->format('d/m/Y')
                                : $item->created_at->format('d/m/Y');
                        @endphp
                        <a href="{{ $albumUrl }}#item-{{ $item->id }}" class="sr-card" title="{{ $item->caption ?? $item->file_name }}">
                            <div class="sr-thumb">
                                @if($thumbUrl && $item->isImage())
                                    <img src="{{ $thumbUrl }}" alt="{{ $item->caption ?? $item->file_name }}" loading="lazy">
                                @elseif($item->isVideo())
                                    🎬
                                @else
                                    📄
                                @endif
                            </div>
                            <div class="sr-info">
                                <div class="sr-name">{{ $item->caption ?? $item->file_name }}</div>
                                <div class="sr-album">
                                    <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" style="vertical-align:middle;margin-right:2px;"><path d="M22 19a2 2 0 01-2 2H4a2 2 0 01-2-2V5a2 2 0 012-2h5l2 3h9a2 2 0 012 2z"/></svg>
                                    {{ $breadcrumb }}
                                </div>
                                <div class="sr-date">📅 {{ $dateLabel }}</div>
                            </div>
                        </a>
                    @endforeach
                </div>

                @if($results->hasPages())
                    <div style="margin-top:24px;display:flex;justify-content:center;">
                        {{ $results->links() }}
                    </div>
                @endif
            @endif

        </div>
    </div>
</div>
@endsection
