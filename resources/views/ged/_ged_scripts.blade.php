{{--
    JS Alpine pour la page GED (index + show).
    Variables attendues :
      $parentId  — int|null (ID du dossier courant, null = racine)
      $folder    — GedFolder|null (dossier courant, null sur l'index)
--}}
@php $parentId ??= null; @endphp

<script>
function gedFolderPage() {
    return {
        // ── State général ─────────────────────────────────────
        _modal: null,
        _loading: false,

        // ── Création dossier ──────────────────────────────────
        _createName: '',
        _createPrivate: false,
        _createError: '',

        // ── Renommer dossier ──────────────────────────────────
        _renameId: null,
        _renameName: '',
        _renamePrivate: false,
        _renameError: '',

        // ── Supprimer dossier ─────────────────────────────────
        _deleteId: null,
        _deleteName: '',
        _deleteError: '',
        _deleteDocCount: 0,
        _deleteChildCount: 0,
        _deleteForce: false,
        _deleteNeedsConfirm: false,

        // ── Upload documents ──────────────────────────────────
        _folderId: {{ $parentId ?? 'null' }},
        _uploads: [],
        _isDragOver: false,
        _dragCounter: 0,

        // ── Prévisualisation ──────────────────────────────────
        _previewName: '',
        _previewUrl: '',
        _previewDownloadUrl: '',
        _previewMime: '',

        // ── Suppression document ──────────────────────────────
        _docDeleteId: null,
        _docDeleteName: '',
        _docDeleteError: '',
        _docDeleteLoading: false,

        // ── Renommage document ────────────────────────────────
        _docRenameId: null,
        _docRenameName: '',
        _docRenameError: '',

        // ── Déplacement document ──────────────────────────────
        _docMoveId: null,
        _docMoveName: '',
        _docMoveTargetId: null,
        _docMoveTargetName: '',
        _docMoveFilter: '',
        _docMoveFolders: [],
        _docMoveFoldersLoading: false,
        _docMoveError: '',

        // ── Historique des versions ───────────────────────────
        _versionsDocId: null,
        _versionsDocName: '',
        _versionsCurrentVersion: 1,
        _versionsCurrentSize: 0,
        _versionsCurrentUploader: '—',
        _versionsCurrentDate: '—',
        _versionsCurrentDownloadUrl: '',
        _versionsList: [],
        _versionsLoading: false,

        // =====================================================================
        // Modales dossier (création / renommer / supprimer)
        // =====================================================================

        openCreate() {
            this._createName = '';
            this._createPrivate = false;
            this._createError = '';
            this._modal = 'create';
            this.$nextTick(() => this.$refs.createName?.focus());
        },

        openRename(id, name, isPrivate) {
            this._renameId = id;
            this._renameName = name;
            this._renamePrivate = isPrivate;
            this._renameError = '';
            this._modal = 'rename';
            this.$nextTick(() => this.$refs.renameName?.focus());
        },

        openDelete(id, name, docCount, childCount) {
            this._deleteId = id;
            this._deleteName = name;
            this._deleteError = '';
            this._deleteDocCount = docCount ?? 0;
            this._deleteChildCount = childCount ?? 0;
            this._deleteForce = false;
            this._deleteNeedsConfirm = (this._deleteDocCount > 0 || this._deleteChildCount > 0);
            this._modal = 'delete';
        },

        async submitCreate() {
            if (!this._createName.trim()) return;
            this._loading = true;
            this._createError = '';
            try {
                const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
                const body = {
                    name: this._createName.trim(),
                    is_private: this._createPrivate ? 1 : 0,
                };
                @if($parentId)
                body.parent_id = {{ $parentId }};
                @endif
                const resp = await fetch('{{ route('ged.folders.store') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify(body),
                });
                const data = await resp.json();
                if (!resp.ok) {
                    this._createError = data.message || data.errors?.name?.[0] || 'Erreur';
                    return;
                }
                location.reload();
            } catch {
                this._createError = 'Erreur réseau.';
            } finally {
                this._loading = false;
            }
        },

        async submitRename() {
            if (!this._renameName.trim()) return;
            this._loading = true;
            this._renameError = '';
            try {
                const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
                const resp = await fetch(`{{ url('ged/folders') }}/${this._renameId}`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        name: this._renameName.trim(),
                        is_private: this._renamePrivate ? 1 : 0,
                    }),
                });
                const data = await resp.json();
                if (!resp.ok) {
                    this._renameError = data.message || data.errors?.name?.[0] || 'Erreur';
                    return;
                }
                location.reload();
            } catch {
                this._renameError = 'Erreur réseau.';
            } finally {
                this._loading = false;
            }
        },

        async submitDelete() {
            if (this._deleteNeedsConfirm && !this._deleteForce) {
                this._deleteError = 'Cochez la case pour confirmer la suppression définitive.';
                return;
            }
            this._loading = true;
            this._deleteError = '';
            try {
                const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
                const body = this._deleteNeedsConfirm ? JSON.stringify({ force: true }) : null;
                const resp = await fetch(`{{ url('ged/folders') }}/${this._deleteId}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': csrf,
                        'Accept': 'application/json',
                        ...(body ? { 'Content-Type': 'application/json' } : {}),
                    },
                    body,
                });
                const data = await resp.json();
                if (!resp.ok) {
                    this._deleteError = data.error || 'Erreur';
                    return;
                }
                location.reload();
            } catch {
                this._deleteError = 'Erreur réseau.';
            } finally {
                this._loading = false;
            }
        },

        // =====================================================================
        // Upload documents — Drag & drop + file picker
        // =====================================================================

        onDragEnter(e) {
            if (!e.dataTransfer?.types?.includes('Files')) return;
            this._dragCounter++;
            this._isDragOver = true;
        },

        onDragLeave() {
            this._dragCounter--;
            if (this._dragCounter <= 0) {
                this._dragCounter = 0;
                this._isDragOver = false;
            }
        },

        onDrop(e) {
            this._isDragOver = false;
            this._dragCounter = 0;
            const files = Array.from(e.dataTransfer?.files ?? []);
            if (files.length) this.uploadFiles(files);
        },

        onFileInputChange(e) {
            const files = Array.from(e.target.files ?? []);
            if (files.length) {
                this.uploadFiles(files);
                e.target.value = '';
            }
        },

        uploadFiles(files) {
            if (!this._folderId) return;
            for (const file of files) {
                this._uploads.push({ name: file.name, progress: 0, status: 'uploading', error: null });
                this._doUpload(file, this._uploads.length - 1);
            }
        },

        _doUpload(file, idx) {
            const formData = new FormData();
            formData.append('folder_id', this._folderId);
            formData.append('files[]', file);
            const self = this;

            const xhr = new XMLHttpRequest();

            xhr.upload.onprogress = (e) => {
                if (e.lengthComputable) {
                    self._uploads.splice(idx, 1, {
                        ...self._uploads[idx],
                        progress: Math.round(e.loaded / e.total * 100),
                    });
                }
            };

            xhr.onload = () => {
                if (xhr.status === 200) {
                    self._uploads.splice(idx, 1, { ...self._uploads[idx], status: 'done', progress: 100 });
                    // Recharge la liste des documents dès que tous les uploads sont terminés
                    if (self._uploads.every(u => u.status === 'done' || u.status === 'error')) {
                        setTimeout(() => location.reload(), 400);
                    }
                } else {
                    let error = `Erreur ${xhr.status}`;
                    try { error = JSON.parse(xhr.responseText)?.message || error; } catch { /* noop */ }
                    self._uploads.splice(idx, 1, { ...self._uploads[idx], status: 'error', error });
                }
            };

            xhr.onerror = () => {
                self._uploads.splice(idx, 1, { ...self._uploads[idx], status: 'error', error: 'Erreur réseau' });
            };

            xhr.open('POST', '{{ route('ged.documents.store') }}');
            xhr.setRequestHeader('X-CSRF-TOKEN', document.querySelector('meta[name="csrf-token"]')?.content ?? '');
            xhr.setRequestHeader('Accept', 'application/json');
            xhr.send(formData);
        },

        clearUploads() {
            this._uploads = [];
        },

        get _hasCompletedUploads() {
            return this._uploads.some(u => u.status === 'queued' || u.status === 'done');
        },

        // =====================================================================
        // Prévisualisation inline
        // =====================================================================

        openPreview(serveUrl, downloadUrl, name, mime) {
            this._previewUrl = serveUrl;
            this._previewDownloadUrl = downloadUrl;
            this._previewName = name;
            this._previewMime = mime;
            this._modal = 'preview';
        },

        // =====================================================================
        // Suppression document
        // =====================================================================

        openDocDelete(id, name) {
            this._docDeleteId = id;
            this._docDeleteName = name;
            this._docDeleteError = '';
            this._modal = 'doc-delete';
        },

        // =====================================================================
        // Historique des versions
        // =====================================================================

        async openVersionHistory(docId, docName, currentVersion) {
            this._versionsDocId = docId;
            this._versionsDocName = docName;
            this._versionsCurrentVersion = currentVersion;
            this._versionsCurrentSize = 0;
            this._versionsCurrentUploader = '—';
            this._versionsCurrentDate = '—';
            this._versionsCurrentDownloadUrl = `{{ url('ged/documents') }}/${docId}/download`;
            this._versionsList = [];
            this._versionsLoading = true;
            this._modal = 'versions';
            try {
                const resp = await fetch(`{{ url('ged/documents') }}/${docId}/versions`, {
                    headers: { 'Accept': 'application/json' },
                });
                if (resp.ok) {
                    const data = await resp.json();
                    this._versionsList = data.versions ?? [];
                    if (data.current) {
                        this._versionsCurrentSize = data.current.size_bytes;
                        this._versionsCurrentUploader = data.current.uploaded_by_name;
                        this._versionsCurrentDate = data.current.created_at;
                    }
                }
            } catch { /* noop */ } finally {
                this._versionsLoading = false;
            }
        },

        async restoreVersion(docId, versionNumber) {
            if (!confirm(`Restaurer la version ${versionNumber} ? La version courante sera archivée.`)) return;
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
            try {
                const resp = await fetch(`{{ url('ged/documents') }}/${docId}/versions/${versionNumber}/restore`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                });
                if (resp.ok) {
                    location.reload();
                } else {
                    alert('Erreur lors de la restauration.');
                }
            } catch {
                alert('Erreur réseau.');
            }
        },

        formatSize(bytes) {
            if (bytes < 1024) return bytes + ' o';
            if (bytes < 1024 * 1024) return Math.round(bytes / 1024 * 10) / 10 + ' Ko';
            return (Math.round(bytes / 1024 / 1024 * 10) / 10).toString().replace('.', ',') + ' Mo';
        },

        // =====================================================================
        // Renommage document
        // =====================================================================

        openDocRename(id, name) {
            this._docRenameId = id;
            this._docRenameName = name;
            this._docRenameError = '';
            this._modal = 'doc-rename';
            this.$nextTick(() => this.$refs.docRenameName?.focus());
        },

        async submitDocRename() {
            if (!this._docRenameName.trim()) return;
            this._loading = true;
            this._docRenameError = '';
            try {
                const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
                const resp = await fetch(`{{ url('ged/documents') }}/${this._docRenameId}`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ name: this._docRenameName.trim() }),
                });
                const data = await resp.json();
                if (!resp.ok) {
                    this._docRenameError = data.message || data.errors?.name?.[0] || 'Erreur';
                    return;
                }
                location.reload();
            } catch {
                this._docRenameError = 'Erreur réseau.';
            } finally {
                this._loading = false;
            }
        },

        // =====================================================================
        // Déplacement document
        // =====================================================================

        get _filteredMoveFolders() {
            const q = this._docMoveFilter.toLowerCase().trim();
            if (!q) return this._docMoveFolders;
            return this._docMoveFolders.filter(f =>
                f.name.toLowerCase().includes(q) || (f.path ?? '').toLowerCase().includes(q)
            );
        },

        async openDocMove(id, name) {
            this._docMoveId = id;
            this._docMoveName = name;
            this._docMoveTargetId = null;
            this._docMoveTargetName = '';
            this._docMoveFilter = '';
            this._docMoveError = '';
            this._modal = 'doc-move';

            if (this._docMoveFolders.length === 0) {
                this._docMoveFoldersLoading = true;
                try {
                    const resp = await fetch('{{ route('ged.folders.all') }}', {
                        headers: { 'Accept': 'application/json' },
                    });
                    if (resp.ok) {
                        const data = await resp.json();
                        this._docMoveFolders = data.folders ?? [];
                    }
                } catch { /* noop */ } finally {
                    this._docMoveFoldersLoading = false;
                }
            }
        },

        async submitDocMove() {
            if (!this._docMoveTargetId) return;
            this._loading = true;
            this._docMoveError = '';
            try {
                const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
                const resp = await fetch(`{{ url('ged/documents') }}/${this._docMoveId}/move`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ target_folder_id: this._docMoveTargetId }),
                });
                const data = await resp.json();
                if (!resp.ok) {
                    this._docMoveError = data.message || 'Erreur';
                    return;
                }
                location.reload();
            } catch {
                this._docMoveError = 'Erreur réseau.';
            } finally {
                this._loading = false;
            }
        },

        // =====================================================================
        // Suppression document
        // =====================================================================

        async submitDocDelete() {
            this._docDeleteLoading = true;
            this._docDeleteError = '';
            try {
                const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
                const resp = await fetch(`{{ url('ged/documents') }}/${this._docDeleteId}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': csrf,
                        'Accept': 'application/json',
                    },
                });
                const data = await resp.json();
                if (!resp.ok) {
                    this._docDeleteError = data.message || 'Erreur';
                    return;
                }
                location.reload();
            } catch {
                this._docDeleteError = 'Erreur réseau.';
            } finally {
                this._docDeleteLoading = false;
            }
        },
    };
}

async function syncGed() {
    const btn = document.getElementById('btn-ged-sync');
    if (!btn) return;
    btn.disabled = true;
    btn.textContent = '⏳ Synchro…';

    const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    try {
        const resp = await fetch('{{ route('admin.settings.ged.sync') }}', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
        });
        const data = await resp.json();
        if (resp.ok) {
            btn.textContent = '✅ Sync lancée';
            setTimeout(() => location.reload(), 5000);
        } else {
            btn.textContent = '❌ Erreur';
            btn.disabled = false;
        }
    } catch {
        btn.textContent = '❌ Erreur réseau';
        btn.disabled = false;
    }
}
</script>
