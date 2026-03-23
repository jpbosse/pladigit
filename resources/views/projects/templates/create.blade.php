@extends('layouts.app')
@section('title', 'Nouveau modèle de projet')

@section('content')
<div style="padding:20px;max-width:600px;">

<div style="margin-bottom:20px;">
    <a href="{{ route('projects.templates.index') }}" style="font-size:12px;color:var(--pd-muted);text-decoration:none;">← Modèles</a>
    <h1 style="font-size:20px;font-weight:700;color:var(--pd-navy);margin:8px 0 4px;">Nouveau modèle</h1>
    <p style="font-size:12px;color:var(--pd-muted);">Vous pourrez ajouter les phases, jalons et tâches après la création.</p>
</div>

@if($errors->any())
<div style="background:#FEE2E2;color:#991B1B;padding:10px 14px;border-radius:8px;margin-bottom:16px;font-size:12px;">
    @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
</div>
@endif

<div class="pd-card">
    <form method="POST" action="{{ route('projects.templates.store') }}">
        @csrf

        <div style="margin-bottom:14px;">
            <label class="pd-label pd-label-req">Nom du modèle</label>
            <input type="text" name="name" class="pd-input" required style="width:100%;"
                   value="{{ old('name') }}" placeholder="Ex: Projet travaux bâtiment">
        </div>

        <div style="margin-bottom:14px;">
            <label class="pd-label">Description</label>
            <textarea name="description" class="pd-input" rows="3" style="width:100%;resize:vertical;"
                      placeholder="Décrivez le type de projet pour lequel ce modèle est adapté…">{{ old('description') }}</textarea>
        </div>

        <div style="margin-bottom:20px;">
            <label class="pd-label">Couleur</label>
            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:4px;">
                @foreach(['#1E3A5F','#16A34A','#EA580C','#8B5CF6','#0891B2','#DC2626','#D97706'] as $c)
                <label style="cursor:pointer;">
                    <input type="radio" name="color" value="{{ $c }}" style="display:none;"
                           {{ old('color','#1E3A5F') === $c ? 'checked' : '' }}>
                    <div style="width:26px;height:26px;border-radius:50%;background:{{ $c }};border:2px solid transparent;transition:border .1s;"
                         onclick="this.parentElement.querySelector('input').checked=true;document.querySelectorAll('[name=color]~div').forEach(d=>d.style.border='2px solid transparent');this.style.border='2px solid #000';">
                    </div>
                </label>
                @endforeach
            </div>
        </div>

        <div style="display:flex;justify-content:flex-end;gap:8px;">
            <a href="{{ route('projects.templates.index') }}" class="pd-btn pd-btn-secondary">Annuler</a>
            <button type="submit" class="pd-btn pd-btn-primary">Créer et ajouter la structure →</button>
        </div>
    </form>
</div>

</div>
@endsection
