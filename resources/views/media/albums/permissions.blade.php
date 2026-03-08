@extends('layouts.app')
@section('title', 'Droits — ' . $album->name)

@push('styles')
<style>
    .toggle-wrap { display: inline-flex; flex-direction: column; align-items: center; gap: 4px; }
    .toggle-label { font-size: 0.65rem; font-weight: 500; color: #94a3b8; letter-spacing: 0.04em; text-transform: uppercase; }
    .toggle { position: relative; display: inline-block; width: 36px; height: 20px; }
    .toggle input { opacity: 0; width: 0; height: 0; }
    .toggle-slider { position: absolute; inset: 0; background: #e2e8f0; border-radius: 20px; cursor: pointer; transition: background 0.2s ease; }
    .toggle-slider:before { content: ''; position: absolute; width: 14px; height: 14px; left: 3px; top: 3px; background: white; border-radius: 50%; transition: transform 0.2s ease; box-shadow: 0 1px 3px rgba(0,0,0,.2); }
    .toggle input:checked + .toggle-slider { background: var(--color-primary, #1E3A5F); }
    .toggle input:checked + .toggle-slider:before { transform: translateX(16px); }
    .toggle input:disabled + .toggle-slider { background: var(--color-primary, #1E3A5F); opacity: .35; cursor: not-allowed; }
    .role-card { background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.25rem; display: flex; flex-direction: column; gap: 1rem; transition: box-shadow 0.15s ease; }
    .role-card:hover { box-shadow: 0 4px 12px rgba(30,58,95,.08); }
    .role-card.is-total { background: #f8fafc; }
    .role-card-name { font-size: 0.875rem; font-weight: 600; color: #1e3a5f; }
    .role-card-badge { font-size: 0.65rem; font-weight: 500; padding: 2px 8px; border-radius: 20px; background: color-mix(in srgb, var(--color-primary, #1E3A5F) 12%, white); color: var(--color-primary, #1E3A5F); display: inline-block; margin-top: 2px; }
    .role-card-toggles { display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; }
    .share-card { background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1rem 1.25rem; display: flex; align-items: center; gap: 1rem; flex-wrap: wrap; transition: box-shadow 0.15s ease; }
    .share-card:hover { box-shadow: 0 4px 12px rgba(30,58,95,.08); }
    .share-card-info { flex: 1; min-width: 140px; }
    .share-card-toggles { display: flex; gap: 1rem; flex-wrap: wrap; }
    .share-card-actions { display: flex; align-items: center; gap: 8px; margin-left: auto; }
    #toast { position: fixed; bottom: 1.5rem; right: 1.5rem; background: #1e3a5f; color: white; padding: 0.6rem 1.2rem; border-radius: 8px; font-size: 0.8rem; font-weight: 500; box-shadow: 0 4px 16px rgba(0,0,0,.2); opacity: 0; transform: translateY(8px); transition: opacity .25s, transform .25s; pointer-events: none; z-index: 50; }
    #toast.show { opacity: 1; transform: translateY(0); }
</style>
@endpush

@section('content')
<div class="max-w-5xl mx-auto px-4 py-6">

    <div id="toast">✓ Droit mis à jour</div>

    {{-- Fil d'Ariane --}}
    <div class="flex items-center gap-2 mb-6 text-sm text-gray-400">
        <a href="{{ route('media.albums.index') }}" class="hover:text-gray-600">Photothèque</a>
        <span>/</span>
        <a href="{{ route('media.albums.show', $album) }}" class="hover:text-gray-600">{{ $album->name }}</a>
        <span>/</span>
        <span class="text-gray-600">Droits d'accès</span>
    </div>

    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-xl font-bold text-gray-800">Droits d'accès</h1>
            <p class="text-sm text-gray-400 mt-0.5">{{ $album->name }}</p>
        </div>
        <a href="{{ route('media.albums.show', $album) }}"
           class="px-4 py-2 rounded-lg border border-gray-200 text-sm text-gray-600 hover:bg-gray-50">
            ← Retour à l'album
        </a>
    </div>

    @if(session('success'))
        <div class="mb-6 p-3 bg-green-50 border border-green-200 text-green-700 rounded-lg text-sm">
            ✓ {{ session('success') }}
        </div>
    @endif

    {{-- Droits par rôle --}}
    <div class="mb-8">
        <div class="flex items-baseline gap-3 mb-4">
            <h2 class="text-sm font-semibold text-gray-700">Droits par rôle</h2>
            <span class="text-xs text-gray-400">Sauvegarde automatique au clic</span>
        </div>

        {{-- Bandeau Président / DGS --}}
        <div class="flex items-center gap-3 px-4 py-3 rounded-xl mb-4"
             style="background: color-mix(in srgb, var(--color-primary, #1E3A5F) 8%, white); border: 1px solid color-mix(in srgb, var(--color-primary, #1E3A5F) 20%, white);">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="color: var(--color-primary, #1E3A5F)">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.955 11.955 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />
            </svg>
            <div>
                <span class="text-sm font-semibold" style="color: var(--color-primary, #1E3A5F)">Président &amp; DGS</span>
                <span class="text-sm text-gray-500 ml-2">— Accès total à tous les albums, sans restriction possible.</span>
            </div>
        </div>

        {{-- 3 rôles configurables --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
            @foreach($configurableRoles as $role => $label)
            @php $s = $roleShares[$role] ?? null; @endphp
            <div class="role-card"
                 x-data="{
                    role: '{{ $role }}',
                    can_view: {{ $s?->can_view ? 'true' : 'false' }},
                    can_download: {{ $s?->can_download ? 'true' : 'false' }},
                    can_edit: {{ $s?->can_edit ? 'true' : 'false' }},
                    can_manage: {{ $s?->can_manage ? 'true' : 'false' }},
                    saving: false,
                    async save() {
                        this.saving = true;
                        const fd = new FormData();
                        fd.append('_token', document.querySelector('meta[name=csrf-token]').content);
                        fd.append('_method', 'PUT');
                        fd.append('roles[' + this.role + '][can_view]', this.can_view ? '1' : '');
                        fd.append('roles[' + this.role + '][can_download]', this.can_download ? '1' : '');
                        fd.append('roles[' + this.role + '][can_edit]', this.can_edit ? '1' : '');
                        fd.append('roles[' + this.role + '][can_manage]', this.can_manage ? '1' : '');
                        await fetch('{{ route('media.albums.permissions.roles', $album) }}', { method: 'POST', body: fd });
                        this.saving = false;
                        showToast();
                    }
                 }">
                <div>
                    <div class="role-card-name">{{ $label }}</div>
                    <div class="text-xs text-gray-400 mt-0.5" x-text="saving ? 'Enregistrement…' : 'Configurez les droits'"></div>
                </div>
                <div class="role-card-toggles">
                    <div class="toggle-wrap">
                        <label class="toggle"><input type="checkbox" x-model="can_view" @change="save()"><span class="toggle-slider"></span></label>
                        <span class="toggle-label">Voir</span>
                    </div>
                    <div class="toggle-wrap">
                        <label class="toggle"><input type="checkbox" x-model="can_download" @change="save()"><span class="toggle-slider"></span></label>
                        <span class="toggle-label">Télécharger</span>
                    </div>
                    <div class="toggle-wrap">
                        <label class="toggle"><input type="checkbox" x-model="can_edit" @change="save()"><span class="toggle-slider"></span></label>
                        <span class="toggle-label">Éditer</span>
                    </div>
                    <div class="toggle-wrap">
                        <label class="toggle"><input type="checkbox" x-model="can_manage" @change="save()"><span class="toggle-slider"></span></label>
                        <span class="toggle-label">Gérer</span>
                    </div>
                </div>
            </div>
            @endforeach
        </div>{{-- fin grille 5 colonnes --}}
    </div>

    {{-- Partages individuels --}}
    @if($deptShares->isNotEmpty() || $userShares->isNotEmpty())
    <div class="mb-8">
        <div class="flex items-baseline gap-3 mb-4">
            <h2 class="text-sm font-semibold text-gray-700">Partages individuels</h2>
            <span class="text-xs text-gray-400">Prioritaires sur les droits par rôle</span>
        </div>
        <div class="flex flex-col gap-3">
            @foreach($deptShares->merge($userShares) as $share)
            <div class="share-card"
                 x-data="{
                    can_view: {{ $share->can_view ? 'true' : 'false' }},
                    can_download: {{ $share->can_download ? 'true' : 'false' }},
                    can_edit: {{ $share->can_edit ? 'true' : 'false' }},
                    can_manage: {{ $share->can_manage ? 'true' : 'false' }},
                    saving: false,
                    async save() {
                        this.saving = true;
                        const fd = new FormData();
                        fd.append('_token', document.querySelector('meta[name=csrf-token]').content);
                        fd.append('_method', 'PATCH');
                        if (this.can_view) fd.append('can_view', '1');
                        if (this.can_download) fd.append('can_download', '1');
                        if (this.can_edit) fd.append('can_edit', '1');
                        if (this.can_manage) fd.append('can_manage', '1');
                        await fetch('{{ route('media.albums.permissions.update', [$album, $share]) }}', { method: 'POST', body: fd });
                        this.saving = false;
                        showToast();
                    }
                 }">
                <div class="share-card-info">
                    @if($share->shared_with_type === 'department')
                        <span class="text-xs bg-blue-50 text-blue-600 px-2 py-0.5 rounded-full font-medium">Direction / Service</span>
                        <div class="font-medium text-gray-700 text-sm mt-1">{{ $share->sharedWithDepartment?->name ?? '—' }}</div>
                    @else
                        <span class="text-xs bg-violet-50 text-violet-600 px-2 py-0.5 rounded-full font-medium">Utilisateur</span>
                        <div class="font-medium text-gray-700 text-sm mt-1">{{ $share->sharedWithUser?->name ?? '—' }}</div>
                        <div class="text-xs text-gray-400">{{ $share->sharedWithUser?->email }}</div>
                    @endif
                </div>
                <div class="share-card-toggles">
                    <div class="toggle-wrap">
                        <label class="toggle"><input type="checkbox" x-model="can_view" @change="save()"><span class="toggle-slider"></span></label>
                        <span class="toggle-label">Voir</span>
                    </div>
                    <div class="toggle-wrap">
                        <label class="toggle"><input type="checkbox" x-model="can_download" @change="save()"><span class="toggle-slider"></span></label>
                        <span class="toggle-label">Télécharger</span>
                    </div>
                    <div class="toggle-wrap">
                        <label class="toggle"><input type="checkbox" x-model="can_edit" @change="save()"><span class="toggle-slider"></span></label>
                        <span class="toggle-label">Éditer</span>
                    </div>
                    <div class="toggle-wrap">
                        <label class="toggle"><input type="checkbox" x-model="can_manage" @change="save()"><span class="toggle-slider"></span></label>
                        <span class="toggle-label">Gérer</span>
                    </div>
                </div>
                <div class="share-card-actions">
                    <span x-show="saving" class="text-xs text-gray-400">…</span>
                    <form method="POST" action="{{ route('media.albums.permissions.destroy', [$album, $share]) }}"
                          onsubmit="return confirm('Supprimer ce partage ?')">
                        @csrf @method('DELETE')
                        <button type="submit"
                                class="w-7 h-7 flex items-center justify-center rounded-full text-gray-300 hover:text-red-500 hover:bg-red-50 transition-colors text-sm">
                            ✕
                        </button>
                    </form>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Ajouter un partage --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
        <div class="p-5 border-b border-gray-100">
            <h2 class="text-sm font-semibold text-gray-700">Ajouter un partage</h2>
            <p class="text-xs text-gray-400 mt-1">Partager avec un utilisateur ou une direction / service.</p>
        </div>
        <form method="POST" action="{{ route('media.albums.permissions.store', $album) }}" class="p-5"
              x-data="{ type: 'user' }">
            @csrf
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-5">
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1.5">Type</label>
                    <select name="shared_with_type" x-model="type"
                            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-100 bg-white">
                        <option value="user">Utilisateur</option>
                        <option value="department">Direction / Service</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1.5">Destinataire</label>
                    <select x-show="type === 'user'" name="shared_with_id" x-bind:disabled="type !== 'user'"
                            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-100 bg-white">
                        <option value="">— Choisir un utilisateur —</option>
                        @foreach($users as $u)
                            <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->email }})</option>
                        @endforeach
                    </select>
                    <select x-show="type === 'department'" name="shared_with_id" x-bind:disabled="type !== 'department'"
                            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-100 bg-white">
                        <option value="">— Choisir une direction / service —</option>
                        @foreach($departments as $dept)
                            <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="flex items-center gap-6 mb-5">
                @foreach([['can_view','Voir'],['can_download','Télécharger'],['can_edit','Éditer'],['can_manage','Gérer']] as [$field,$cap])
                <div class="toggle-wrap">
                    <label class="toggle">
                        <input type="checkbox" name="{{ $field }}" value="1" {{ $field === 'can_view' ? 'checked' : '' }}>
                        <span class="toggle-slider"></span>
                    </label>
                    <span class="toggle-label">{{ $cap }}</span>
                </div>
                @endforeach
            </div>
            <button type="submit"
                    class="px-5 py-2 rounded-lg text-white text-sm font-medium hover:opacity-90 transition-opacity"
                    style="background-color: var(--color-primary, #1E3A5F);">
                Partager
            </button>
        </form>
    </div>

</div>

<script>
function showToast() {
    const t = document.getElementById('toast');
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 2000);
}
</script>
@endsection
