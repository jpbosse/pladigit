@extends('layouts.admin')
@section('title', 'Intégrité GED')

@push('styles')
<style>
.gi-header { margin-bottom:24px; }
.gi-title   { font-size:22px; font-weight:700; color:var(--pd-navy); margin:0 0 4px; }
.gi-subtitle{ font-size:13px; color:var(--pd-muted); margin:0; }

.gi-actions { display:flex; align-items:center; gap:12px; flex-wrap:wrap; margin-bottom:24px; }
.gi-scan-btn {
    display:inline-flex; align-items:center; gap:7px;
    padding:9px 18px; background:var(--pd-accent); color:#fff;
    border:none; border-radius:8px; font-size:13px; font-weight:600; cursor:pointer;
    transition:opacity .15s;
}
.gi-scan-btn:disabled { opacity:.55; cursor:default; }
.gi-scan-btn svg { width:15px; height:15px; fill:none; stroke:currentColor; stroke-width:2; stroke-linecap:round; }
.gi-scan-btn.spinning svg { animation:gi-spin .8s linear infinite; }
@keyframes gi-spin { to { transform:rotate(360deg); } }
.gi-scanned-at { font-size:11px; color:var(--pd-muted); }

.gi-tabs { display:flex; gap:4px; border-bottom:2px solid var(--pd-border); margin-bottom:20px; flex-wrap:wrap; }
.gi-tab {
    padding:8px 14px; font-size:12px; font-weight:600; color:var(--pd-muted);
    cursor:pointer; border:none; background:none;
    border-bottom:2px solid transparent; margin-bottom:-2px;
    display:flex; align-items:center; gap:6px;
}
.gi-tab.active { color:var(--pd-accent); border-bottom-color:var(--pd-accent); }
.gi-badge {
    font-size:10px; font-weight:700; padding:1px 5px; border-radius:99px; line-height:1.4; color:#fff;
    background:#e74c3c;
}
.gi-badge.ok   { background:#22c55e; }
.gi-badge.warn { background:#f59e0b; }

.gi-empty { text-align:center; padding:60px 20px; color:var(--pd-muted); font-size:13px; }
.gi-empty svg { opacity:.3; margin:0 auto 12px; display:block; width:48px; height:48px; fill:none; stroke:currentColor; stroke-width:1.5; }
.gi-empty strong { display:block; font-size:15px; color:var(--pd-text); margin-bottom:6px; }

.gi-bar {
    display:flex; align-items:center; gap:10px; padding:10px 14px;
    background:var(--pd-surface); border:1px solid var(--pd-border);
    border-radius:8px; margin-bottom:14px; flex-wrap:wrap;
}
.gi-bar-count { font-size:12px; color:var(--pd-muted); flex:1; }

.gi-table { width:100%; border-collapse:collapse; font-size:12px; }
.gi-table th {
    text-align:left; padding:8px 10px; background:var(--pd-surface);
    color:var(--pd-muted); font-weight:600; font-size:11px;
    text-transform:uppercase; letter-spacing:.04em; border-bottom:1px solid var(--pd-border);
}
.gi-table td { padding:8px 10px; border-bottom:1px solid var(--pd-border); vertical-align:middle; color:var(--pd-text); }
.gi-table tr:hover td { background:var(--pd-surface); }
.gi-table .mono { font-family:monospace; font-size:11px; color:var(--pd-muted); word-break:break-all; }
.gi-checkbox { width:16px; height:16px; cursor:pointer; accent-color:var(--pd-accent); }

.gi-btn {
    display:inline-flex; align-items:center; gap:5px; padding:5px 12px;
    border-radius:6px; font-size:12px; font-weight:600; cursor:pointer; border:1px solid transparent;
    transition:background .1s;
}
.gi-btn:disabled { opacity:.4; cursor:default; }
.gi-btn-danger   { background:#fef2f2; color:#e74c3c; border-color:#fca5a5; }
.gi-btn-danger:hover:not(:disabled)   { background:#fee2e2; }
.gi-btn-warning  { background:#fffbeb; color:#b45309; border-color:#fcd34d; }
.gi-btn-warning:hover:not(:disabled)  { background:#fef3c7; }
.gi-btn-secondary{ background:var(--pd-surface); color:var(--pd-text); border-color:var(--pd-border); }
.gi-btn-secondary:hover:not(:disabled){ background:var(--pd-border); }

.gi-alert {
    display:flex; align-items:flex-start; gap:10px; padding:10px 14px;
    border-radius:8px; font-size:12px; margin-bottom:16px; line-height:1.5;
}
.gi-alert svg { flex-shrink:0; margin-top:1px; width:15px; height:15px; fill:none; stroke:currentColor; stroke-width:2; stroke-linecap:round; }
.gi-alert-success{ background:#f0fdf4; color:#15803d; border:1px solid #bbf7d0; }
.gi-alert-error  { background:#fef2f2; color:#b91c1c; border:1px solid #fca5a5; }
.gi-alert-warning{ background:#fffbeb; color:#92400e; border:1px solid #fcd34d; }
</style>
@endpush

@section('admin-content')
<div x-data="gedIntegrity()" x-init="init()">

    <div class="gi-header">
        <h1 class="gi-title">Intégrité GED</h1>
        <p class="gi-subtitle">
            Détecte les incohérences entre la base de données et le stockage physique (fichiers manquants, fichiers orphelins, corbeille).
        </p>
    </div>

    <div class="gi-actions">
        <button class="gi-scan-btn" :class="{ spinning: _scanning }" :disabled="_scanning" @click="scan()">
            <svg viewBox="0 0 24 24"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-3"/></svg>
            <span x-text="_scanning ? 'Analyse en cours…' : 'Lancer l\'analyse'"></span>
        </button>
        <span class="gi-scanned-at" x-show="_scannedAt" x-text="'Dernière analyse : ' + _scannedAt"></span>
    </div>

    {{-- Alertes --}}
    <template x-if="_error">
        <div class="gi-alert gi-alert-error">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <span x-text="_error"></span>
        </div>
    </template>
    <template x-if="_actionMsg">
        <div class="gi-alert gi-alert-success">
            <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
            <span x-text="_actionMsg"></span>
        </div>
    </template>

    {{-- Tabs --}}
    <div class="gi-tabs" x-show="_scanned">
        <template x-for="tab in _tabs" :key="tab.key">
            <button class="gi-tab" :class="{ active: _tab === tab.key }" @click="_tab = tab.key">
                <span x-text="tab.label"></span>
                <span class="gi-badge"
                      :class="{ ok: tab.count === 0, warn: tab.count > 0 && tab.key.startsWith('soft') }"
                      x-text="tab.count"></span>
            </button>
        </template>
    </div>

    {{-- ── Tab 1 : Orphelins BDD actifs ──────────────────────── --}}
    <div x-show="_tab === 'db_orphans' && _scanned">
        <div class="gi-alert gi-alert-warning">
            <svg viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            Documents actifs en base dont le fichier physique est introuvable sur le stockage.
            L'action ci-dessous les passe en corbeille (soft-delete).
        </div>
        <div class="gi-bar">
            <span class="gi-bar-count" x-text="_dbOrphans.length + ' document(s) concerné(s)'"></span>
            <button class="gi-btn gi-btn-secondary" @click="selectAll('db')" x-show="_dbOrphans.length > 0">Tout sélectionner</button>
            <button class="gi-btn gi-btn-warning" :disabled="_dbOrphansSelected.length === 0 || _acting"
                    @click="purge('db_orphans')">
                Mettre en corbeille (<span x-text="_dbOrphansSelected.length"></span>)
            </button>
        </div>
        <template x-if="_dbOrphans.length === 0">
            <div class="gi-empty">
                <svg viewBox="0 0 24 24"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                <strong>Aucun orphelin BDD</strong>
                Tous les documents actifs ont leur fichier sur le stockage.
            </div>
        </template>
        <template x-if="_dbOrphans.length > 0">
            <table class="gi-table">
                <thead>
                    <tr>
                        <th style="width:32px;"></th>
                        <th>Nom</th>
                        <th>Dossier</th>
                        <th>Chemin disque</th>
                        <th>Taille</th>
                        <th>Créé le</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="doc in _dbOrphans" :key="doc.id">
                        <tr>
                            <td><input type="checkbox" class="gi-checkbox" :value="doc.id" x-model="_dbOrphansSelected"></td>
                            <td x-text="doc.name"></td>
                            <td x-text="doc.folder_name"></td>
                            <td class="mono" x-text="doc.disk_path"></td>
                            <td x-text="formatSize(doc.size_bytes)"></td>
                            <td x-text="doc.created_at"></td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </template>
    </div>

    {{-- ── Tab 2 : Versions orphelines ────────────────────────── --}}
    <div x-show="_tab === 'version_orphans' && _scanned">
        <div class="gi-alert gi-alert-warning">
            <svg viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            Versions archivées dont le fichier physique est introuvable sur le stockage.
            L'action ci-dessous supprime définitivement ces enregistrements de version.
        </div>
        <div class="gi-bar">
            <span class="gi-bar-count" x-text="_versionOrphans.length + ' version(s) concernée(s)'"></span>
            <button class="gi-btn gi-btn-secondary" @click="selectAll('version')" x-show="_versionOrphans.length > 0">Tout sélectionner</button>
            <button class="gi-btn gi-btn-danger" :disabled="_versionOrphansSelected.length === 0 || _acting"
                    @click="purge('version_orphans')">
                Supprimer (<span x-text="_versionOrphansSelected.length"></span>)
            </button>
        </div>
        <template x-if="_versionOrphans.length === 0">
            <div class="gi-empty">
                <svg viewBox="0 0 24 24"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                <strong>Aucune version orpheline</strong>
                Toutes les versions archivées ont leur fichier sur le stockage.
            </div>
        </template>
        <template x-if="_versionOrphans.length > 0">
            <table class="gi-table">
                <thead>
                    <tr>
                        <th style="width:32px;"></th>
                        <th>Document</th>
                        <th>Version</th>
                        <th>Chemin disque</th>
                        <th>Taille</th>
                        <th>Archivé le</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="v in _versionOrphans" :key="v.id">
                        <tr>
                            <td><input type="checkbox" class="gi-checkbox" :value="v.id" x-model="_versionOrphansSelected"></td>
                            <td x-text="v.doc_name"></td>
                            <td x-text="'v' + v.version_number"></td>
                            <td class="mono" x-text="v.disk_path"></td>
                            <td x-text="formatSize(v.size_bytes)"></td>
                            <td x-text="v.created_at"></td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </template>
    </div>

    {{-- ── Tab 3 : Documents en corbeille ─────────────────────── --}}
    <div x-show="_tab === 'soft_docs' && _scanned">
        <div class="gi-alert gi-alert-warning">
            <svg viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            Documents passés en corbeille (soft-deleted). La suppression définitive efface aussi les fichiers physiques et les versions archivées.
        </div>
        <div class="gi-bar">
            <span class="gi-bar-count" x-text="_softDocs.length + ' document(s) en corbeille'"></span>
            <button class="gi-btn gi-btn-secondary" @click="selectAll('softDocs')" x-show="_softDocs.length > 0">Tout sélectionner</button>
            <button class="gi-btn gi-btn-danger" :disabled="_softDocsSelected.length === 0 || _acting"
                    @click="purge('soft_docs')">
                Supprimer définitivement (<span x-text="_softDocsSelected.length"></span>)
            </button>
        </div>
        <template x-if="_softDocs.length === 0">
            <div class="gi-empty">
                <svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>
                <strong>Corbeille vide</strong>
                Aucun document en attente de suppression définitive.
            </div>
        </template>
        <template x-if="_softDocs.length > 0">
            <table class="gi-table">
                <thead>
                    <tr>
                        <th style="width:32px;"></th>
                        <th>Nom</th>
                        <th>Dossier</th>
                        <th>Chemin disque</th>
                        <th>Taille</th>
                        <th>Supprimé le</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="doc in _softDocs" :key="doc.id">
                        <tr>
                            <td><input type="checkbox" class="gi-checkbox" :value="doc.id" x-model="_softDocsSelected"></td>
                            <td x-text="doc.name"></td>
                            <td x-text="doc.folder_name"></td>
                            <td class="mono" x-text="doc.disk_path"></td>
                            <td x-text="formatSize(doc.size_bytes)"></td>
                            <td x-text="doc.deleted_at"></td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </template>
    </div>

    {{-- ── Tab 4 : Dossiers en corbeille ──────────────────────── --}}
    <div x-show="_tab === 'soft_folders' && _scanned">
        <div class="gi-alert gi-alert-warning">
            <svg viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            Dossiers passés en corbeille (soft-deleted). La suppression définitive est irréversible.
        </div>
        <div class="gi-bar">
            <span class="gi-bar-count" x-text="_softFolders.length + ' dossier(s) en corbeille'"></span>
            <button class="gi-btn gi-btn-secondary" @click="selectAll('softFolders')" x-show="_softFolders.length > 0">Tout sélectionner</button>
            <button class="gi-btn gi-btn-danger" :disabled="_softFoldersSelected.length === 0 || _acting"
                    @click="purge('soft_folders')">
                Supprimer définitivement (<span x-text="_softFoldersSelected.length"></span>)
            </button>
        </div>
        <template x-if="_softFolders.length === 0">
            <div class="gi-empty">
                <svg viewBox="0 0 24 24"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                <strong>Corbeille vide</strong>
                Aucun dossier en attente de suppression définitive.
            </div>
        </template>
        <template x-if="_softFolders.length > 0">
            <table class="gi-table">
                <thead>
                    <tr>
                        <th style="width:32px;"></th>
                        <th>Nom</th>
                        <th>Chemin</th>
                        <th>Documents</th>
                        <th>Supprimé le</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="folder in _softFolders" :key="folder.id">
                        <tr>
                            <td><input type="checkbox" class="gi-checkbox" :value="folder.id" x-model="_softFoldersSelected"></td>
                            <td x-text="folder.name"></td>
                            <td class="mono" x-text="folder.path"></td>
                            <td x-text="folder.docs_count"></td>
                            <td x-text="folder.deleted_at"></td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </template>
    </div>

    {{-- ── Tab 5 : Orphelins stockage ──────────────────────────── --}}
    <div x-show="_tab === 'storage_orphans' && _scanned">
        <div class="gi-alert gi-alert-warning">
            <svg viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            Fichiers physiques présents sur le stockage sans enregistrement correspondant en base.
            La suppression est <strong>irréversible</strong>.
        </div>
        <div class="gi-bar">
            <span class="gi-bar-count" x-text="_storageOrphans.length + ' fichier(s) orphelin(s)'"></span>
            <button class="gi-btn gi-btn-secondary" @click="selectAll('storage')" x-show="_storageOrphans.length > 0">Tout sélectionner</button>
            <button class="gi-btn gi-btn-danger" :disabled="_storageOrphansSelected.length === 0 || _acting"
                    @click="purge('storage_orphans')">
                Supprimer du stockage (<span x-text="_storageOrphansSelected.length"></span>)
            </button>
        </div>
        <template x-if="_storageOrphans.length === 0">
            <div class="gi-empty">
                <svg viewBox="0 0 24 24"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                <strong>Aucun orphelin stockage</strong>
                Tous les fichiers physiques ont un enregistrement en base.
            </div>
        </template>
        <template x-if="_storageOrphans.length > 0">
            <table class="gi-table">
                <thead>
                    <tr>
                        <th style="width:32px;"></th>
                        <th>Nom</th>
                        <th>Chemin</th>
                        <th>Taille</th>
                        <th>Modifié le</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="file in _storageOrphans" :key="file.path">
                        <tr>
                            <td><input type="checkbox" class="gi-checkbox" :value="file.path" x-model="_storageOrphansSelected"></td>
                            <td x-text="file.name"></td>
                            <td class="mono" x-text="file.path"></td>
                            <td x-text="formatSize(file.size)"></td>
                            <td x-text="formatMtime(file.mtime)"></td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </template>
    </div>

    {{-- État initial (avant scan) --}}
    <div x-show="!_scanned && !_scanning">
        <div class="gi-empty">
            <svg viewBox="0 0 24 24" style="width:56px;height:56px;"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <strong>Aucune analyse effectuée</strong>
            Cliquez sur « Lancer l'analyse » pour détecter les anomalies.
        </div>
    </div>

</div>

@push('scripts')
<script>
function gedIntegrity() {
    return {
        _scanning: false,
        _acting:   false,
        _scanned:  false,
        _error:    null,
        _actionMsg: null,
        _scannedAt: null,
        _tab: 'db_orphans',

        _dbOrphans:              [],
        _dbOrphansSelected:      [],
        _versionOrphans:         [],
        _versionOrphansSelected: [],
        _softDocs:               [],
        _softDocsSelected:       [],
        _softFolders:            [],
        _softFoldersSelected:    [],
        _storageOrphans:         [],
        _storageOrphansSelected: [],

        get _tabs() {
            return [
                { key: 'db_orphans',       label: 'Orphelins BDD',       count: this._dbOrphans.length },
                { key: 'version_orphans',  label: 'Versions orphelines',  count: this._versionOrphans.length },
                { key: 'soft_docs',        label: 'Docs corbeille',       count: this._softDocs.length },
                { key: 'soft_folders',     label: 'Dossiers corbeille',   count: this._softFolders.length },
                { key: 'storage_orphans',  label: 'Orphelins stockage',   count: this._storageOrphans.length },
            ];
        },

        init() {},

        async scan() {
            this._scanning   = true;
            this._error      = null;
            this._actionMsg  = null;
            this._scanned    = false;
            this._clearSelections();

            try {
                const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
                const resp = await fetch('{{ route('ged.integrity.scan') }}', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                });
                if (!resp.ok) { this._error = 'Erreur lors de l\'analyse (' + resp.status + ').'; return; }
                const data = await resp.json();

                this._dbOrphans       = data.db_orphans       ?? [];
                this._versionOrphans  = data.version_orphans  ?? [];
                this._softDocs        = data.soft_deleted_docs    ?? [];
                this._softFolders     = data.soft_deleted_folders ?? [];
                this._storageOrphans  = data.storage_orphans  ?? [];
                this._scannedAt       = data.scanned_at ?? '';
                this._scanned         = true;

                // Aller sur le premier onglet avec des anomalies, sinon rester sur db_orphans
                const first = this._tabs.find(t => t.count > 0);
                if (first) this._tab = first.key;

            } catch {
                this._error = 'Erreur réseau lors de l\'analyse.';
            } finally {
                this._scanning = false;
            }
        },

        async purge(type) {
            this._actionMsg = null;
            this._error     = null;

            let url, body, confirmMsg;

            if (type === 'db_orphans') {
                if (!this._dbOrphansSelected.length) return;
                url        = '{{ route('ged.integrity.purge-db-orphans') }}';
                body       = { doc_ids: this._dbOrphansSelected.map(Number) };
                confirmMsg = `Mettre en corbeille ${this._dbOrphansSelected.length} document(s) ? Cette action est réversible.`;
            } else if (type === 'version_orphans') {
                if (!this._versionOrphansSelected.length) return;
                url        = '{{ route('ged.integrity.purge-version-orphans') }}';
                body       = { version_ids: this._versionOrphansSelected.map(Number) };
                confirmMsg = `Supprimer définitivement ${this._versionOrphansSelected.length} enregistrement(s) de version ? Action irréversible.`;
            } else if (type === 'soft_docs') {
                if (!this._softDocsSelected.length) return;
                url        = '{{ route('ged.integrity.purge-soft-docs') }}';
                body       = { doc_ids: this._softDocsSelected.map(Number) };
                confirmMsg = `Supprimer définitivement ${this._softDocsSelected.length} document(s) et leurs fichiers ? Action irréversible.`;
            } else if (type === 'soft_folders') {
                if (!this._softFoldersSelected.length) return;
                url        = '{{ route('ged.integrity.purge-soft-folders') }}';
                body       = { folder_ids: this._softFoldersSelected.map(Number) };
                confirmMsg = `Supprimer définitivement ${this._softFoldersSelected.length} dossier(s) ? Action irréversible.`;
            } else if (type === 'storage_orphans') {
                if (!this._storageOrphansSelected.length) return;
                url        = '{{ route('ged.integrity.purge-storage-orphans') }}';
                body       = { paths: this._storageOrphansSelected };
                confirmMsg = `Supprimer physiquement ${this._storageOrphansSelected.length} fichier(s) du stockage ? Action irréversible.`;
            } else {
                return;
            }

            if (!confirm(confirmMsg)) return;

            this._acting = true;
            try {
                const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
                const resp = await fetch(url, {
                    method:  'POST',
                    headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                    body:    JSON.stringify(body),
                });
                const data = await resp.json();
                if (!resp.ok) { this._error = data.message || 'Erreur lors de l\'action.'; return; }
                this._actionMsg = `${data.deleted} élément(s) traité(s) avec succès.`;
                // Re-scan automatique
                await this.scan();
            } catch {
                this._error = 'Erreur réseau.';
            } finally {
                this._acting = false;
            }
        },

        selectAll(group) {
            if (group === 'db')          this._dbOrphansSelected       = this._dbOrphans.map(d => d.id);
            else if (group === 'version') this._versionOrphansSelected  = this._versionOrphans.map(v => v.id);
            else if (group === 'softDocs') this._softDocsSelected       = this._softDocs.map(d => d.id);
            else if (group === 'softFolders') this._softFoldersSelected = this._softFolders.map(f => f.id);
            else if (group === 'storage') this._storageOrphansSelected  = this._storageOrphans.map(f => f.path);
        },

        _clearSelections() {
            this._dbOrphansSelected      = [];
            this._versionOrphansSelected = [];
            this._softDocsSelected       = [];
            this._softFoldersSelected    = [];
            this._storageOrphansSelected = [];
        },

        formatSize(bytes) {
            if (!bytes) return '—';
            if (bytes < 1024) return bytes + ' o';
            if (bytes < 1024 * 1024) return Math.round(bytes / 1024 * 10) / 10 + ' Ko';
            return (Math.round(bytes / 1024 / 1024 * 10) / 10).toString().replace('.', ',') + ' Mo';
        },

        formatMtime(ts) {
            if (!ts) return '—';
            return new Date(ts * 1000).toLocaleDateString('fr-FR', { day:'2-digit', month:'2-digit', year:'numeric' });
        },
    };
}
</script>
@endpush

@endsection
