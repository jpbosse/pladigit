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

        @if(in_array(auth()->user()?->role, ['admin', 'president', 'dgs']))
        <div class="ph-nav-section">Administration</div>
        @php $dupCount = \App\Models\Tenant\MediaItem::whereNotNull('sha256_hash')->selectRaw('sha256_hash')->groupBy('sha256_hash')->havingRaw('COUNT(*) > 1')->get()->count(); @endphp
        <a href="{{ route('media.duplicates.index') }}"
           class="ph-nav-item {{ request()->routeIs('media.duplicates.*') ? 'active' : '' }}">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="2" y="7" width="16" height="13" rx="2"/><path d="M6 7V5a2 2 0 012-2h12a2 2 0 012 2v13a2 2 0 01-2 2"/></svg>
            Doublons
            @if($dupCount > 0)
                <span class="ph-nav-count" style="background:#e74c3c;color:#fff">{{ $dupCount }}</span>
            @endif
        </a>
        <a href="{{ route('media.integrity.index') }}"
           class="ph-nav-item {{ request()->routeIs('media.integrity.*') ? 'active' : '' }}">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M9 12l2 2 4-4"/><path d="M20.618 5.984A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622C17.176 19.29 21 14.591 21 9a12.02 12.02 0 00-.382-3.016z"/></svg>
            Intégrité
        </a>
        @endif

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
