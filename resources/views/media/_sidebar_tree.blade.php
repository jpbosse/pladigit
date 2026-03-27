{{--
    Arbre lazy-load de navigation de la photothèque.
    Variables attendues :
      $albumTree    — collection d'albums racine (avec items_count, children_count, coverItem)
      $activeAlbumId — int|null  ID de l'album courant (null sur index)
      $ancestorIds  — int[]      IDs des ancêtres de l'album courant (pour auto-dépliage)
--}}
@php
    $activeAlbumId  = $activeAlbumId ?? null;
    $ancestorIds    = $ancestorIds   ?? [];
    $canReorganize  = auth()->check() && in_array(
        auth()->user()->role ?? '',
        ['admin', 'president', 'dgs']
    );
    $rootsJson = $albumTree->map(fn($a) => [
        'id'           => $a->id,
        'name'         => $a->name,
        'nas_path'     => $a->nas_path ?? null,
        'items_count'  => $a->items_count,
        'has_children' => ($a->children_count ?? 0) > 0,
        'url'          => route('media.albums.show', $a),
        'thumb_url'    => $a->coverItem
            ? route('media.items.serve', [$a->id, $a->coverItem->id, 'thumb'])
            : null,
        'depth'        => 0,
        'expanded'     => false,
        'loaded'       => false,
    ])->values()->toJson();
@endphp

