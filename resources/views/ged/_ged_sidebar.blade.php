{{--
    Sidebar GED — arborescence lazy-load des dossiers.
    Variables attendues :
      $sidebarTree    — collection<GedFolder> racine
      $activeFolderId — int|null
      $ancestorIds    — int[]
--}}
@php
    $activeFolderId ??= null;
    $ancestorIds    ??= [];
    $canReorganize  = auth()->check() && in_array(
        auth()->user()->role ?? '',
        ['admin', 'president', 'dgs']
    );
    $rootsJson = $sidebarTree->map(fn ($f) => [
        'id'           => $f->id,
        'name'         => $f->name,
        'slug'         => $f->slug,
        'path'         => $f->path,
        'url'          => route('ged.folders.show', $f),
        'has_children' => ($f->children_count ?? 0) > 0,
        'is_private'   => (bool) $f->is_private,
        'doc_count'    => $f->documents_count ?? 0,
        'depth'        => 0,
        'expanded'     => false,
    ])->values()->toJson();
@endphp

<aside id="ged-sidebar">

    <div class="ged-sidebar-header">
        <a href="{{ route('ged.index') }}" class="ged-new-btn">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M22 19a2 2 0 01-2 2H4a2 2 0 01-2-2V5a2 2 0 012-2h5l2 3h9a2 2 0 012 2z"/><path d="M12 11v6M9 14h6"/></svg>
            GED
        </a>
    </div>

    <nav class="ged-nav">
        <div class="ged-nav-section">Dossiers</div>

        <div x-data="gedFolderTree({{ $rootsJson }}, {{ $activeFolderId ?? 'null' }}, {{ $canReorganize ? 'true' : 'false' }})"
             x-init="init({{ json_encode($ancestorIds) }})">

            {{-- Recherche --}}
            <div style="padding:6px 8px;">
                <div style="position:relative;">
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"
                         style="position:absolute;left:8px;top:50%;transform:translateY(-50%);color:var(--pd-muted);pointer-events:none;">
                        <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                    <input type="text"
                           x-model.debounce.300ms="query"
                           @input="doSearch()"
                           placeholder="Rechercher un dossier…"
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
                        <a :href="r.url" class="ged-nav-item" style="min-height:32px;">
                            <span class="ged-tree-toggle-ph"></span>
                            <span>📁</span>
                            <span class="ged-nav-name" x-text="r.name + (r.is_private ? ' 🔒' : '')"></span>
                        </a>
                    </template>
                </div>
            </div>

            {{-- Arbre --}}
            <div x-show="!query">

                {{-- Zone racine pour DnD --}}
                <div x-show="canReorganize && draggingId !== null"
                     class="ged-dnd-root-zone"
                     :class="{ 'visible': draggingId !== null, 'dnd-over': rootZoneOver }"
                     @dragover.prevent="rootZoneOver = true"
                     @dragleave="rootZoneOver = false"
                     @drop.prevent="rootZoneOver = false; dropOnRoot($event)">
                    Déposer ici pour mettre à la racine
                </div>

                <template x-for="node in nodes" :key="node.id + '-' + node.depth">
                    <div class="ged-nav-item"
                         :class="{
                             active: node.id === activeId,
                             'dnd-dragging': draggingId === node.id,
                             'dnd-over': dropTargetId === node.id,
                         }"
                         :style="`padding-left:${6 + node.depth * 18}px`"
                         :draggable="canReorganize ? 'true' : 'false'"
                         @dragstart="canReorganize && folderDragStart(node.id, $event)"
                         @dragend="folderDragEnd()"
                         @dragover.prevent="nodeDragOver(node, $event)"
                         @dragleave="nodeDragLeave(node, $event)"
                         @drop.prevent.stop="nodeDrop(node, $event)">

                        {{-- Toggle expand --}}
                        <button x-show="node.has_children"
                                @click.prevent.stop="toggle(node)"
                                class="ged-tree-toggle"
                                :title="node.expanded ? 'Réduire' : 'Développer'">
                            <svg x-show="node.expanded" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="6 9 12 15 18 9"/></svg>
                            <svg x-show="!node.expanded" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="9 6 15 12 9 18"/></svg>
                            <span x-show="loadingId === node.id" style="font-size:9px;">…</span>
                        </button>
                        <span x-show="!node.has_children" class="ged-tree-toggle-ph"></span>

                        {{-- Spinner déplacement --}}
                        <template x-if="movingId === node.id">
                            <span style="font-size:10px;color:var(--pd-accent);flex-shrink:0;">⟳</span>
                        </template>

                        {{-- Icône dossier --}}
                        <span x-show="movingId !== node.id" style="font-size:14px;flex-shrink:0;">
                            📁<span x-show="node.is_private" style="font-size:9px;margin-left:1px;">🔒</span>
                        </span>

                        {{-- Nom cliquable --}}
                        <a :href="node.url"
                           class="ged-nav-name"
                           style="text-decoration:none;color:inherit;"
                           @click.stop="if (!draggingId) window.location = node.url">
                            <span x-text="node.name"></span>
                        </a>

                        {{-- Compteur --}}
                        <span class="ged-nav-count" x-text="node.doc_count"></span>
                    </div>
                </template>

                <template x-if="nodes.length === 0">
                    <div style="font-size:11px;color:var(--pd-muted);padding:10px 14px;font-style:italic;">Aucun dossier</div>
                </template>
            </div>

        </div>{{-- /x-data gedFolderTree --}}

    </nav>

