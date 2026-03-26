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
    <div id="ged-main" x-data="gedFolderPage()">

        {{-- En-tête + fil d'Ariane --}}
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
                <button class="pd-btn pd-btn-sm pd-btn-primary" @click="openCreate()">
                    + Nouveau dossier
                </button>
                <button class="pd-btn pd-btn-sm" disabled title="Upload disponible au Jalon 2">
                    📤 Uploader
                </button>
            </div>
        </div>

        {{-- Flash --}}
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
                            <th>Ajouté par</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($documents as $doc)
                            <tr>
                                <td style="text-align:center;font-size:16px;">{{ $doc->icon() }}</td>
                                <td>{{ $doc->name }}</td>
                                <td style="color:var(--pd-muted);font-size:12px;">{{ $doc->mime_type }}</td>
                                <td style="color:var(--pd-muted);font-size:12px;">{{ $doc->humanSize() }}</td>
                                <td style="color:var(--pd-muted);font-size:12px;">v{{ $doc->current_version }}</td>
                                <td style="color:var(--pd-muted);font-size:12px;">{{ $doc->creator?->name ?? '—' }}</td>
                                <td style="color:var(--pd-muted);font-size:12px;">{{ $doc->created_at->format('d/m/Y') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" style="text-align:center;color:var(--pd-muted);padding:20px;font-style:italic;">
                                    Aucun document — l'upload sera disponible au Jalon 2.
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

        {{-- ── Modal confirmation suppression ─────────────────────── --}}
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

    </div>{{-- /ged-main --}}

</div>{{-- /ged-wrap --}}
@endsection

@push('scripts')
@include('ged._ged_scripts', ['parentId' => $folder->id])
@endpush
