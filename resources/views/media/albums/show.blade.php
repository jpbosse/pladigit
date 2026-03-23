@extends('layouts.app')

@section('title', $album->name . ' — Photothèque')

@push('styles')
<style>
/* ── Layout général ─────────────────────────────────────── */
#ph-wrap {
    display: flex;
    height: calc(100vh - var(--pd-topbar-h) - var(--pd-footer-h));
    overflow: hidden;
}
/* ── Sidebar ────────────────────────────────────────────── */
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
    transition: background .15s;
}
.ph-upload-btn:hover { background: var(--pd-navy-light); }
.ph-nav { flex: 1; overflow-y: auto; padding: 6px 0; }
.ph-nav-section { padding: 8px 14px 3px; font-size: 10px; font-weight: 600; color: var(--pd-muted); text-transform: uppercase; letter-spacing: .5px; }
.ph-nav-item {
    display: flex; align-items: center; gap: 7px;
    padding: 5px 14px; cursor: pointer; color: var(--pd-muted);
    font-size: 12px; text-decoration: none;
    transition: background .1s, color .1s;
    border-right: 2px solid transparent; border: none; width: 100%; background: transparent; text-align: left;
}
.ph-nav-item:hover { background: var(--pd-surface); color: var(--pd-text); }
.ph-nav-item.active { background: var(--pd-surface); color: var(--pd-navy); font-weight: 600; border-right: 2px solid var(--pd-accent); }
.ph-nav-child { padding-left: 28px; }
.ph-nav-count { margin-left: auto; font-size: 10px; background: var(--pd-border); padding: 1px 6px; border-radius: 10px; color: var(--pd-muted); }
.ph-storage { padding: 10px 14px; border-top: 1px solid var(--pd-border); font-size: 11px; color: var(--pd-muted); }
.ph-storage-bar { height: 4px; background: var(--pd-border); border-radius: 2px; margin: 5px 0 3px; overflow: hidden; }
.ph-storage-fill { height: 100%; background: var(--pd-accent); border-radius: 2px; }
/* ── Main ───────────────────────────────────────────────── */
#ph-main { flex: 1; display: flex; flex-direction: column; min-width: 0; overflow: hidden; }
#ph-header {
    height: 46px; flex-shrink: 0;
    border-bottom: 1px solid var(--pd-border);
    display: flex; align-items: center; padding: 0 16px; gap: 12px;
    background: var(--pd-surface);
}
.ph-breadcrumb { display: flex; align-items: center; gap: 5px; font-size: 12px; color: var(--pd-muted); white-space: nowrap; }
.ph-breadcrumb a { color: var(--pd-muted); text-decoration: none; }
.ph-breadcrumb a:hover { color: var(--pd-text); }
.ph-breadcrumb .current { color: var(--pd-text); font-weight: 600; }
.ph-search-wrap {
    flex: 1; max-width: 280px;
    display: flex; align-items: center; gap: 6px;
    background: var(--pd-bg); border: 1px solid var(--pd-border);
    border-radius: 8px; padding: 0 10px; height: 30px;
}
.ph-search-wrap input { border: none; background: transparent; font-size: 12px; color: var(--pd-text); outline: none; width: 100%; }
.ph-header-right { margin-left: auto; display: flex; align-items: center; gap: 5px; }
.ph-hbtn {
    width: 28px; height: 28px;
    border: 1px solid var(--pd-border); border-radius: 7px;
    background: transparent; cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    color: var(--pd-muted); font-size: 12px; transition: all .15s;
    text-decoration: none;
}
.ph-hbtn:hover { background: var(--pd-bg); color: var(--pd-text); }
.ph-hbtn.active { background: var(--pd-navy); color: #fff; border-color: var(--pd-navy); }
@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
#ph-toolbar {
    height: 40px; flex-shrink: 0;
    border-bottom: 1px solid var(--pd-border);
    display: flex; align-items: center; padding: 0 16px; gap: 7px;
    background: var(--pd-surface);
}
.ph-filter {
    padding: 3px 9px; border-radius: 20px;
    border: 1px solid var(--pd-border); background: transparent;
    cursor: pointer; font-size: 11px; color: var(--pd-muted);
    text-decoration: none; transition: all .1s; white-space: nowrap;
}
.ph-filter:hover { border-color: var(--pd-accent); color: var(--pd-text); }
.ph-filter.active { background: var(--pd-navy); color: #fff; border-color: var(--pd-navy); }
.ph-vsep { width: 1px; height: 18px; background: var(--pd-border); flex-shrink: 0; }
.ph-select {
    font-size: 11px; border: 1px solid var(--pd-border);
    border-radius: 6px; padding: 3px 6px;
    background: transparent; color: var(--pd-text); cursor: pointer;
}
.ph-toolbar-right { margin-left: auto; display: flex; align-items: center; gap: 6px; font-size: 11px; color: var(--pd-muted); }
#ph-sel-bar {
    background: var(--pd-navy); color: #fff;
    padding: 7px 16px; display: none; align-items: center; gap: 10px;
    font-size: 12px; flex-shrink: 0;
}
#ph-sel-bar.visible { display: flex; }
.ph-sel-btn {
    padding: 3px 10px; border-radius: 6px;
    border: 1px solid rgba(255,255,255,.3); background: transparent;
    color: #fff; font-size: 11px; cursor: pointer;
}
.ph-sel-btn:hover { background: rgba(255,255,255,.15); }
#ph-content { flex: 1; overflow-y: auto; padding: 14px 16px; }
/* Dropzone */
.ph-dropzone {
    border: 2px dashed var(--pd-border); border-radius: 12px;
    padding: 16px 20px; text-align: center; margin-bottom: 14px;
    cursor: pointer; transition: border-color .15s, background .15s;
    font-size: 12px; color: var(--pd-muted);
}
.ph-dropzone.dragover { border-color: var(--pd-accent); background: rgba(59,154,225,0.05); }
.ph-dz-actions { display: flex; justify-content: center; gap: 8px; margin-top: 10px; flex-wrap: wrap; }
.ph-dz-btn {
    padding: 5px 12px; border-radius: 7px; font-size: 11px;
    font-weight: 600; cursor: pointer;
    border: 1px solid var(--pd-border); background: var(--pd-surface); color: var(--pd-text);
    transition: background .1s;
}
.ph-dz-btn.primary { background: var(--pd-navy); color: #fff; border-color: var(--pd-navy); }
.ph-progress { margin-top: 8px; max-width: 280px; margin-inline: auto; }
.ph-progress-bar { height: 4px; background: var(--pd-border); border-radius: 2px; overflow: hidden; }
.ph-progress-fill { height: 100%; background: var(--pd-accent); border-radius: 2px; transition: width .3s; }
/* Sous-albums */
.ph-subalbums { display: grid; grid-template-columns: repeat(auto-fill, minmax(90px, 1fr)); gap: 8px; margin-bottom: 14px; }
.ph-sub-card {
    border: 1px solid var(--pd-border); border-radius: 8px;
    overflow: hidden; text-decoration: none; background: var(--pd-surface);
    transition: box-shadow .15s;
}
.ph-sub-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,.1); }
.ph-sub-thumb { aspect-ratio: 1; background: var(--pd-bg); display: flex; align-items: center; justify-content: center; font-size: 20px; color: var(--pd-muted); }
.ph-sub-info { padding: 5px 7px; }
.ph-sub-name { font-size: 11px; font-weight: 600; color: var(--pd-text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ph-sub-count { font-size: 10px; color: var(--pd-muted); }
/* Grille */
.ph-grid { display: grid; gap: 10px; }
.ph-grid[data-cols="3"] { grid-template-columns: repeat(3, 1fr); }
.ph-grid[data-cols="4"] { grid-template-columns: repeat(4, 1fr); }
.ph-grid[data-cols="5"] { grid-template-columns: repeat(5, 1fr); }
.ph-grid[data-cols="6"] { grid-template-columns: repeat(6, 1fr); }
/* Carte */
.ph-card {
    position: relative; aspect-ratio: 1;
    border-radius: 8px; overflow: hidden;
    border: 1px solid var(--pd-border); cursor: pointer;
    background: var(--pd-bg); transition: box-shadow .15s, transform .15s;
}
.ph-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,.12); transform: translateY(-1px); }
.ph-card img { width: 100%; height: 100%; object-fit: cover; transition: transform .3s; }
.ph-card:hover img { transform: scale(1.04); }
.ph-overlay {
    position: absolute; inset: 0;
    background: linear-gradient(to top, rgba(30,58,95,.7) 0%, transparent 55%);
    opacity: 0; transition: opacity .15s;
    display: flex; flex-direction: column; justify-content: flex-end;
    padding: 8px; pointer-events: none;
}
.ph-card:hover .ph-overlay { opacity: 1; }
.ph-card-name { color: #fff; font-size: 10px; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-bottom: 4px; }
.ph-card-acts { display: flex; gap: 4px; pointer-events: auto; }
.ph-act-cover-active { color:#f59e0b !important; filter: drop-shadow(0 0 3px rgba(245,158,11,.6)); }
.ph-dup-badge {
    position:absolute;top:4px;left:4px;z-index:3;
    background:rgba(239,68,68,.9);color:#fff;
    font-size:9px;font-weight:700;
    padding:2px 5px;border-radius:4px;
    display:flex;align-items:center;gap:3px;
    pointer-events:none;
}
.ph-act {
    width: 22px; height: 22px;
    background: rgba(255,255,255,.2); border: none; border-radius: 4px;
    color: #fff; font-size: 10px; cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    transition: background .1s; text-decoration: none;
}
.ph-act:hover { background: rgba(255,255,255,.4); }
.ph-type-badge {
    position: absolute; top: 6px; left: 6px;
    background: rgba(0,0,0,.5); color: #fff;
    font-size: 9px; padding: 1px 5px; border-radius: 3px;
    font-weight: 600; text-transform: uppercase;
}
.ph-check {
    position: absolute; top: 6px; right: 6px;
    width: 16px; height: 16px; border-radius: 3px;
    border: 1.5px solid rgba(255,255,255,.6);
    background: rgba(0,0,0,.2);
    display: flex; align-items: center; justify-content: center;
    font-size: 9px; color: transparent; transition: all .1s;
    pointer-events: auto;
}
.ph-card.selected .ph-check { background: var(--pd-accent); border-color: var(--pd-accent); color: #fff; }
.ph-card.selected { outline: 2px solid var(--pd-accent); outline-offset: 2px; }
.ph-placeholder { display: flex; align-items: center; justify-content: center; width:100%;height:100%; font-size: 28px; color: var(--pd-muted); }
/* Vue liste */
.ph-list { display: flex; flex-direction: column; gap: 2px; }
.ph-list-row {
    display: grid; grid-template-columns: 36px 1fr 60px 90px 70px 50px;
    align-items: center; gap: 10px; padding: 5px 8px; border-radius: 6px;
    font-size: 12px; cursor: pointer; border: 1px solid transparent; transition: background .1s;
}
.ph-list-row:hover { background: var(--pd-bg); border-color: var(--pd-border); }
.ph-list-row.selected { background: rgba(59,154,225,0.08); border-color: var(--pd-accent); }
.ph-list-th { color: var(--pd-muted); font-size: 10px; font-weight: 600; text-transform: uppercase; letter-spacing: .4px; padding: 0 8px 4px; cursor: default; }
.ph-list-thumb { width: 32px; height: 32px; border-radius: 4px; object-fit: cover; }
.ph-list-thumb-icon { width: 32px; height: 32px; border-radius: 4px; display: flex; align-items: center; justify-content: center; font-size: 14px; }
.ph-list-name { font-weight: 500; color: var(--pd-text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ph-list-meta { color: var(--pd-muted); text-align: right; white-space: nowrap; }
/* Footer */
#ph-footer {
    height: 34px; flex-shrink: 0;
    border-top: 1px solid var(--pd-border);
    display: flex; align-items: center; justify-content: space-between;
    padding: 0 16px; font-size: 11px; color: var(--pd-muted);
    background: var(--pd-surface);
}
.ph-page-btn {
    width: 24px; height: 24px;
    border: 1px solid var(--pd-border); border-radius: 5px;
    background: transparent; cursor: pointer;
    font-size: 11px; color: var(--pd-muted);
    display: flex; align-items: center; justify-content: center; transition: all .1s;
}
.ph-page-btn:hover:not(:disabled) { background: var(--pd-bg); }
.ph-page-btn.active { background: var(--pd-navy); color: #fff; border-color: var(--pd-navy); }
.ph-page-btn:disabled { opacity: .3; cursor: not-allowed; }
/* Panneau info */
#ph-panel {
    width: 228px; flex-shrink: 0;
    border-left: 1px solid var(--pd-border);
    display: flex; flex-direction: column;
    background: var(--pd-surface);
    overflow: hidden; transition: width .2s;
}
#ph-panel.collapsed { width: 0; pointer-events: none; }
.ph-panel-hd {
    height: 46px; flex-shrink: 0;
    border-bottom: 1px solid var(--pd-border);
    display: flex; align-items: center; justify-content: space-between;
    padding: 0 12px; font-size: 12px; font-weight: 600; color: var(--pd-text);
}
.ph-panel-body { flex: 1; overflow-y: auto; padding: 12px; }
.ph-panel-thumb {
    width: 100%; aspect-ratio: 4/3; border-radius: 8px; overflow: hidden;
    border: 1px solid var(--pd-border); margin-bottom: 10px;
    background: var(--pd-bg); display: flex; align-items: center; justify-content: center;
}
.ph-panel-thumb img { width: 100%; height: 100%; object-fit: cover; }
.ph-panel-title { font-size: 12px; font-weight: 600; color: var(--pd-text); margin-bottom: 10px; word-break: break-word; }
.ph-meta-row { display: flex; justify-content: space-between; padding: 5px 0; border-bottom: 1px solid var(--pd-border); gap: 8px; }
.ph-meta-lbl { font-size: 11px; color: var(--pd-muted); flex-shrink: 0; }
.ph-meta-val { font-size: 11px; color: var(--pd-text); font-weight: 500; text-align: right; word-break: break-word; }
.ph-panel-empty { text-align: center; color: var(--pd-muted); font-size: 12px; padding: 30px 10px; }
.ph-panel-acts { padding: 10px 12px; border-top: 1px solid var(--pd-border); display: flex; flex-direction: column; gap: 5px; }
.ph-panel-btn {
    padding: 6px 10px; border: 1px solid var(--pd-border);
    border-radius: 7px; background: transparent; cursor: pointer;
    font-size: 11px; color: var(--pd-text); text-align: left;
    text-decoration: none; display: block; transition: background .1s;
}
.ph-panel-btn:hover { background: var(--pd-bg); }
.ph-panel-btn.navy { background: var(--pd-navy); color: #fff; border-color: var(--pd-navy); }
.ph-panel-btn.danger { color: #e74c3c; }
.ph-panel-btn.danger:hover { background: rgba(231,76,60,.08); border-color: rgba(231,76,60,.3); }
/* Lightbox */
#ph-lb {
    position: fixed; inset: 0; z-index: 9999;
    background: rgba(0,0,0,.92);
    display: none; align-items: center; justify-content: center;
}
#ph-lb.open { display: flex; }
.ph-lb-close {
    position: absolute; top: 16px; right: 20px;
    width: 36px; height: 36px; border-radius: 50%;
    background: rgba(255,255,255,.15); border: none;
    color: #fff; font-size: 16px; cursor: pointer;
    display: flex; align-items: center; justify-content: center;
}
.ph-lb-nav {
    position: absolute; top: 50%; transform: translateY(-50%);
    width: 44px; height: 44px; border-radius: 50%;
    background: rgba(255,255,255,.15); border: none;
    color: #fff; font-size: 20px; cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    transition: background .15s;
}
.ph-lb-nav:hover { background: rgba(255,255,255,.3); }
.ph-lb-nav.prev { left: 20px; }
.ph-lb-nav.next { right: 20px; }
.ph-lb-info {
    position: absolute; bottom: 20px; left: 50%; transform: translateX(-50%);
    background: rgba(0,0,0,.6); color: #fff;
    font-size: 12px; padding: 4px 14px; border-radius: 20px; white-space: nowrap;
}
/* Modal import */
#ph-import-modal {
    position: fixed; inset: 0; z-index: 9998;
    background: rgba(0,0,0,.5);
    display: none; align-items: center; justify-content: center;
}
#ph-import-modal.open { display: flex; }
.ph-modal-box {
    background: var(--pd-surface); border-radius: 14px;
    width: 540px; max-width: 95vw; max-height: 80vh;
    display: flex; flex-direction: column;
    border: 1px solid var(--pd-border); overflow: hidden;
}
.ph-modal-hd { padding: 14px 18px; border-bottom: 1px solid var(--pd-border); display: flex; align-items: center; justify-content: space-between; }
.ph-modal-title { font-size: 13px; font-weight: 600; color: var(--pd-text); }
.ph-modal-body { flex: 1; overflow-y: auto; padding: 16px 18px; }
.ph-modal-ft { padding: 10px 18px; border-top: 1px solid var(--pd-border); display: flex; justify-content: flex-end; gap: 8px; }
.ph-import-file { display: flex; align-items: center; padding: 5px 0; border-bottom: 1px solid var(--pd-border); font-size: 12px; gap: 6px; }
.ph-import-file .name { flex: 1; color: var(--pd-text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ph-import-file .size { color: var(--pd-muted); white-space: nowrap; font-size: 11px; }
.ph-badge { font-size: 10px; padding: 1px 7px; border-radius: 10px; font-weight: 600; white-space: nowrap; }
.ph-badge.new { background: #dcfce7; color: #15803d; }
</style>
@endpush

@section('content')
@php
    $quotaMb  = app(\App\Services\TenantManager::class)->current()?->storage_quota_mb ?? 10240;
    $usedBytes = \App\Models\Tenant\MediaItem::sum('file_size_bytes');
    $usedMb   = round($usedBytes / 1024 / 1024, 1);
    $usedPct  = $quotaMb > 0 ? min(100, round($usedMb / $quotaMb * 100)) : 0;
@endphp

<div id="ph-wrap"
     x-data="phototheque('{{ route('media.items.store', $album) }}', '{{ csrf_token() }}', {{ $userCols }})"
     @dragover.window.prevent @drop.window.prevent>

    {{-- ══ SIDEBAR ══════════════════════════════════════════ --}}
    <aside id="ph-sidebar">
        <div class="ph-sidebar-header">
            @can('upload', $album)
            <button class="ph-upload-btn" @click="$refs.fileInput.click()">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M17 8l-5-5-5 5M12 3v12"/></svg>
                Téléverser
            </button>
            @endcan
        </div>

        <nav class="ph-nav">
            <div class="ph-nav-section">Navigation</div>
            <a href="{{ route('media.albums.index') }}" class="ph-nav-item">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                Tous les albums
            </a>

            <div class="ph-nav-section">Albums</div>
            @foreach($albumTree as $root)
                <a href="{{ route('media.albums.show', $root) }}"
                   class="ph-nav-item {{ $root->id === $album->id ? 'active' : '' }}">
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M22 19a2 2 0 01-2 2H4a2 2 0 01-2-2V5a2 2 0 012-2h5l2 3h9a2 2 0 012 2z"/></svg>
                    <span style="flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $root->name }}</span>
                    <span class="ph-nav-count">{{ $root->items_count }}</span>
                </a>
                @foreach($root->children as $child)
                    <a href="{{ route('media.albums.show', $child) }}"
                       class="ph-nav-item ph-nav-child {{ $child->id === $album->id ? 'active' : '' }}">
                        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                        <span style="flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $child->name }}</span>
                        <span class="ph-nav-count">{{ $child->items_count }}</span>
                    </a>
                @endforeach
            @endforeach

            <div class="ph-nav-section">Actions</div>
            <a href="{{ route('media.albums.create') }}" class="ph-nav-item">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                Nouvel album
            </a>
            <a href="{{ route('media.albums.create', ['parent_id' => $album->id]) }}" class="ph-nav-item">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M22 19a2 2 0 01-2 2H4a2 2 0 01-2-2V5a2 2 0 012-2h5l2 3h9a2 2 0 012 2z"/><path d="M12 11v6M9 14h6"/></svg>
                Nouveau sous-dossier
            </a>
        </nav>

        <div class="ph-storage">
            {{ $usedMb }} Mo / {{ $quotaMb >= 1024 ? round($quotaMb/1024,1).' Go' : $quotaMb.' Mo' }}
            <div class="ph-storage-bar">
                <div class="ph-storage-fill" style="width:{{ $usedPct }}%"></div>
            </div>
            {{ $usedPct }}% utilisé
        </div>
    </aside>

    {{-- ══ MAIN ═════════════════════════════════════════════ --}}
    <div id="ph-main">

        {{-- Header --}}
        <div id="ph-header">
            <div class="ph-breadcrumb">
                <a href="{{ route('media.albums.index') }}">Photothèque</a>
                @if($album->parent)
                    <span>›</span>
                    <a href="{{ route('media.albums.show', $album->parent) }}">{{ $album->parent->name }}</a>
                @endif
                <span>›</span>
                <span class="current">{{ $album->name }}</span>
            </div>

            <div class="ph-search-wrap">
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                <input type="text" placeholder="Rechercher…" x-model="search">
            </div>

            <div class="ph-header-right">
                @foreach([3,4,5,6] as $c)
                <button class="ph-hbtn" :class="{ active: cols === {{ $c }} }" @click="setCols({{ $c }})">{{ $c }}</button>
                @endforeach
                <div class="ph-vsep"></div>
                <button class="ph-hbtn" :class="{ active: viewMode === 'grid' }" @click="viewMode='grid'" title="Grille">⊞</button>
                <button class="ph-hbtn" :class="{ active: viewMode === 'list' }" @click="viewMode='list'" title="Liste">☰</button>
                <button class="ph-hbtn" :class="{ active: panelOpen }" @click="togglePanel()" title="Infos">ℹ</button>
                @can('manage', $album)
                <a href="{{ route('media.albums.permissions.edit', $album) }}" class="ph-hbtn" title="Droits">🔐</a>
                <a href="{{ route('media.albums.edit', $album) }}" class="ph-hbtn" title="Modifier">✏️</a>
                @if($album->cover_item_id)
                <form method="POST" action="{{ route('media.albums.cover.reset', $album) }}"
                      style="display:inline;"
                      onsubmit="return confirm('Réinitialiser la couverture ? La première image sera utilisée automatiquement.')">
                    @csrf @method('DELETE')
                    <button type="submit" class="ph-hbtn" title="Réinitialiser la couverture"
                            style="color:#f59e0b;border-color:rgba(245,158,11,.3);">⭐↺</button>
                </form>
                @endif
                <form method="POST" action="{{ route('media.albums.destroy', $album) }}"
                      onsubmit="return confirm('Supprimer l\'album « {{ addslashes($album->name) }} » et tous ses fichiers ?')"
                      style="display:inline;">
                    @csrf @method('DELETE')
                    <button type="submit" class="ph-hbtn" title="Supprimer l'album"
                            style="color:#e74c3c;border-color:rgba(231,76,60,.3);">🗑</button>
                </form>
                @endcan
                <div class="ph-vsep"></div>
                <button class="ph-hbtn" id="btn-nas-sync" onclick="syncNas()" title="Synchroniser le NAS">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
                </button>
            </div>
        </div>

        {{-- Barre sélection --}}
        <div id="ph-sel-bar" :class="{ visible: selected.length > 0 }">
            <span x-text="selected.length + ' sélectionné(s)'"></span>
            <button class="ph-sel-btn" @click="selectAll()">Tout</button>
            <button class="ph-sel-btn" @click="clearSelection()">Annuler</button>
            @can('upload', $album)
            <button class="ph-sel-btn" @click="deleteSelected()">🗑 Supprimer</button>
            @endcan
        </div>

        {{-- Toolbar --}}
        <div id="ph-toolbar">
            @foreach(['all' => 'Tout', 'images' => 'Images', 'videos' => 'Vidéos', 'pdf' => 'PDF'] as $val => $lbl)
                <a href="{{ request()->fullUrlWithQuery(['type' => $val, 'page' => 1]) }}"
                   class="ph-filter {{ $filterType === $val ? 'active' : '' }}">{{ $lbl }}</a>
            @endforeach
            <div class="ph-vsep"></div>
            <span style="font-size:11px;color:var(--pd-muted);">Tri :</span>
            <select class="ph-select" onchange="location.href=this.value">
                @foreach(['date' => 'Date', 'name' => 'Nom', 'size' => 'Taille'] as $v => $l)
                    <option value="{{ request()->fullUrlWithQuery(['sort' => $v, 'page' => 1]) }}" {{ $sortBy === $v ? 'selected' : '' }}>{{ $l }}</option>
                @endforeach
            </select>
            <a href="{{ request()->fullUrlWithQuery(['dir' => $sortDir === 'asc' ? 'desc' : 'asc', 'page' => 1]) }}"
               class="ph-hbtn" style="text-decoration:none;" title="Inverser">{{ $sortDir === 'asc' ? '↑' : '↓' }}</a>
            <div class="ph-toolbar-right">
                <span>Par page :</span>
                @foreach([10, 24, 48] as $n)
                    <a href="{{ request()->fullUrlWithQuery(['per_page' => $n, 'page' => 1]) }}"
                       class="ph-filter {{ !$showAll && $perPage === $n ? 'active' : '' }}" style="padding:2px 8px;">{{ $n }}</a>
                @endforeach
                <a href="{{ request()->fullUrlWithQuery(['per_page' => 'all', 'page' => 1]) }}"
                   class="ph-filter {{ $showAll ? 'active' : '' }}" style="padding:2px 8px;">Toutes</a>
                <div class="ph-vsep"></div>
                <span>{{ $items->total() }} fichier{{ $items->total() > 1 ? 's' : '' }}</span>
            </div>
        </div>

        {{-- Flash --}}
        @if(session('success'))
            <div style="margin:8px 14px 0;padding:7px 12px;background:rgba(46,204,113,.1);border:1px solid rgba(46,204,113,.3);border-radius:7px;font-size:12px;color:#1a8a4a;">✓ {{ session('success') }}</div>
        @endif
        @if(session('upload_errors'))
            <div style="margin:8px 14px 0;padding:7px 12px;background:rgba(231,76,60,.08);border:1px solid rgba(231,76,60,.25);border-radius:7px;font-size:12px;color:#c0392b;">
                @foreach(session('upload_errors') as $err)<div>• {{ $err }}</div>@endforeach
            </div>
        @endif

        {{-- Contenu --}}
        <div id="ph-content"
             @dragover.prevent="dragging=true"
             @dragleave.prevent="dragging=false"
             @drop.prevent="handleDrop($event)">

            {{-- Dropzone --}}
            @can('upload', $album)
            <div class="ph-dropzone" :class="{ dragover: dragging }">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" style="margin-bottom:5px;color:var(--pd-muted)"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M17 8l-5-5-5 5M12 3v12"/></svg>
                <p style="margin-bottom:8px;font-size:12px;">Glissez vos fichiers ici</p>
                <div class="ph-dz-actions">
                    <label class="ph-dz-btn primary" style="cursor:pointer;">
                        Choisir des fichiers
                        <input type="file" multiple class="hidden" accept="image/*,video/*,application/pdf" x-ref="fileInput" @change="handleFileInput($event)">
                    </label>
                    <label class="ph-dz-btn" style="cursor:pointer;">
                        📷 Appareil photo
                        <input type="file" multiple class="hidden" accept="image/*,video/*" capture="environment" @change="handleFileInput($event)">
                    </label>

		<button class="ph-dz-btn" @click.stop="openImportModal()">
                        📂 Importer un dossier
                    </button>
                    <button class="ph-dz-btn" onclick="openZipModal()">
                        🗜 Importer un ZIP
                    </button>
                </div>
                <p style="font-size:10px;margin-top:7px;color:var(--pd-muted);">JPEG · PNG · WEBP · GIF · MP4 · MOV · PDF — 200 Mo max · ZIP 500 Mo max</p>




                <div x-show="uploading" class="ph-progress">
                    <div class="ph-progress-bar"><div class="ph-progress-fill" :style="'width:' + progress + '%'"></div></div>
                    <p style="font-size:11px;color:var(--pd-muted);margin-top:4px;" x-text="statusText"></p>
                </div>
            </div>
            @endcan

            {{-- Sous-albums --}}
            @if($album->children->isNotEmpty())
                <div style="margin-bottom:6px;font-size:10px;font-weight:600;color:var(--pd-muted);text-transform:uppercase;letter-spacing:.4px;">
                    Sous-albums ({{ $album->children->count() }})
                </div>
                <div class="ph-subalbums">
                    @foreach($album->children as $child)
                        <a href="{{ route('media.albums.show', $child) }}" class="ph-sub-card">
                            <div class="ph-sub-thumb">🗂️</div>
                            <div class="ph-sub-info">
                                <div class="ph-sub-name">{{ $child->name }}</div>
                                <div class="ph-sub-count">{{ $child->items_count }} fich.</div>
                            </div>
                        </a>
                    @endforeach
                </div>
                <hr style="border:none;border-top:1px solid var(--pd-border);margin-bottom:14px;">
            @endif

            {{-- Vide --}}
            @if($items->isEmpty())
                <div style="text-align:center;padding:50px 0;color:var(--pd-muted);">
                    <div style="font-size:36px;margin-bottom:10px;">🖼️</div>
                    <p style="font-size:13px;font-weight:500;color:var(--pd-text);">Aucun fichier</p>
                    <p style="font-size:12px;margin-top:4px;">{{ $filterType !== 'all' ? 'Aucun fichier de ce type.' : 'Téléversez vos premiers fichiers ci-dessus.' }}</p>
                </div>
            @else

                {{-- Vue grille --}}
                <div x-show="viewMode === 'grid'" class="ph-grid" :data-cols="cols">
                    @foreach($items as $index => $item)
                        <div class="ph-card"
                             :class="{ selected: isSelected({{ $item->id }}) }"
                             data-duplicate="{{ $item->is_duplicate ? 'true' : 'false' }}"
                             @click="cardClick({{ $item->id }}, {{ $index }}, $event)">
                            @if($item->isVideo())
                                <span class="ph-type-badge">Vidéo</span>
                                <div style="width:100%;height:100%;background:linear-gradient(135deg,#1e293b,#334155);display:flex;align-items:center;justify-content:center;">
                                    <svg width="28" height="28" viewBox="0 0 24 24" fill="white" style="opacity:.6"><path d="M8 5v14l11-7z"/></svg>
                                </div>
                            @elseif($item->isPdf())
                                <span class="ph-type-badge">PDF</span>
                                <div class="ph-placeholder">📄</div>
                            @else
                                <img src="{{ route('media.items.serve', [$album, $item, 'thumb']) }}"
                                     alt="{{ $item->caption ?? $item->file_name }}" loading="lazy">
                            @endif
                            @if($item->is_duplicate)
                            <div class="ph-dup-badge" title="Ce fichier existe en doublon dans la photothèque">⚠ Doublon</div>
                            @endif
                            <div class="ph-check" @click.stop="toggleSelect({{ $item->id }})">✓</div>
                            <div class="ph-overlay">
                                <div class="ph-card-name">{{ $item->caption ?? $item->file_name }}</div>
                                <div class="ph-card-acts">
                                    <button class="ph-act" title="Plein écran" @click.stop="openLightbox({{ $index }})">⤢</button>
                                    <a href="{{ route('media.items.download', [$album, $item]) }}" class="ph-act" title="Télécharger" @click.stop>↓</a>
                                    @if($canAdmin && $item->isImage())
                                    <form method="POST" action="{{ route('media.albums.cover', [$album, $item]) }}"
                                          style="display:inline;" @click.stop>
                                        @csrf @method('PUT')
                                        <button type="submit" class="ph-act{{ $coverItem?->id === $item->id ? ' ph-act-cover-active' : '' }}"
                                                @click.stop title="Définir comme couverture">⭐</button>
                                    </form>
                                    @endif
                                    @can('upload', $album)
                                    <form method="POST" action="{{ route('media.items.destroy', [$album, $item]) }}"
                                          @submit.prevent="if(confirm('Supprimer ?')) $el.submit()" style="display:inline;">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="ph-act" @click.stop title="Supprimer">✕</button>
                                    </form>
                                    @endcan
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Vue liste --}}
                <div x-show="viewMode === 'list'" class="ph-list">
                    <div class="ph-list-row ph-list-th" style="border:none;cursor:default;">
                        <div></div><div>Nom</div><div style="text-align:right;">Type</div>
                        <div style="text-align:right;">Dimensions</div>
                        <div style="text-align:right;">Taille</div><div></div>
                    </div>
                    @foreach($items as $index => $item)
                        <div class="ph-list-row" :class="{ selected: isSelected({{ $item->id }}) }"
                             @click="cardClick({{ $item->id }}, {{ $index }}, $event); openLightbox({{ $index }})">
                            @if($item->isImage())
                                <img class="ph-list-thumb" src="{{ route('media.items.serve', [$album, $item, 'thumb']) }}" alt="">
                            @elseif($item->isVideo())
                                <div class="ph-list-thumb-icon" style="background:#1e293b;">▶</div>
                            @else
                                <div class="ph-list-thumb-icon" style="background:#fde68a;">📄</div>
                            @endif
                            <div class="ph-list-name">{{ $item->caption ?? $item->file_name }}</div>
                            <div class="ph-list-meta">{{ strtoupper(pathinfo($item->file_name, PATHINFO_EXTENSION)) }}</div>
                            <div class="ph-list-meta">{{ $item->width_px ? $item->width_px.'×'.$item->height_px : '—' }}</div>
                            <div class="ph-list-meta">{{ $item->humanSize() }}</div>
                            <div class="ph-list-meta">
                                <a href="{{ route('media.items.download', [$album, $item]) }}" @click.stop style="color:var(--pd-muted);text-decoration:none;font-size:13px;">↓</a>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Footer pagination --}}
        <div id="ph-footer">
            <span>
                @if(!$showAll && $items->hasPages())Page {{ $items->currentPage() }} / {{ $items->lastPage() }} · @endif
                {{ number_format($items->total()) }} fichier{{ $items->total() > 1 ? 's' : '' }}
            </span>
            @if($items->hasPages())
            <div style="display:flex;align-items:center;gap:3px;">
                <button class="ph-page-btn" onclick="go('{{ $items->previousPageUrl() }}')" {{ $items->onFirstPage() ? 'disabled' : '' }}>‹</button>
                @foreach($items->getUrlRange(max(1,$items->currentPage()-2), min($items->lastPage(),$items->currentPage()+2)) as $pg => $url)
                    <button class="ph-page-btn {{ $pg === $items->currentPage() ? 'active' : '' }}" onclick="go('{{ $url }}')">{{ $pg }}</button>
                @endforeach
                <button class="ph-page-btn" onclick="go('{{ $items->nextPageUrl() }}')" {{ !$items->hasMorePages() ? 'disabled' : '' }}>›</button>
            </div>
            @endif
        </div>
    </div>

    {{-- ══ PANNEAU INFO ═════════════════════════════════════ --}}
    <div id="ph-panel" :class="{ collapsed: !panelOpen }">
        <div class="ph-panel-hd">
            <span>Informations</span>
            <button class="ph-hbtn" @click="togglePanel()" style="width:24px;height:24px;font-size:10px;">✕</button>
        </div>
        <div class="ph-panel-body">
            <template x-if="activeItem">
                <div>
                    <div class="ph-panel-thumb">
                        <img :src="activeItem.thumb" :alt="activeItem.name" x-show="activeItem.isImage" style="width:100%;height:100%;object-fit:cover;">
                        <span x-show="!activeItem.isImage" style="font-size:32px;" x-text="activeItem.isPdf ? '📄' : '🎬'"></span>
                    </div>
                    <div class="ph-panel-title" x-text="activeItem.name"></div>
                    <template x-if="activeItem.is_duplicate">
                        <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:6px;padding:6px 10px;margin:6px 0;display:flex;align-items:center;gap:6px;font-size:11px;color:#dc2626;">
                            <span>⚠</span>
                            <span>Ce fichier est un doublon</span>
                        </div>
                    </template>
                    <div>
                        <div class="ph-meta-row"><span class="ph-meta-lbl">Taille</span><span class="ph-meta-val" x-text="activeItem.size"></span></div>
                        <template x-if="activeItem.dims">
                            <div class="ph-meta-row"><span class="ph-meta-lbl">Dimensions</span><span class="ph-meta-val" x-text="activeItem.dims"></span></div>
                        </template>
                        <template x-if="activeItem.taken_at">
                            <div class="ph-meta-row"><span class="ph-meta-lbl">Prise le</span><span class="ph-meta-val" x-text="activeItem.taken_at"></span></div>
                        </template>
                        <div class="ph-meta-row"><span class="ph-meta-lbl">Ajouté le</span><span class="ph-meta-val" x-text="activeItem.date"></span></div>
                        <template x-if="activeItem.camera">
                            <div class="ph-meta-row"><span class="ph-meta-lbl">Appareil</span><span class="ph-meta-val" x-text="activeItem.camera"></span></div>
                        </template>
                        <template x-if="activeItem.software">
                            <div class="ph-meta-row"><span class="ph-meta-lbl">💾 Logiciel</span><span class="ph-meta-val" x-text="activeItem.software"></span></div>
                        </template>
                        <template x-if="activeItem.focal">
                            <div class="ph-meta-row"><span class="ph-meta-lbl">Focale</span><span class="ph-meta-val" x-text="activeItem.focal"></span></div>
                        </template>
                        <template x-if="activeItem.aperture">
                            <div class="ph-meta-row"><span class="ph-meta-lbl">Ouverture</span><span class="ph-meta-val" x-text="activeItem.aperture"></span></div>
                        </template>
                        <template x-if="activeItem.exposure">
                            <div class="ph-meta-row"><span class="ph-meta-lbl">Exposition</span><span class="ph-meta-val" x-text="activeItem.exposure"></span></div>
                        </template>
                        <template x-if="activeItem.iso">
                            <div class="ph-meta-row"><span class="ph-meta-lbl">ISO</span><span class="ph-meta-val" x-text="activeItem.iso"></span></div>
                        </template>
                        <template x-if="activeItem.flash">
                            <div class="ph-meta-row"><span class="ph-meta-lbl">⚡ Flash</span><span class="ph-meta-val" x-text="activeItem.flash"></span></div>
                        </template>
                        <template x-if="activeItem.metering">
                            <div class="ph-meta-row"><span class="ph-meta-lbl">🎯 Mesure</span><span class="ph-meta-val" x-text="activeItem.metering"></span></div>
                        </template>
                        <template x-if="activeItem.white_balance">
                            <div class="ph-meta-row"><span class="ph-meta-lbl">🌡 Balance</span><span class="ph-meta-val" x-text="activeItem.white_balance"></span></div>
                        </template>
                        <template x-if="activeItem.exposure_mode">
                            <div class="ph-meta-row"><span class="ph-meta-lbl">📊 Exposition</span><span class="ph-meta-val" x-text="activeItem.exposure_mode"></span></div>
                        </template>
                        <template x-if="activeItem.gps_label">
                            <div class="ph-meta-row">
                                <span class="ph-meta-lbl">📍 GPS</span>
                                <span class="ph-meta-val">
                                    <a :href="activeItem.gps_url" target="_blank" style="color:#3b82f6;text-decoration:none;" x-text="activeItem.gps_label"></a>
                                    <span x-show="activeItem.altitude" x-text="' · ' + activeItem.altitude" style="color:#9ca3af;font-size:10px;"></span>
                                </span>
                            </div>
                        </template>
                        <template x-if="activeItem.sha256">
                            <div class="ph-meta-row"><span class="ph-meta-lbl">SHA-256</span><span class="ph-meta-val" style="font-size:10px;font-family:monospace;" x-text="activeItem.sha256"></span></div>
                        </template>
                    </div>
                </div>
            </template>
            <div x-show="!activeItem" class="ph-panel-empty">
                Cliquez sur une photo pour voir ses informations
            </div>
        </div>
        <div class="ph-panel-acts" x-show="activeItem">
            <a :href="activeItem?.download" class="ph-panel-btn navy">↓ Télécharger</a>
            <button class="ph-panel-btn" @click="openLightboxItem()">⤢ Plein écran</button>
            @if($canAdmin)
            <button class="ph-panel-btn" x-show="activeItem?.isImage" @click="setCoverItem()">⭐ Couverture</button>
            @endif
            @can('upload', $album)
            <button class="ph-panel-btn danger" @click="deletePanelItem()">🗑 Supprimer</button>
            @endcan
        </div>
    </div>

</div>

{{-- Lightbox --}}
<div id="ph-lb" @keydown.window.escape="closeLb()">
    <button class="ph-lb-close" onclick="closeLb()">✕</button>
    <button class="ph-lb-nav prev" id="lb-prev" onclick="lbGo(-1)">‹</button>
    <div id="lb-content" style="display:flex;align-items:center;justify-content:center;max-width:90vw;max-height:85vh;"></div>
    <button class="ph-lb-nav next" id="lb-next" onclick="lbGo(1)">›</button>
    <div class="ph-lb-info" id="lb-info"></div>
</div>

{{-- Modal import dossier --}}
<div id="ph-import-modal">
    <div class="ph-modal-box">
        <div class="ph-modal-hd">
            <span class="ph-modal-title">Importer un dossier</span>
            <button class="ph-hbtn" onclick="closeImportModal()" style="width:26px;height:26px;">✕</button>
        </div>
        <div class="ph-modal-body" id="import-body">
            <div style="text-align:center;padding:24px 0;color:var(--pd-muted);">
                <div style="font-size:30px;margin-bottom:10px;">📂</div>
                <p style="font-size:13px;margin-bottom:14px;">Sélectionnez le dossier de votre appareil photo<br><span style="font-size:11px;">(DCIM, Téléchargements, Images…)</span></p>
                <label class="ph-dz-btn primary" style="cursor:pointer;display:inline-block;">
                    Choisir un dossier
                    <input type="file" id="folder-input" webkitdirectory multiple accept="image/*,video/*,application/pdf" class="hidden" onchange="scanFolder(this.files)">
                </label>
            </div>
        </div>
        <div class="ph-modal-ft" id="import-footer" style="display:none;">
            <button class="ph-dz-btn" onclick="closeImportModal()">Annuler</button>
            <button class="ph-dz-btn primary" id="import-btn" onclick="launchImport()">Importer</button>
        </div>
    </div>
</div>

{{-- Modale confirmation doublon --}}
<div id="ph-dup-modal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.5);align-items:center;justify-content:center;">
    <div style="background:var(--pd-surface);border-radius:12px;padding:24px;max-width:480px;width:90%;box-shadow:0 20px 60px rgba(0,0,0,.3);">
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:16px;">
            <span style="font-size:24px;">⚠️</span>
            <div>
                <div style="font-size:15px;font-weight:700;color:var(--pd-text);">Fichiers en doublon détectés</div>
                <div style="font-size:12px;color:var(--pd-muted);" id="ph-dup-success-msg"></div>
            </div>
        </div>
        <div id="ph-dup-list" style="margin-bottom:16px;max-height:200px;overflow-y:auto;"></div>
        <div style="font-size:12px;color:var(--pd-muted);margin-bottom:16px;">
            Cochez les fichiers que vous souhaitez importer quand même.
        </div>
        <div style="display:flex;gap:8px;justify-content:flex-end;">
            <button onclick="closeDuplicateModal()" class="pd-btn pd-btn-secondary pd-btn-sm">Ignorer tous</button>
            <button type="button" onclick="confirmDuplicates()" class="pd-btn pd-btn-primary pd-btn-sm">Importer sélectionnés</button>
        </div>
    </div>
</div>


@endsection

@push('scripts')
<script>
const PH_ITEMS = @json($itemsForJs);
const PH_CSRF = '{{ csrf_token() }}';
let lbIdx = 0;

function go(url) { if (url) location.href = url; }

document.addEventListener('alpine:init', () => {
    Alpine.data('phototheque', (uploadUrl, csrfToken, defaultCols) => ({
        dragging: false, uploading: false, progress: 0, statusText: '',
        cols: defaultCols, viewMode: 'grid', panelOpen: true,
        selected: [], activeItem: null, search: '',

        setCols(n) {
            this.cols = n;
            fetch('{{ route('media.prefs.cols') }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: JSON.stringify({ cols: n }),
            }).catch(() => {});
        },

        isSelected(id) { return this.selected.includes(id); },
        toggleSelect(id) {
            if (this.isSelected(id)) this.selected = this.selected.filter(s => s !== id);
            else this.selected.push(id);
        },
        selectAll()     { this.selected = PH_ITEMS.map(i => i.id); },
        clearSelection(){ this.selected = []; },

        cardClick(id, index, event) {
            if (event.ctrlKey || event.metaKey || event.shiftKey) {
                this.toggleSelect(id);
            } else {
                this.activeItem = PH_ITEMS.find(i => i.id === id) || null;
                if (this.activeItem && !this.panelOpen) this.panelOpen = true;
            }
        },

        togglePanel() { this.panelOpen = !this.panelOpen; },

        handleDrop(event) {
            this.dragging = false;
            if (event.dataTransfer.files.length) this.upload(event.dataTransfer.files);
        },
        handleFileInput(event) {
            if (event.target.files.length) this.upload(event.target.files);
        },
        upload(files, forceNames = []) {
            const fd = new FormData();
            Array.from(files).forEach(f => fd.append('files[]', f));
            forceNames.forEach(n => fd.append('force_names[]', n));
            fd.append('_token', csrfToken);
            this.uploading = true; this.progress = 0;
            this.statusText = `Envoi de ${files.length} fichier(s)…`;
            const xhr = new XMLHttpRequest();
            xhr.upload.addEventListener('progress', e => {
                if (e.lengthComputable) { this.progress = Math.round(e.loaded/e.total*100); this.statusText = `${this.progress}%…`; }
            });
            xhr.addEventListener('load', () => {
                this.uploading = false;
                try {
                    const res = JSON.parse(xhr.responseText);
                    if (res.duplicates && res.duplicates.length > 0) {
                        this._pendingFiles = files;
                        this._pendingForceNames = forceNames;
                        this._pendingDuplicates = res.duplicates;
                        openDuplicateModal(res.duplicates, res.success, files, uploadUrl);
                        return;
                    }
                } catch(e) {}
                if (xhr.status < 400) location.reload();
                else this.statusText = 'Erreur ' + xhr.status;
            });
            xhr.addEventListener('error', () => { this.uploading = false; this.statusText = 'Erreur réseau.'; });
            xhr.open('POST', uploadUrl);
            xhr.setRequestHeader('Accept', 'application/json');
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.send(fd);
        },
        forceUploadDuplicates(fileNames) {
            // Re-soumettre uniquement les fichiers confirmés
            const filesToForce = Array.from(this._pendingFiles).filter(f => fileNames.includes(f.name));
            if (filesToForce.length > 0) {
                this.upload(filesToForce, fileNames);
            } else {
                location.reload();
            }
        },

        openLightbox(index)   { lbIdx = index; renderLb(); document.getElementById('ph-lb').classList.add('open'); },
        openLightboxItem()    { if (!this.activeItem) return; const i = PH_ITEMS.findIndex(x => x.id === this.activeItem.id); if (i >= 0) this.openLightbox(i); },
        openImportModal()     { openImportModal(); },

        setCoverItem() {
            if (!this.activeItem || !this.activeItem.is_cover) return;
            const f = document.createElement('form');
            f.method = 'POST'; f.action = this.activeItem.cover_url;
            f.innerHTML = `<input name="_token" value="${PH_CSRF}"><input name="_method" value="PUT">`;
            document.body.appendChild(f); f.submit();
        },
        deletePanelItem() {
            if (!this.activeItem || !confirm('Supprimer ce fichier ?')) return;
            const f = document.createElement('form');
            f.method = 'POST'; f.action = this.activeItem.destroy;
            f.innerHTML = `<input name="_token" value="${PH_CSRF}"><input name="_method" value="DELETE">`;
            document.body.appendChild(f); f.submit();
        },
        deleteSelected() {
            if (!this.selected.length || !confirm(`Supprimer ${this.selected.length} fichier(s) ?`)) return;
            Promise.all(this.selected.map(id => {
                const item = PH_ITEMS.find(i => i.id === id);
                if (!item) return Promise.resolve();
                return fetch(item.destroy, { method: 'POST', headers: { 'X-CSRF-TOKEN': PH_CSRF }, body: new URLSearchParams({ _method: 'DELETE' }) });
            })).then(() => location.reload());
        },
    }));
});

// Lightbox
function renderLb() {
    const item = PH_ITEMS[lbIdx];
    if (!item) return;
    const el = document.getElementById('lb-content');
    if (item.isImage) el.innerHTML = `<img src="${item.full}" style="max-width:90vw;max-height:85vh;object-fit:contain;border-radius:4px;">`;
    else if (item.isVideo) el.innerHTML = `<video controls autoplay style="max-width:90vw;max-height:85vh;border-radius:4px;"><source src="${item.full}" type="${item.mime}"></video>`;
    else el.innerHTML = `<div style="text-align:center;color:#fff;padding:40px;"><div style="font-size:50px;margin-bottom:14px;">📄</div><p style="font-size:14px;">${item.name}</p><a href="${item.download}" style="display:inline-block;margin-top:14px;padding:8px 20px;background:rgba(255,255,255,.2);color:#fff;border-radius:8px;text-decoration:none;">↓ Télécharger</a></div>`;
    document.getElementById('lb-info').textContent = `${item.name} — ${lbIdx + 1} / ${PH_ITEMS.length}`;
    document.getElementById('lb-prev').style.display = lbIdx > 0 ? '' : 'none';
    document.getElementById('lb-next').style.display = lbIdx < PH_ITEMS.length - 1 ? '' : 'none';
}
function lbGo(d) { lbIdx = Math.max(0, Math.min(PH_ITEMS.length-1, lbIdx+d)); renderLb(); }
function closeLb() { document.getElementById('ph-lb').classList.remove('open'); }

// ── Modale doublons ───────────────────────────────────────────────────────────
let _dupPhComponent = null;

let _dupPendingFiles = null;
let _dupUploadUrl = null;

function openDuplicateModal(duplicates, successCount, files, uploadUrl) {
    _dupPendingFiles = files;
    _dupUploadUrl = uploadUrl;
    const modal = document.getElementById('ph-dup-modal');
    const list  = document.getElementById('ph-dup-list');
    const msg   = document.getElementById('ph-dup-success-msg');

    msg.textContent = successCount > 0 ? `${successCount} fichier(s) importé(s) avec succès.` : '';

    list.innerHTML = duplicates.map(d => `
        <label style="display:flex;align-items:flex-start;gap:10px;padding:10px;border:1px solid var(--pd-border);border-radius:8px;margin-bottom:6px;cursor:pointer;background:var(--pd-surface2);">
            <input type="checkbox" value="${d.file_name}" checked
                   style="accent-color:var(--pd-navy);margin-top:2px;flex-shrink:0;width:15px;height:15px;">
            <div>
                <div style="font-size:12px;font-weight:600;color:var(--pd-text);">${d.file_name}</div>
                <div style="font-size:11px;color:var(--pd-muted);margin-top:2px;">
                    Doublon de « ${d.original_file_name} »
                    ${d.same_album ? 'dans cet album' : `dans l'album « ${d.original_album_name} »`}
                </div>
            </div>
        </label>
    `).join('');

    modal.style.display = 'flex';

    // Référence au composant Alpine pour le re-upload
    _dupPhComponent = document.querySelector('[x-data]')?.__x;
}

function closeDuplicateModal() {
    document.getElementById('ph-dup-modal').style.display = 'none';
    location.reload();
}

function confirmDuplicates() {
    const checked = Array.from(
        document.querySelectorAll('#ph-dup-list input[type=checkbox]:checked')
    ).map(cb => cb.value);

    document.getElementById('ph-dup-modal').style.display = 'none';

    if (checked.length === 0 || !_dupPendingFiles || !_dupUploadUrl) {
        location.reload();
        return;
    }

    const fd = new FormData();
    const filesToForce = Array.from(_dupPendingFiles).filter(f => checked.includes(f.name));
    filesToForce.forEach(f => fd.append('files[]', f));
    checked.forEach(n => fd.append('force_names[]', n));
    fd.append('_token', PH_CSRF);

    const xhr = new XMLHttpRequest();
    xhr.addEventListener('load', () => { location.reload(); });
    xhr.addEventListener('error', () => { location.reload(); });
    xhr.open('POST', _dupUploadUrl);
    xhr.setRequestHeader('Accept', 'application/json');
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    xhr.send(fd);
}
document.getElementById('ph-lb')?.addEventListener('click', e => { if (e.target === e.currentTarget) closeLb(); });

// Import dossier
let folderFiles = [];
function openImportModal()  { document.getElementById('ph-import-modal').classList.add('open'); }
function closeImportModal() { document.getElementById('ph-import-modal').classList.remove('open'); resetImport(); }
function resetImport() {
    folderFiles = [];
    document.getElementById('import-body').innerHTML = `
        <div style="text-align:center;padding:24px 0;color:var(--pd-muted);">
            <div style="font-size:30px;margin-bottom:10px;">📂</div>
            <p style="font-size:13px;margin-bottom:14px;">Sélectionnez le dossier de votre appareil photo</p>
            <label class="ph-dz-btn primary" style="cursor:pointer;display:inline-block;">Choisir un dossier
                <input type="file" id="folder-input" webkitdirectory multiple accept="image/*,video/*,application/pdf" class="hidden" onchange="scanFolder(this.files)">
            </label>
        </div>`;
    document.getElementById('import-footer').style.display = 'none';
}
function scanFolder(files) {
    const allowed = ['image/jpeg','image/png','image/webp','image/gif','video/mp4','video/quicktime','application/pdf'];
    folderFiles = Array.from(files).filter(f => allowed.includes(f.type));
    const fmt = b => b > 1048576 ? (b/1048576).toFixed(1)+' Mo' : Math.round(b/1024)+' Ko';
    const body = document.getElementById('import-body');
    if (!folderFiles.length) { body.innerHTML = '<p style="text-align:center;padding:20px;font-size:13px;color:var(--pd-muted);">Aucun fichier supporté.</p>'; return; }
    body.innerHTML = `
        <p style="font-size:12px;color:var(--pd-muted);margin-bottom:10px;"><strong style="color:var(--pd-text);">${folderFiles.length} fichier(s)</strong> trouvé(s). Les doublons seront ignorés automatiquement.</p>
        <div style="max-height:260px;overflow-y:auto;">
            ${folderFiles.slice(0,50).map(f => `
                <div class="ph-import-file">
                    <span class="name">${f.name}</span>
                    <span class="size">${fmt(f.size)}</span>
                    <span class="ph-badge new">Nouveau</span>
                </div>`).join('')}
            ${folderFiles.length > 50 ? `<p style="font-size:11px;color:var(--pd-muted);padding:6px 0;">… et ${folderFiles.length-50} autres</p>` : ''}
        </div>`;
    document.getElementById('import-footer').style.display = 'flex';
    document.getElementById('import-btn').textContent = `Importer ${folderFiles.length} fichier(s)`;
}
function launchImport() {
    if (!folderFiles.length) return;
    const btn = document.getElementById('import-btn');
    btn.disabled = true; let done = 0;
    const BATCH = 5;
    function next(i) {
        if (i >= folderFiles.length) { closeImportModal(); location.reload(); return; }
        const batch = folderFiles.slice(i, i+BATCH);
        const fd = new FormData();
        batch.forEach(f => fd.append('files[]', f));
        fd.append('_token', PH_CSRF);
        fetch('{{ route('media.items.store', $album) }}', { method:'POST', body:fd })
            .finally(() => { done += batch.length; btn.textContent = `Import… ${done}/${folderFiles.length}`; next(i+BATCH); });
    }
    next(0);
}

// Sync NAS
function syncNas() {
    const btn = document.getElementById('btn-nas-sync');
    if (!btn) return;
    btn.disabled = true;
    btn.style.color = 'var(--pd-accent)';
    btn.querySelector('svg').style.animation = 'spin 1s linear infinite';
    fetch('{{ route('media.sync') }}', { method:'POST', headers:{ 'X-CSRF-TOKEN': PH_CSRF, 'Accept':'application/json' } })
        .then(r => r.json())
        .then(d => {
            btn.style.color = d.ok ? '#22c55e' : '#f59e0b';
            if (d.message) {
                const toast = document.createElement('div');
                toast.textContent = (d.ok ? '✓ ' : '⚠ ') + d.message;
                toast.style.cssText = 'position:fixed;bottom:20px;left:50%;transform:translateX(-50%);padding:8px 18px;border-radius:20px;font-size:12px;font-weight:500;z-index:9999;box-shadow:0 4px 16px rgba(0,0,0,.15);';
                toast.style.background = d.ok ? '#22c55e' : '#f59e0b';
                toast.style.color = '#fff';
                document.body.appendChild(toast);
                setTimeout(() => toast.remove(), 4000);
            }
            setTimeout(() => {
                btn.disabled = false;
                btn.style.color = '';
                btn.querySelector('svg').style.animation = '';
                if (d.ok && d.stats && (d.stats.files_added > 0 || d.stats.files_removed > 0 || d.stats.albums_created > 0 || d.stats.albums_removed > 0)) {
                    location.reload();
                }
            }, 1500);
        })
        .catch(() => { btn.disabled = false; btn.style.color = ''; btn.querySelector('svg').style.animation = ''; });
}

// Clavier
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') { closeLb(); closeImportModal(); closeZipModal(); }
    if (document.getElementById('ph-lb').classList.contains('open')) {
        if (e.key === 'ArrowLeft') lbGo(-1);
        if (e.key === 'ArrowRight') lbGo(1);
    }
});

// ── Import ZIP ───────────────────────────────────────────────
function openZipModal()  { document.getElementById('ph-zip-modal').classList.add('open'); }
function closeZipModal() { document.getElementById('ph-zip-modal').classList.remove('open'); resetZip(); }
function resetZip() {
    document.getElementById('zip-info').innerHTML = '';
    document.getElementById('zip-input').value = '';
    document.getElementById('zip-btn').disabled = true;
    document.getElementById('zip-btn').textContent = 'Lancer l\'import';
}





document.addEventListener('DOMContentLoaded', function() {
    document.querySelector('#ph-zip-modal form')?.addEventListener('submit', function() {
        const btn = document.getElementById('zip-btn');
        btn.textContent = '⏳ Upload en cours…';
        btn.disabled = true;
    });
});



function scanZip(input) {
    const file = input.files[0];
    if (!file) return;
    const fmt = b => b > 1048576 ? (b/1048576).toFixed(1)+' Mo' : Math.round(b/1024)+' Ko';
    document.getElementById('zip-info').innerHTML = `
        <div style="background:var(--pd-bg);border:1px solid var(--pd-border);border-radius:6px;padding:10px 14px;font-size:12px;">
            <div style="font-weight:600;color:var(--pd-text);margin-bottom:4px;">🗜 ${file.name}</div>
            <div style="color:var(--pd-muted);">${fmt(file.size)} — extraction en arrière-plan</div>
        </div>
        <p style="font-size:11px;color:var(--pd-muted);margin-top:8px;">Les photos apparaîtront progressivement dans l'album après l'import.</p>`;
    document.getElementById('zip-btn').disabled = false;
}



</script>

{{-- Modal ZIP --}}
@can('upload', $album)
<div id="ph-zip-modal" style="position:fixed;inset:0;z-index:500;background:rgba(15,25,40,0.7);backdrop-filter:blur(4px);align-items:center;justify-content:center">
    <div style="background:var(--pd-surface);border-radius:12px;padding:2rem;width:100%;max-width:440px;margin:1rem;box-shadow:0 24px 64px rgba(0,0,0,.25);position:relative">
        <button onclick="closeZipModal()" style="position:absolute;top:1rem;right:1rem;background:none;border:none;font-size:1.1rem;color:var(--pd-muted);cursor:pointer;">✕</button>
        <div style="font-size:1rem;font-weight:700;color:var(--pd-text);margin-bottom:0.25rem;">Importer un fichier ZIP</div>
        <div style="font-size:0.8rem;color:var(--pd-muted);margin-bottom:1.5rem;">Les photos contenues dans le ZIP seront ajoutées à cet album. Les doublons sont ignorés automatiquement.</div>
        <form method="POST" action="{{ route('media.items.import-zip', $album) }}" enctype="multipart/form-data">
            @csrf
            <div style="margin-bottom:1.25rem;">
                <label style="display:block;font-size:0.78rem;font-weight:600;color:var(--pd-text);margin-bottom:0.4rem;">Fichier ZIP <span style="color:var(--pd-muted);font-weight:400;">(500 Mo max)</span></label>
                <input id="zip-input" type="file" name="zip_file" accept=".zip,application/zip" required
                    onchange="scanZip(this)"
                    style="width:100%;padding:0.6rem;border:1px solid var(--pd-border);border-radius:4px;font-size:0.85rem;color:var(--pd-text);background:var(--pd-bg);cursor:pointer;">
            </div>
            <div id="zip-info" style="margin-bottom:1.25rem;"></div>
         



		<button id="zip-btn" type="submit" disabled
	                style="width:100%;padding:0.8rem;background:var(--pd-navy);color:white;border:none;border-radius:4px;font-size:0.9rem;font-weight:700;cursor:pointer;transition:opacity 0.2s;">
	                Lancer l'import
        	 </button>
        </form>
    </div>
</div>
@endcan

<style>
#ph-zip-modal { display: none; }
#ph-zip-modal.open { display: flex; }
</style>
@endpush
