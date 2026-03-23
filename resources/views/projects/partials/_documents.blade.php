{{-- _documents.blade.php — Documents liés au projet --}}
{{-- Reçoit : $project, $canEdit --}}

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
