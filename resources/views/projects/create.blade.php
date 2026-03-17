{{-- resources/views/projects/create.blade.php --}}
@extends('layouts.app')
@section('title', 'Nouveau projet')
@section('content')

<div class="pd-page-header">
    <div>
        <h1 class="pd-page-title">Nouveau projet</h1>
    </div>
    <a href="{{ route('projects.index') }}" class="pd-btn pd-btn-secondary">← Retour</a>
</div>

<div style="max-width:640px;">
    <form method="POST" action="{{ route('projects.store') }}">
        @csrf

        <div class="pd-form-group">
            <label class="pd-label">Nom du projet *</label>
            <input type="text" name="name" class="pd-input" required autofocus
                   value="{{ old('name') }}" placeholder="Ex : Refonte site internet">
            @error('name')<div class="pd-error">{{ $message }}</div>@enderror
        </div>

        <div class="pd-form-group">
            <label class="pd-label">Description</label>
            <textarea name="description" class="pd-input" rows="3"
                      placeholder="Description optionnelle du projet">{{ old('description') }}</textarea>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
            <div class="pd-form-group">
                <label class="pd-label">Statut</label>
                <select name="status" class="pd-input">
                    @foreach(\App\Models\Tenant\Project::statusLabels() as $value => $label)
                    <option value="{{ $value }}" {{ old('status', 'active') === $value ? 'selected' : '' }}>
                        {{ $label }}
                    </option>
                    @endforeach
                </select>
            </div>
            <div class="pd-form-group">
                <label class="pd-label">Couleur</label>
                <input type="color" name="color" class="pd-input" style="height:42px;padding:4px;"
                       value="{{ old('color', '#1E3A5F') }}">
            </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
            <div class="pd-form-group">
                <label class="pd-label">Date de début</label>
                <input type="date" name="start_date" class="pd-input" value="{{ old('start_date') }}">
                @error('start_date')<div class="pd-error">{{ $message }}</div>@enderror
            </div>
            <div class="pd-form-group">
                <label class="pd-label">Date de fin</label>
                <input type="date" name="due_date" class="pd-input" value="{{ old('due_date') }}">
                @error('due_date')<div class="pd-error">{{ $message }}</div>@enderror
            </div>
        </div>

        <div style="display:flex;gap:12px;margin-top:24px;">
            <button type="submit" class="pd-btn pd-btn-primary">Créer le projet</button>
            <a href="{{ route('projects.index') }}" class="pd-btn pd-btn-secondary">Annuler</a>
        </div>
    </form>
</div>
@endsection
