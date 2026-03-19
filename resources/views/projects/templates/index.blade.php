@extends('layouts.app')
@section('title', 'Modèles de projets')

@section('content')
<div style="padding:20px;">

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;padding-bottom:14px;border-bottom:0.5px solid var(--pd-border);">
    <div>
        <h1 style="font-size:20px;font-weight:700;color:var(--pd-navy);margin:0;">Modèles de projets</h1>
        <p style="font-size:12px;color:var(--pd-muted);margin:3px 0 0;">
            {{ $templates->count() }} modèle{{ $templates->count() > 1 ? 's' : '' }} — réutilisez une structure type pour créer rapidement un nouveau projet
        </p>
    </div>
    <div style="display:flex;gap:8px;">
        <a href="{{ route('projects.index') }}" class="pd-btn pd-btn-secondary" style="font-size:12px;">← Projets</a>
        @can('create', \App\Models\Tenant\Project::class)
        <a href="{{ route('projects.templates.create') }}" class="pd-btn pd-btn-primary" style="font-size:12px;">+ Nouveau modèle</a>
        @endcan
    </div>
</div>

@if(session('success'))
<div style="padding:10px 14px;background:#D1FAE5;color:#065F46;border-radius:8px;margin-bottom:16px;font-size:12px;">{{ session('success') }}</div>
@endif

@if($templates->isEmpty())
<div style="text-align:center;padding:60px 20px;color:var(--pd-muted);">
    <div style="font-size:40px;margin-bottom:12px;">📋</div>
    <p style="font-size:14px;margin-bottom:16px;">Aucun modèle de projet pour le moment.</p>
    @can('create', \App\Models\Tenant\Project::class)
    <a href="{{ route('projects.templates.create') }}" class="pd-btn pd-btn-primary">Créer le premier modèle</a>
    @endcan
</div>
@else
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:14px;">
    @foreach($templates as $tpl)
    <div style="background:var(--pd-surface);border:0.5px solid var(--pd-border);border-radius:10px;border-top:4px solid {{ $tpl->color }};padding:16px;"
         x-data="{ showApply: false }">

        <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:10px;">
            <h3 style="font-size:14px;font-weight:600;color:var(--pd-text);margin:0;">{{ $tpl->name }}</h3>
            @can('create', \App\Models\Tenant\Project::class)
            <div style="display:flex;gap:6px;">
                <a href="{{ route('projects.templates.edit', $tpl) }}"
                   style="font-size:11px;color:var(--pd-muted);padding:2px 7px;border:0.5px solid var(--pd-border);border-radius:5px;text-decoration:none;">✏️</a>
                <form method="POST" action="{{ route('projects.templates.destroy', $tpl) }}"
                      onsubmit="return confirm('Supprimer ce modèle ?');" style="display:inline;">
                    @csrf @method('DELETE')
                    <button type="submit" style="font-size:11px;color:var(--pd-muted);padding:2px 7px;border:0.5px solid var(--pd-border);border-radius:5px;background:none;cursor:pointer;">×</button>
                </form>
            </div>
            @endcan
        </div>

        @if($tpl->description)
        <p style="font-size:12px;color:var(--pd-muted);margin:0 0 10px;line-height:1.5;">{{ Str::limit($tpl->description, 80) }}</p>
        @endif

        <div style="display:flex;gap:10px;font-size:11px;color:var(--pd-muted);margin-bottom:12px;">
            <span>🏁 {{ $tpl->milestoneCount() }} jalons</span>
            <span>✓ {{ $tpl->taskCount() }} tâches</span>
            <span>👤 {{ $tpl->creator?->name }}</span>
        </div>

        {{-- Modal appliquer --}}
        <button @click="showApply=true" class="pd-btn pd-btn-primary" style="width:100%;font-size:12px;">
            Utiliser ce modèle
        </button>

        <div x-show="showApply" x-cloak
             style="position:fixed;inset:0;z-index:9000;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,.45);backdrop-filter:blur(2px);"
             @click.self="showApply=false">
            <div class="pd-modal pd-modal-md" style="animation:pd-modal-in .18s ease-out;">
                <div class="pd-modal-header" style="background:#1E3A5F;border-radius:14px 14px 0 0;padding:20px 20px 16px;border-bottom:none;display:flex;align-items:flex-start;justify-content:space-between;">
                    <div>
                        <div class="pd-modal-title" style="font-size:16px;font-weight:700;color:#fff;line-height:1.3;">Créer un projet depuis ce modèle</div>
                        <div class="pd-modal-subtitle" style="font-size:12px;color:rgba(255,255,255,.75);margin-top:3px;">Modèle : {{ $tpl->name }}</div>
                    </div>
                    <button type="button" @click="showApply=false" class="pd-modal-close" style="background:none;border:none;cursor:pointer;color:rgba(255,255,255,.8);font-size:22px;line-height:1;padding:0 2px;margin-left:12px;flex-shrink:0;">×</button>
                </div>
                <form method="POST" action="{{ route('projects.templates.apply', $tpl) }}">
                    @csrf
                <div class="pd-modal-body">
                    <div style="margin-bottom:12px;">
                        <label class="pd-label pd-label-req">Nom du projet</label>
                        <input type="text" name="name" class="pd-input" required style="width:100%;"
                               placeholder="{{ $tpl->name }}">
                    </div>
                    <div style="margin-bottom:12px;">
                        <label class="pd-label pd-label-req">Date de démarrage</label>
                        <input type="date" name="start_date" class="pd-input" required style="width:100%;"
                               value="{{ now()->format('Y-m-d') }}">
                    </div>
                    <div style="margin-bottom:18px;">
                        <label class="pd-label">Statut initial</label>
                        <select name="status" class="pd-input" style="width:100%;">
                            <option value="active">Actif</option>
                            <option value="draft">Brouillon</option>
                        </select>
                    </div>
                </div>
                <div class="pd-modal-footer">
                    <button type="button" @click="showApply=false" class="pd-btn pd-btn-secondary pd-btn-sm">Annuler</button>
                    <button type="submit" class="pd-btn pd-btn-primary pd-btn-sm">Créer le projet</button>
                </div>
                </form>
            </div>
        </div>

    </div>
    @endforeach
</div>
@endif

</div>
@endsection
