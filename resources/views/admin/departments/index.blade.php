@extends('layouts.admin')
@section('title', 'Hiérarchie organisationnelle')

@section('admin-content')

{{-- ── En-tête ─────────────────────────────────────────────────────── --}}
<div class="mb-6">
    <div class="flex justify-between items-start flex-wrap gap-3">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Hiérarchie organisationnelle</h1>
            <p class="text-sm text-gray-500 mt-1">Structure libre — Directions, Services, Pôles, Bureaux…</p>
        </div>
        <a href="{{ route('admin.departments.organigramme') }}" target="_blank"
           class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-white text-sm font-medium hover:opacity-90 transition"
           style="background-color: var(--color-primary, #1E3A5F);">
            🗂 Organigramme
        </a>
    </div>

    {{-- Compteurs --}}
    <div class="grid grid-cols-3 gap-4 mt-4">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg flex items-center justify-center text-xl bg-blue-50">🏢</div>
            <div>
                <p class="text-2xl font-bold text-gray-800">{{ $stats['roots'] }}</p>
                <p class="text-xs text-gray-500">Entité(s) racine</p>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg flex items-center justify-center text-xl bg-green-50">📂</div>
            <div>
                <p class="text-2xl font-bold text-gray-800">{{ $stats['total'] }}</p>
                <p class="text-xs text-gray-500">Total entités</p>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg flex items-center justify-center text-xl bg-orange-50">👥</div>
            <div>
                <p class="text-2xl font-bold text-gray-800">{{ $stats['members'] }}</p>
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

    {{-- ── Colonne gauche : arborescence accordéon ─────────────────── --}}
    <div class="lg:col-span-2 space-y-1">

        {{-- Bouton tout déplier / replier --}}
        @if($roots->count())
        <div class="flex justify-end mb-2">
            <button onclick="toggleAll()" id="toggleAllBtn"
                    class="text-xs px-3 py-1.5 rounded-lg border border-gray-200 text-gray-500 hover:bg-gray-50 transition">
                ▼ Tout déplier
            </button>
        </div>
        @endif

        @forelse($roots as $root)
            @include('admin.departments.partials.dept-node-admin', ['node' => $root, 'depth' => 0, 'allDepts' => $allDepts])
        @empty
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-12 text-center text-gray-400">
                <p class="text-5xl mb-3">🏢</p>
                <p class="text-sm font-medium text-gray-500">Aucune entité créée.</p>
                <p class="text-xs mt-1">Commencez par créer une entité racine dans le formulaire.</p>
            </div>
        @endforelse
    </div>

    {{-- ── Colonne droite : formulaire création ─────────────────────── --}}
    <div class="space-y-4">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <h2 class="text-sm font-semibold text-gray-700 mb-4 pb-2 border-b flex items-center gap-2">
                <span>➕</span> Nouvelle entité
            </h2>
            <form method="POST" action="{{ route('admin.departments.store') }}" class="space-y-3">
                @csrf

                {{-- Nom --}}
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Nom <span class="text-red-500">*</span></label>
                    <input type="text" name="name"
                           value="{{ old('name') }}"
                           placeholder="Ex : Direction des Finances"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-200"
                           required>
                </div>

                {{-- Label libre --}}
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Type / Label</label>
                    <input type="text" name="label" id="labelInput"
                           value="{{ old('label') }}"
                           placeholder="Direction, Pôle, Comité…"
                           maxlength="100"
                           list="label-suggestions"
                           autocomplete="off"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-200">
                    <datalist id="label-suggestions">
                        @foreach($labelSuggestions as $suggestion)
                            <option value="{{ $suggestion }}">
                        @endforeach
                    </datalist>
                    <p class="text-xs text-gray-400 mt-1">Affiché dans l'organigramme. Les labels déjà utilisés sont suggérés automatiquement.</p>
                </div>

                {{-- Couleur --}}
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Couleur (optionnel)</label>
                    <div class="flex items-center gap-2">
                        <input type="color" name="color"
                               value="{{ old('color', '#1E3A5F') }}"
                               class="w-10 h-9 rounded border border-gray-300 cursor-pointer p-0.5">
                        <span class="text-xs text-gray-400">Appliquée sur le nœud de l'organigramme.</span>
                    </div>
                </div>

                {{-- Rattaché à --}}
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Rattaché à (optionnel)</label>
                    <select name="parent_id"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-200">
                        <option value="">— Aucun (entité racine) —</option>
                        @foreach($allDepts as $dept)
                            <option value="{{ $dept->id }}" {{ old('parent_id') == $dept->id ? 'selected' : '' }}>
                                {{ str_repeat('  ', $dept->depth ?? 0) }}{{ $dept->label ? '[' . $dept->label . '] ' : '' }}{{ $dept->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Transversal --}}
                <div class="flex items-center gap-2">
                    <input type="hidden" name="is_transversal" value="0">
                    <input type="checkbox" name="is_transversal" id="isTransversal" value="1"
                           {{ old('is_transversal') ? 'checked' : '' }}
                           class="w-4 h-4 rounded border-gray-300 text-blue-600">
                    <label for="isTransversal" class="text-xs text-gray-600">
                        Entité transversale
                        <span class="text-gray-400">(hors hiérarchie stricte)</span>
                    </label>
                </div>

                {{-- Ordre --}}
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Ordre d'affichage</label>
                    <input type="number" name="sort_order"
                           value="{{ old('sort_order', 0) }}"
                           min="0" max="999"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-200">
                </div>

                <button type="submit"
                        class="w-full py-2.5 rounded-lg text-white text-sm font-medium hover:opacity-90 transition mt-1"
                        style="background-color: #1E3A5F;">
                    Créer l'entité
                </button>
            </form>
        </div>

        {{-- Info --}}
        <div class="bg-gray-50 rounded-xl border border-gray-100 p-4 text-xs text-gray-500 space-y-1">
            <p class="font-medium text-gray-600 mb-2">ℹ️ Liberté de structure</p>
            <p>• Créez n'importe quelle hiérarchie : Pôle → Direction → Service → Bureau.</p>
            <p>• Aucune restriction sur le type ou la profondeur.</p>
            <p>• Un nœud transversal est affiché avec un marqueur visuel distinct.</p>
            <p>• Impossible de supprimer un nœud avec des membres ou des enfants.</p>
        </div>
    </div>

