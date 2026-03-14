@extends('layouts.app')
@section('title', 'Photothèque')

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
.ph-nav-child { padding-left: 28px; }
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
@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
#ph-content { flex: 1; overflow-y: auto; padding: 20px; }
.ph-albums-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 14px;
}
.ph-album-card {
    border: 1px solid var(--pd-border); border-radius: 10px;
    overflow: hidden; text-decoration: none;
    background: var(--pd-surface); transition: box-shadow .15s, transform .15s;
    display: block;
}
.ph-album-card:hover { box-shadow: 0 6px 20px rgba(0,0,0,.1); transform: translateY(-2px); }
.ph-album-thumb {
    aspect-ratio: 1; background: var(--pd-bg);
    display: flex; align-items: center; justify-content: center;
    font-size: 32px; color: var(--pd-muted); overflow: hidden;
}
.ph-album-thumb img { width: 100%; height: 100%; object-fit: cover; transition: transform .3s; }
.ph-album-card:hover .ph-album-thumb img { transform: scale(1.05); }
.ph-album-info { padding: 8px 10px; }
.ph-album-name { font-size: 12px; font-weight: 600; color: var(--pd-text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-bottom: 4px; }
.ph-album-meta { display: flex; align-items: center; justify-content: space-between; }
.ph-album-count { font-size: 11px; color: var(--pd-muted); }
.ph-vis-badge { font-size: 10px; padding: 1px 6px; border-radius: 10px; font-weight: 500; }
.ph-new-album {
    border: 2px dashed var(--pd-border); border-radius: 10px;
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    aspect-ratio: 1; text-decoration: none; color: var(--pd-muted);
    font-size: 12px; gap: 6px; transition: border-color .15s, color .15s;
    padding: 20px;
}
.ph-new-album:hover { border-color: var(--pd-accent); color: var(--pd-accent); }
.ph-new-album-icon { font-size: 24px; }
.ph-album-card-wrap { position: relative; }
.ph-album-actions {
    position: absolute; top: 6px; right: 6px;
    display: none; gap: 4px;
}
.ph-album-card-wrap:hover .ph-album-actions { display: flex; }
.ph-album-act {
    width: 24px; height: 24px; border-radius: 5px;
    display: flex; align-items: center; justify-content: center;
    font-size: 11px; text-decoration: none; border: none; cursor: pointer;
    background: rgba(255,255,255,.9); color: var(--pd-text);
    box-shadow: 0 1px 4px rgba(0,0,0,.15); transition: background .1s;
}
.ph-album-act:hover { background: #fff; }
.ph-album-act.danger { color: #e74c3c; }
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
            <a href="{{ route('media.albums.index') }}" class="ph-nav-item active">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                Tous les albums
                <span class="ph-nav-count">{{ $albums->total() }}</span>
            </a>

            <div class="ph-nav-section">Albums</div>

            {{-- Recherche AJAX — compatible 12 000+ dossiers --}}
            <div x-data="albumSearch()" style="padding:0 8px 6px;">
                <div style="position:relative;">
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"
                         style="position:absolute;left:8px;top:50%;transform:translateY(-50%);color:var(--pd-muted);pointer-events:none;">
                        <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                    <input type="text"
                           x-model.debounce.300ms="query"
                           @input="search()"
                           placeholder="Rechercher un album…"
                           style="width:100%;box-sizing:border-box;padding:5px 8px 5px 26px;font-size:11px;border:1px solid var(--pd-border);border-radius:6px;background:var(--pd-bg);color:var(--pd-text);outline:none;">
                    <button x-show="query" @click="clear()"
                            style="position:absolute;right:6px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--pd-muted);font-size:12px;padding:0;line-height:1;">✕</button>
                </div>

                {{-- Résultats de recherche --}}
                <div x-show="query && results !== null" style="margin-top:4px;">
                    <template x-if="loading">
                        <div style="font-size:11px;color:var(--pd-muted);padding:6px 4px;">Recherche…</div>
                    </template>
                    <template x-if="!loading && results !== null && results.length === 0">
                        <div style="font-size:11px;color:var(--pd-muted);padding:6px 4px;">Aucun résultat</div>
                    </template>
                    <template x-for="album in results" :key="album.id">
                        <a :href="album.url" class="ph-nav-item" style="flex-direction:column;align-items:flex-start;gap:1px;">
                            <div style="display:flex;align-items:center;gap:5px;width:100%;">
                                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M22 19a2 2 0 01-2 2H4a2 2 0 01-2-2V5a2 2 0 012-2h5l2 3h9a2 2 0 012 2z"/></svg>
                                <span style="flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" x-text="album.name"></span>
                                <span class="ph-nav-count" x-text="album.items_count"></span>
                            </div>
                            <div x-show="album.path" style="font-size:10px;color:var(--pd-muted);padding-left:15px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;width:100%;" x-text="album.path"></div>
                        </a>
                    </template>
                </div>

                {{-- Albums racine (affichés quand pas de recherche) --}}
                <div x-show="!query">
                    @foreach($albumTree as $root)
                    <a href="{{ route('media.albums.show', $root) }}" class="ph-nav-item">
                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M22 19a2 2 0 01-2 2H4a2 2 0 01-2-2V5a2 2 0 012-2h5l2 3h9a2 2 0 012 2z"/></svg>
                        <span style="flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $root->name }}</span>
                        <span class="ph-nav-count">{{ $root->items_count }}</span>
                    </a>
                    @endforeach
                </div>
            </div>
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
            <span style="font-size:13px;font-weight:600;color:var(--pd-text);">Photothèque</span>
            <span style="font-size:12px;color:var(--pd-muted);">— {{ $albums->total() }} album(s)</span>
            <div style="margin-left:auto;display:flex;align-items:center;gap:6px;">
                <button class="ph-hbtn" id="btn-nas-sync" onclick="syncNas()" title="Synchroniser le NAS">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
                </button>
                @if(Auth::user()?->role === 'admin')
                <a href="{{ route('admin.settings.media') }}" class="ph-hbtn" title="Paramètres">⚙</a>
                @endif
                <a href="{{ route('media.albums.create') }}" class="ph-hbtn" title="Nouvel album"
                   style="background:var(--pd-navy);color:#fff;border-color:var(--pd-navy);">+</a>
            </div>
        </div>

        <div id="ph-content">

            @if(session('success'))
                <div style="margin-bottom:14px;padding:8px 14px;background:rgba(46,204,113,.1);border:1px solid rgba(46,204,113,.3);border-radius:8px;font-size:12px;color:#1a8a4a;">
                    ✓ {{ session('success') }}
                </div>
            @endif

            @if($albums->isEmpty())
                <div style="text-align:center;padding:60px 0;color:var(--pd-muted);">
                    <div style="font-size:40px;margin-bottom:12px;">🗂️</div>
                    <p style="font-size:14px;font-weight:500;color:var(--pd-text);">Aucun album pour le moment</p>
                    <p style="font-size:12px;margin-top:4px;margin-bottom:16px;">Créez votre premier album pour commencer.</p>
                    <a href="{{ route('media.albums.create') }}"
                       style="display:inline-block;padding:8px 20px;background:var(--pd-navy);color:#fff;border-radius:8px;text-decoration:none;font-size:12px;font-weight:600;">
                        + Créer un album
                    </a>
                </div>
            @else
                <div class="ph-albums-grid">
                    @foreach($albums as $album)
                        <div class="ph-album-card-wrap">
                            <a href="{{ route('media.albums.show', $album) }}" class="ph-album-card">
                                <div class="ph-album-thumb">
                                    @if($album->cover_path)
                                        <img src="{{ route('media.items.serve', ['album' => $album->id, 'item' => 0]) }}"
                                             alt="{{ $album->name }}">
                                    @else
                                        🗂️
                                    @endif
                                </div>
                                <div class="ph-album-info">
                                    <div class="ph-album-name">{{ $album->name }}</div>
                                    <div class="ph-album-meta">
                                        <span class="ph-album-count">
                                            {{ $album->items_count }} fich.
                                            @if($album->children_count > 0)
                                                · {{ $album->children_count }} ss-alb.
                                            @endif
                                        </span>
                                        <span class="ph-vis-badge
                                            @if($album->visibility === 'public') " style="background:#dcfce7;color:#15803d;"
                                            @elseif($album->visibility === 'restricted') " style="background:#fef3c7;color:#92400e;"
                                            @else " style="background:var(--pd-bg);color:var(--pd-muted);"
                                            @endif">
                                            {{ $album->visibilityLabel() }}
                                        </span>
                                    </div>
                                </div>
                            </a>
                            @can('manage', $album)
                            <div class="ph-album-actions">
                                <a href="{{ route('media.albums.edit', $album) }}" class="ph-album-act" title="Modifier">✏️</a>
                                <form method="POST" action="{{ route('media.albums.destroy', $album) }}"
                                      onsubmit="return confirm('Supprimer l\'album \"{{ addslashes($album->name) }}\" ?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="ph-album-act danger" title="Supprimer">🗑</button>
                                </form>
                            </div>
                            @endcan
                        </div>
                    @endforeach

                    {{-- Carte création --}}
                    <a href="{{ route('media.albums.create') }}" class="ph-new-album">
                        <span class="ph-new-album-icon">+</span>
                        <span>Nouvel album</span>
                    </a>
                </div>

                @if($albums->hasPages())
                    <div style="margin-top:20px;display:flex;justify-content:center;">
                        {{ $albums->links() }}
                    </div>
                @endif
            @endif
        </div>
    </div>
</div>

@push('scripts')
<script>
const PH_CSRF = '{{ csrf_token() }}';

function syncNas() {
    const btn = document.getElementById('btn-nas-sync');
    if (!btn) return;
    btn.disabled = true;
    btn.style.color = 'var(--pd-accent)';
    btn.querySelector('svg').style.animation = 'spin 1s linear infinite';
    fetch('{{ route('media.sync') }}', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': PH_CSRF, 'Accept': 'application/json' }
    })
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
    .catch(() => { btn.disabled = false; btn.style.color = ''; });
}

function albumSearch() {
    return {
        query: '',
        results: null,
        loading: false,
        _timer: null,

        search() {
            clearTimeout(this._timer);
            if (this.query.length < 2) {
                this.results = null;
                return;
            }
            this.loading = true;
            this._timer = setTimeout(async () => {
                try {
                    const url = '{{ route('media.albums.search') }}?q=' + encodeURIComponent(this.query);
                    const resp = await fetch(url, {
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    });
                    this.results = await resp.json();
                } catch (e) {
                    this.results = [];
                } finally {
                    this.loading = false;
                }
            }, 300);
        },

        clear() {
            this.query = '';
            this.results = null;
        }
    };
}
</script>
@endpush
@endsection
