@extends('layouts.app')
@section('title', 'GED — Gestion de documents')

@push('styles')
@include('ged._ged_styles')
@endpush

@section('content')
<div id="ged-wrap">

    {{-- ── Sidebar ─────────────────────────────────────────────────── --}}
    @include('ged._ged_sidebar', [
        'sidebarTree'   => $sidebarTree,
        'activeFolderId' => null,
        'ancestorIds'   => [],
    ])

    {{-- ── Contenu principal ──────────────────────────────────────── --}}
    <div id="ged-main" x-data="gedFolderPage()">

        {{-- En-tête --}}
        <div id="ged-header">
            <div class="ged-breadcrumb">
                <span>GED</span>
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

        {{-- Grille des dossiers racine --}}
        <div id="ged-content">

            @if($folders->isEmpty())
                <div class="ged-empty">
                    <div style="font-size:48px;margin-bottom:12px;">📁</div>
                    <div style="font-size:14px;color:var(--pd-muted);">Aucun dossier — créez-en un pour commencer.</div>
                </div>
            @else
                <div class="ged-folder-grid">
                    @foreach($folders as $folder)
                        <div class="ged-folder-card"
                             data-folder-id="{{ $folder->id }}"
                             data-folder-name="{{ $folder->name }}">
                            <a href="{{ route('ged.folders.show', $folder) }}" class="ged-folder-card-link">
                                <div class="ged-folder-icon">
                                    📁
                                    @if($folder->is_private)
                                        <span class="ged-private-badge" title="Dossier privé">🔒</span>
                                    @endif
                                </div>
                                <div class="ged-folder-name">{{ $folder->name }}</div>
                                <div class="ged-folder-meta">
                                    {{ $folder->children_count }} dossier{{ $folder->children_count != 1 ? 's' : '' }}
                                    · {{ $folder->documents_count }} doc{{ $folder->documents_count != 1 ? 's' : '' }}
                                </div>
                            </a>
                            <div class="ged-folder-actions">
                                <button class="ged-action-btn" title="Renommer"
                                        @click.prevent="openRename({{ $folder->id }}, '{{ addslashes($folder->name) }}', {{ $folder->is_private ? 'true' : 'false' }})">✏️</button>
                                @if($folder->children_count == 0 && $folder->documents_count == 0)
                                    <button class="ged-action-btn ged-action-delete" title="Supprimer"
                                            @click.prevent="openDelete({{ $folder->id }}, '{{ addslashes($folder->name) }}')">🗑</button>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- Section documents racine (vide pour l'instant — Jalon 2) --}}
            <div class="ged-section-title" style="margin-top:24px;">Documents à la racine</div>
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
                        <tr>
                            <td colspan="7" style="text-align:center;color:var(--pd-muted);padding:20px;font-style:italic;">
                                Aucun document — l'upload sera disponible au Jalon 2.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

        </div>{{-- /ged-content --}}

        {{-- ── Modal création dossier ──────────────────────────────── --}}
        <div x-show="_modal === 'create'" class="ged-modal-backdrop" @click.self="_modal = null">
            <div class="ged-modal">
                <div class="ged-modal-header">
                    <span>Nouveau dossier</span>
                    <button @click="_modal = null" class="ged-modal-close">✕</button>
                </div>
                <form @submit.prevent="submitCreate()">
                    <div class="ged-modal-body">
                        <label class="pd-label">Nom <span style="color:var(--pd-danger)">*</span></label>
                        <input x-ref="createName" x-model="_createName" type="text" class="pd-input" placeholder="ex: Ressources Humaines" required maxlength="255">
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
@include('ged._ged_scripts', ['parentId' => null])
@endpush