<div x-data="albumTree({{ $rootsJson }}, {{ $activeAlbumId ?? 'null' }}, {{ $canReorganize ? 'true' : 'false' }})"
     x-init="init({{ json_encode($ancestorIds) }})">

    {{-- ── Recherche ───────────────────────────────────────── --}}
    <div style="padding:6px 8px;">
        <div style="position:relative;">
            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"
                 style="position:absolute;left:8px;top:50%;transform:translateY(-50%);color:var(--pd-muted);pointer-events:none;">
                <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
            </svg>
            <input type="text"
                   x-model.debounce.300ms="query"
                   @input="doSearch()"
                   placeholder="Rechercher un album…"
                   style="width:100%;box-sizing:border-box;padding:6px 28px 6px 26px;font-size:11px;border:1px solid var(--pd-border);border-radius:6px;background:var(--pd-bg);color:var(--pd-text);outline:none;">
            <button x-show="query" @click="clearSearch()"
                    style="position:absolute;right:6px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--pd-muted);font-size:12px;padding:0;line-height:1;">✕</button>
        </div>

        {{-- Résultats de recherche --}}
        <div x-show="query && searchResults !== null" style="margin-top:4px;">
            <template x-if="searching">
                <div style="font-size:11px;color:var(--pd-muted);padding:6px 4px;">Recherche…</div>
            </template>
            <template x-if="!searching && searchResults !== null && searchResults.length === 0">
                <div style="font-size:11px;color:var(--pd-muted);padding:6px 4px;">Aucun résultat</div>
            </template>
            <template x-for="r in searchResults" :key="r.id">
                <a :href="r.url" class="ph-nav-item" style="min-height:36px;">
                    <span class="ph-tree-toggle-ph"></span>
                    <template x-if="r.thumb_url">
                        <img :src="r.thumb_url" :alt="r.name"
                             style="width:30px;height:30px;object-fit:cover;border-radius:5px;flex-shrink:0;min-width:30px;min-height:30px;max-width:30px;max-height:30px;">
                    </template>
                    <template x-if="!r.thumb_url">
                        <span class="ph-nav-thumb-ph">🗂️</span>
                    </template>
                    <span class="ph-nav-name" x-text="r.name"></span>
                    <span class="ph-nav-count" x-text="r.items_count"></span>
                </a>
            </template>
        </div>
    </div>

    {{-- ── Arbre ───────────────────────────────────────────── --}}
    <div x-show="!query">

        {{-- Zone "Racine" — apparaît quand on drague un album (admin seulement) --}}
        <div x-show="canReorganize && draggingAlbumId !== null"
             class="ph-dnd-root-zone"
             :class="{ 'visible': draggingAlbumId !== null, 'dnd-over': rootZoneOver }"
             @dragover.prevent="rootZoneOver = true"
             @dragleave="rootZoneOver = false"
             @drop.prevent="rootZoneOver = false; dropOnRoot($event)">
            Déposer ici pour mettre à la racine
        </div>

        <template x-for="node in nodes" :key="node.id + '-' + node.depth">
            <div>
                <div class="ph-nav-item"
                     :class="{
                         active: node.id === activeId,
                         'dnd-dragging': draggingAlbumId === node.id,
                         'dnd-over': dropTargetId === node.id,
                     }"
                     :style="`padding-left:${6 + node.depth * 18}px`"
                     x-bind:draggable="canReorganize ? 'true' : 'false'"
                     @dragstart="canReorganize && albumDragStart(node.id, $event)"
                     @dragend="albumDragEnd()"
                     @dragover.prevent="nodeDragOver(node, $event)"
                     @dragleave="nodeDragLeave(node, $event)"
                     @drop.prevent.stop="nodeDrop(node, $event)">

                    {{-- Bouton déplier/replier --}}
                    <button x-show="node.has_children"
                            @click.prevent.stop="toggle(node)"
                            class="ph-tree-toggle"
                            :title="node.expanded ? 'Réduire' : 'Développer'">
                        <svg x-show="node.expanded" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="6 9 12 15 18 9"/></svg>
                        <svg x-show="!node.expanded" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="9 6 15 12 9 18"/></svg>
                        <span x-show="loadingId === node.id" style="font-size:9px;">…</span>
                    </button>
                    <span x-show="!node.has_children" class="ph-tree-toggle-ph"></span>

                    {{-- Spinner pendant le déplacement --}}
                    <template x-if="movingAlbumId === node.id">
                        <span style="font-size:10px;color:var(--pd-accent);flex-shrink:0;">⟳</span>
                    </template>

                    {{-- Vignette / icône dossier --}}
                    <template x-if="node.thumb_url && movingAlbumId !== node.id">
                        <img :src="node.thumb_url" :alt="node.name"
                             style="width:30px;height:30px;object-fit:cover;border-radius:5px;flex-shrink:0;min-width:30px;min-height:30px;max-width:30px;max-height:30px;">
                    </template>
                    <template x-if="!node.thumb_url && movingAlbumId !== node.id">
                        <span class="ph-nav-thumb-ph">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
                                <path x-show="node.expanded" d="M22 19a2 2 0 01-2 2H4a2 2 0 01-2-2V5a2 2 0 012-2h5l2 3h9a2 2 0 012 2z"/>
                                <path x-show="!node.expanded" d="M22 19a2 2 0 01-2 2H4a2 2 0 01-2-2V5a2 2 0 012-2h5l2 3h9a2 2 0 012 2z"/>
                            </svg>
                        </span>
                    </template>

                    {{-- Nom cliquable --}}
                    <a :href="node.url"
                       class="ph-nav-name"
                       style="text-decoration:none;color:inherit;"
                       @click.stop="if (!draggingAlbumId) window.location = node.url">
                        <span x-text="node.name"></span>
                    </a>

                    {{-- Bouton + sous-album (visible au survol) --}}
                    <a :href="`{{ url('media/albums/create') }}?parent_id=${node.id}`"
                       class="ph-nav-addchild"
                       title="Créer un sous-album"
                       @click.stop>+</a>

                    {{-- Compteur --}}
                    <span class="ph-nav-count" x-text="node.items_count"></span>
                </div>
            </div>
        </template>

        <template x-if="nodes.length === 0">
            <div style="font-size:11px;color:var(--pd-muted);padding:10px 14px;font-style:italic;">Aucun album</div>
        </template>
    </div>
</div>

