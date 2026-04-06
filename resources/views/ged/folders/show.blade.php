@extends('layouts.app')
@section('title', $folder->name.' — GED')

@push('styles')
@include('ged._ged_styles')
@endpush

@section('content')
<div id="ged-wrap">

    {{-- ── Sidebar ─────────────────────────────────────────────────── --}}
    @include('ged._ged_sidebar', [
        'sidebarTree'    => $sidebarTree,
        'activeFolderId' => $folder->id,
        'ancestorIds'    => $ancestorIds,
    ])

    {{-- ── Contenu principal ──────────────────────────────────────── --}}
    <div id="ged-main"
         x-data="gedFolderPage()"
         @dragenter.prevent="onDragEnter($event)"
         @dragover.prevent
         @dragleave.prevent="onDragLeave($event)"
         @drop.prevent="onDrop($event)">

        {{-- Overlay drag & drop ─────────────────────────────────────── --}}
        <div class="ged-drop-overlay" x-show="_isDragOver" x-cloak>
            <div class="ged-drop-center">
                <div class="ged-drop-icon">📤</div>
                <div class="ged-drop-label">Déposez vos fichiers ici</div>
                <div class="ged-drop-hint">PDF, Word, Excel, images…</div>
            </div>
        </div>

        {{-- Input fichier caché ─────────────────────────────────────── --}}
        <input type="file" multiple hidden x-ref="fileInput"
               accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.odt,.ods,.odp,.odg,.jpg,.jpeg,.png,.gif,.webp,.tif,.tiff,.svg,.txt,.csv,.html,.zip"
               @change="onFileInputChange($event)">

        {{-- En-tête + fil d'Ariane ──────────────────────────────────── --}}
        <div id="ged-header">
            <div class="ged-breadcrumb">
                <a href="{{ route('ged.index') }}">GED</a>
                @foreach($ancestors as $ancestor)
                    <span class="ged-breadcrumb-sep">›</span>
                    <a href="{{ route('ged.folders.show', $ancestor) }}">{{ $ancestor->name }}</a>
                @endforeach
                <span class="ged-breadcrumb-sep">›</span>
                <span class="ged-breadcrumb-current">{{ $folder->name }}</span>
                @if($folder->is_private)
                    <span title="Dossier privé" style="margin-left:4px;">🔒</span>
                @endif
            </div>

            <div class="ged-header-right">
                <form method="GET" action="{{ route('ged.search') }}" style="display:flex;gap:0;">
                    <input type="search" name="q" placeholder="Rechercher…"
                           style="width:160px;padding:4px 8px;font-size:12px;border:1px solid var(--pd-border);border-right:none;border-radius:6px 0 0 6px;background:var(--pd-surface);color:var(--pd-text);">
                    <button type="submit"
                            style="padding:4px 10px;font-size:12px;border:1px solid var(--pd-border);border-radius:0 6px 6px 0;background:var(--pd-surface);cursor:pointer;"
                            title="Rechercher">🔍</button>
                </form>
                <button class="pd-btn pd-btn-sm pd-btn-primary" @click="openCreate()">
                    + Nouveau dossier
                </button>
                <button class="pd-btn pd-btn-sm" @click="$refs.fileInput.click()">
                    📤 Uploader
                </button>
                <button class="pd-btn pd-btn-sm" id="btn-ged-sync" onclick="syncGed()" title="Synchroniser le NAS">
                    🔄 Sync NAS
                </button>
                @can('managePermissions', $folder)
                <a href="{{ route('ged.permissions.index', $folder) }}" class="pd-btn pd-btn-sm" title="Gérer les droits d'accès">
                    🔐 Droits
                </a>
                @endcan
            </div>
        </div>

        {{-- File d'upload ───────────────────────────────────────────── --}}
        <div class="ged-upload-queue" x-show="_uploads.length > 0" x-cloak>
            <template x-for="(u, i) in _uploads" :key="i">
                <div class="ged-upload-item">
                    <span x-text="u.status === 'queued' ? '✅' : u.status === 'error' ? '❌' : '⏳'"></span>
                    <span class="ged-upload-name" x-text="u.name"></span>
                    <template x-if="u.status === 'uploading'">
                        <div class="ged-upload-progress-wrap">
                            <div class="ged-upload-bar" :style="'width:' + u.progress + '%'"></div>
                        </div>
                    </template>
                    <span x-show="u.status === 'queued'" class="ged-upload-status">En traitement…</span>
                    <span x-show="u.status === 'error'" class="ged-upload-err" x-text="u.error"></span>
                </div>
            </template>
            <div class="ged-upload-queue-footer" x-show="_hasCompletedUploads">
                <button class="pd-btn pd-btn-sm" @click="clearUploads(); location.reload()">
                    Actualiser la liste
                </button>
            </div>
        </div>

        {{-- Flash ──────────────────────────────────────────────────── --}}
        @if(session('success'))
            <div class="ged-flash ged-flash-success">{{ session('success') }}</div>
        @endif

        @if($errors->any())
            <div class="ged-flash ged-flash-error">{{ $errors->first() }}</div>
        @endif

        <div id="ged-content">

            {{-- Sous-dossiers --}}
            @if($subFolders->isNotEmpty())
                <div class="ged-section-title">Sous-dossiers</div>
                <div class="ged-folder-grid">
                    @foreach($subFolders as $sub)
                        <div class="ged-folder-card"
                             data-folder-id="{{ $sub->id }}"
                             data-folder-name="{{ $sub->name }}">
                            <a href="{{ route('ged.folders.show', $sub) }}" class="ged-folder-card-link">
                                <div class="ged-folder-icon">
                                    📁
                                    @if($sub->is_private)
                                        <span class="ged-private-badge" title="Dossier privé">🔒</span>
                                    @endif
                                </div>
                                <div class="ged-folder-name">{{ $sub->name }}</div>
                                <div class="ged-folder-meta">
                                    {{ $sub->children_count }} dossier{{ $sub->children_count != 1 ? 's' : '' }}
                                    · {{ $sub->documents_count }} doc{{ $sub->documents_count != 1 ? 's' : '' }}
                                </div>
                            </a>
                            <div class="ged-folder-actions">
                                <button class="ged-action-btn" title="Renommer"
                                        @click.prevent="openRename({{ $sub->id }}, '{{ addslashes($sub->name) }}', {{ $sub->is_private ? 'true' : 'false' }})">✏️</button>
                                @if($sub->children_count == 0 && $sub->documents_count == 0)
                                    <button class="ged-action-btn ged-action-delete" title="Supprimer"
                                            @click.prevent="openDelete({{ $sub->id }}, '{{ addslashes($sub->name) }}')">🗑</button>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- Documents du dossier --}}
            <div class="ged-section-title" style="margin-top:{{ $subFolders->isNotEmpty() ? '24px' : '0' }}">
                Documents
            </div>
            <div class="ged-doc-table-wrap">
                <table class="ged-doc-table">
                    <thead>
                        <tr>
                            <th style="width:32px;"></th>
                            <th>Nom</th>
                            <th>Type</th>
                            <th>Taille</th>
                            <th>Version</th>
                            <th>Projets</th>
                            <th>Ajouté par</th>
                            <th>Date</th>
                            <th style="width:96px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($documents as $doc)
                            <tr>
                                <td style="text-align:center;font-size:16px;">{{ $doc->icon() }}</td>
                                <td>
                                    @if($doc->isPreviewable())
                                        <button class="ged-doc-name-btn"
                                                @click="openPreview('{{ route('ged.documents.serve', $doc) }}', '{{ route('ged.documents.download', $doc) }}', '{{ addslashes($doc->name) }}', '{{ addslashes($doc->mime_type ?? '') }}')">
                                            {{ $doc->name }}
                                        </button>
                                    @else
                                        <a href="{{ route('ged.documents.download', $doc) }}" class="ged-doc-name-link">{{ $doc->name }}</a>
                                    @endif
                                </td>
                                <td style="color:var(--pd-muted);font-size:12px;">{{ $doc->mime_type }}</td>
                                <td style="color:var(--pd-muted);font-size:12px;">{{ $doc->humanSize() }}</td>
                                <td>
                                    <button class="ged-version-badge {{ $doc->current_version === 1 ? 'ged-version-badge--v1' : '' }}"
                                            @click="openVersionHistory({{ $doc->id }}, '{{ addslashes($doc->name) }}', {{ $doc->current_version }})">
                                        v{{ $doc->current_version }}
                                    </button>
                                </td>
                                <td style="font-size:12px;">
                                    @php $linkedProjects = $doc->linkedProjects() @endphp
                                    @if($linkedProjects->isEmpty())
                                        <span style="color:var(--pd-muted);">—</span>
                                    @else
                                        <span title="{{ $linkedProjects->pluck('name')->join(', ') }}"
                                              style="display:inline-flex;align-items:center;gap:4px;padding:2px 8px;border-radius:20px;background:var(--pd-surface2);border:0.5px solid var(--pd-border);font-size:11px;font-weight:600;">
                                            🗂 {{ $linkedProjects->count() }}
                                        </span>
                                    @endif
                                </td>
                                <td style="color:var(--pd-muted);font-size:12px;">{{ $doc->creator?->name ?? '—' }}</td>
                                <td style="color:var(--pd-muted);font-size:12px;">{{ $doc->created_at->format('d/m/Y') }}</td>
                                <td>
                                    <div class="ged-doc-actions">
                                        @if($doc->isPreviewable())
                                            <button class="ged-action-btn" title="Prévisualiser"
                                                    @click="openPreview('{{ route('ged.documents.serve', $doc) }}', '{{ route('ged.documents.download', $doc) }}', '{{ addslashes($doc->name) }}', '{{ addslashes($doc->mime_type ?? '') }}')">👁</button>
                                        @endif
                                        @if($doc->isCollaboraSupported())
                                            <a href="{{ route('ged.documents.editor', $doc) }}" class="ged-action-btn" title="Ouvrir dans Collabora" target="_blank">✏️</a>
                                        @endif
                                        <a href="{{ route('ged.documents.download', $doc) }}" class="ged-action-btn" title="Télécharger" download>⬇</a>
                                        <button class="ged-action-btn" title="Renommer"
                                                @click="openDocRename({{ $doc->id }}, '{{ addslashes($doc->name) }}')">✏️</button>
                                        <button class="ged-action-btn" title="Déplacer"
                                                @click="openDocMove({{ $doc->id }}, '{{ addslashes($doc->name) }}')">📂</button>
                                        <button class="ged-action-btn ged-action-delete" title="Supprimer"
                                                @click="openDocDelete({{ $doc->id }}, '{{ addslashes($doc->name) }}')">🗑</button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" style="text-align:center;color:var(--pd-muted);padding:32px;font-style:italic;">
                                    Aucun document — glissez des fichiers ici ou cliquez sur « Uploader ».
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

        </div>{{-- /ged-content --}}

        {{-- ── Modal création sous-dossier ─────────────────────────── --}}
        <div x-show="_modal === 'create'" class="ged-modal-backdrop" @click.self="_modal = null">
            <div class="ged-modal">
                <div class="ged-modal-header">
                    <span>Nouveau sous-dossier</span>
                    <button @click="_modal = null" class="ged-modal-close">✕</button>
                </div>
                <form @submit.prevent="submitCreate()">
                    <div class="ged-modal-body">
                        <label class="pd-label">Nom <span style="color:var(--pd-danger)">*</span></label>
                        <input x-ref="createName" x-model="_createName" type="text" class="pd-input"
                               placeholder="ex: 2024" required maxlength="255">
                        <label style="display:flex;align-items:center;gap:8px;margin-top:12px;font-size:13px;cursor:pointer;">
                            <input type="checkbox" x-model="_createPrivate"> 🔒 Dossier privé
                        </label>
                        <div x-show="_createError" style="color:var(--pd-danger);font-size:12px;margin-top:8px;" x-text="_createError"></div>
                    </div>
                    <div class="ged-modal-footer">
                        <button type="button" class="pd-btn pd-btn-sm" @click="_modal = null">Annuler</button>
                        <button type="submit" class="pd-btn pd-btn-sm pd-btn-primary" :disabled="_loading">
                            <span x-show="!_loading">Créer</span>
                            <span x-show="_loading">…</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- ── Modal renommer ───────────────────────────────────────── --}}
        <div x-show="_modal === 'rename'" class="ged-modal-backdrop" @click.self="_modal = null">
            <div class="ged-modal">
                <div class="ged-modal-header">
                    <span>Renommer le dossier</span>
                    <button @click="_modal = null" class="ged-modal-close">✕</button>
                </div>
                <form @submit.prevent="submitRename()">
                    <div class="ged-modal-body">
                        <label class="pd-label">Nom <span style="color:var(--pd-danger)">*</span></label>
                        <input x-ref="renameName" x-model="_renameName" type="text" class="pd-input" required maxlength="255">
                        <label style="display:flex;align-items:center;gap:8px;margin-top:12px;font-size:13px;cursor:pointer;">
                            <input type="checkbox" x-model="_renamePrivate"> 🔒 Dossier privé
                        </label>
                        <div x-show="_renameError" style="color:var(--pd-danger);font-size:12px;margin-top:8px;" x-text="_renameError"></div>
                    </div>
                    <div class="ged-modal-footer">
                        <button type="button" class="pd-btn pd-btn-sm" @click="_modal = null">Annuler</button>
                        <button type="submit" class="pd-btn pd-btn-sm pd-btn-primary" :disabled="_loading">
                            <span x-show="!_loading">Enregistrer</span>
                            <span x-show="_loading">…</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- ── Modal confirmation suppression dossier ─────────────── --}}
        <div x-show="_modal === 'delete'" class="ged-modal-backdrop" @click.self="_modal = null">
            <div class="ged-modal">
                <div class="ged-modal-header">
                    <span>Supprimer le dossier</span>
                    <button @click="_modal = null" class="ged-modal-close">✕</button>
                </div>
                <div class="ged-modal-body">
                    <p style="font-size:13px;">Supprimer le dossier <strong x-text="_deleteName"></strong> ?</p>
                    <p style="font-size:12px;color:var(--pd-muted);">Cette action est irréversible.</p>
                    <div x-show="_deleteError" style="color:var(--pd-danger);font-size:12px;margin-top:8px;" x-text="_deleteError"></div>
                </div>
                <div class="ged-modal-footer">
                    <button type="button" class="pd-btn pd-btn-sm" @click="_modal = null">Annuler</button>
                    <button type="button" class="pd-btn pd-btn-sm pd-btn-danger" @click="submitDelete()" :disabled="_loading">
                        <span x-show="!_loading">Supprimer</span>
                        <span x-show="_loading">…</span>
                    </button>
                </div>
            </div>
        </div>

        {{-- ── Modal suppression document ───────────────────────────── --}}
        <div x-show="_modal === 'doc-delete'" class="ged-modal-backdrop" @click.self="_modal = null">
            <div class="ged-modal">
                <div class="ged-modal-header">
                    <span>Supprimer le document</span>
                    <button @click="_modal = null" class="ged-modal-close">✕</button>
                </div>
                <div class="ged-modal-body">
                    <p style="font-size:13px;">Supprimer <strong x-text="_docDeleteName"></strong> ?</p>
                    <p style="font-size:12px;color:var(--pd-muted);">Cette action est irréversible.</p>
                    <div x-show="_docDeleteError" style="color:var(--pd-danger);font-size:12px;margin-top:8px;" x-text="_docDeleteError"></div>
                </div>
                <div class="ged-modal-footer">
                    <button type="button" class="pd-btn pd-btn-sm" @click="_modal = null">Annuler</button>
                    <button type="button" class="pd-btn pd-btn-sm pd-btn-danger" @click="submitDocDelete()" :disabled="_docDeleteLoading">
                        <span x-show="!_docDeleteLoading">Supprimer</span>
                        <span x-show="_docDeleteLoading">…</span>
                    </button>
                </div>
            </div>
        </div>

        {{-- ── Modal historique des versions ──────────────────────── --}}
        <div x-show="_modal === 'versions'" class="ged-modal-backdrop" @click.self="_modal = null">
            <div class="ged-modal" style="max-width:640px;">
                <div class="ged-modal-header">
                    <span>Versions — <span x-text="_versionsDocName" style="font-style:italic;"></span></span>
                    <button @click="_modal = null" class="ged-modal-close">✕</button>
                </div>
                <div class="ged-modal-body" style="padding:0;">
                    <div x-show="_versionsLoading" style="padding:24px;text-align:center;color:var(--pd-muted);">
                        Chargement…
                    </div>
                    <table class="ged-doc-table" x-show="!_versionsLoading">
                        <thead>
                            <tr>
                                <th>Version</th>
                                <th>Taille</th>
                                <th>Par</th>
                                <th>Date</th>
                                <th style="width:64px;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            {{-- Ligne version courante (toujours présente) --}}
                            <tr style="background:var(--pd-surface2);">
                                <td style="font-size:12px;">
                                    <span style="font-weight:700;" x-text="'v' + _versionsCurrentVersion"></span>
                                    <span style="font-size:10px;color:#059669;margin-left:4px;">● courante</span>
                                </td>
                                <td style="font-size:12px;color:var(--pd-muted);" x-text="formatSize(_versionsCurrentSize)"></td>
                                <td style="font-size:12px;color:var(--pd-muted);" x-text="_versionsCurrentUploader"></td>
                                <td style="font-size:12px;color:var(--pd-muted);" x-text="_versionsCurrentDate"></td>
                                <td>
                                    <div class="ged-doc-actions" style="opacity:1;">
                                        <a :href="_versionsCurrentDownloadUrl" class="ged-action-btn" title="Télécharger" download>⬇</a>
                                    </div>
                                </td>
                            </tr>
                            {{-- Versions archivées --}}
                            <template x-for="v in _versionsList" :key="v.version_number">
                                <tr>
                                    <td style="font-size:12px;color:var(--pd-muted);" x-text="'v' + v.version_number"></td>
                                    <td style="font-size:12px;color:var(--pd-muted);" x-text="formatSize(v.size_bytes)"></td>
                                    <td style="font-size:12px;color:var(--pd-muted);" x-text="v.uploaded_by_name"></td>
                                    <td style="font-size:12px;color:var(--pd-muted);" x-text="v.created_at"></td>
                                    <td>
                                        <div class="ged-doc-actions">
                                            <a :href="v.download_url" class="ged-action-btn" title="Télécharger" download>⬇</a>
                                            <button class="ged-action-btn" title="Restaurer cette version"
                                                    @click="restoreVersion(_versionsDocId, v.version_number)">↩</button>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                            {{-- Message si aucune version archivée --}}
                            <tr x-show="!_versionsLoading && _versionsList.length === 0">
                                <td colspan="5" style="padding:16px 12px;font-size:12px;color:var(--pd-muted);font-style:italic;">
                                    Aucune version archivée — ceci est la version originale du document.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="ged-modal-footer">
                    <button type="button" class="pd-btn pd-btn-sm" @click="_modal = null">Fermer</button>
                </div>
            </div>
        </div>

        {{-- ── Modal renommer document ─────────────────────────────── --}}
        <div x-show="_modal === 'doc-rename'" class="ged-modal-backdrop" @click.self="_modal = null">
            <div class="ged-modal">
                <div class="ged-modal-header">
                    <span>Renommer le document</span>
                    <button @click="_modal = null" class="ged-modal-close">✕</button>
                </div>
                <form @submit.prevent="submitDocRename()">
                    <div class="ged-modal-body">
                        <label class="pd-label">Nom <span style="color:var(--pd-danger)">*</span></label>
                        <input x-ref="docRenameName" x-model="_docRenameName" type="text" class="pd-input" required maxlength="255">
                        <div x-show="_docRenameError" style="color:var(--pd-danger);font-size:12px;margin-top:8px;" x-text="_docRenameError"></div>
                    </div>
                    <div class="ged-modal-footer">
                        <button type="button" class="pd-btn pd-btn-sm" @click="_modal = null">Annuler</button>
                        <button type="submit" class="pd-btn pd-btn-sm pd-btn-primary" :disabled="_loading">
                            <span x-show="!_loading">Enregistrer</span>
                            <span x-show="_loading">…</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- ── Modal déplacer document ──────────────────────────────── --}}
        <div x-show="_modal === 'doc-move'" class="ged-modal-backdrop" @click.self="_modal = null">
            <div class="ged-modal">
                <div class="ged-modal-header">
                    <span>Déplacer <span x-text="_docMoveName" style="font-style:italic;font-weight:600;"></span></span>
                    <button @click="_modal = null" class="ged-modal-close">✕</button>
                </div>
                <div class="ged-modal-body">
                    <label class="pd-label">Dossier de destination</label>
                    <input type="search" x-model="_docMoveFilter"
                           placeholder="Filtrer les dossiers…"
                           class="pd-input"
                           style="margin-bottom:8px;">
                    <div x-show="_docMoveFoldersLoading" style="color:var(--pd-muted);font-size:12px;padding:8px 0;">Chargement…</div>
                    <div x-show="!_docMoveFoldersLoading"
                         style="max-height:260px;overflow-y:auto;border:1px solid var(--pd-border);border-radius:6px;">
                        <template x-for="f in _filteredMoveFolders" :key="f.id">
                            <div @click="_docMoveTargetId = f.id; _docMoveTargetName = f.name"
                                 :style="_docMoveTargetId === f.id ? 'background:rgba(30,58,95,0.08);font-weight:600;' : ''"
                                 style="padding:8px 12px;cursor:pointer;font-size:13px;border-bottom:0.5px solid var(--pd-border);"
                                 onmouseover="if(this.style.fontWeight !== '600') this.style.background='rgba(30,58,95,0.04)'"
                                 onmouseout="if(this.style.fontWeight !== '600') this.style.background='transparent'">
                                <span style="color:var(--pd-muted);font-size:11px;margin-right:6px;" x-text="f.path"></span>
                                <span x-text="f.name"></span>
                            </div>
                        </template>
                        <div x-show="_filteredMoveFolders.length === 0 && !_docMoveFoldersLoading"
                             style="padding:16px;text-align:center;color:var(--pd-muted);font-size:12px;font-style:italic;">
                            Aucun dossier trouvé.
                        </div>
                    </div>
                    <div x-show="_docMoveTargetId" style="margin-top:8px;font-size:12px;color:var(--pd-muted);">
                        Déplacer vers : <strong x-text="_docMoveTargetName"></strong>
                    </div>
                    <div x-show="_docMoveError" style="color:var(--pd-danger);font-size:12px;margin-top:8px;" x-text="_docMoveError"></div>
                </div>
                <div class="ged-modal-footer">
                    <button type="button" class="pd-btn pd-btn-sm" @click="_modal = null">Annuler</button>
                    <button type="button" class="pd-btn pd-btn-sm pd-btn-primary"
                            :disabled="_loading || !_docMoveTargetId"
                            @click="submitDocMove()">
                        <span x-show="!_loading">Déplacer</span>
                        <span x-show="_loading">…</span>
                    </button>
                </div>
            </div>
        </div>

        {{-- ── Modal prévisualisation inline ───────────────────────── --}}
        <div x-show="_modal === 'preview'" class="ged-modal-backdrop" @click.self="_modal = null">
            <div class="ged-preview-modal">
                <div class="ged-modal-header">
                    <span x-text="_previewName" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;flex:1;"></span>
                    <div style="display:flex;gap:6px;align-items:center;flex-shrink:0;">
                        <a :href="_previewDownloadUrl" class="pd-btn pd-btn-sm" download>⬇ Télécharger</a>
                        <button @click="_modal = null" class="ged-modal-close">✕</button>
                    </div>
                </div>
                <div class="ged-preview-body">
                    <template x-if="_modal === 'preview' && _previewMime.startsWith('image/')">
                        <img :src="_previewUrl" class="ged-preview-img" :alt="_previewName">
                    </template>
                    <template x-if="_modal === 'preview' && !_previewMime.startsWith('image/')">
                        <iframe :src="_previewUrl" class="ged-preview-frame" :title="_previewName"></iframe>
                    </template>
                </div>
            </div>
        </div>

    </div>{{-- /ged-main --}}

</div>{{-- /ged-wrap --}}
@endsection

@push('scripts')
@include('ged._ged_scripts', ['parentId' => $folder->id])
@endpush
