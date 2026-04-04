@extends('layouts.app')
@section('title', 'Doublons — Photothèque')

@push('styles')
@include('media._ph_base_styles')
<style>
/* ── Layout ────────────────────────────────────────────── */
.dup-layout {
    display: flex;
    min-height: calc(100vh - 56px);
}
.dup-content {
    flex: 1;
    min-width: 0;
    padding: 20px 24px 40px;
    max-width: 1200px;
}

/* ── Header stats ──────────────────────────────────────── */
.dup-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 20px;
}
.dup-title { font-size: 20px; font-weight: 700; color: var(--pd-text); }
.dup-stats {
    display: flex;
    gap: 16px;
    flex-wrap: wrap;
}
.dup-stat {
    background: var(--pd-surface);
    border: 1px solid var(--pd-border);
    border-radius: 8px;
    padding: 8px 14px;
    text-align: center;
    min-width: 90px;
}
.dup-stat-val { font-size: 20px; font-weight: 700; color: var(--pd-accent); }
.dup-stat-lbl { font-size: 10px; color: var(--pd-muted); text-transform: uppercase; letter-spacing: .05em; margin-top: 2px; }

/* ── Action bar ────────────────────────────────────────── */
.dup-bar {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 14px;
    background: var(--pd-surface);
    border: 1px solid var(--pd-border);
    border-radius: 8px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}
.dup-bar-count {
    font-size: 12px;
    color: var(--pd-muted);
    flex: 1;
}
.dup-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 7px 14px;
    border-radius: 7px;
    font-size: 12px;
    font-weight: 500;
    cursor: pointer;
    border: none;
    transition: opacity .15s;
}
.dup-btn:disabled { opacity: .4; cursor: not-allowed; }
.dup-btn-danger  { background: #e74c3c; color: #fff; }
.dup-btn-outline { background: none; border: 1px solid var(--pd-border); color: var(--pd-text); }
.dup-btn-outline:hover:not(:disabled) { border-color: var(--pd-accent); color: var(--pd-accent); }
.dup-btn-danger:hover:not(:disabled)  { opacity: .85; }
.dup-btn-sm { padding: 5px 10px; font-size: 11px; }

/* ── Groupe ────────────────────────────────────────────── */
.dup-group {
    background: var(--pd-surface);
    border: 1px solid var(--pd-border);
    border-radius: 10px;
    margin-bottom: 14px;
    overflow: hidden;
}
.dup-group-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 14px;
    border-bottom: 1px solid var(--pd-border);
    background: var(--pd-bg);
    gap: 10px;
    flex-wrap: wrap;
}
.dup-group-info {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 12px;
    color: var(--pd-muted);
}
.dup-group-badge {
    background: #e74c3c;
    color: #fff;
    font-size: 10px;
    font-weight: 600;
    padding: 2px 8px;
    border-radius: 10px;
}
.dup-group-filename {
    font-size: 12px;
    font-weight: 600;
    color: var(--pd-text);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 280px;
}
.dup-group-actions { display: flex; gap: 6px; flex-wrap: wrap; }