</aside>

@once
@push('scripts')
<script>
function gedFolderTree(initialRoots, activeId, canReorganize) {
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
        draggingId: null,
        dropTargetId: null,
        rootZoneOver: false,
        movingId: null,

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
        },

        removeDescendants(node) {
            const idx = this.nodes.indexOf(node);
            let end = idx + 1;
            while (end < this.nodes.length && this.nodes[end].depth > node.depth) end++;
            this.nodes.splice(idx + 1, end - idx - 1);
        },

        async fetchChildren(id, depth) {
            try {
                const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
                const resp = await fetch(`{{ url('ged/folders') }}/${id}/children`, {
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf }
                });
                const data = await resp.json();
                return data.map(c => ({ ...c, depth, expanded: false }));
            } catch { return []; }
        },

        doSearch() {
            clearTimeout(this._searchTimer);
            if (this.query.length < 2) { this.searchResults = null; return; }
            this.searching = true;
            this._searchTimer = setTimeout(async () => {
                try {
                    const resp = await fetch(`{{ url('ged') }}?search=${encodeURIComponent(this.query)}`, {
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    });
                    // Recherche côté serveur à implémenter au besoin
                    // Pour l'instant, filtre côté client sur les nœuds chargés
                    const query = this.query.toLowerCase();
                    this.searchResults = this.nodes
                        .filter(n => n.name.toLowerCase().includes(query))
                        .map(n => ({ id: n.id, name: n.name, url: n.url, is_private: n.is_private }));
                } catch { this.searchResults = []; }
                this.searching = false;
            }, 300);
        },

        clearSearch() {
            this.query = '';
            this.searchResults = null;
        },

        // ── DnD dossiers ──────────────────────────────────────────
        folderDragStart(id, event) {
            this.draggingId = id;
            event.dataTransfer.setData('application/x-ged-folder-id', id);
            event.dataTransfer.effectAllowed = 'move';
        },
        folderDragEnd() {
            this.draggingId = null;
            this.dropTargetId = null;
            this.rootZoneOver = false;
        },
        nodeDragOver(node, event) {
            const hasFolderId = event.dataTransfer.types.includes('application/x-ged-folder-id');
            if (!hasFolderId) return;
            if (this.draggingId === node.id) return;
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
            const folderId = parseInt(event.dataTransfer.getData('application/x-ged-folder-id'));
            if (folderId && folderId !== node.id) {
                await this.doMoveFolder(folderId, node.id);
            }
        },
        async dropOnRoot(event) {
            const folderId = parseInt(event.dataTransfer.getData('application/x-ged-folder-id'));
            if (folderId) await this.doMoveFolder(folderId, null);
        },
        async doMoveFolder(folderId, newParentId) {
            this.movingId = folderId;
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
            try {
                const resp = await fetch(`{{ url('ged/folders') }}/${folderId}/move`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ parent_id: newParentId }),
                });
                const data = await resp.json();
                if (!resp.ok) {
                    alert(data.error || 'Erreur lors du déplacement.');
                    return;
                }
                location.reload();
            } catch {
                alert('Erreur réseau.');
            } finally {
                this.movingId = null;
            }
        },
    };
}
</script>
@endpush
@endonce
