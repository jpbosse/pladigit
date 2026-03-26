{{--
    Sidebar unifiée de la photothèque.
    Variables attendues :
      $albumTree      — collection d'albums racine (buildSidebarTree)
      $activeAlbumId  — int|null  (null = on est sur l'index)
      $ancestorIds    — int[]     (vide sur l'index)
      $album          — MediaAlbum|null  (null = on est sur l'index)
      $totalAlbums    — int|null  (affiché dans le badge "Tous les albums" sur l'index)
--}}
@php
    $album        ??= null;
    $activeAlbumId ??= null;
    $ancestorIds  ??= [];
    $totalAlbums  ??= null;
    $quotaMb   = app(\App\Services\TenantManager::class)->current()?->storage_quota_mb ?? 10240;
    $usedBytes = \App\Models\Tenant\MediaItem::sum('file_size_bytes');
    $usedMb    = round($usedBytes / 1024 / 1024, 1);
    $usedPct   = $quotaMb > 0 ? min(100, round($usedMb / $quotaMb * 100)) : 0;
@endphp

<aside id="ph-sidebar">

    {{-- ── Bouton principal ────────────────────────────────── --}}
    <div class="ph-sidebar-header">
        @if($album)
            @can('upload', $album)
            <button class="ph-upload-btn" @click="$refs.fileInput.click()">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M17 8l-5-5-5 5M12 3v12"/></svg>
                Téléverser
            </button>
            @endcan
        @else
            <a href="{{ route('media.albums.create') }}" class="ph-upload-btn">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                Nouvel album
            </a>
        @endif
    </div>

    {{-- ── Arbre de navigation ─────────────────────────────── --}}
    <nav class="ph-nav">
        <div class="ph-nav-section">Navigation</div>

        <a href="{{ route('media.albums.index') }}"
           class="ph-nav-item {{ $activeAlbumId === null ? 'active' : '' }}">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
            Tous les albums
            @if($totalAlbums !== null)
                <span class="ph-nav-count">{{ $totalAlbums }}</span>
            @endif
        </a>

        <div class="ph-nav-section">Albums</div>
        @include('media._sidebar_tree', [
            'albumTree'     => $albumTree,
            'activeAlbumId' => $activeAlbumId,
            'ancestorIds'   => $ancestorIds,
        ])

        @if($album)
        <div class="ph-nav-section">Actions</div>
        <a href="{{ route('media.albums.create') }}" class="ph-nav-item">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
            Nouvel album
        </a>
        <a href="{{ route('media.albums.create', ['parent_id' => $album->id]) }}" class="ph-nav-item">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M22 19a2 2 0 01-2 2H4a2 2 0 01-2-2V5a2 2 0 012-2h5l2 3h9a2 2 0 012 2z"/><path d="M12 11v6M9 14h6"/></svg>
            Nouveau sous-dossier
        </a>
        @endif
    </nav>

    {{-- ── Jauge stockage ──────────────────────────────────── --}}
    <div class="ph-storage">
        {{ $usedMb }} Mo / {{ $quotaMb >= 1024 ? round($quotaMb/1024,1).' Go' : $quotaMb.' Mo' }}
        <div class="ph-storage-bar">
            <div class="ph-storage-fill" style="width:{{ $usedPct }}%
                @if($usedPct >= 90) ;background:#e74c3c
                @elseif($usedPct >= 80) ;background:#f59e0b
                @endif"></div>
        </div>
        {{ $usedPct }}% utilisé
    </div>

</aside>