/* ── Grille de photos ──────────────────────────────────── */
.dup-photos {
    display: flex;
    flex-wrap: wrap;
    gap: 0;
}
.dup-photo {
    display: flex;
    flex-direction: column;
    border-right: 1px solid var(--pd-border);
    width: 220px;
    min-width: 0;
    cursor: pointer;
    transition: background .1s;
    position: relative;
}
.dup-photo:last-child { border-right: none; }
.dup-photo.selected { background: rgba(37,99,235,.07); }
.dup-photo.is-original { background: rgba(16,185,129,.05); }
.dup-photo.is-original.selected { background: rgba(16,185,129,.12); }
.dup-photo-check {
    position: absolute;
    top: 8px;
    left: 8px;
    z-index: 2;
    width: 18px;
    height: 18px;
    border-radius: 4px;
    border: 2px solid rgba(255,255,255,.8);
    background: rgba(0,0,0,.35);
    display: flex;
    align-items: center;
    justify-content: center;
    pointer-events: none;
    transition: background .1s, border-color .1s;
}
.dup-photo.selected .dup-photo-check {
    background: var(--pd-accent);
    border-color: var(--pd-accent);
}
.dup-photo-check svg { display: none; }
.dup-photo.selected .dup-photo-check svg { display: block; }
.dup-photo-orig-badge {
    position: absolute;
    top: 8px;
    right: 8px;
    background: #10b981;
    color: #fff;
    font-size: 9px;
    font-weight: 700;
    padding: 2px 6px;
    border-radius: 6px;
    text-transform: uppercase;
    letter-spacing: .04em;
    z-index: 2;
}
.dup-photo-thumb {
    width: 100%;
    aspect-ratio: 1;
    object-fit: cover;
    background: var(--pd-bg);
    display: block;
}
.dup-photo-thumb-ph {
    width: 100%;
    aspect-ratio: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 36px;
    background: var(--pd-bg);
}
.dup-photo-info {
    padding: 8px 10px;
    flex: 1;
}
.dup-photo-album {
    font-size: 11px;
    font-weight: 600;
    color: var(--pd-accent);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    margin-bottom: 3px;
}
.dup-photo-path {
    font-size: 10px;
    color: var(--pd-muted);
    word-break: break-all;
    line-height: 1.35;
    margin-bottom: 4px;
}
.dup-photo-meta {
    display: flex;
    justify-content: space-between;
    font-size: 10px;
    color: var(--pd-muted);
}

/* ── Empty state ───────────────────────────────────────── */
.dup-empty {
    text-align: center;
    padding: 60px 20px;
    color: var(--pd-muted);
}
.dup-empty-icon { font-size: 48px; margin-bottom: 12px; }
.dup-empty-title { font-size: 16px; font-weight: 600; color: var(--pd-text); margin-bottom: 6px; }

