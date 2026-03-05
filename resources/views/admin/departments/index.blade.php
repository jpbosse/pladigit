@extends('layouts.admin')
@section('title', 'Directions & Services')

@section('admin-content')

{{-- ── En-tête + compteurs ─────────────────────────────────────────── --}}
<div class="mb-6">
    <div class="flex justify-between items-start">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Directions & Services</h1>
            <p class="text-sm text-gray-500 mt-1">Structure organisationnelle du tenant</p>
        </div>
        <a href="{{ route('admin.admin.departments.organigramme') }}" target="_blank"
           class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-white text-sm font-medium"
           style="background-color: var(--color-primary, #1E3A5F);">
            🖨 Organigramme
        </a>
        <div class="hidden">
        </div>
    </div>

    {{-- Compteurs --}}
    <div class="grid grid-cols-3 gap-4 mt-4">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg flex items-center justify-center text-xl" style="background-color: #EFF6FF;">🏢</div>
            <div>
                <p class="text-2xl font-bold text-gray-800">{{ $directions->count() }}</p>
                <p class="text-xs text-gray-500">Direction(s)</p>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg flex items-center justify-center text-xl" style="background-color: #F0FDF4;">📂</div>
            <div>
                <p class="text-2xl font-bold text-gray-800">{{ $directions->sum(fn($d) => $d->children->count()) }}</p>
                <p class="text-xs text-gray-500">Service(s)</p>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg flex items-center justify-center text-xl" style="background-color: #FFF7ED;">👥</div>
            <div>
                <p class="text-2xl font-bold text-gray-800">{{ $directions->sum('members_count') }}</p>
                <p class="text-xs text-gray-500">Membre(s) total</p>
            </div>
        </div>
    </div>
</div>

{{-- ── Alertes ──────────────────────────────────────────────────────── --}}
@if(session('success'))
    <div class="bg-green-50 border border-green-300 text-green-700 rounded-lg p-3 mb-4 text-sm flex items-center gap-2">
        <span>✓</span> {{ session('success') }}
    </div>
@endif
@if($errors->has('delete'))
    <div class="bg-red-50 border border-red-300 text-red-700 rounded-lg p-3 mb-4 text-sm flex items-center gap-2">
        <span>⚠</span> {{ $errors->first('delete') }}
    </div>
@elseif($errors->any())
    <div class="bg-red-50 border border-red-300 text-red-700 rounded-lg p-3 mb-4 text-sm flex items-center gap-2">
        <span>⚠</span> {{ $errors->first() }}
    </div>
@endif

