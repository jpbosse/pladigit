{{-- _documents.blade.php — Documents liés au projet --}}
{{-- Reçoit : $project, $canEdit --}}

{{-- ══════════════════════════════════════════════════════════════════════ --}}
{{-- Section : Documents GED liés                                          --}}
{{-- ══════════════════════════════════════════════════════════════════════ --}}
<div x-data="gedLinksManager({{ $project->id }})" x-init="load()" style="margin-bottom:28px;">

    <div class="section-hdr">
        <div>
            <div class="section-title">Documents GED liés</div>
            <div class="section-sub" x-text="gedLinks.length + ' lien' + (gedLinks.length > 1 ? 's' : '')"></div>
        </div>
        @if($canEdit)
        <button @click="showPicker=true;loadPicker(0)"
                class="pd-btn pd-btn-sm pd-btn-primary">
            🗂 Lier un document GED
        </button>
        @endif
    </div>

    {{-- Liste des liens --}}
    <div x-show="gedLoading" style="padding:20px;text-align:center;color:var(--pd-muted);font-size:13px;">Chargement…</div>
    <div x-show="!gedLoading && gedLinks.length === 0" style="padding:20px;text-align:center;color:var(--pd-muted);font-size:13px;font-style:italic;">
        Aucun document GED lié à ce projet.
    </div>
    <template x-if="!gedLoading && gedLinks.length > 0">
    <div>
        <template x-for="lnk in gedLinks" :key="lnk.id">
        <div style="display:flex;align-items:center;gap:10px;padding:10px 14px;background:var(--pd-surface);border:0.5px solid var(--pd-border);border-radius:8px;margin-bottom:6px;">
            <span x-text="lnk.document_icon" style="font-size:18px;flex-shrink:0;"></span>
            <div style="flex:1;min-width:0;">
                <div style="font-size:13px;font-weight:500;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" x-text="lnk.document_name"></div>
                <div style="font-size:11px;color:var(--pd-muted);">
                    <template x-if="lnk.folder_name">
                        <span>📁 <span x-text="lnk.folder_name"></span> · </span>
                    </template>
                    <span x-text="lnk.document_size"></span>
                    · lié par <span x-text="lnk.linked_by"></span>
                    · <span x-text="lnk.linked_at"></span>
                </div>
            </div>
            <template x-if="lnk.serve_url">
                <a :href="lnk.serve_url" target="_blank" rel="noopener"
                   style="font-size:11px;padding:4px 10px;border-radius:6px;border:0.5px solid var(--pd-border);background:var(--pd-surface2);color:var(--pd-text);text-decoration:none;flex-shrink:0;">
                    👁 Voir
                </a>
            </template>
            <a :href="lnk.download_url" download
               style="font-size:11px;padding:4px 10px;border-radius:6px;border:0.5px solid var(--pd-border);background:var(--pd-surface2);color:var(--pd-text);text-decoration:none;flex-shrink:0;">
                ⬇ Télécharger
            </a>
            @if($canEdit)
            <button @click="unlinkGed(lnk.id)"
                    style="font-size:11px;padding:4px 8px;border-radius:6px;border:0.5px solid #FCA5A5;background:#FEF2F2;color:#991B1B;cursor:pointer;flex-shrink:0;">
                🗑
            </button>
            @endif
        </div>
        </template>
    </div>
    </template>

    {{-- Picker GED --}}
    @if($canEdit)
    <div x-show="showPicker" x-cloak
         style="position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:900;display:flex;align-items:center;justify-content:center;"
         @click.self="showPicker=false">
        <div style="background:var(--pd-surface);border-radius:14px;width:560px;max-width:95vw;max-height:80vh;display:flex;flex-direction:column;overflow:hidden;box-shadow:0 8px 40px rgba(0,0,0,.22);">

            {{-- En-tête picker --}}
            <div style="padding:16px 20px;border-bottom:0.5px solid var(--pd-border);display:flex;align-items:center;justify-content:space-between;">
                <div>
                    <div style="font-size:14px;font-weight:700;">Choisir un document GED</div>
                    <div x-show="pickerPath.length > 0" style="font-size:11px;color:var(--pd-muted);margin-top:2px;">
                        <span @click="loadPicker(0)" style="cursor:pointer;color:var(--pd-accent);">Racine</span>
                        <template x-for="(seg, i) in pickerPath" :key="i">
                            <span>
                                <span style="margin:0 4px;">›</span>
                                <span @click="loadPicker(seg.id)" style="cursor:pointer;color:var(--pd-accent);" x-text="seg.name"></span>
                            </span>
                        </template>
                    </div>
                </div>
                <button @click="showPicker=false"
                        style="font-size:18px;background:none;border:none;cursor:pointer;color:var(--pd-muted);">✕</button>
            </div>

            {{-- Corps picker --}}
            <div style="flex:1;overflow-y:auto;padding:12px 16px;">

                {{-- Retour --}}
                <template x-if="pickerFolderId !== null">
                    <div @click="pickerBack()"
                         style="display:flex;align-items:center;gap:8px;padding:8px 10px;border-radius:8px;cursor:pointer;margin-bottom:4px;color:var(--pd-muted);font-size:13px;"
                         @mouseenter="$el.style.background='var(--pd-surface2)'" @mouseleave="$el.style.background=''">
                        ← Retour
                    </div>
                </template>

                {{-- Dossiers --}}
                <template x-for="f in pickerFolders" :key="f.id">
                    <div @click="loadPicker(f.id)"
                         style="display:flex;align-items:center;gap:10px;padding:8px 10px;border-radius:8px;cursor:pointer;margin-bottom:2px;"
                         @mouseenter="$el.style.background='var(--pd-surface2)'" @mouseleave="$el.style.background=''">
                        <span style="font-size:16px;">📁</span>
                        <div style="flex:1;min-width:0;">
                            <div style="font-size:13px;font-weight:500;" x-text="f.name"></div>
                            <div style="font-size:11px;color:var(--pd-muted);" x-text="f.children_count + ' dossiers · ' + f.documents_count + ' docs'"></div>
                        </div>
                        <span style="color:var(--pd-muted);font-size:12px;">›</span>
                    </div>
                </template>

                {{-- Documents --}}
                <template x-if="pickerDocs.length > 0">
                    <div>
                        <div style="font-size:10px;font-weight:700;color:var(--pd-muted);text-transform:uppercase;letter-spacing:.06em;margin:10px 0 6px;">Documents</div>
                        <template x-for="d in pickerDocs" :key="d.id">
                            <div @click="selectGedDoc(d)"
                                 style="display:flex;align-items:center;gap:10px;padding:8px 10px;border-radius:8px;cursor:pointer;margin-bottom:2px;"
                                 @mouseenter="$el.style.background='var(--pd-surface2)'" @mouseleave="$el.style.background=''">
                                <span x-text="d.icon" style="font-size:16px;"></span>
                                <div style="flex:1;min-width:0;">
                                    <div style="font-size:13px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" x-text="d.name"></div>
                                    <div style="font-size:11px;color:var(--pd-muted);" x-text="d.size"></div>
                                </div>
                            </div>
                        </template>
                    </div>
                </template>

                <div x-show="pickerFolders.length === 0 && pickerDocs.length === 0"
                     style="padding:32px;text-align:center;color:var(--pd-muted);font-size:13px;font-style:italic;">
                    Dossier vide.
                </div>
            </div>

            {{-- Pied --}}
            <div x-show="pickerError" x-text="pickerError"
                 style="padding:8px 20px;font-size:12px;color:var(--pd-danger);background:#FEF2F2;"></div>
        </div>
    </div>
    @endif

