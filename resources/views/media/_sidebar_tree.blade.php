{{--
    Arbre lazy-load de navigation de la photothèque.
    Variables attendues :
      $albumTree    — collection d'albums racine (avec items_count, children_count)
      $activeAlbumId — int|null  ID de l'album courant (null sur index)
      $ancestorIds  — int[]      IDs des ancêtres de l'album courant (pour auto-dépliage)
--}}
@php
    $activeAlbumId = $activeAlbumId ?? null;
    $ancestorIds   = $ancestorIds   ?? [];
    $rootsJson = $albumTree->map(fn($a) => [
        'id'           => $a->id,
        'name'         => $a->name,
        'nas_path'     => $a->nas_path ?? null,
        'items_count'  => $a->items_count,
        'has_children' => ($a->children_count ?? 0) > 0,
        'url'          => route('media.albums.show', $a),
        'depth'        => 0,
        'expanded'     => false,
        'loaded'       => false,
    ])->values()->toJson();
@endphp

<div x-data="albumTree({{ $rootsJson }}, {{ $activeAlbumId ?? 'null' }})"
     x-init="init({{ json_encode($ancestorIds) }})">

    {{-- ── Recherche ───────────────────────────────────────── --}}
    <div style="padding:0 8px 6px;">
        <div style="position:relative;">
            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"
                 style="position:absolute;left:8px;top:50%;transform:translateY(-50%);color:var(--pd-muted);pointer-events:none;">
                <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
            </svg>
            <input type="text"
                   x-model.debounce.300ms="query"
                   @input="doSearch()"
                   placeholder="Nom ou chemin NAS…"
                   style="width:100%;box-sizing:border-box;padding:5px 28px 5px 26px;font-size:11px;border:1px solid var(--pd-border);border-radius:6px;background:var(--pd-bg);color:var(--pd-text);outline:none;">
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
                <a :href="r.url" class="ph-nav-item" style="flex-direction:column;align-items:flex-start;gap:1px;">
                    <div style="display:flex;align-items:center;gap:5px;width:100%;">
                        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M22 19a2 2 0 01-2 2H4a2 2 0 01-2-2V5a2 2 0 012-2h5l2 3h9a2 2 0 012 2z"/></svg>
                        <span style="flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" x-text="r.name"></span>
                        <span class="ph-nav-count" x-text="r.items_count"></span>
                    </div>
                    <div x-show="r.path" style="font-size:10px;color:var(--pd-muted);padding-left:15px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;width:100%;font-family:monospace;" x-text="r.path"></div>
                </a>
            </template>
        </div>
    </div>

    {{-- ── Arbre ───────────────────────────────────────────── --}}
    <div x-show="!query">
        <template x-for="node in nodes" :key="node.id + '-' + node.depth">
            <div>
                <div class="ph-nav-item"
                     :class="{ active: node.id === activeId }"
                     :style="`padding-left:${8 + node.depth * 14}px`"
                     style="padding-right:8px;">

                    {{-- Bouton déplier/replier --}}
                    <button x-show="node.has_children"
                            @click.prevent.stop="toggle(node)"
                            style="width:16px;height:16px;flex-shrink:0;background:none;border:none;cursor:pointer;color:var(--pd-muted);font-size:10px;padding:0;display:flex;align-items:center;justify-content:center;border-radius:3px;transition:background .1s;"
                            :style="loadingId === node.id ? 'opacity:.5' : ''"
                            :title="node.expanded ? 'Réduire' : 'Développer'">
                        <span x-text="loadingId === node.id ? '…' : (node.expanded ? '▾' : '›')"></span>
                    </button>
                    <span x-show="!node.has_children" style="width:16px;flex-shrink:0;"></span>

                    {{-- Lien vers l'album --}}
                    <a :href="node.url"
                       style="flex:1;display:flex;align-items:center;gap:6px;text-decoration:none;color:inherit;min-width:0;overflow:hidden;">
                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" style="flex-shrink:0;">
                            <path x-show="node.expanded" d="M22 19a2 2 0 01-2 2H4a2 2 0 01-2-2V5a2 2 0 012-2h5l2 3h9a2 2 0 012 2z"/>
                            <path x-show="!node.expanded" d="M22 19a2 2 0 01-2 2H4a2 2 0 01-2-2V5a2 2 0 012-2h5l2 3h9a2 2 0 012 2z"/>
                        </svg>
                        <span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" x-text="node.name"></span>
                    </a>
                    <span class="ph-nav-count" x-text="node.items_count" style="flex-shrink:0;"></span>
                </div>
                {{-- Chemin NAS en sous-titre si profondeur 0 --}}
                <template x-if="node.depth === 0 && node.nas_path">
                    <div :style="`padding-left:${8 + node.depth * 14 + 22}px`"
                         style="font-size:9px;color:var(--pd-muted);font-family:monospace;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;padding-right:8px;margin-top:-2px;margin-bottom:2px;"
                         x-text="node.nas_path">
                    </div>
                </template>
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
function albumTree(initialRoots, activeId) {
    return {
        nodes: initialRoots,
        activeId,
        loadingId: null,
        query: '',
        searching: false,
        searchResults: null,
        _searchTimer: null,
        _childrenCache: {},

        async init(ancestorIds) {
            // Auto-dépliage des ancêtres de l'album courant
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
            // Marquer les enfants comme non-chargés dans le cache pour reload propre
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
    };
}
</script>
@endpush
@endonce
