@extends('layouts.app')
@section('title', 'DataGrid')

@push('styles')
<style>
/* ── Layout général ─────────────────────────────────────────── */
#dg-wrap {
    display: flex;
    height: calc(100vh - var(--pd-topbar-h) - var(--pd-footer-h));
    overflow: hidden;
}

/* ── Sidebar ────────────────────────────────────────────────── */
#dg-sidebar {
    width: 240px; flex-shrink: 0;
    border-right: 1px solid var(--pd-border);
    display: flex; flex-direction: column;
    background: var(--pd-surface2, #f8f9fb); overflow: hidden;
}
.dg-sidebar-header {
    padding: 12px 14px 10px;
    border-bottom: 1px solid var(--pd-border);
}
.dg-new-btn {
    width: 100%; padding: 7px 12px;
    background: var(--pd-navy); color: #fff;
    border: none; border-radius: 8px; font-size: 12px; cursor: pointer;
    display: flex; align-items: center; justify-content: center; gap: 6px;
    text-decoration: none; transition: background .15s;
}
.dg-new-btn:hover { opacity: .9; }
.dg-nav { flex: 1; overflow-y: auto; padding: 4px 0; }
.dg-nav-section {
    padding: 8px 14px 3px;
    font-size: 10px; font-weight: 600; color: var(--pd-muted);
    text-transform: uppercase; letter-spacing: .5px;
}
.dg-nav-item {
    display: flex; align-items: center; gap: 6px;
    padding: 6px 10px 6px 14px; min-height: 34px;
    cursor: pointer; color: var(--pd-muted);
    font-size: 12px; text-decoration: none;
    transition: background .1s, color .1s;
    border-right: 3px solid transparent;
    border-top: none; border-bottom: none; border-left: none;
    width: 100%; background: transparent; text-align: left;
    box-sizing: border-box;
}
.dg-nav-item:hover { background: var(--pd-surface, #f0f2f5); color: var(--pd-text); }
.dg-nav-item.active {
    background: var(--pd-surface, #f0f2f5);
    color: var(--pd-navy); font-weight: 600;
    border-right-color: var(--pd-accent, var(--pd-navy));
}
.dg-nav-count {
    flex-shrink: 0; font-size: 10px;
    background: var(--pd-border); padding: 1px 6px;
    border-radius: 10px; color: var(--pd-muted);
    margin-left: auto;
}
/* Dossier drop target */
.dg-nav-item.drag-over {
    background: color-mix(in srgb, var(--pd-navy) 10%, transparent);
    border-right-color: var(--pd-navy);
}

/* ── Main ───────────────────────────────────────────────────── */
#dg-main { flex: 1; display: flex; flex-direction: column; overflow: hidden; }
#dg-header {
    display: flex; align-items: center; gap: 10px;
    padding: 12px 24px;
    border-bottom: 1px solid var(--pd-border);
    background: var(--pd-bg); flex-shrink: 0;
}
#dg-header h1 { font-size: 16px; font-weight: 700; color: var(--pd-text); margin: 0; flex: 1; }
#dg-content { flex: 1; overflow-y: auto; padding: 24px; }

/* ── Cartes grilles ─────────────────────────────────────────── */
.dg-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
    gap: 14px;
}
.dg-card {
    display: block; background: var(--pd-bg);
    border: 1px solid var(--pd-border); border-radius: 12px;
    padding: 18px; text-decoration: none;
    transition: border-color .15s, box-shadow .15s;
    cursor: grab; position: relative;
}
.dg-card:hover {
    border-color: var(--pd-navy);
    box-shadow: 0 2px 8px rgba(0,0,0,.08);
}
.dg-card.dragging { opacity: .4; cursor: grabbing; }
.dg-card-label { font-size: 14px; font-weight: 600; color: var(--pd-text); margin-bottom: 4px; }
.dg-card-desc { font-size: 12px; color: var(--pd-muted); margin-bottom: 10px; }
.dg-card-meta { font-size: 11px; color: var(--pd-muted); }
.dg-empty {
    text-align: center; padding: 60px 24px; color: var(--pd-muted);
}

/* ── Modal dossier ──────────────────────────────────────────── */
#dg-modal-folder {
    display: none; position: fixed; inset: 0;
    background: rgba(0,0,0,.45); z-index: 1000;
    align-items: center; justify-content: center;
}
#dg-modal-folder.open { display: flex; }
</style>
@endpush

