{{-- resources/views/projects/edit.blade.php --}}
@extends('layouts.app')
@section('title', 'Modifier — ' . $project->name)
@section('content')

<div class="pd-page-header">
    <div>
        <h1 class="pd-page-title">Modifier le projet</h1>
        <p class="pd-page-sub">{{ $project->name }}</p>
    </div>
    <a href="{{ route('projects.show', $project) }}" class="pd-btn pd-btn-secondary">← Retour</a>
</div>

<div style="max-width:640px;">

    {{-- Paramètres du projet --}}
    <form method="POST" action="{{ route('projects.update', $project) }}" style="margin-bottom:40px;">
        @csrf @method('PUT')

        <div class="pd-form-group">
            <label class="pd-label">Nom du projet *</label>
            <input type="text" name="name" class="pd-input" required
                   value="{{ old('name', $project->name) }}">
            @error('name')<div class="pd-error">{{ $message }}</div>@enderror
        </div>

        <div class="pd-form-group">
            <label class="pd-label">Description</label>
            <textarea name="description" class="pd-input" rows="3">{{ old('description', $project->description) }}</textarea>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
            <div class="pd-form-group">
                <label class="pd-label">Statut</label>
                <select name="status" class="pd-input">
                    @foreach(\App\Models\Tenant\Project::statusLabels() as $value => $label)
                    <option value="{{ $value }}" {{ old('status', $project->status) === $value ? 'selected' : '' }}>
                        {{ $label }}
                    </option>
                    @endforeach
                </select>
            </div>
            <div class="pd-form-group">
                <label class="pd-label">Couleur</label>
                <input type="color" name="color" class="pd-input" style="height:42px;padding:4px;"
                       value="{{ old('color', $project->color) }}">
            </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
            <div class="pd-form-group">
                <label class="pd-label">Date de début</label>
                <input type="date" name="start_date" class="pd-input"
                       value="{{ old('start_date', $project->start_date?->format('Y-m-d')) }}">
            </div>
            <div class="pd-form-group">
                <label class="pd-label">Date de fin</label>
                <input type="date" name="due_date" class="pd-input"
                       value="{{ old('due_date', $project->due_date?->format('Y-m-d')) }}">
                @error('due_date')<div class="pd-error">{{ $message }}</div>@enderror
            </div>
        </div>

        <div style="display:flex;gap:12px;margin-top:24px;">
            <button type="submit" class="pd-btn pd-btn-primary">Enregistrer</button>
            <a href="{{ route('projects.show', $project) }}" class="pd-btn pd-btn-secondary">Annuler</a>
        </div>
    </form>

    {{-- Gestion des membres (si owner) --}}
    @can('manageMembers', $project)
    <div style="border-top:0.5px solid var(--pd-border);padding-top:32px;" id="membres">
        <h2 style="font-size:16px;font-weight:600;margin-bottom:16px;">Membres du projet</h2>

        {{-- Membres actuels --}}
        @foreach($project->projectMembers()->with('user')->get() as $pm)
        <div style="display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:0.5px solid var(--pd-border);">
            <div style="width:32px;height:32px;border-radius:50%;background:var(--pd-navy-light);color:var(--pd-navy);font-size:12px;font-weight:600;display:flex;align-items:center;justify-content:center;">
                {{ strtoupper(substr($pm->user->name ?? '?', 0, 2)) }}
            </div>
            <div style="flex:1;">
                <div style="font-size:13px;font-weight:500;">{{ $pm->user->name ?? '—' }}</div>
                <div style="font-size:12px;color:var(--pd-muted);">{{ \App\Enums\ProjectRole::from($pm->role)->label() }}</div>
            </div>
            <form method="POST" action="{{ route('projects.members.destroy', [$project, $pm->user_id]) }}">
                @csrf @method('DELETE')
                <button type="submit" class="pd-btn pd-btn-xs pd-btn-danger"
                        onclick="return confirm('Retirer ce membre ?')">Retirer</button>
            </form>
        </div>
        @endforeach

        {{-- Ajouter un membre --}}
        <form method="POST" action="{{ route('projects.members.store', $project) }}"
              style="display:flex;gap:8px;margin-top:16px;flex-wrap:wrap;">
            @csrf
            <select name="user_id" class="pd-input" style="flex:1;min-width:180px;" required>
                <option value="">Sélectionner un utilisateur…</option>
                @foreach(\App\Models\Tenant\User::on('tenant')->where('status','active')->orderBy('name')->get() as $u)
                <option value="{{ $u->id }}">{{ $u->name }}</option>
                @endforeach
            </select>
            <select name="role" class="pd-input" style="width:160px;" required>
                @foreach(\App\Enums\ProjectRole::options() as $v => $l)
                <option value="{{ $v }}">{{ $l }}</option>
                @endforeach
            </select>
            <button type="submit" class="pd-btn pd-btn-primary">Ajouter</button>
        </form>
    </div>
    @endcan

</div>
@endsection
