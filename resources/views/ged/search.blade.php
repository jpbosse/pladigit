@extends('layouts.app')
@section('title', 'Recherche GED')

@push('styles')
@include('ged._ged_styles')
@endpush

@section('content')
<div id="ged-wrap">

    {{-- ── Sidebar ─────────────────────────────────────────────────── --}}
    @include('ged._ged_sidebar', [
        'sidebarTree'    => $sidebarTree,
        'activeFolderId' => null,
        'ancestorIds'    => [],
    ])

    {{-- ── Contenu principal ──────────────────────────────────────── --}}
    <div id="ged-main">

        {{-- En-tête --}}
        <div id="ged-header">
            <div class="ged-breadcrumb">
                <a href="{{ route('ged.index') }}">GED</a>
                <span class="ged-breadcrumb-sep">›</span>
                <span class="ged-breadcrumb-current">Recherche</span>
            </div>
        </div>

        {{-- Barre de recherche --}}
        <div style="padding:20px 24px 0;">
            <form method="GET" action="{{ route('ged.search') }}" style="display:flex;gap:8px;max-width:560px;">
                <input type="search" name="q" value="{{ $q }}"
                       placeholder="Nom de document, de dossier…"
                       class="pd-input"
                       style="flex:1;"
                       autofocus>
                <button type="submit" class="pd-btn pd-btn-primary">Rechercher</button>
                @if($q !== '')
                    <a href="{{ route('ged.search') }}" class="pd-btn">Effacer</a>
                @endif
            </form>
        </div>

        <div id="ged-content" style="padding-top:16px;">

            @if($q === '')
                <div class="ged-empty">
                    <div style="font-size:40px;margin-bottom:12px;">🔍</div>
                    <div style="font-size:14px;color:var(--pd-muted);">Saisissez un terme pour rechercher dans vos documents et dossiers.</div>
                </div>

            @elseif($documents->isEmpty() && $folders->isEmpty())
                <div class="ged-empty">
                    <div style="font-size:40px;margin-bottom:12px;">🔍</div>
                    <div style="font-size:14px;color:var(--pd-muted);">Aucun résultat pour « {{ $q }} ».</div>
                </div>

            @else
                <div style="font-size:13px;color:var(--pd-muted);margin-bottom:16px;padding:0 4px;">
                    {{ $documents->count() + $folders->count() }} résultat(s) pour « <strong>{{ $q }}</strong> »
                </div>

                {{-- Dossiers --}}
                @if($folders->isNotEmpty())
                    <div class="ged-section-title">Dossiers ({{ $folders->count() }})</div>
                    <div class="ged-folder-grid" style="margin-bottom:24px;">
                        @foreach($folders as $folder)
                            <div class="ged-folder-card">
                                <a href="{{ route('ged.folders.show', $folder) }}" class="ged-folder-card-link">
                                    <div class="ged-folder-icon">
                                        📁
                                        @if($folder->is_private)
                                            <span class="ged-private-badge" title="Dossier privé">🔒</span>
                                        @endif
                                    </div>
                                    <div class="ged-folder-name">{{ $folder->name }}</div>
                                    <div class="ged-folder-meta">
                                        {{ $folder->path }}
                                    </div>
                                </a>
                            </div>
                        @endforeach
                    </div>
                @endif

                {{-- Documents --}}
                @if($documents->isNotEmpty())
                    <div class="ged-section-title">Documents ({{ $documents->count() }})</div>
                    <div class="ged-doc-table-wrap">
                        <table class="ged-doc-table">
                            <thead>
                                <tr>
                                    <th style="width:32px;"></th>
                                    <th>Nom</th>
                                    <th>Dossier</th>
                                    <th>Taille</th>
                                    <th>Version</th>
                                    <th>Ajouté par</th>
                                    <th>Date</th>
                                    <th style="width:64px;"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($documents as $doc)
                                    <tr>
                                        <td style="text-align:center;font-size:16px;">{{ $doc->icon() }}</td>
                                        <td>
                                            @if($doc->isPreviewable())
                                                <a href="{{ route('ged.documents.serve', $doc) }}" target="_blank" class="ged-doc-name-link">{{ $doc->name }}</a>
                                            @else
                                                <a href="{{ route('ged.documents.download', $doc) }}" class="ged-doc-name-link">{{ $doc->name }}</a>
                                            @endif
                                        </td>
                                        <td style="color:var(--pd-muted);font-size:12px;">
                                            @if($doc->folder)
                                                <a href="{{ route('ged.folders.show', $doc->folder) }}"
                                                   style="color:var(--pd-muted);text-decoration:none;"
                                                   onmouseover="this.style.color='var(--pd-navy)'"
                                                   onmouseout="this.style.color='var(--pd-muted)'">
                                                    📁 {{ $doc->folder->name }}
                                                </a>
                                            @else
                                                <span style="color:var(--pd-muted);">—</span>
                                            @endif
                                        </td>
                                        <td style="color:var(--pd-muted);font-size:12px;">{{ $doc->humanSize() }}</td>
                                        <td>
                                            <span class="ged-version-badge {{ $doc->current_version === 1 ? 'ged-version-badge--v1' : '' }}">
                                                v{{ $doc->current_version }}
                                            </span>
                                        </td>
                                        <td style="color:var(--pd-muted);font-size:12px;">{{ $doc->creator?->name ?? '—' }}</td>
                                        <td style="color:var(--pd-muted);font-size:12px;">{{ $doc->created_at->format('d/m/Y') }}</td>
                                        <td>
                                            <a href="{{ route('ged.documents.download', $doc) }}" class="ged-action-btn" title="Télécharger" download>⬇</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            @endif

        </div>{{-- /ged-content --}}
    </div>{{-- /ged-main --}}
</div>{{-- /ged-wrap --}}
@endsection