/* ── Spinner ───────────────────────────────────────────── */
.dup-spinner {
    display: inline-block;
    width: 14px;
    height: 14px;
    border: 2px solid rgba(255,255,255,.4);
    border-top-color: #fff;
    border-radius: 50%;
    animation: spin .6s linear infinite;
    vertical-align: middle;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* ── Confirm modal ─────────────────────────────────────── */
.dup-modal-bg {
    position: fixed; inset: 0;
    background: rgba(0,0,0,.5);
    z-index: 1000;
    display: flex; align-items: center; justify-content: center;
}
.dup-modal {
    background: var(--pd-surface);
    border-radius: 12px;
    padding: 24px;
    max-width: 400px;
    width: 90%;
    box-shadow: 0 20px 60px rgba(0,0,0,.3);
}
.dup-modal-title { font-size: 16px; font-weight: 700; margin-bottom: 10px; }
.dup-modal-body  { font-size: 13px; color: var(--pd-muted); margin-bottom: 20px; line-height: 1.5; }
.dup-modal-actions { display: flex; gap: 8px; justify-content: flex-end; }
</style>
@endpush

@section('content')
<div class="dup-layout" x-data="dupManager({{ $groups->map(fn($g) => ['hash' => $g['hash'], 'ids' => $g['items']->pluck('id'), 'copy_ids' => $g['items']->skip(1)->pluck('id')->values(), 'first_id' => $g['items']->first()?->id])->values()->toJson() }})">

    {{-- Sidebar --}}
    @include('media._ph_sidebar', [
        'albumTree'      => $albumTree,
        'activeAlbumId'  => null,
        'ancestorIds'    => [],
        'album'          => null,
        'totalAlbums'    => null,
    ])

    {{-- Contenu principal --}}
    <div class="dup-content">

        {{-- Header --}}
        <div class="dup-header">
            <div>
                <div class="dup-title">Gestion des doublons</div>
                <div style="font-size:12px;color:var(--pd-muted);margin-top:3px;">
                    Fichiers identiques (même contenu) détectés dans plusieurs albums
                </div>
            </div>
            <div class="dup-stats">
                <div class="dup-stat">
                    <div class="dup-stat-val">{{ $totalGroups }}</div>
                    <div class="dup-stat-lbl">Groupes</div>
                </div>
                <div class="dup-stat">
                    <div class="dup-stat-val">{{ $totalDupItems }}</div>
                    <div class="dup-stat-lbl">Fichiers</div>
                </div>
                <div class="dup-stat">
                    <div class="dup-stat-val">{{ $wastedBytes >= 1073741824 ? round($wastedBytes/1073741824,1).' Go' : round($wastedBytes/1048576,1).' Mo' }}</div>
                    <div class="dup-stat-lbl">Gaspillé</div>
                </div>
            </div>
        </div>

        @if($groups->isEmpty())
            <div class="dup-empty">
                <div class="dup-empty-icon">✅</div>
                <div class="dup-empty-title">Aucun doublon détecté</div>
                <p style="font-size:13px;">La photothèque ne contient pas de fichiers en double.</p>
            </div>
        @else

        {{-- Barre d'actions globale --}}
        <div class="dup-bar">
            <div class="dup-bar-count">
                <span x-text="selected.length === 0 ? 'Cliquez sur les photos à supprimer' : selected.length + ' photo(s) sélectionnée(s)'"></span>
            </div>
            <button class="dup-btn dup-btn-outline dup-btn-sm"
                    @click="selectAll()"
                    x-text="allSelected() ? 'Tout désélectionner' : 'Tout sélectionner'">
            </button>
            <button class="dup-btn dup-btn-outline dup-btn-sm"
                    @click="selectAllDuplicates()"
                    title="Garder le fichier le plus ancien de chaque groupe, sélectionner les autres">
                Garder les originaux
            </button>
            <button class="dup-btn dup-btn-danger"
                    :disabled="selected.length === 0 || loading"
                    @click="confirmDelete(selected, selected.length + ' photo(s) sélectionnée(s)')">
                <template x-if="loading"><span class="dup-spinner"></span></template>
                <template x-if="!loading">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4h6v2"/></svg>
                </template>
                Supprimer la sélection (<span x-text="selected.length"></span>)
            </button>
        </div>

        {{-- Groupes de doublons --}}
        @foreach($groups as $group)
        @php
            $firstItem     = $group['items']->first();
            $filename      = $firstItem ? basename($firstItem->file_path) : '—';
            $size          = $firstItem?->file_size_bytes ?? 0;
            $sizeStr       = $size >= 1048576 ? round($size/1048576,1).' Mo' : round($size/1024).' Ko';
            $uniquePaths   = $group['items']->pluck('file_path')->unique()->count();
            $isDbDuplicate = $uniquePaths === 1; // tous les items pointent vers le même fichier NAS
        @endphp
        <div class="dup-group" id="group-{{ $group['hash'] }}">
            <div class="dup-group-head">
                <div style="display:flex;align-items:center;gap:8px;min-width:0;flex-wrap:wrap;">
                    <span class="dup-group-badge">× {{ $group['count'] }}</span>
                    <span class="dup-group-filename" title="{{ $filename }}">{{ $filename }}</span>
                    <span class="dup-group-info">· {{ $sizeStr }} par exemplaire</span>
                    @if($isDbDuplicate)
                        <span style="background:#f59e0b;color:#fff;font-size:10px;font-weight:600;padding:2px 7px;border-radius:10px;"
                              title="Ces {{ $group['count'] }} enregistrements pointent tous vers le même fichier physique. La suppression ne touchera que la base de données.">
                            Doublons BDD
                        </span>
                    @endif
                </div>
                <div class="dup-group-actions">
                    <button class="dup-btn dup-btn-outline dup-btn-sm"
                            title="Sélectionne uniquement les copies (pas l'original)"
                            @click="toggleGroup({{ $group['items']->skip(1)->pluck('id')->values()->toJson() }})">
                        Sélectionner les copies
                    </button>
                    <button class="dup-btn dup-btn-outline dup-btn-sm"
                            title="Conserver le fichier le plus ancien, supprimer les autres"
                            @click="confirmDelete(
                                {{ $group['items']->skip(1)->pluck('id')->values()->toJson() }},
                                'Garder 1 exemplaire de {{ addslashes($filename) }}, supprimer {{ $group['count'] - 1 }} doublon(s)'
                            )">
                        Garder le + ancien
                    </button>
                    <button class="dup-btn dup-btn-danger dup-btn-sm"
                            @click="confirmDelete(
                                {{ $group['items']->pluck('id')->toJson() }},
                                'Supprimer tous les {{ $group['count'] }} exemplaires de {{ addslashes($filename) }}'
                            )">
                        Tout supprimer
                    </button>
                </div>
            </div>

            <div class="dup-photos">
                @foreach($group['items'] as $idx => $item)
                @php
                    $isFirst   = $idx === 0;
                    $hasThumb  = $item->isImage() && $item->album;
                    $thumbUrl  = $hasThumb ? route('media.items.serve', [$item->album, $item, 'thumb']) : null;
                    $albumUrl  = $item->album ? route('media.albums.show', $item->album) : '#';
                    $albumName = $item->album?->name ?? '—';
                    $filePath  = $item->file_path;
                    $createdAt = $item->created_at?->format('d/m/Y');
                    $itemSize  = $item->file_size_bytes
                        ? ($item->file_size_bytes >= 1048576
                            ? round($item->file_size_bytes/1048576,1).' Mo'
                            : round($item->file_size_bytes/1024).' Ko')
                        : '—';
                @endphp
                <div class="dup-photo {{ $isFirst ? 'is-original' : '' }}"
                     :class="{ selected: isSelected({{ $item->id }}) }"
                     @click="toggleItem({{ $item->id }})">

                    {{-- Badge informatif "Le plus ancien" (groupes à chemins distincts uniquement) --}}
                    @if($isFirst && !$isDbDuplicate)
                    <span class="dup-photo-orig-badge">Le plus ancien</span>
                    @endif

                    {{-- Checkbox visuelle --}}
                    <div class="dup-photo-check">
                        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="3" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg>
                    </div>

                    {{-- Miniature --}}
                    @if($thumbUrl)
                        <img class="dup-photo-thumb"
                             src="{{ $thumbUrl }}"
                             alt="{{ $item->file_name }}"
                             loading="lazy">
                    @elseif($item->isVideo())
                        <div class="dup-photo-thumb-ph" style="font-size:32px;">🎬</div>
                    @elseif($item->isPdf())
                        <div class="dup-photo-thumb-ph" style="font-size:32px;">📄</div>
                    @else
                        <div class="dup-photo-thumb-ph">🖼️</div>
                    @endif

                    {{-- Infos --}}
                    <div class="dup-photo-info">
                        <div class="dup-photo-album">
                            <a href="{{ $albumUrl }}" @click.stop style="color:inherit;text-decoration:none;">
                                📁 {{ $albumName }}
                            </a>
                        </div>
                        <div class="dup-photo-path" title="{{ $filePath }}">{{ $filePath }}</div>
                        <div class="dup-photo-meta">
                            <span>{{ $itemSize }}</span>
                            <span>{{ $createdAt }}</span>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endforeach

        {{-- Pagination --}}
        @if($pagination->hasPages())
        <div style="display:flex;justify-content:center;gap:6px;margin-top:24px;flex-wrap:wrap;">
            @if($pagination->onFirstPage())
                <span class="dup-btn dup-btn-outline dup-btn-sm" style="opacity:.4;cursor:default;">← Précédent</span>
            @else
                <a href="{{ $pagination->previousPageUrl() }}" class="dup-btn dup-btn-outline dup-btn-sm">← Précédent</a>
            @endif

            <span style="font-size:12px;color:var(--pd-muted);line-height:30px;padding:0 8px;">
                Page {{ $pagination->currentPage() }} / {{ $pagination->lastPage() }}
            </span>

            @if($pagination->hasMorePages())
                <a href="{{ $pagination->nextPageUrl() }}" class="dup-btn dup-btn-outline dup-btn-sm">Suivant →</a>
            @else
                <span class="dup-btn dup-btn-outline dup-btn-sm" style="opacity:.4;cursor:default;">Suivant →</span>
            @endif
        </div>
        @endif

        @endif {{-- /groups empty --}}
    </div>{{-- /dup-content --}}

    {{-- Modal de confirmation --}}
    <div class="dup-modal-bg" x-show="confirm.open" x-cloak @click.self="confirm.open = false">
        <div class="dup-modal" @click.stop>
            <div class="dup-modal-title">Confirmer la suppression</div>
            <div class="dup-modal-body" x-text="confirm.message"></div>
            <div class="dup-modal-actions">
                <button class="dup-btn dup-btn-outline" @click="confirm.open = false">Annuler</button>
                <button class="dup-btn dup-btn-danger" @click="doDelete()" :disabled="loading">
                    <template x-if="loading"><span class="dup-spinner"></span></template>
                    <template x-if="!loading">Supprimer</template>
                </button>
            </div>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
function dupManager(groups) {
    return {
        selected: [],
        loading:  false,
        confirm: { open: false, ids: [], message: '' },

        // ── Sélection ───────────────────────────────────────────────
        isSelected(id)    { return this.selected.includes(id); },
        toggleItem(id)    {
            const idx = this.selected.indexOf(id);
            if (idx === -1) this.selected.push(id);
            else            this.selected.splice(idx, 1);
        },
        toggleGroup(ids) {
            const allIn = ids.every(id => this.selected.includes(id));
            if (allIn) {
                this.selected = this.selected.filter(id => !ids.includes(id));
            } else {
                ids.forEach(id => { if (!this.selected.includes(id)) this.selected.push(id); });
            }
        },
        allSelected() {
            const copyIds = groups.flatMap(g => g.copy_ids);
            return copyIds.length > 0 && copyIds.every(id => this.selected.includes(id));
        },
        selectAll() {
            // "Tout sélectionner" = toutes les copies, jamais les originaux
            if (this.allSelected()) {
                this.selected = [];
            } else {
                this.selected = groups.flatMap(g => g.copy_ids);
            }
        },
        selectAllDuplicates() {
            this.selected = groups.flatMap(g => g.copy_ids);
        },

        // ── Suppression ─────────────────────────────────────────────
        confirmDelete(ids, message) {
            if (!ids || ids.length === 0) return;
            this.confirm = { open: true, ids: Array.from(ids), message };
        },
        async doDelete() {
            const ids = this.confirm.ids;
            if (!ids.length) return;
            this.loading = true;
            try {
                const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
                const resp = await fetch('{{ route('media.duplicates.destroy') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ item_ids: ids }),
                });
                const data = await resp.json();
                if (!resp.ok) {
                    alert(data.error || 'Erreur lors de la suppression.');
                } else {
                    // Retirer les items supprimés de la sélection
                    ids.forEach(id => {
                        const idx = this.selected.indexOf(id);
                        if (idx !== -1) this.selected.splice(idx, 1);
                    });
                    // Recharger la page pour mettre à jour les groupes
                    window.location.reload();
                }
            } catch {
                alert('Erreur réseau lors de la suppression.');
            } finally {
                this.loading  = false;
                this.confirm.open = false;
            }
        },
    };
}
</script>
@endpush