</div>
{{-- ══ fin section GED ══════════════════════════════════════════════════ --}}

<div x-data="docsManager({{ $project->id }})" x-init="load()">

{{-- ── En-tête ── --}}
<div class="section-hdr">
    <div>
        <div class="section-title">Documents</div>
        <div class="section-sub" x-text="docs.length + ' document' + (docs.length > 1 ? 's' : '')"></div>
    </div>
    @if($canEdit)
    <div style="display:flex;gap:8px;">
        <button @click="showUpload=!showUpload;showLink=false"
                class="pd-btn pd-btn-sm pd-btn-primary">
            📎 Joindre un fichier
        </button>
        <button @click="showLink=!showLink;showUpload=false"
                class="pd-btn pd-btn-sm pd-btn-secondary">
            🔗 Ajouter un lien
        </button>
    </div>
    @endif
</div>

{{-- ── Formulaire upload fichier ── --}}
@if($canEdit)
<div x-show="showUpload" x-cloak
     style="background:var(--pd-surface2);border:0.5px solid var(--pd-border);border-radius:10px;padding:16px;margin-bottom:16px;">
    <div style="font-size:13px;font-weight:600;margin-bottom:12px;">Joindre un fichier</div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px;">
        <div>
            <label class="pd-label">Fichier <span style="color:var(--pd-danger);">*</span></label>
            <input type="file" x-ref="fileInput"
                   accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.odt,.ods,.odp,.txt,.csv,.zip,.png,.jpg,.jpeg"
                   class="pd-input" style="padding:6px;">
            <div style="font-size:10px;color:var(--pd-muted);margin-top:3px;">
                PDF, Word, Excel, PowerPoint, ODF, images, ZIP — max 50 Mo
            </div>
        </div>
        <div>
            <label class="pd-label">Nom affiché</label>
            <input type="text" x-model="uploadForm.name" class="pd-input"
                   placeholder="Laisser vide = nom du fichier">
        </div>
    </div>
    <div style="margin-bottom:10px;">
        <label class="pd-label">Description (optionnel)</label>
        <input type="text" x-model="uploadForm.description" class="pd-input"
               placeholder="Compte-rendu réunion du 15/03, Cahier des charges v2...">
    </div>
    <div style="display:flex;gap:8px;align-items:center;">
        <button @click="uploadFile()" :disabled="uploading"
                class="pd-btn pd-btn-sm pd-btn-primary"
                :style="uploading ? 'opacity:.6;cursor:not-allowed;' : ''">
            <span x-show="!uploading">Joindre</span>
            <span x-show="uploading">Envoi…</span>
        </button>
        <button @click="showUpload=false" class="pd-btn pd-btn-sm pd-btn-secondary">Annuler</button>
        <span x-show="uploadError" x-text="uploadError" style="font-size:11px;color:var(--pd-danger);"></span>
    </div>
