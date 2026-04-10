@extends('layouts.app')
@section('title', 'Photothèque')

@push('styles')
@include('media._ph_base_styles')
<style>
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

<div id="ph-wrap">

    {{-- Sidebar --}}
    @include('media._ph_sidebar', [
        'albumTree'     => $albumTree,
        'activeAlbumId' => null,
        'ancestorIds'   => [],
        'album'         => null,
        'totalAlbums'   => $albums->total(),
    ])

    {{-- Main --}}
    <div id="ph-main">

        <div id="ph-header">
            <div class="ph-breadcrumb">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" style="flex-shrink:0;"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                <span class="current">Photothèque</span>
            </div>

            <form method="GET" action="{{ route('media.search') }}" style="flex:1;max-width:300px;">
                <div class="ph-search-wrap">
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" style="color:var(--pd-muted);flex-shrink:0;"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input type="text" name="q" placeholder="Rechercher dans la photothèque…" autocomplete="off">
                </div>
            </form>

            <div class="ph-header-right">
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
                                    @php $cover = $album->relationLoaded('coverItem') ? $album->coverItem : $album->resolveCoverItem(); @endphp
                                    @if($cover)
                                        <img src="{{ route('media.items.serve', [$album, $cover, 'thumb']) }}"
                                             alt="{{ $album->name }}">
                                    @else
                                        <span style="font-size:32px;">🗂️</span>
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


</script>
@endpush
@endsection