</div>

{{-- ═══════════════════════════════════════════════════════════════ --}}
{{-- Modal modification                                             --}}
{{-- ═══════════════════════════════════════════════════════════════ --}}
<div id="editModal" class="hidden fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-md mx-4">
        <div class="flex justify-between items-center px-6 py-4 border-b">
            <h3 class="text-base font-semibold text-gray-800">Modifier l'entité</h3>
            <button onclick="closeEditModal()" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
        </div>
        <form method="POST" id="editForm" class="px-6 py-5 space-y-4">
            @csrf @method('PUT')

            {{-- Nom --}}
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Nom <span class="text-red-500">*</span></label>
                <input type="text" name="name" id="editName"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-200"
                       required>
            </div>

            {{-- Label --}}
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Type / Label</label>
                <input type="text" name="label" id="editLabel"
                       maxlength="100"
                       placeholder="Direction, Pôle, Comité…"
                       list="label-suggestions"
                       autocomplete="off"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-200">
                {{-- datalist partagé avec le formulaire de création --}}
            </div>

            {{-- Couleur --}}
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Couleur</label>
                <div class="flex items-center gap-2">
                    <input type="color" name="color" id="editColor"
                           class="w-10 h-9 rounded border border-gray-300 cursor-pointer p-0.5">
                    <button type="button" onclick="document.getElementById('editColor').value='#1E3A5F'"
                            class="text-xs text-gray-400 hover:text-gray-600 underline">
                        Réinitialiser
                    </button>
                </div>
            </div>

            {{-- Rattaché à --}}
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Rattaché à</label>
                <select name="parent_id" id="editParentId"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-200">
                    <option value="">— Aucun (entité racine) —</option>
                    @foreach($allDepts as $dept)
                        <option value="{{ $dept->id }}">
                            {{ $dept->label ? '[' . $dept->label . '] ' : '' }}{{ $dept->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Transversal --}}
            <div class="flex items-center gap-2">
                <input type="hidden" name="is_transversal" value="0">
                <input type="checkbox" name="is_transversal" id="editTransversal" value="1"
                       class="w-4 h-4 rounded border-gray-300 text-blue-600">
                <label for="editTransversal" class="text-xs text-gray-600">Entité transversale</label>
            </div>

            {{-- Ordre --}}
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Ordre d'affichage</label>
                <input type="number" name="sort_order" id="editSortOrder"
                       min="0" max="999"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-200">
            </div>

            <div class="flex gap-3 pt-2">
                <button type="submit"
                        class="flex-1 py-2.5 rounded-lg text-white text-sm font-medium hover:opacity-90 transition"
                        style="background-color: #1E3A5F;">
                    Enregistrer
                </button>
                <button type="button" onclick="closeEditModal()"
                        class="flex-1 py-2.5 rounded-lg border border-gray-300 text-sm text-gray-600 hover:bg-gray-50 transition">
                    Annuler
                </button>
            </div>
        </form>
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════════════ --}}
{{-- Scripts                                                        --}}
{{-- ═══════════════════════════════════════════════════════════════ --}}
<script>
// ─── Modal modification ───────────────────────────────────────────────
function openEditModal(id, name, label, color, parentId, isTransversal, sortOrder) {
    document.getElementById('editForm').action = '/admin/departments/' + id;
    document.getElementById('editName').value        = name || '';
    document.getElementById('editLabel').value       = label || '';
    document.getElementById('editColor').value       = color || '#1E3A5F';
    document.getElementById('editSortOrder').value   = sortOrder || 0;
    document.getElementById('editTransversal').checked = !!isTransversal;

    const parentSelect = document.getElementById('editParentId');
    parentSelect.value = parentId || '';

    document.getElementById('editModal').classList.remove('hidden');
    setTimeout(() => document.getElementById('editName').focus(), 50);
}

function closeEditModal() {
    document.getElementById('editModal').classList.add('hidden');
}

// Fermeture au clic sur le fond
document.getElementById('editModal').addEventListener('click', function(e) {
    if (e.target === this) closeEditModal();
});

// ─── Accordéon — tout déplier / replier ──────────────────────────────
let allExpanded = false;
function toggleAll() {
    allExpanded = !allExpanded;
    document.querySelectorAll('.dept-children').forEach(el => {
        el.style.display = allExpanded ? 'block' : 'none';
    });
    document.querySelectorAll('.dept-toggle').forEach(el => {
        el.textContent = allExpanded ? '▼' : '▶';
    });
    document.getElementById('toggleAllBtn').textContent =
        allExpanded ? '▲ Tout replier' : '▼ Tout déplier';
}

function toggleNode(btn) {
    const children = btn.closest('.dept-header').nextElementSibling;
    if (!children || !children.classList.contains('dept-children')) return;
    const isOpen = children.style.display !== 'none';
    children.style.display = isOpen ? 'none' : 'block';
    btn.textContent = isOpen ? '▶' : '▼';
}
</script>

@endsection