</div>

{{-- ── Formulaire lien URL ── --}}
<div x-show="showLink" x-cloak
     style="background:var(--pd-surface2);border:0.5px solid var(--pd-border);border-radius:10px;padding:16px;margin-bottom:16px;">
    <div style="font-size:13px;font-weight:600;margin-bottom:12px;">Ajouter un lien</div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px;">
        <div>
            <label class="pd-label">Nom <span style="color:var(--pd-danger);">*</span></label>
            <input type="text" x-model="linkForm.name" class="pd-input"
                   placeholder="Dossier Drive, Arrêté municipal…">
        </div>
        <div>
            <label class="pd-label">URL <span style="color:var(--pd-danger);">*</span></label>
            <input type="url" x-model="linkForm.path" class="pd-input"
                   placeholder="https://…">
        </div>
    </div>
    <div style="margin-bottom:10px;">
        <label class="pd-label">Description (optionnel)</label>
        <input type="text" x-model="linkForm.description" class="pd-input" placeholder="Contexte du lien…">
    </div>
    <div style="display:flex;gap:8px;align-items:center;">
        <button @click="saveLink()" class="pd-btn pd-btn-sm pd-btn-primary">Enregistrer</button>
        <button @click="showLink=false" class="pd-btn pd-btn-sm pd-btn-secondary">Annuler</button>
        <span x-show="linkError" x-text="linkError" style="font-size:11px;color:var(--pd-danger);"></span>
    </div>
</div>
@endif

{{-- ── Liste des documents ── --}}
<div x-show="loading" style="padding:32px;text-align:center;color:var(--pd-muted);font-size:13px;">
    Chargement…
</div>

<div x-show="!loading && docs.length === 0" style="padding:32px;text-align:center;color:var(--pd-muted);font-size:13px;">
    Aucun document joint à ce projet.
</div>