@once
@push('scripts')
<script>
function albumTree(initialRoots, activeId, canReorganize) {
    return {
        nodes: initialRoots,
        activeId,
        canReorganize,
        loadingId: null,
        query: '',
        searching: false,
        searchResults: null,
        _searchTimer: null,
        _childrenCache: {},
        // DnD état
        draggingAlbumId: null,
        dropTargetId: null,
        rootZoneOver: false,
        movingAlbumId: null,

        async init(ancestorIds) {
            for (const id of ancestorIds) {
                const node = this.nodes.find(n => n.id === id);
                if (node && !node.expanded) await this.toggle(node, true);
            }
        },

        async toggle(node, silent = false) {
            if (!node.has_children) return;

            if (node.expanded) {
                node.expanded = false;
                this.removeDescendants(node);
            } else {
                node.expanded = true;
                if (!this._childrenCache[node.id]) {
                    if (!silent) this.loadingId = node.id;
                    this._childrenCache[node.id] = await this.fetchChildren(node.id, node.depth + 1);
                    this.loadingId = null;
                }
                const idx = this.nodes.indexOf(node);
                this.nodes.splice(idx + 1, 0, ...this._childrenCache[node.id].map(c => ({ ...c })));
            }
            this.saveState();
        },

        removeDescendants(node) {
            const idx = this.nodes.indexOf(node);
            let end = idx + 1;
            while (end < this.nodes.length && this.nodes[end].depth > node.depth) end++;
            for (let i = idx + 1; i < end; i++) {
                if (this.nodes[i].expanded) this.nodes[i].expanded = false;
            }
            this.nodes.splice(idx + 1, end - idx - 1);
        },

        async fetchChildren(id, depth) {
            try {
                const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
                const resp = await fetch(`{{ url('media/albums') }}/${id}/children`, {
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf }
                });
                const data = await resp.json();
                return data.map(c => ({ ...c, depth, expanded: false, loaded: false }));
            } catch { return []; }
        },

        saveState() {
            const ids = this.nodes.filter(n => n.expanded).map(n => n.id);
            localStorage.setItem('ph_tree_exp', JSON.stringify(ids));
        },

        doSearch() {
            clearTimeout(this._searchTimer);
            if (this.query.length < 2) { this.searchResults = null; return; }
            this.searching = true;
            this._searchTimer = setTimeout(async () => {
                try {
                    const resp = await fetch(`{{ route('media.albums.search') }}?q=${encodeURIComponent(this.query)}`, {
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    });
                    this.searchResults = await resp.json();
                } catch { this.searchResults = []; }
                this.searching = false;
            }, 300);
        },

        clearSearch() {
            this.query = '';
            this.searchResults = null;
        },

        // ── DnD albums ───────────────────────────────────────────
        albumDragStart(id, event) {
            this.draggingAlbumId = id;
            event.dataTransfer.setData('application/x-album-id', id);
            event.dataTransfer.effectAllowed = 'move';
        },
        albumDragEnd() {
            this.draggingAlbumId = null;
            this.dropTargetId    = null;
            this.rootZoneOver    = false;
        },
        nodeDragOver(node, event) {
            const hasAlbum = event.dataTransfer.types.includes('application/x-album-id');
            const hasPhoto = event.dataTransfer.types.includes('application/x-media-item-id');
            if (!hasAlbum && !hasPhoto) return;
            // Ne pas accepter de déposer un album sur lui-même
            if (hasAlbum && this.draggingAlbumId === node.id) return;
            event.preventDefault();
            this.dropTargetId = node.id;
        },
        nodeDragLeave(node, event) {
            if (!event.currentTarget.contains(event.relatedTarget)) {
                if (this.dropTargetId === node.id) this.dropTargetId = null;
            }
        },
        async nodeDrop(node, event) {
            this.dropTargetId = null;
            const albumId       = event.dataTransfer.getData('application/x-album-id');
            const photoId       = event.dataTransfer.getData('application/x-media-item-id');
            const sourceAlbumId = event.dataTransfer.getData('application/x-media-source-album');

            if (albumId && parseInt(albumId) !== node.id) {
                await this.doMoveAlbum(parseInt(albumId), node.id);
            } else if (photoId && sourceAlbumId) {
                await this.doMovePhoto(parseInt(photoId), parseInt(sourceAlbumId), node.id);
            }
        },
        async dropOnRoot(event) {
            const albumId = event.dataTransfer.getData('application/x-album-id');
            if (albumId) await this.doMoveAlbum(parseInt(albumId), null);
        },
        async doMoveAlbum(albumId, newParentId) {
            this.movingAlbumId = albumId;
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
            try {
                const resp = await fetch(`{{ url('media/albums') }}/${albumId}/move-album`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify({ parent_id: newParentId }),
                });
                const data = await resp.json();
                if (!resp.ok) { alert(data.error || 'Erreur lors du déplacement.'); return; }
                location.reload();
            } catch { alert('Erreur réseau.'); }
            finally   { this.movingAlbumId = null; }
        },
        async doMovePhoto(itemId, sourceAlbumId, targetAlbumId) {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
            try {
                const resp = await fetch(`{{ url('media/albums') }}/${sourceAlbumId}/items/move`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify({ item_ids: [itemId], target_album_id: targetAlbumId }),
                });
                const data = await resp.json();
                if (!resp.ok) { alert(data.error || 'Erreur lors du déplacement.'); return; }
                if (data.moved > 0) location.reload();
            } catch { alert('Erreur réseau.'); }
        },
    };
}
</script>
@endpush
@endonce