@section('content')
<div id="dg-wrap">

    {{-- ── Sidebar ───────────────────────────────────────────────── --}}
    <aside id="dg-sidebar">
        <div class="dg-sidebar-header">
            @if(auth()->user()->isAdmin())
            <a href="{{ route('datagrid.import') }}" class="dg-new-btn">
                <span style="font-size:14px;">+</span> Importer une grille
            </a>
            @else
            <div style="font-size:12px;font-weight:600;color:var(--pd-text);padding:4px 0;">DataGrid</div>
            @endif
        </div>

        <nav class="dg-nav">
            <div class="dg-nav-section">Navigation</div>

            {{-- Toutes les grilles --}}
            <a href="{{ route('datagrid.index') }}"
               class="dg-nav-item {{ ! request()->query('folder') ? 'active' : '' }}"
               data-folder-id="">
                <span>📇</span>
                <span>Toutes les grilles</span>
                <span class="dg-nav-count">{{ $totalCount }}</span>
            </a>

            {{-- Grilles sans dossier --}}
            @if($unfoldered->isNotEmpty())
            <a href="{{ route('datagrid.index', ['folder' => 'none']) }}"
               class="dg-nav-item {{ request()->query('folder') === 'none' ? 'active' : '' }}"
               data-folder-id="none"
               ondragover="event.preventDefault();this.classList.add('drag-over')"
               ondragleave="this.classList.remove('drag-over')"
               ondrop="dropOnFolder(event, null)">
                <span>📋</span>
                <span>Sans dossier</span>
                <span class="dg-nav-count">{{ $unfoldered->count() }}</span>
            </a>
            @endif

            @if($folders->isNotEmpty())
            <div class="dg-nav-section">Dossiers</div>
            @foreach($folders as $folder)
            <div style="position:relative;display:flex;align-items:center;">
                <a href="{{ route('datagrid.index', ['folder' => $folder->id]) }}"
                   class="dg-nav-item {{ request()->query('folder') == $folder->id ? 'active' : '' }}"
                   style="flex:1;"
                   data-folder-id="{{ $folder->id }}"
                   ondragover="event.preventDefault();this.classList.add('drag-over')"
                   ondragleave="this.classList.remove('drag-over')"
                   ondrop="dropOnFolder(event, {{ $folder->id }})">
                    <span>📁</span>
                    <span style="flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $folder->label }}</span>
                    <span class="dg-nav-count">{{ $folder->tables->count() }}</span>
                </a>
                @if(auth()->user()->isAdmin())
                <div style="display:flex;gap:2px;padding-right:6px;flex-shrink:0;">
                    <button onclick="openFolderEdit({{ $folder->id }}, '{{ addslashes($folder->label) }}')"
                            style="padding:2px 5px;background:none;border:none;cursor:pointer;color:var(--pd-muted);font-size:11px;border-radius:4px;"
                            title="Renommer">✏️</button>
                    <form method="POST" action="{{ route('datagrid.folder.destroy', $folder) }}"
                          onsubmit="return confirm('Supprimer ce dossier ? Les grilles seront déplacées à la racine.')">
                        @csrf @method('DELETE')
                        <button type="submit"
                                style="padding:2px 5px;background:none;border:none;cursor:pointer;color:#dc2626;font-size:11px;border-radius:4px;"
                                title="Supprimer">🗑</button>
                    </form>
                </div>
                @endif
            </div>
            @endforeach
            @endif

            @if(auth()->user()->isAdmin())
            <div style="padding:8px 14px;">
                <button onclick="openFolderCreate()"
                        style="width:100%;padding:6px 10px;background:none;border:1px dashed var(--pd-border);border-radius:7px;font-size:11px;color:var(--pd-muted);cursor:pointer;text-align:left;">
                    + Nouveau dossier
                </button>
            </div>
            @endif
        </nav>
    </aside>

    {{-- ── Main ─────────────────────────────────────────────────── --}}
    <div id="dg-main">
        <div id="dg-header">
            <h1>
                @if(request()->query('folder') === 'none')
                    Grilles sans dossier
                @elseif(request()->query('folder') && ($activeFolder = $folders->firstWhere('id', (int) request()->query('folder'))))
                    📁 {{ $activeFolder->label }}
                @else
                    Toutes les grilles
                @endif
            </h1>
            @if(session('success'))
            <div style="padding:6px 12px;background:#dcfce7;border:1px solid #86efac;border-radius:7px;font-size:12px;color:#15803d;">
                ✓ {{ session('success') }}
            </div>
            @endif
        </div>

        <div id="dg-content">
            @php
                // Sélection des grilles à afficher selon le filtre
                $folder_param = request()->query('folder');
                if ($folder_param === 'none') {
                    $displayTables = $unfoldered;
                } elseif ($folder_param) {
                    $f = $folders->firstWhere('id', (int) $folder_param);
                    $displayTables = $f ? $f->tables : collect();
                } else {
                    // Toutes
                    $all = collect();
                    foreach ($folders as $f) { $all = $all->merge($f->tables); }
                    $displayTables = $all->merge($unfoldered)->sortBy('label');
                }
            @endphp

            @if($displayTables->isEmpty())
            <div class="dg-empty">
                <div style="font-size:40px;margin-bottom:12px;">📇</div>
                <p style="font-size:14px;font-weight:600;color:var(--pd-text);">Aucune grille ici</p>
                @if(auth()->user()->isAdmin())
                <p style="font-size:12px;margin-top:4px;">
                    <a href="{{ route('datagrid.import') }}" style="color:var(--pd-navy);">Importer une grille</a>
                </p>
                @endif
            </div>
            @else
            <div class="dg-grid" id="dg-grid">
                @foreach($displayTables as $table)
                <a href="{{ route('datagrid.show', $table) }}"
                   class="dg-card"
                   draggable="{{ auth()->user()->isAdmin() ? 'true' : 'false' }}"
                   data-table-id="{{ $table->id }}"
                   ondragstart="dragStart(event, {{ $table->id }})">
                    <div class="dg-card-label">{{ $table->label }}</div>
                    @if($table->description)
                    <div class="dg-card-desc">{{ $table->description }}</div>
                    @endif
                    <div class="dg-card-meta">
                        {{ $table->columns_count }} colonne{{ $table->columns_count !== 1 ? 's' : '' }}
                        @if($table->has_rgpd)
                        <span style="margin-left:6px;padding:1px 5px;background:#fef3c7;border:1px solid #fcd34d;border-radius:4px;font-size:10px;color:#92400e;">RGPD</span>
                        @endif
                    </div>
                </a>
                @endforeach
            </div>
            @endif
        </div>
    </div>