<template x-if="!loading && docs.length > 0">
<div>
    {{-- Fichiers --}}
    <template x-if="files.length > 0">
    <div style="margin-bottom:20px;">
        <div style="font-size:10px;font-weight:700;color:var(--pd-muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:8px;">
            Fichiers (<span x-text="files.length"></span>)
        </div>
        <template x-for="doc in files" :key="doc.id">
        <div style="display:flex;align-items:center;gap:10px;padding:10px 14px;background:var(--pd-surface);border:0.5px solid var(--pd-border);border-radius:8px;margin-bottom:6px;">
            <span x-text="doc.icon" style="font-size:18px;flex-shrink:0;"></span>
            <div style="flex:1;min-width:0;">
                <div style="font-size:13px;font-weight:500;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" x-text="doc.name"></div>
                <div style="font-size:11px;color:var(--pd-muted);">
                    <span x-text="doc.size"></span>
                    · <span x-text="doc.uploader"></span>
                    · <span x-text="doc.created_at"></span>
                    <template x-if="doc.description">
                        · <span x-text="doc.description" style="font-style:italic;"></span>
                    </template>
                </div>
            </div>
            <a :href="doc.download_url"
               style="font-size:11px;padding:4px 10px;border-radius:6px;border:0.5px solid var(--pd-border);background:var(--pd-surface2);color:var(--pd-text);text-decoration:none;flex-shrink:0;"
               download>
                ⬇ Télécharger
            </a>
            @if($canEdit)
            <button @click="deleteDoc(doc.id)"
                    style="font-size:11px;padding:4px 8px;border-radius:6px;border:0.5px solid #FCA5A5;background:#FEF2F2;color:#991B1B;cursor:pointer;flex-shrink:0;">
                🗑
            </button>
            @endif
        </div>
        </template>
    </div>
    </template>

    {{-- Liens --}}
    <template x-if="links.length > 0">
    <div>
        <div style="font-size:10px;font-weight:700;color:var(--pd-muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:8px;">
            Liens (<span x-text="links.length"></span>)
        </div>
        <template x-for="doc in links" :key="doc.id">
        <div style="display:flex;align-items:center;gap:10px;padding:10px 14px;background:var(--pd-surface);border:0.5px solid var(--pd-border);border-radius:8px;margin-bottom:6px;">
            <span style="font-size:18px;flex-shrink:0;">🔗</span>
            <div style="flex:1;min-width:0;">
                <a :href="doc.download_url" target="_blank" rel="noopener"
                   style="font-size:13px;font-weight:500;color:var(--pd-accent);text-decoration:none;"
                   x-text="doc.name"></a>
                <div style="font-size:11px;color:var(--pd-muted);">
                    <span x-text="doc.uploader"></span>
                    · <span x-text="doc.created_at"></span>
                    <template x-if="doc.description">
                        · <span x-text="doc.description" style="font-style:italic;"></span>
                    </template>
                </div>
            </div>
            @if($canEdit)
            <button @click="deleteDoc(doc.id)"
                    style="font-size:11px;padding:4px 8px;border-radius:6px;border:0.5px solid #FCA5A5;background:#FEF2F2;color:#991B1B;cursor:pointer;flex-shrink:0;">
                🗑
            </button>
            @endif
        </div>
        </template>
    </div>
    </template>
</div>
</template>

</div>

<script>
function gedLinksManager(projectId) {
    return {
        projectId,
        gedLinks: [],
        gedLoading: true,
        showPicker: false,
        pickerFolderId: null,
        pickerPath: [],   // [{id, name}, ...]
        pickerFolders: [],
        pickerDocs: [],
        pickerError: '',

        csrfToken() {
            return document.querySelector('meta[name="csrf-token"]').content;
        },

        async load() {
            this.gedLoading = true;
            try {
                const r = await fetch(`/projects/${this.projectId}/ged-links`, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                const data = await r.json();
                this.gedLinks = data.links ?? [];
            } catch (e) { console.error(e); }
            this.gedLoading = false;
        },

        async loadPicker(folderId) {
            this.pickerError = '';
            const url = `/projects/${this.projectId}/ged-links/picker` + (folderId ? `?folder_id=${folderId}` : '');
            try {
                const r = await fetch(url, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                const data = await r.json();
                this.pickerFolderId = data.folder_id ?? null;
                this.pickerFolders = data.folders ?? [];
                this.pickerDocs    = data.documents ?? [];

                // Mise à jour du fil d'Ariane
                if (folderId) {
                    const existing = this.pickerPath.findIndex(s => s.id === folderId);
                    if (existing >= 0) {
                        this.pickerPath = this.pickerPath.slice(0, existing + 1);
                    } else {
                        const folder = this.pickerFolders.find(f => f.id === folderId)
                            ?? this.pickerDocs.find(d => d.id === folderId);
                        const name = folder?.name ?? `Dossier ${folderId}`;
                        this.pickerPath.push({ id: folderId, name });
                    }
                } else {
                    this.pickerPath = [];
                }
            } catch (e) {
                this.pickerError = 'Impossible de charger le dossier GED.';
            }
        },

        pickerBack() {
            const prev = this.pickerPath[this.pickerPath.length - 2];
            this.pickerPath = this.pickerPath.slice(0, -1);
            this.loadPicker(prev ? prev.id : 0);
        },

        async selectGedDoc(doc) {
            this.pickerError = '';
            try {
                const body = new URLSearchParams({
                    _token: this.csrfToken(),
                    ged_document_id: doc.id,
                });
                const r = await fetch(`/projects/${this.projectId}/ged-links`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/json' },
                    body: body.toString(),
                });
                const data = await r.json();
                if (data.success) {
                    this.gedLinks.unshift(data.link);
                    this.showPicker = false;
                } else {
                    this.pickerError = data.message ?? 'Erreur lors de la liaison.';
                }
            } catch (e) {
                this.pickerError = 'Erreur réseau.';
            }
        },

        async unlinkGed(linkId) {
            if (!confirm('Supprimer ce lien GED ?')) return;
            await fetch(`/projects/${this.projectId}/ged-links/${linkId}`, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': this.csrfToken(), 'Accept': 'application/json' },
            });
            this.gedLinks = this.gedLinks.filter(l => l.id !== linkId);
        },
    };
}

