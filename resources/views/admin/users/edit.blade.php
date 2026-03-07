@extends('layouts.admin')
@section('title', 'Modifier ' . $user->name)

@section('admin-content')

<div class="text-sm text-gray-500 mb-4">
    <a href="{{ route('admin.users.index') }}" class="hover:underline">Utilisateurs</a>
    <span class="mx-2">›</span><span>{{ $user->name }}</span>
</div>

<div class="bg-white rounded-xl shadow p-6">
    <h1 class="text-xl font-bold text-gray-800 mb-2">Modifier {{ $user->name }}</h1>

    @if($user->ldap_dn)
        <p class="text-sm text-blue-600 mb-6">⚠ Compte LDAP — certains champs sont gérés par l'annuaire</p>
    @endif

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

    <form method="POST" action="{{ route('admin.users.update', $user) }}">
        @csrf @method('PUT')

        {{-- Identité --}}
        <div class="grid grid-cols-2 gap-4 mb-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nom complet</label>
                <input type="text" name="name" value="{{ old('name', $user->name) }}"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2"
                       required>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                <input type="email" value="{{ $user->email }}"
                       class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm bg-gray-50 text-gray-400"
                       disabled>
                <p class="text-xs text-gray-400 mt-1">L'email ne peut pas être modifié</p>
            </div>
        </div>

        {{-- Rôle + Statut --}}
        <div class="grid grid-cols-2 gap-4 mb-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Rôle</label>
                <select name="role" id="roleSelect"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none"
                        onchange="updateDepartmentLabel()">
                    @foreach(App\Enums\UserRole::cases() as $role)
                        <option value="{{ $role->value }}"
                            {{ old('role', $user->role) === $role->value ? 'selected' : '' }}>
                            {{ $role->label() }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Statut</label>
                <select name="status" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none">
                    @foreach(['active' => 'Actif', 'inactive' => 'Inactif', 'locked' => 'Verrouillé'] as $val => $label)
                        <option value="{{ $val }}" {{ old('status', $user->status) === $val ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>

        {{-- Directions & Services --}}
        <div class="mb-6 border rounded-xl p-4 bg-gray-50">
            <div class="flex justify-between items-center mb-3">
                <label class="text-sm font-medium text-gray-700" id="deptLabel">
                    Directions / Services
                </label>
                <button type="button" onclick="toggleNewDept()"
                        class="text-xs text-blue-600 hover:underline">
                    + Créer un nouveau
                </button>
            </div>

            {{-- Arborescence de sélection --}}
            <div class="space-y-1 max-h-56 overflow-y-auto pr-1">
                @forelse($directions as $direction)
                    {{-- Direction --}}
                    <label class="flex items-center gap-2 p-2 rounded-lg hover:bg-white cursor-pointer border border-transparent hover:border-gray-200">
                        <input type="checkbox" name="department_ids[]" value="{{ $direction->id }}"
                               class="rounded border-gray-300"
                               {{ in_array($direction->id, old('department_ids', $userDeptIds)) ? 'checked' : '' }}>
                        <span class="text-sm">🏢 <span class="font-medium text-gray-700">{{ $direction->name }}</span>
                            <span class="text-xs text-gray-400 ml-1">(direction)</span>
                        </span>
                    </label>
                    {{-- Services de cette direction --}}
                    @foreach($services->where('parent_id', $direction->id) as $service)
                        <label class="flex items-center gap-2 p-2 pl-8 rounded-lg hover:bg-white cursor-pointer border border-transparent hover:border-gray-200">
                            <input type="checkbox" name="department_ids[]" value="{{ $service->id }}"
                                   class="rounded border-gray-300"
                                   {{ in_array($service->id, old('department_ids', $userDeptIds)) ? 'checked' : '' }}>
                            <span class="text-sm">📂 {{ $service->name }}
                                <span class="text-xs text-gray-400 ml-1">(service)</span>
                            </span>
                        </label>
                    @endforeach
                @empty
                    <p class="text-xs text-gray-400 italic p-2">
                        Aucune direction créée.
                        <a href="{{ route('admin.departments.index') }}" class="text-blue-500 hover:underline">Créer les directions</a> d'abord.
                    </p>
                @endforelse
            </div>

            {{-- Formulaire inline de création --}}
            <div id="newDeptForm" class="hidden mt-3 pt-3 border-t">
                <p class="text-xs font-medium text-gray-600 mb-2">Créer et affecter un nouveau département :</p>
                <div class="grid grid-cols-2 gap-2 mb-2">
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Type</label>
                        <select name="new_department_type" id="newDeptType"
                                class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-xs"
                                onchange="toggleParentSelect()">
                            <option value="direction">Direction</option>
                            <option value="service">Service</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Nom</label>
                        <input type="text" name="new_department_name"
                               placeholder="Nom du département"
                               class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-xs">
                    </div>
                </div>
                <div id="parentSelect" class="hidden">
                    <label class="block text-xs text-gray-500 mb-1">Direction parente</label>
                    <select name="new_department_parent_id"
                            class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-xs mb-2">
                        <option value="">— Choisir —</option>
                        @foreach($directions as $dir)
                            <option value="{{ $dir->id }}">{{ $dir->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        {{-- Mot de passe (comptes locaux uniquement) --}}
        @if(!$user->ldap_dn)
        <div class="border-t pt-4 mb-6">
            <p class="text-sm font-medium text-gray-700 mb-3">
                Changer le mot de passe
                <span class="text-xs text-gray-400 font-normal">(laisser vide pour ne pas modifier)</span>
            </p>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Nouveau mot de passe</label>
                    <input type="password" name="password"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2">
                </div>
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Confirmer</label>
                    <input type="password" name="password_confirmation"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2">
                </div>
            </div>
        </div>
        @endif

        <div class="flex gap-3">
            <button type="submit"
                    class="px-6 py-2 rounded-lg text-white text-sm font-medium hover:opacity-90 transition"
                    style="background-color: #1E3A5F;">
                Enregistrer
            </button>
            <a href="{{ route('admin.users.index') }}"
               class="px-6 py-2 rounded-lg border border-gray-300 text-sm text-gray-700 hover:bg-gray-50">
                Annuler
            </a>
        </div>
    </form>
</div>

<script>
function updateDepartmentLabel() {
    const role = document.getElementById('roleSelect').value;
    const label = document.getElementById('deptLabel');
    const labels = {
        'admin':          'Accès global — aucune affectation requise',
        'president':      'Accès global — aucune affectation requise',
        'dgs':            'Direction(s) sous responsabilité',
        'resp_direction': 'Direction(s) gérée(s) (responsable)',
        'resp_service':   'Service(s) géré(s) (responsable)',
        'user':           'Service(s) / Direction(s) d\'appartenance',
    };
    label.textContent = labels[role] || 'Directions / Services';
}

function toggleNewDept() {
    document.getElementById('newDeptForm').classList.toggle('hidden');
}

function toggleParentSelect() {
    const type = document.getElementById('newDeptType').value;
    document.getElementById('parentSelect').classList.toggle('hidden', type !== 'service');
}

updateDepartmentLabel();
</script>

@endsection