</div>

{{-- ── Modal dossier ────────────────────────────────────────────── --}}
<div id="dg-modal-folder" onclick="if(event.target===this)closeFolderModal()">
    <div style="background:var(--pd-bg);border-radius:12px;padding:24px;width:380px;box-shadow:0 20px 60px rgba(0,0,0,.25);">
        <h2 id="dg-modal-title" style="margin:0 0 16px;font-size:15px;font-weight:700;color:var(--pd-text);">Nouveau dossier</h2>
        <form id="dg-folder-form" method="POST" action="{{ route('datagrid.folder.store') }}">
            @csrf
            <span id="dg-method-field"></span>
            <input name="label" id="dg-folder-label" type="text" placeholder="Nom du dossier"
                   required maxlength="255"
                   style="width:100%;padding:9px 12px;border:1px solid var(--pd-border);border-radius:8px;font-size:13px;box-sizing:border-box;margin-bottom:14px;background:var(--pd-bg);color:var(--pd-text);">
            <div style="display:flex;justify-content:flex-end;gap:8px;">
                <button type="button" onclick="closeFolderModal()"
                        style="padding:8px 16px;border:1px solid var(--pd-border);border-radius:7px;font-size:12px;font-weight:600;cursor:pointer;background:var(--pd-bg);color:var(--pd-text);">
                    Annuler
                </button>
                <button type="submit" id="dg-modal-submit"
                        style="padding:8px 16px;background:var(--pd-navy);color:#fff;border:none;border-radius:7px;font-size:12px;font-weight:600;cursor:pointer;">
                    Créer
                </button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
const DG_CSRF = '{{ csrf_token() }}';

// ── Drag & Drop ──────────────────────────────────────────────────
let draggedTableId = null;

function dragStart(event, tableId) {
    draggedTableId = tableId;
    event.dataTransfer.effectAllowed = 'move';
    setTimeout(() => event.target.classList.add('dragging'), 0);
}

document.addEventListener('dragend', () => {
    document.querySelectorAll('.dg-card.dragging').forEach(el => el.classList.remove('dragging'));
    document.querySelectorAll('.dg-nav-item.drag-over').forEach(el => el.classList.remove('drag-over'));
});

function dropOnFolder(event, folderId) {
    event.preventDefault();
    event.currentTarget.classList.remove('drag-over');
    if (!draggedTableId) return;

    fetch(`/datagrid/${draggedTableId}/move`, {
        method: 'PATCH',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': DG_CSRF,
            'Accept': 'application/json',
        },
        body: JSON.stringify({ folder_id: folderId }),
    })
    .then(r => r.ok ? location.reload() : r.json().then(d => alert(d.message || 'Erreur')))
    .catch(() => alert('Erreur réseau'));
}

// ── Modal dossier ────────────────────────────────────────────────
function openFolderCreate() {
    document.getElementById('dg-modal-title').textContent = 'Nouveau dossier';
    document.getElementById('dg-folder-label').value = '';
    document.getElementById('dg-folder-form').action = '{{ route("datagrid.folder.store") }}';
    document.getElementById('dg-method-field').innerHTML = '';
    document.getElementById('dg-modal-submit').textContent = 'Créer';
    document.getElementById('dg-modal-folder').classList.add('open');
    setTimeout(() => document.getElementById('dg-folder-label').focus(), 50);
}

function openFolderEdit(id, label) {
    document.getElementById('dg-modal-title').textContent = 'Renommer le dossier';
    document.getElementById('dg-folder-label').value = label;
    document.getElementById('dg-folder-form').action = `/datagrid/folders/${id}`;
    document.getElementById('dg-method-field').innerHTML = '<input type="hidden" name="_method" value="PATCH">';
    document.getElementById('dg-modal-submit').textContent = 'Enregistrer';
    document.getElementById('dg-modal-folder').classList.add('open');
    setTimeout(() => document.getElementById('dg-folder-label').focus(), 50);
}

function closeFolderModal() {
    document.getElementById('dg-modal-folder').classList.remove('open');
}

document.addEventListener('keydown', e => { if (e.key === 'Escape') closeFolderModal(); });
</script>
@endpush

@endsection
