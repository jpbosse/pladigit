{{-- resources/views/projects/edit.blade.php --}}
@extends('layouts.app')
@section('title', 'Modifier — ' . $project->name)

@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/trix@2.0.8/dist/trix.css">
<script src="https://cdn.jsdelivr.net/npm/trix@2.0.8/dist/trix.umd.min.js"></script>
<style>
trix-editor { min-height:100px;border:1px solid var(--pd-border);border-radius:0 0 8px 8px;padding:10px 12px;font-size:12px;font-family:'DM Sans',system-ui,sans-serif;color:var(--pd-text);background:var(--pd-surface);outline:none; }
trix-editor:focus { border-color:var(--pd-navy); }
trix-toolbar .trix-button-group { border:0.5px solid var(--pd-border);border-radius:8px 8px 0 0;background:var(--pd-surface2); }
trix-toolbar .trix-button { border:none;color:var(--pd-muted);font-size:11px; }
trix-toolbar .trix-button.trix-active { color:var(--pd-navy);background:var(--pd-bg2); }
trix-toolbar .trix-button--icon-attach { display:none; }
.edit-grid { display:grid;grid-template-columns:1fr 300px;gap:14px;align-items:start; }
.edit-card { background:var(--pd-surface);border:1px solid var(--pd-border);border-radius:10px;padding:18px;margin-bottom:12px; }
.edit-card-title { font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--pd-muted);margin-bottom:12px; }
.edit-tabs { display:flex;gap:2px;border-bottom:1px solid var(--pd-border);margin-bottom:16px; }
.edit-tab { padding:7px 14px;font-size:12px;font-weight:500;color:var(--pd-muted);border:none;background:none;cursor:pointer;border-bottom:2px solid transparent;margin-bottom:-1px;border-radius:6px 6px 0 0;transition:all .12s; }
.edit-tab.active { color:var(--pd-navy);border-bottom-color:var(--pd-navy);font-weight:600; }
.edit-tab:hover:not(.active) { color:var(--pd-text);background:var(--pd-surface2); }
.member-row { display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid var(--pd-border); }
.member-row:last-child { border:none; }
.member-av { width:30px;height:30px;border-radius:50%;background:var(--pd-bg2);color:var(--pd-navy);font-size:11px;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0; }
.danger-zone { background:#FFF5F5;border:1px solid #FCA5A5;border-radius:10px;padding:16px; }
</style>
@endpush

@section('content')
<div style="padding:20px;" x-data="{ tab: 'info' }">

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;">
    <div style="display:flex;align-items:center;gap:10px;">
        <div style="width:11px;height:11px;border-radius:50%;background:{{ $project->color }};flex-shrink:0;"></div>
        <div>
            <h1 style="font-size:18px;font-weight:700;margin:0;color:var(--pd-navy);">Modifier le projet</h1>
            <p style="font-size:12px;color:var(--pd-muted);margin:2px 0 0;">{{ $project->name }}</p>
        </div>
    </div>
    <a href="{{ route('projects.show', $project) }}" class="pd-btn pd-btn-secondary pd-btn-sm">← Retour</a>
</div>

@if(session('success'))
<div style="padding:9px 14px;background:#D1FAE5;color:#065F46;border-radius:8px;margin-bottom:12px;font-size:12px;">{{ session('success') }}</div>
@endif
@if($errors->any())
<div style="padding:9px 14px;background:#FEE2E2;color:#991B1B;border-radius:8px;margin-bottom:12px;font-size:12px;">
    @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
</div>
@endif

<div class="edit-tabs">
    <button class="edit-tab" :class="{ active: tab==='info' }" @click="tab='info'">⚙ Général</button>
    @can('manageMembers', $project)
    <button class="edit-tab" :class="{ active: tab==='membres' }" @click="tab='membres'">👥 Membres</button>
    @endcan
    <button class="edit-tab" :class="{ active: tab==='actions' }" @click="tab='actions'">📋 Actions</button>
    @can('delete', $project)
    <button class="edit-tab" :class="{ active: tab==='danger' }" @click="tab='danger'" style="color:#DC2626;">⚠ Danger</button>
    @endcan
</div>

{{-- ONGLET : Général --}}
<div x-show="tab==='info'" x-cloak>
<div class="edit-grid">
    <div>
        <form method="POST" action="{{ route('projects.update', $project) }}">
            @csrf @method('PUT')
            <div class="edit-card">
                <div class="edit-card-title">Informations</div>
                <div class="pd-form-group">
                    <label class="pd-label pd-label-req">Nom du projet</label>
                    <input type="text" name="name" class="pd-input" required value="{{ old('name', $project->name) }}">
                </div>
                <div class="pd-form-group">
                    <label class="pd-label">Description</label>
                    <input id="description-input" type="hidden" name="description" value="{{ old('description', $project->description) }}">
                    <trix-editor input="description-input" placeholder="Objectif, contexte…"></trix-editor>
                </div>
                <div class="pd-form-row-2">
                    <div class="pd-form-group" style="margin-bottom:0;">
                        <label class="pd-label pd-label-req">Statut</label>
                        <select name="status" class="pd-input">
                            @foreach(\App\Models\Tenant\Project::statusLabels() as $value => $label)
                            <option value="{{ $value }}" {{ old('status',$project->status)===$value?'selected':'' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="pd-form-group" style="margin-bottom:0;">
                        <label class="pd-label">Couleur</label>
                        <input type="color" name="color" class="pd-input" value="{{ old('color', $project->color) }}">
                    </div>
                </div>
            </div>
            <div class="edit-card">
                <div class="edit-card-title">Dates</div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                    <div>
                        <label class="pd-label">Début</label>
                        <input type="date" name="start_date" class="pd-input" style="width:100%;" value="{{ old('start_date', $project->start_date?->format('Y-m-d')) }}">
                    </div>
                    <div>
                        <label class="pd-label">Fin prévue</label>
                        <input type="date" name="due_date" class="pd-input" style="width:100%;" value="{{ old('due_date', $project->due_date?->format('Y-m-d')) }}">
                    </div>
                </div>
            </div>
            <div class="pd-form-actions">
                <a href="{{ route('projects.show', $project) }}" class="pd-btn pd-btn-secondary pd-btn-sm">Annuler</a>
                <button type="submit" class="pd-btn pd-btn-primary pd-btn-sm">Enregistrer</button>
            </div>
        </form>
    </div>
    <div>
        <div class="edit-card">
            <div class="edit-card-title">Visibilité</div>
            <form method="POST" action="{{ route('projects.update', $project) }}">
                @csrf @method('PUT')
                <input type="hidden" name="name" value="{{ $project->name }}">
                <input type="hidden" name="status" value="{{ $project->status }}">

                {{-- Mode Normal --}}
                <div style="padding:10px 12px;background:var(--pd-surface2);border-radius:8px;border:0.5px solid var(--pd-border);margin-bottom:8px;">
                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">
                        <div style="width:8px;height:8px;border-radius:50%;background:#16A34A;flex-shrink:0;"></div>
                        <span style="font-size:12px;font-weight:600;color:var(--pd-text);">Normal</span>
                        @if(!$project->is_private)
                        <span style="font-size:10px;padding:1px 7px;border-radius:8px;background:#D1FAE5;color:#065F46;font-weight:600;">actif</span>
                        @endif
                    </div>
                    <p style="font-size:11px;color:var(--pd-muted);margin:0;line-height:1.6;padding-left:16px;">
                        Les membres explicites peuvent modifier selon leur rôle.<br>
                        La hiérarchie organisationnelle (Resp. Direction / Service) voit ce projet en <strong>lecture seule</strong> automatiquement.<br>
                        Pour qu'un supérieur puisse modifier, ajoutez-le dans l'onglet <em>Membres</em> avec le rôle Chef de projet ou Contributeur.
                    </p>
                </div>

                {{-- Mode Privé --}}
                <label style="display:flex;align-items:flex-start;gap:10px;cursor:pointer;padding:10px 12px;background:var(--pd-surface2);border-radius:8px;border:0.5px solid {{ $project->is_private ? '#C4B5FD' : 'var(--pd-border)' }};">
                    <input type="checkbox" name="is_private" value="1"
                           {{ old('is_private', $project->is_private) ? 'checked' : '' }}
                           style="width:15px;height:15px;accent-color:#6D28D9;margin-top:2px;flex-shrink:0;">
                    <div>
                        <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">
                            <span style="font-size:12px;font-weight:600;color:var(--pd-text);">🔒 Projet privé</span>
                            @if($project->is_private)
                            <span style="font-size:10px;padding:1px 7px;border-radius:8px;background:#EDE9FE;color:#6D28D9;font-weight:600;">actif</span>
                            @endif
                        </div>
                        <p style="font-size:11px;color:var(--pd-muted);margin:0;line-height:1.6;">
                            Membres explicites uniquement — la hiérarchie ne voit pas ce projet.<br>
                            Pour donner accès, ajoutez la personne dans l'onglet <em>Membres</em>.
                        </p>
                    </div>
                </label>

                <div style="margin-top:10px;text-align:right;">
                    <button type="submit" class="pd-btn pd-btn-secondary pd-btn-sm" style="font-size:11px;">Mettre à jour</button>
                </div>
            </form>
        </div>
        <div class="edit-card">
            <div class="edit-card-title">Système</div>
            <div style="font-size:11px;color:var(--pd-muted);line-height:2;">
                <div>Créé par <strong style="color:var(--pd-text);">{{ $project->creator?->name ?? '—' }}</strong></div>
                <div>Le {{ $project->created_at->translatedFormat('d M Y') }}</div>
                <div>{{ $project->projectMembers->count() }} membre{{ $project->projectMembers->count() > 1 ? 's' : '' }}</div>
                <div>{{ $project->tasks()->whereNull('deleted_at')->count() }} tâche{{ $project->tasks()->whereNull('deleted_at')->count() > 1 ? 's' : '' }}</div>
            </div>
        </div>
    </div>
</div>
</div>

{{-- ONGLET : Membres --}}
@can('manageMembers', $project)
<div x-show="tab==='membres'" x-cloak style="max-width:540px;">
    <div class="edit-card">
        <div class="edit-card-title">Équipe du projet</div>

        {{-- Tableau des rôles --}}
        <div style="margin-bottom:16px;border:0.5px solid var(--pd-border);border-radius:8px;overflow:hidden;font-size:11px;">
            <div style="display:grid;grid-template-columns:130px 1fr;background:var(--pd-surface2);">
                <div style="padding:7px 10px;font-weight:700;color:var(--pd-muted);text-transform:uppercase;letter-spacing:.04em;border-right:0.5px solid var(--pd-border);">Rôle</div>
                <div style="padding:7px 10px;font-weight:700;color:var(--pd-muted);text-transform:uppercase;letter-spacing:.04em;">Droits</div>
            </div>
            @foreach(\App\Enums\ProjectRole::cases() as $role)
            <div style="display:grid;grid-template-columns:130px 1fr;border-top:0.5px solid var(--pd-border);">
                <div style="padding:8px 10px;font-weight:600;color:var(--pd-text);border-right:0.5px solid var(--pd-border);">{{ $role->label() }}</div>
                <div style="padding:8px 10px;color:var(--pd-muted);line-height:1.4;">{{ $role->description() }}</div>
            </div>
            @endforeach
        </div>
        @foreach($project->projectMembers()->with('user')->orderByRaw("FIELD(role,'owner','member','viewer')")->get() as $pm)
        <div class="member-row">
            <div class="member-av">{{ strtoupper(substr($pm->user->name??'?',0,2)) }}</div>
            <div style="flex:1;">
                <div style="font-size:13px;font-weight:500;">{{ $pm->user->name ?? '—' }}</div>
                <div style="font-size:11px;color:var(--pd-muted);">{{ \App\Enums\ProjectRole::from($pm->role)->label() }}</div>
            </div>
            @if($pm->role !== 'owner')
            <form method="POST" action="{{ route('projects.members.destroy',[$project,$pm->user_id]) }}" onsubmit="return confirm('Retirer {{ $pm->user->name }} ?')">
                @csrf @method('DELETE')
                <button type="submit" class="pd-btn pd-btn-xs pd-btn-danger">Retirer</button>
            </form>
            @else
            <span style="font-size:11px;color:var(--pd-muted);padding:3px 8px;">Propriétaire</span>
            @endif
        </div>
        @endforeach
        <form method="POST" action="{{ route('projects.members.store', $project) }}" style="margin-top:14px;padding-top:14px;border-top:1px solid var(--pd-border);">
            @csrf
            <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--pd-muted);margin-bottom:10px;">Ajouter un membre</div>
            <div style="display:grid;grid-template-columns:1fr 1fr auto;gap:12px;align-items:end;">
                <div>
                    <label class="pd-label">Utilisateur</label>
                    <select name="user_id" class="pd-input" required style="width:100%;">
                        <option value="">Choisir…</option>
                        @foreach(\App\Models\Tenant\User::on('tenant')->where('status','active')->orderBy('name')->get() as $u)
                        <option value="{{ $u->id }}">{{ $u->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="pd-label">Rôle</label>
                    <select name="role" class="pd-input" required style="width:100%;">
                        @foreach(\App\Enums\ProjectRole::options() as $v => $l)
                        <option value="{{ $v }}">{{ $l }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <button type="submit" class="pd-btn pd-btn-primary pd-btn-sm">Ajouter</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endcan

{{-- ONGLET : Actions --}}
<div x-show="tab==='actions'" x-cloak style="max-width:540px;" x-data="{ showDuplicate: false, showSaveTemplate: false }">
    <div class="edit-card">
        <div class="edit-card-title">Dupliquer ce projet</div>
        <p style="font-size:12px;color:var(--pd-muted);margin:0 0 12px;line-height:1.5;">Crée un nouveau projet avec la même structure sans les données métier. Les dates sont décalées depuis la nouvelle date de démarrage.</p>
        <button @click="showDuplicate=true" class="pd-btn pd-btn-secondary pd-btn-sm">📋 Dupliquer</button>
    </div>
    @can('create', \App\Models\Tenant\Project::class)
    <div class="edit-card">
        <div class="edit-card-title">Sauvegarder comme modèle</div>
        <p style="font-size:12px;color:var(--pd-muted);margin:0 0 12px;line-height:1.5;">Capture la structure pour la réutiliser comme point de départ pour de futurs projets similaires.</p>
        <button @click="showSaveTemplate=true" class="pd-btn pd-btn-secondary pd-btn-sm">📋 Sauvegarder comme modèle</button>
    </div>
    @endcan

    {{-- Modal : Dupliquer --}}
    <div x-show="showDuplicate" x-cloak style="position:fixed;inset:0;z-index:9000;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,.45);backdrop-filter:blur(2px);" @click.self="showDuplicate=false">
        <div class="pd-modal pd-modal-md" style="animation:pd-modal-in .18s ease-out;">
            <div style="background:#1E3A5F;border-radius:14px 14px 0 0;padding:18px 20px;display:flex;align-items:flex-start;justify-content:space-between;">
                <div>
                    <div style="font-size:15px;font-weight:700;color:#fff;">Dupliquer « {{ $project->name }} »</div>
                    <div style="font-size:11px;color:rgba(255,255,255,.7);margin-top:2px;">Copie la structure — phases, jalons et tâches</div>
                </div>
                <button @click="showDuplicate=false" style="background:none;border:none;cursor:pointer;color:rgba(255,255,255,.8);font-size:20px;line-height:1;margin-left:12px;">×</button>
            </div>
            <form method="POST" action="{{ route('projects.duplicate', $project) }}">
                @csrf
                <div class="pd-modal-body">
                    <div class="pd-form-group"><label class="pd-label pd-label-req">Nom du nouveau projet</label><input type="text" name="name" class="pd-input" required style="width:100%;" value="Copie de {{ $project->name }}"></div>
                    <div class="pd-form-group"><label class="pd-label pd-label-req">Date de démarrage</label><input type="date" name="start_date" class="pd-input" required style="width:100%;" value="{{ now()->format('Y-m-d') }}"></div>
                    <div class="pd-form-group" style="margin-bottom:0;"><label class="pd-label">Statut initial</label><select name="status" class="pd-input" style="width:100%;"><option value="draft">Brouillon</option><option value="active">Actif</option></select></div>
                </div>
                <div class="pd-modal-footer">
                    <button type="button" @click="showDuplicate=false" class="pd-btn pd-btn-secondary pd-btn-sm">Annuler</button>
                    <button type="submit" class="pd-btn pd-btn-primary pd-btn-sm">Dupliquer</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Modal : Sauvegarder comme modèle --}}
    <div x-show="showSaveTemplate" x-cloak style="position:fixed;inset:0;z-index:9000;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,.45);backdrop-filter:blur(2px);" @click.self="showSaveTemplate=false">
        <div class="pd-modal pd-modal-md" style="animation:pd-modal-in .18s ease-out;">
            <div style="background:#0891B2;border-radius:14px 14px 0 0;padding:18px 20px;display:flex;align-items:flex-start;justify-content:space-between;">
                <div>
                    <div style="font-size:15px;font-weight:700;color:#fff;">Créer un modèle depuis ce projet</div>
                    <div style="font-size:11px;color:rgba(255,255,255,.7);margin-top:2px;">Capture la structure pour la réutiliser</div>
                </div>
                <button @click="showSaveTemplate=false" style="background:none;border:none;cursor:pointer;color:rgba(255,255,255,.8);font-size:20px;line-height:1;margin-left:12px;">×</button>
            </div>
            <form method="POST" action="{{ route('projects.save_as_template', $project) }}">
                @csrf
                <div class="pd-modal-body">
                    <div class="pd-form-group"><label class="pd-label pd-label-req">Nom du modèle</label><input type="text" name="name" class="pd-input" required style="width:100%;" value="{{ $project->name }}"></div>
                    <div class="pd-form-group" style="margin-bottom:0;"><label class="pd-label">Description</label><textarea name="description" class="pd-input" rows="2" style="width:100%;resize:vertical;">{{ $project->description }}</textarea></div>
                </div>
                <div class="pd-modal-footer">
                    <button type="button" @click="showSaveTemplate=false" class="pd-btn pd-btn-secondary pd-btn-sm">Annuler</button>
                    <button type="submit" class="pd-btn pd-btn-primary pd-btn-sm">Créer le modèle</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ONGLET : Danger --}}
@can('delete', $project)
<div x-show="tab==='danger'" x-cloak style="max-width:540px;">
    <div class="danger-zone">
        <div style="font-size:13px;font-weight:700;color:#991B1B;margin-bottom:6px;">⚠ Zone de danger</div>
        <p style="font-size:12px;color:#7F1D1D;margin-bottom:14px;line-height:1.5;">La suppression est irréversible. Toutes les tâches, jalons, budgets et données associées seront supprimés définitivement.</p>
        <form method="POST" action="{{ route('projects.destroy', $project) }}" onsubmit="return confirm('Supprimer définitivement « {{ $project->name }} » ?\n\nCette action est irréversible.')">
            @csrf @method('DELETE')
            <button type="submit" class="pd-btn pd-btn-danger pd-btn-sm">Supprimer ce projet</button>
        </form>
    </div>
</div>
@endcan

</div>
@endsection
