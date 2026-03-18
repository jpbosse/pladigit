{{-- resources/views/projects/edit.blade.php --}}
@extends('layouts.app')
@section('title', 'Modifier — ' . $project->name)

@push('styles')

    {{-- Trix rich text editor --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/trix@2.0.8/dist/trix.css">
    <script src="https://cdn.jsdelivr.net/npm/trix@2.0.8/dist/trix.umd.min.js"></script>
    <style>
    trix-editor {
        min-height: 120px;
        border: 1px solid var(--pd-border);
        border-radius: 0 0 8px 8px;
        padding: 10px 12px;
        font-size: 13px;
        font-family: 'DM Sans', system-ui, sans-serif;
        color: var(--pd-text);
        background: var(--pd-surface);
        outline: none;
    }
    trix-editor:focus {
        border-color: var(--pd-navy);
        box-shadow: 0 0 0 3px color-mix(in srgb, var(--pd-navy) 15%, transparent);
    }
    trix-toolbar .trix-button-group {
        border: 0.5px solid var(--pd-border);
        border-radius: 8px 8px 0 0;
        background: var(--pd-surface2);
    }
    trix-toolbar .trix-button {
        border: none;
        color: var(--pd-muted);
    }
    trix-toolbar .trix-button.trix-active { color: var(--pd-navy); background: var(--pd-bg2); }
    trix-toolbar .trix-button--icon-attach { display: none; }
    </style>
<style>
.edit-wrap  { max-width:620px; }
.edit-card  { background:var(--pd-surface); border:1px solid var(--pd-border); border-radius:12px; padding:24px; margin-bottom:24px; }
.edit-card-title { font-size:14px; font-weight:700; color:var(--pd-text); margin-bottom:18px; display:flex; align-items:center; gap:8px; }
.edit-card-title .dot { width:8px; height:8px; border-radius:50%; background:var(--pd-navy); flex-shrink:0; }
.member-row { display:flex; align-items:center; gap:12px; padding:10px 0; border-bottom:1px solid var(--pd-border); }
.member-row:last-child { border:none; }
.member-av  { width:34px; height:34px; border-radius:50%; background:var(--pd-bg2); color:var(--pd-navy); font-size:12px; font-weight:700; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.danger-card { background:#FFF5F5; border:1px solid #FCA5A5; border-radius:12px; padding:20px 24px; }
</style>
@endpush

@section('content')
<div style="padding:20px;">

<div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:24px;">
    <div>
        <div style="display:flex;align-items:center;gap:10px;">
            <div style="width:12px;height:12px;border-radius:50%;background:{{ $project->color }};flex-shrink:0;"></div>
            <h1 style="font-size:20px;font-weight:700;margin:0;">Modifier le projet</h1>
        </div>
        <p style="font-size:13px;color:var(--pd-muted);margin:4px 0 0 22px;">{{ $project->name }}</p>
    </div>
    <a href="{{ route('projects.show', $project) }}" class="pd-btn pd-btn-secondary pd-btn-sm">
        ← Retour
    </a>
</div>

<div class="edit-wrap">

    {{-- ── Informations générales ── --}}
    <div class="edit-card">
        <div class="edit-card-title">
            <span class="dot"></span> Informations générales
        </div>
        <form method="POST" action="{{ route('projects.update', $project) }}">
            @csrf @method('PUT')

            <div class="pd-form-group">
                <label class="pd-label pd-label-req">Nom du projet</label>
                <input type="text" name="name" class="pd-input @error('name') is-invalid @enderror"
                       required value="{{ old('name', $project->name) }}"
                       placeholder="Nom du projet">
                @error('name')<div class="pd-error">{{ $message }}</div>@enderror
            </div>

            <div class="pd-form-group">
                <label class="pd-label">Description</label>
                <input id="description-input" type="hidden" name="description" value="{{ old('description', $project->description) }}">
                <trix-editor input="description-input" placeholder="Objectif, contexte, points importants…"></trix-editor>
            </div>

            <div class="pd-form-row pd-form-row-2">
                <div class="pd-form-group" style="margin-bottom:0;">
                    <label class="pd-label pd-label-req">Statut</label>
                    <select name="status" class="pd-input">
                        @foreach(\App\Models\Tenant\Project::statusLabels() as $value => $label)
                        <option value="{{ $value }}" {{ old('status',$project->status)===$value?'selected':'' }}>
                            {{ $label }}
                        </option>
                        @endforeach
                    </select>
                </div>
                <div class="pd-form-group" style="margin-bottom:0;">
                    <label class="pd-label">Couleur d'identification</label>
                    <input type="color" name="color" class="pd-input"
                           value="{{ old('color', $project->color) }}">
                </div>
            </div>

            <div class="pd-form-row pd-form-row-2" style="margin-bottom:0;">
                <div class="pd-form-group" style="margin-bottom:0;">
                    <label class="pd-label">Date de début</label>
                    <input type="date" name="start_date" class="pd-input"
                           value="{{ old('start_date', $project->start_date?->format('Y-m-d')) }}">
                    @error('start_date')<div class="pd-error">{{ $message }}</div>@enderror
                </div>
                <div class="pd-form-group" style="margin-bottom:0;">
                    <label class="pd-label">Date de fin prévue</label>
                    <input type="date" name="due_date" class="pd-input @error('due_date') is-invalid @enderror"
                           value="{{ old('due_date', $project->due_date?->format('Y-m-d')) }}">
                    @error('due_date')<div class="pd-error">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="pd-form-actions">
                <a href="{{ route('projects.show', $project) }}" class="pd-btn pd-btn-secondary pd-btn-sm">Annuler</a>
                <button type="submit" class="pd-btn pd-btn-primary pd-btn-sm">Enregistrer</button>
            </div>
        </form>
    </div>

    {{-- ── Membres ── --}}
    @can('manageMembers', $project)
    <div class="edit-card">
        <div class="edit-card-title">
            <span class="dot" style="background:#7C3AED;"></span> Membres du projet
        </div>

        @foreach($project->projectMembers()->with('user')->orderByRaw("FIELD(role,'owner','member','viewer')")->get() as $pm)
        <div class="member-row">
            <div class="member-av">{{ strtoupper(substr($pm->user->name??'?',0,2)) }}</div>
            <div style="flex:1;">
                <div style="font-size:13px;font-weight:500;">{{ $pm->user->name ?? '—' }}</div>
                <div style="font-size:11px;color:var(--pd-muted);">{{ \App\Enums\ProjectRole::from($pm->role)->label() }}</div>
            </div>
            @if($pm->role !== 'owner')
            <form method="POST" action="{{ route('projects.members.destroy',[$project,$pm->user_id]) }}"
                  onsubmit="return confirm('Retirer {{ $pm->user->name }} ?')">
                @csrf @method('DELETE')
                <button type="submit" class="pd-btn pd-btn-xs pd-btn-danger">Retirer</button>
            </form>
            @else
            <span style="font-size:11px;color:var(--pd-muted);padding:4px 8px;">Propriétaire</span>
            @endif
        </div>
        @endforeach

        <form method="POST" action="{{ route('projects.members.store', $project) }}"
              style="margin-top:16px;padding-top:16px;border-top:1px solid var(--pd-border);">
            @csrf
            <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--pd-muted);margin-bottom:12px;">
                Ajouter un membre
            </div>
            <div class="pd-form-row pd-form-row-3" style="margin-bottom:0;">
                <div class="pd-form-group" style="margin-bottom:0;grid-column:span 1;">
                    <label class="pd-label">Utilisateur</label>
                    <select name="user_id" class="pd-input" required>
                        <option value="">Choisir…</option>
                        @foreach(\App\Models\Tenant\User::on('tenant')->where('status','active')->orderBy('name')->get() as $u)
                        <option value="{{ $u->id }}">{{ $u->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="pd-form-group" style="margin-bottom:0;">
                    <label class="pd-label">Rôle</label>
                    <select name="role" class="pd-input" required>
                        @foreach(\App\Enums\ProjectRole::options() as $v => $l)
                        <option value="{{ $v }}">{{ $l }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="pd-form-group" style="margin-bottom:0;display:flex;align-items:flex-end;">
                    <button type="submit" class="pd-btn pd-btn-primary pd-btn-sm" style="width:100%;">Ajouter</button>
                </div>
            </div>
        </form>
    </div>
    @endcan

    {{-- ── Zone de danger ── --}}
    @can('delete', $project)
    <div class="danger-card">
        <div style="font-size:14px;font-weight:700;color:#991B1B;margin-bottom:6px;">Zone de danger</div>
        <p style="font-size:12px;color:#7F1D1D;margin-bottom:16px;line-height:1.5;">
            La suppression du projet est irréversible. Toutes les tâches, jalons, budgets et données associées seront supprimés définitivement.
        </p>
        <form method="POST" action="{{ route('projects.destroy', $project) }}"
              onsubmit="return confirm('Supprimer définitivement « {{ $project->name }} » ?\n\nCette action est irréversible.')">
            @csrf @method('DELETE')
            <button type="submit" class="pd-btn pd-btn-danger pd-btn-sm">
                Supprimer ce projet
            </button>
        </form>
    </div>
    @endcan

</div>
</div>
@endsection