{{-- ── Contenu principal ────────────────────────────────────────────── --}}
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    {{-- Colonne gauche : arborescence --}}
    <div class="lg:col-span-2 space-y-4">
        @forelse($directions as $direction)
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">

            {{-- En-tête direction --}}
            <div class="px-5 py-4 flex justify-between items-center"
                 style="background: linear-gradient(135deg, #EFF6FF 0%, #DBEAFE 100%); border-bottom: 1px solid #BFDBFE;">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-lg bg-white shadow-sm flex items-center justify-center text-lg">🏢</div>
                    <div>
                        <p class="font-semibold text-gray-800">{{ $direction->name }}</p>
                        <div class="flex items-center gap-2 mt-0.5">
                            <span class="inline-flex items-center gap-1 text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full font-medium">
                                👥 {{ $direction->members_count }} membre(s)
                            </span>
                            @if($direction->managers->count())
                                <span class="text-xs text-gray-500">
                                    Resp. : {{ $direction->managers->pluck('name')->join(', ') }}
                                </span>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <button onclick="openMembersModal({{ $direction->id }}, '{{ addslashes($direction->name) }}')"
                            class="text-xs px-3 py-1.5 rounded-lg bg-white border border-blue-200 text-blue-600 hover:bg-blue-50 font-medium transition">
                        👥 Membres
                    </button>
                    <button onclick="openEditModal('direction', {{ $direction->id }}, '{{ addslashes($direction->name) }}', null)"
                            class="text-xs px-3 py-1.5 rounded-lg bg-white border border-gray-200 text-gray-600 hover:bg-gray-50 font-medium transition">
                        ✏️ Renommer
                    </button>
                    <form method="POST" action="{{ route('admin.departments.destroy', $direction) }}"
                          onsubmit="return confirm('Supprimer la direction ?')">
                        @csrf @method('DELETE')
                        <button type="submit"
                                class="text-xs px-3 py-1.5 rounded-lg bg-white border border-red-200 text-red-500 hover:bg-red-50 font-medium transition">
                            🗑 Supprimer
                        </button>
                    </form>
                </div>
            </div>

            {{-- Services --}}
            @if($direction->children->count())
            <div class="divide-y divide-gray-50">
                @foreach($direction->children as $service)
                <div class="px-5 py-3 flex justify-between items-center hover:bg-gray-50 transition group"
                     style="padding-left: 2.5rem;">
                    <div class="flex items-center gap-3">
                        <div class="flex items-center gap-2">
                            <div class="w-4 h-px bg-gray-200"></div>
                            <div class="w-8 h-8 rounded-lg bg-gray-50 border border-gray-100 flex items-center justify-center text-sm">📂</div>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-700">{{ $service->name }}</p>
                            <div class="flex items-center gap-2 mt-0.5">
                                <span class="inline-flex items-center gap-1 text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full">
                                    👥 {{ $service->members->count() }} membre(s)
                                </span>
                                @if($service->managers->count())
                                    <span class="text-xs text-gray-400">
                                        Resp. : {{ $service->managers->pluck('name')->join(', ') }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 opacity-0 group-hover:opacity-100 transition">
                        <button onclick="openMembersModal({{ $service->id }}, '{{ addslashes($service->name) }}')"
                                class="text-xs px-2.5 py-1 rounded-lg border border-blue-200 text-blue-600 hover:bg-blue-50 font-medium transition">
                            👥 Membres
                        </button>
                        <button onclick="openEditModal('service', {{ $service->id }}, '{{ addslashes($service->name) }}', {{ $direction->id }})"
                                class="text-xs px-2.5 py-1 rounded-lg border border-gray-200 text-gray-600 hover:bg-gray-50 font-medium transition">
                            ✏️
                        </button>
                        <form method="POST" action="{{ route('admin.departments.destroy', $service) }}"
                              onsubmit="return confirm('Supprimer ce service ?')">
                            @csrf @method('DELETE')
                            <button type="submit"
                                    class="text-xs px-2.5 py-1 rounded-lg border border-red-200 text-red-500 hover:bg-red-50 font-medium transition">
                                🗑
                            </button>
                        </form>
                    </div>
                </div>
                @endforeach
            </div>
            @else
            <div class="px-10 py-4 flex items-center gap-2 text-xs text-gray-400 italic">
                <div class="w-4 h-px bg-gray-200"></div>
                Aucun service dans cette direction.
            </div>
            @endif

        </div>
        @empty
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-12 text-center text-gray-400">
            <p class="text-5xl mb-3">🏢</p>
            <p class="text-sm font-medium text-gray-500">Aucune direction créée.</p>
            <p class="text-xs mt-1">Commencez par créer une direction dans le formulaire.</p>
        </div>
        @endforelse
    </div>

    {{-- Colonne droite : formulaires --}}
    <div class="space-y-4">

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <h2 class="text-sm font-semibold text-gray-700 mb-3 pb-2 border-b flex items-center gap-2">
                <span>🏢</span> Nouvelle direction
            </h2>
            <form method="POST" action="{{ route('admin.departments.store') }}">
                @csrf
                <input type="hidden" name="type" value="direction">
                <input type="text" name="name" placeholder="Nom de la direction"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm mb-3 focus:outline-none focus:ring-2 focus:ring-blue-200"
                       required>
                <div class="mb-3">
                    <label class="block text-xs text-gray-500 mb-1">Direction parente (optionnel)</label>
                    <select name="parent_id"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-200">
                        <option value="">— Aucune (direction racine) —</option>
                        @foreach($directions as $dir)
                            <option value="{{ $dir->id }}">{{ $dir->name }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit"
                        class="w-full py-2 rounded-lg text-white text-sm font-medium hover:opacity-90 transition"
                        style="background-color: #1E3A5F;">
                    Créer la direction
                </button>
            </form>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <h2 class="text-sm font-semibold text-gray-700 mb-3 pb-2 border-b flex items-center gap-2">
                <span>📂</span> Nouveau service
            </h2>
            <form method="POST" action="{{ route('admin.departments.store') }}">
                @csrf
                <input type="hidden" name="type" value="service">
                <div class="mb-3">
                    <label class="block text-xs text-gray-500 mb-1">Direction parente</label>
                    <select name="parent_id"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-200"
                            required>
                        <option value="">— Choisir une direction —</option>
                        @foreach($directions as $dir)
                            <option value="{{ $dir->id }}">{{ $dir->name }}</option>
                        @endforeach
                    </select>
                </div>
                <input type="text" name="name" placeholder="Nom du service"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm mb-3 focus:outline-none focus:ring-2 focus:ring-blue-200"
                       required>
                <button type="submit"
                        class="w-full py-2 rounded-lg text-white text-sm font-medium hover:opacity-90 transition"
                        style="background-color: #1E3A5F;">
                    Créer le service
                </button>
            </form>
        </div>

        <div class="bg-gray-50 rounded-xl border border-gray-100 p-4 text-xs text-gray-500 space-y-1">
            <p class="font-medium text-gray-600 mb-2">ℹ️ Règles</p>
            <p>• Un service doit appartenir à une direction.</p>
            <p>• Impossible de supprimer une direction contenant des services.</p>
            <p>• Impossible de supprimer un département avec des membres.</p>
        </div>

    </div>
</div>

{{-- Modal renommer --}}
<div id="editModal" class="hidden fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-xl p-6 w-full max-w-sm mx-4">
        <h3 class="text-base font-semibold text-gray-800 mb-4" id="editModalTitle">Renommer</h3>
        <form method="POST" id="editForm">
            @csrf @method('PUT')
            <input type="hidden" name="parent_id" id="editParentId">
            <input type="text" name="name" id="editName"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm mb-4 focus:outline-none focus:ring-2 focus:ring-blue-200"
                   required>
            <div class="flex gap-3">
                <button type="submit"
                        class="flex-1 py-2 rounded-lg text-white text-sm font-medium"
                        style="background-color: #1E3A5F;">
                    Enregistrer
                </button>
                <button type="button" onclick="closeEditModal()"
                        class="flex-1 py-2 rounded-lg border border-gray-300 text-sm text-gray-600 hover:bg-gray-50">
                    Annuler
                </button>
            </div>
        </form>
    </div>
</div>

{{-- Modal membres --}}
<div id="membersModal" class="hidden fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-xl p-6 w-full max-w-md mx-4">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-base font-semibold text-gray-800" id="membersModalTitle">Membres</h3>
            <button onclick="closeMembersModal()" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
        </div>
        <div id="membersModalContent" class="text-sm text-gray-600 min-h-16 max-h-80 overflow-y-auto"></div>
        <div class="flex justify-end mt-4 pt-4 border-t">
            <button onclick="closeMembersModal()"
                    class="px-4 py-2 rounded-lg border border-gray-300 text-sm text-gray-600 hover:bg-gray-50">
                Fermer
            </button>
        </div>
    </div>
</div>

<script>
function openEditModal(type, id, name, parentId) {
    document.getElementById('editModalTitle').textContent =
        'Renommer ' + (type === 'direction' ? 'la direction' : 'le service');
    document.getElementById('editForm').action = '/admin/departments/' + id;
    document.getElementById('editName').value = name;
    document.getElementById('editParentId').value = parentId || '';
    document.getElementById('editModal').classList.remove('hidden');
    setTimeout(() => document.getElementById('editName').focus(), 50);
}
function closeEditModal() {
    document.getElementById('editModal').classList.add('hidden');
}

const departmentsData = {!! json_encode($departmentsJson) !!};