function docsManager(projectId, milestoneId = null, taskId = null) {
    return {
        projectId,
        milestoneId,
        taskId,
        docs: [],
        loading: true,
        showUpload: false,
        showLink: false,
        uploading: false,
        uploadError: '',
        linkError: '',
        uploadForm: { name: '', description: '' },
        linkForm: { name: '', path: '', description: '' },

        get files() { return this.docs.filter(d => d.type === 'file'); },
        get links() { return this.docs.filter(d => d.type === 'link'); },

        csrfToken() {
            return document.querySelector('meta[name="csrf-token"]').content;
        },

        buildParams() {
            const p = new URLSearchParams();
            if (this.milestoneId) p.set('milestone_id', this.milestoneId);
            if (this.taskId) p.set('task_id', this.taskId);
            return p.toString() ? '?' + p.toString() : '';
        },

        async load() {
            this.loading = true;
            try {
                const r = await fetch(`/projects/${this.projectId}/documents${this.buildParams()}`, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                const data = await r.json();
                this.docs = data.documents ?? [];
            } catch (e) { console.error(e); }
            this.loading = false;
        },

        async uploadFile() {
            this.uploadError = '';
            const fileEl = this.$refs.fileInput;
            if (!fileEl?.files?.length) { this.uploadError = 'Sélectionnez un fichier.'; return; }
            this.uploading = true;
            const fd = new FormData();
            fd.append('file', fileEl.files[0]);
            fd.append('name', this.uploadForm.name);
            fd.append('description', this.uploadForm.description);
            if (this.milestoneId) fd.append('milestone_id', this.milestoneId);
            if (this.taskId) fd.append('task_id', this.taskId);
            fd.append('_token', this.csrfToken());
            try {
                const r = await fetch(`/projects/${this.projectId}/documents/upload`, { method: 'POST', body: fd });
                const data = await r.json();
                if (data.success) {
                    this.docs.unshift(data.document);
                    this.showUpload = false;
                    this.uploadForm = { name: '', description: '' };
                    fileEl.value = '';
                } else {
                    this.uploadError = data.message ?? 'Erreur lors de l\'envoi.';
                }
            } catch (e) { this.uploadError = 'Erreur réseau.'; }
            this.uploading = false;
        },

        async saveLink() {
            this.linkError = '';
            if (!this.linkForm.name) { this.linkError = 'Nom requis.'; return; }
            if (!this.linkForm.path) { this.linkError = 'URL requise.'; return; }
            const body = new URLSearchParams({
                _token: this.csrfToken(),
                name: this.linkForm.name,
                path: this.linkForm.path,
                description: this.linkForm.description,
            });
            if (this.milestoneId) body.set('milestone_id', this.milestoneId);
            if (this.taskId) body.set('task_id', this.taskId);
            try {
                const r = await fetch(`/projects/${this.projectId}/documents/link`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: body.toString(),
                });
                const data = await r.json();
                if (data.success) {
                    this.docs.unshift(data.document);
                    this.showLink = false;
                    this.linkForm = { name: '', path: '', description: '' };
                } else {
                    this.linkError = data.message ?? 'Erreur.';
                }
            } catch (e) { this.linkError = 'Erreur réseau.'; }
        },

        async deleteDoc(id) {
            if (!confirm('Supprimer ce document ?')) return;
            const r = await fetch(`/projects/${this.projectId}/documents/${id}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': this.csrfToken(),
                    'Accept': 'application/json',
                },
            });
            const data = await r.json();
            if (data.success) this.docs = this.docs.filter(d => d.id !== id);
        },
    };
}
</script>
