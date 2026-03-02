@extends('layouts.admin')
@section('title', 'Directions & Services')

@section('admin-content')

<div class="flex justify-between items-center mb-6">
    <h1 class="text-2xl font-bold text-gray-800">Directions & Services</h1>
</div>

@if(session('success'))
    <div class="bg-green-50 border border-green-300 text-green-700 rounded-lg p-3 mb-4 text-sm">
        ✓ {{ session('success') }}
    </div>
@endif

@if($errors->any())
    <div class="bg-red-50 border border-red-300 text-red-700 rounded-lg p-3 mb-4 text-sm">
        {{ $errors->first() }}
    </div>
@endif

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    {{-- Colonne gauche : arborescence --}}
    <div class="lg:col-span-2 space-y-4">
        @forelse($directions as $direction)
        <div class="bg-white rounded-xl shadow overflow-hidden">
            {{-- En-tête direction --}}
            <div class="px-5 py-4 flex justify-between items-center border-b"
                 style="background-color: #EFF6FF;">
                <div class="flex items-center gap-3">
                    <span class="text-lg">🏢</span>
                    <div>
                        <p class="font-semibold text-gray-800">{{ $direction->name }}</p>
                        <p class="text-xs text-gray-500">
                            {{ $direction->members_count }} membre(s)
                            @if($direction->managers->count())
                                — Resp. : {{ $direction->managers->pluck('name')->join(', ') }}
                            @endif
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <button onclick="openEditModal('direction', {{ $direction->id }}, '{{ addslashes($direction->name) }}', null)"
                            class="text-xs px-2 py-1 rounded border border-gray-300 text-gray-500 hover:bg-gray-50">
                        Renommer
                    </button>
                    <form method="POST" action="{{ route('admin.departments.destroy', $direction) }}"
                          onsubmit="return confirm('Supprimer cette direction ?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="text-xs px-2 py-1 rounded border border-red-200 text-red-500 hover:bg-red-50">
                            Supprimer
                        </button>
                    </form>
                </div>
            </div>

            {{-- Services de cette direction --}}
            @if($direction->children->count())
            <div class="divide-y divide-gray-50">
                @foreach($direction->children as $service)
                <div class="px-5 py-3 flex justify-between items-center pl-10 hover:bg-gray-50">
                    <div class="flex items-center gap-3">
                        <span class="text-base">📂</span>
                        <div>
                            <p class="text-sm font-medium text-gray-700">{{ $service->name }}</p>
                            <p class="text-xs text-gray-400">
                                {{ $service->members->count() }} membre(s)
                                @if($service->managers->count())
                                    — Resp. : {{ $service->managers->pluck('name')->join(', ') }}
                                @endif
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <button onclick="openEditModal('service', {{ $service->id }}, '{{ addslashes($service->name) }}', {{ $direction->id }})"
                                class="text-xs px-2 py-1 rounded border border-gray-300 text-gray-500 hover:bg-gray-50">
                            Renommer
                        </button>
                        <form method="POST" action="{{ route('admin.departments.destroy', $service) }}"
                              onsubmit="return confirm('Supprimer ce service ?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-xs px-2 py-1 rounded border border-red-200 text-red-500 hover:bg-red-50">
                                Supprimer
                            </button>
                        </form>
                    </div>
                </div>
                @endforeach
            </div>
            @else
            <p class="px-10 py-3 text-xs text-gray-400 italic">Aucun service dans cette direction.</p>
            @endif
        </div>
        @empty
        <div class="bg-white rounded-xl shadow p-8 text-center text-gray-400">
            <p class="text-4xl mb-2">🏢</p>
            <p class="text-sm">Aucune direction créée.</p>
            <p class="text-xs mt-1">Commencez par créer une direction dans le formulaire.</p>
        </div>
        @endforelse
    </div>

    {{-- Colonne droite : formulaires --}}
    <div class="space-y-4">

        {{-- Nouvelle direction --}}
        <div class="bg-white rounded-xl shadow p-5">
            <h2 class="text-sm font-semibold text-gray-700 mb-3 pb-2 border-b">
                ➕ Nouvelle direction
            </h2>
            <form method="POST" action="{{ route('admin.departments.store') }}">
                @csrf
                <input type="hidden" name="type" value="direction">
                <input type="text" name="name" placeholder="Nom de la direction"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm mb-3 focus:outline-none focus:ring-2"
                       required>
                <button type="submit"
                        class="w-full py-2 rounded-lg text-white text-sm font-medium hover:opacity-90 transition"
                        style="background-color: #1E3A5F;">
                    Créer la direction
                </button>
            </form>
        </div>

        {{-- Nouveau service --}}
        <div class="bg-white rounded-xl shadow p-5">
            <h2 class="text-sm font-semibold text-gray-700 mb-3 pb-2 border-b">
                ➕ Nouveau service
            </h2>
            <form method="POST" action="{{ route('admin.departments.store') }}">
                @csrf
                <input type="hidden" name="type" value="service">
                <div class="mb-3">
                    <label class="block text-xs text-gray-500 mb-1">Direction parente</label>
                    <select name="parent_id"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none"
                            required>
                        <option value="">— Choisir une direction —</option>
                        @foreach($directions as $dir)
                            <option value="{{ $dir->id }}">{{ $dir->name }}</option>
                        @endforeach
                    </select>
                </div>
                <input type="text" name="name" placeholder="Nom du service"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm mb-3 focus:outline-none focus:ring-2"
                       required>
                <button type="submit"
                        class="w-full py-2 rounded-lg text-white text-sm font-medium hover:opacity-90 transition"
                        style="background-color: #1E3A5F;">
                    Créer le service
                </button>
            </form>
        </div>

    </div>
</div>

{{-- Modal renommer --}}
<div id="editModal" class="hidden fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-lg p-6 w-full max-w-sm">
        <h3 class="text-base font-semibold text-gray-800 mb-4" id="editModalTitle">Renommer</h3>
        <form method="POST" id="editForm">
            @csrf @method('PUT')
            <input type="hidden" name="parent_id" id="editParentId">
            <input type="text" name="name" id="editName"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm mb-4 focus:outline-none focus:ring-2"
                   required>
            <div class="flex gap-3">
                <button type="submit"
                        class="flex-1 py-2 rounded-lg text-white text-sm font-medium"
                        style="background-color: #1E3A5F;">
                    Enregistrer
                </button>
                <button type="button" onclick="closeEditModal()"
                        class="flex-1 py-2 rounded-lg border border-gray-300 text-sm text-gray-600">
                    Annuler
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditModal(type, id, name, parentId) {
    document.getElementById('editModalTitle').textContent = 'Renommer ' + (type === 'direction' ? 'la direction' : 'le service');
    document.getElementById('editForm').action = '/admin/departments/' + id;
    document.getElementById('editName').value = name;
    document.getElementById('editParentId').value = parentId || '';
    document.getElementById('editModal').classList.remove('hidden');
}
function closeEditModal() {
    document.getElementById('editModal').classList.add('hidden');
}
</script>

@endsection
