{{-- resources/views/projects/create.blade.php --}}
@extends('layouts.app')
@section('title', 'Nouveau projet')

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
.create-wrap { max-width:620px; }
.create-card { background:var(--pd-surface); border:1px solid var(--pd-border); border-radius:12px; padding:24px; margin-bottom:20px; }
.create-card-title { font-size:14px; font-weight:700; color:var(--pd-text); margin-bottom:18px; display:flex; align-items:center; gap:8px; }
.create-dot { width:8px; height:8px; border-radius:50%; background:var(--pd-navy); flex-shrink:0; }
</style>
@endpush

@section('content')
<div style="padding:20px;">

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;">
    <div>
        <h1 style="font-size:20px;font-weight:700;margin:0;">Nouveau projet</h1>
        <p style="font-size:13px;color:var(--pd-muted);margin:4px 0 0;">
            Renseignez les informations de base — vous pourrez affiner après création.
        </p>
    </div>
    <a href="{{ route('projects.index') }}" class="pd-btn pd-btn-secondary pd-btn-sm">← Retour</a>
</div>

<form method="POST" action="{{ route('projects.store') }}" class="create-wrap">
    @csrf

    <div class="create-card">
        <div class="create-card-title"><span class="create-dot"></span> Informations générales</div>

        <div class="pd-form-group">
            <label class="pd-label pd-label-req">Nom du projet</label>
            <input type="text" name="name" class="pd-input @error('name') is-invalid @enderror"
                   required autofocus value="{{ old('name') }}"
                   placeholder="Ex : Refonte site internet, Déploiement ERP…">
            @error('name')<div class="pd-error">{{ $message }}</div>@enderror
        </div>

        <div class="pd-form-group">
            <label class="pd-label">Description</label>
            <input id="description-input" type="hidden" name="description" value="{{ old('description') }}">
                    <trix-editor input="description-input" placeholder="Objectif, contexte, points importants…"></trix-editor>
        </div>

        <div class="pd-form-row pd-form-row-2" style="margin-bottom:0;">
            <div class="pd-form-group" style="margin-bottom:0;">
                <label class="pd-label pd-label-req">Statut initial</label>
                <select name="status" class="pd-input">
                    @foreach(\App\Models\Tenant\Project::statusLabels() as $value => $label)
                    @if($value !== 'archived')
                    <option value="{{ $value }}" {{ old('status','active')===$value?'selected':'' }}>
                        {{ $label }}
                    </option>
                    @endif
                    @endforeach
                </select>
            </div>
            <div class="pd-form-group" style="margin-bottom:0;">
                <label class="pd-label">Couleur</label>
                <input type="color" name="color" class="pd-input"
                       value="{{ old('color', '#1E3A5F') }}">
                <div class="pd-hint">Identifiant visuel dans les listes et kanban</div>
            </div>
        </div>
    </div>

    <div class="create-card">
        <div class="create-card-title"><span class="create-dot" style="background:#EA580C;"></span> Planning</div>

        <div class="pd-form-row pd-form-row-2" style="margin-bottom:0;">
            <div class="pd-form-group" style="margin-bottom:0;">
                <label class="pd-label">Date de début</label>
                <input type="date" name="start_date" class="pd-input"
                       value="{{ old('start_date') }}">
                @error('start_date')<div class="pd-error">{{ $message }}</div>@enderror
            </div>
            <div class="pd-form-group" style="margin-bottom:0;">
                <label class="pd-label">Date de fin prévue</label>
                <input type="date" name="due_date" class="pd-input @error('due_date') is-invalid @enderror"
                       value="{{ old('due_date') }}">
                @error('due_date')<div class="pd-error">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>

    {{-- Visibilité --}}
    <div style="background:var(--pd-surface2);border-radius:10px;border:0.5px solid var(--pd-border);margin-bottom:20px;overflow:hidden;">
        {{-- Mode Normal --}}
        <div style="padding:12px 16px;border-bottom:0.5px solid var(--pd-border);">
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">
                <div style="width:8px;height:8px;border-radius:50%;background:#16A34A;flex-shrink:0;"></div>
                <span style="font-size:12px;font-weight:600;color:var(--pd-text);">Normal (par défaut)</span>
            </div>
            <p style="font-size:11px;color:var(--pd-muted);margin:0;line-height:1.6;padding-left:16px;">
                Les membres explicites peuvent modifier selon leur rôle (Chef de projet / Contributeur).<br>
                La hiérarchie organisationnelle (Resp. Direction / Service) voit ce projet en <strong>lecture seule</strong> automatiquement.<br>
                Pour qu'un supérieur puisse modifier, ajoutez-le comme membre avec le rôle Chef de projet ou Contributeur.
            </p>
        </div>
        {{-- Mode Privé --}}
        <label style="display:flex;align-items:flex-start;gap:12px;cursor:pointer;padding:12px 16px;">
            <input type="checkbox" name="is_private" value="1" {{ old('is_private') ? 'checked' : '' }}
                   style="width:15px;height:15px;accent-color:#6D28D9;cursor:pointer;margin-top:2px;flex-shrink:0;">
            <div>
                <div style="font-size:12px;font-weight:600;color:var(--pd-text);margin-bottom:4px;">🔒 Rendre ce projet privé</div>
                <p style="font-size:11px;color:var(--pd-muted);margin:0;line-height:1.6;">
                    Membres explicites uniquement — la hiérarchie ne voit pas ce projet.<br>
                    Pour donner accès, ajoutez la personne directement dans les membres du projet.
                </p>
            </div>
        </label>
    </div>

    <div style="display:flex;justify-content:flex-end;gap:8px;">
        <a href="{{ route('projects.index') }}" class="pd-btn pd-btn-secondary">Annuler</a>
        <button type="submit" class="pd-btn pd-btn-primary">Créer le projet</button>
    </div>

</form>
</div>
@endsection
