@extends('layouts.app')
@section('title', 'Partages — ' . $item->file_name)

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
    .share-card { background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1rem 1.25rem; display: flex; align-items: center; gap: 1rem; flex-wrap: wrap; transition: box-shadow 0.15s ease; }
    .share-card:hover { box-shadow: 0 4px 12px rgba(30,58,95,.08); }
    .share-card-info { flex: 1; min-width: 140px; }
    .share-card-toggles { display: flex; gap: 1rem; }
    .share-card-actions { margin-left: auto; }
    #toast { position: fixed; bottom: 1.5rem; right: 1.5rem; background: #1e3a5f; color: white; padding: 0.6rem 1.2rem; border-radius: 8px; font-size: 0.8rem; font-weight: 500; box-shadow: 0 4px 16px rgba(0,0,0,.2); opacity: 0; transform: translateY(8px); transition: opacity .25s, transform .25s; pointer-events: none; z-index: 50; }
    #toast.show { opacity: 1; transform: translateY(0); }
</style>
@endpush

@section('content')
<div class="max-w-3xl mx-auto px-4 py-8">

    <div id="toast">✓ Droit mis à jour</div>

    <div class="mb-6">
        <a href="{{ route('media.items.show', [$item->album_id, $item]) }}"
           class="text-gray-400 hover:text-gray-600 text-sm">← Retour au média</a>
    </div>

    <h1 class="text-xl font-bold text-gray-800 mb-1">Partages individuels</h1>
    <p class="text-sm text-gray-500 mb-6">
        Partagez <span class="font-medium">{{ $item->file_name }}</span> avec des utilisateurs
        ou directions/services, indépendamment des droits de l'album.
    </p>

    @if(session('success'))
    <div class="mb-4 px-4 py-3 bg-green-50 border border-green-200 rounded-lg text-green-700 text-sm">
        ✓ {{ session('success') }}
    </div>
    @endif

    {{-- Partages actifs --}}
    @if($deptShares->isNotEmpty() || $userShares->isNotEmpty())
    <div class="mb-8">
        <div class="flex items-baseline gap-3 mb-4">
            <h2 class="text-sm font-semibold text-gray-700">Partages actifs</h2>
            <span class="text-xs text-gray-400">Sauvegarde automatique au clic</span>
        </div>
        <div class="flex flex-col gap-3">
            @foreach($deptShares->merge($userShares) as $share)
            <div class="share-card"
                 x-data="{
                    can_view: {{ $share->can_view ? 'true' : 'false' }},
                    can_download: {{ $share->can_download ? 'true' : 'false' }},
                    saving: false,
                    async save() {
                        this.saving = true;
                        const fd = new FormData();
                        fd.append('_token', document.querySelector('meta[name=csrf-token]').content);
                        fd.append('_method', 'PATCH');
                        if (this.can_view) fd.append('can_view', '1');
                        if (this.can_download) fd.append('can_download', '1');
                        await fetch('{{ route('media.items.shares.update', [$item, $share]) }}', { method: 'POST', body: fd });
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
                </div>
                <div class="share-card-actions">
                    <span x-show="saving" class="text-xs text-gray-400 mr-2">…</span>
                    <form method="POST" action="{{ route('media.items.shares.destroy', [$item, $share]) }}"
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
    @else
    <p class="text-sm text-gray-400 mb-8">Aucun partage individuel — accès via les droits de l'album uniquement.</p>
    @endif

    {{-- Ajouter un partage --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
        <div class="p-5 border-b border-gray-100">
            <h2 class="text-sm font-semibold text-gray-700">Ajouter un partage</h2>
        </div>
        <form method="POST" action="{{ route('media.items.shares.store', $item) }}" class="p-5"
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
                <div class="toggle-wrap">
                    <label class="toggle">
                        <input type="checkbox" name="can_view" value="1" checked>
                        <span class="toggle-slider"></span>
                    </label>
                    <span class="toggle-label">Voir</span>
                </div>
                <div class="toggle-wrap">
                    <label class="toggle">
                        <input type="checkbox" name="can_download" value="1">
                        <span class="toggle-slider"></span>
                    </label>
                    <span class="toggle-label">Télécharger</span>
                </div>
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
