@extends('layouts.super-admin')
@section('title', 'Colonne — ' . $column->name)

@section('content')
<div style="max-width:680px;">

    {{-- ── En-tête ──────────────────────────────────────────────────────── --}}
    <div class="mb-6">
        <a href="{{ route('super-admin.datagrids.edit', [$org, $table->id]) }}"
           class="text-xs text-gray-400 no-underline hover:text-gray-600">
            ← Retour à la grille
        </a>
        <div class="flex items-center gap-3 mt-3 flex-wrap">
            <h1 class="text-xl font-bold m-0" style="font-family:'Sora',sans-serif;">
                <code class="font-mono text-base bg-gray-100 border border-gray-200 rounded px-2 py-1">{{ $column->name }}</code>
            </h1>
            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-500">
                {{ $column->type->label() }}
            </span>
            @if($column->required)
            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-700">
                Obligatoire
            </span>
            @endif
        </div>
        <div class="text-xs text-gray-400 mt-1">{{ $table->label }} — {{ $org->name }}</div>
    </div>

    {{-- ── Feedback ─────────────────────────────────────────────────────── --}}
    <div id="badge-ok" class="hidden mb-4 px-4 py-3 bg-green-50 border border-green-200 rounded-lg text-sm font-semibold text-green-800">
        ✓ Modifications enregistrées
    </div>
    <div id="badge-err" class="hidden mb-4 px-4 py-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-600"></div>

    {{-- ── Bloc 1 : Identité ────────────────────────────────────────────── --}}
    <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm mb-4">
        <h2 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-4 pb-2 border-b">① Identité</h2>

        <div class="mb-4">
            <label class="block text-xs font-semibold text-gray-700 mb-1">
                Nom technique <span class="font-normal text-gray-400">(colonne MySQL)</span>
            </label>
            <input id="f-name" type="text" value="{{ $column->name }}" pattern="[a-z][a-z0-9_]*"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-blue-200">
            <div id="err-name" class="hidden text-xs text-red-600 mt-1"></div>
            <div class="flex items-center gap-2 mt-2 px-3 py-2 bg-amber-50 border border-amber-200 rounded-lg">
                <span class="text-sm">⚠️</span>
                <span class="text-xs text-amber-800">Renommer la colonne MySQL est irréversible sans sauvegarde préalable.</span>
            </div>
        </div>

        <div>
            <label class="block text-xs font-semibold text-gray-700 mb-1">
                Label <span class="font-normal text-gray-400">(affiché aux utilisateurs)</span>
            </label>
            <input id="f-label" type="text" value="{{ $column->label }}"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-200">
            <div id="err-label" class="hidden text-xs text-red-600 mt-1"></div>
        </div>
    </div>

    {{-- ── Bloc 2 : Type et format ──────────────────────────────────────── --}}
    <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm mb-4">
        <h2 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-4 pb-2 border-b">② Type et format</h2>

        <div class="mb-4">
            <label class="block text-xs font-semibold text-gray-700 mb-1">Type de données</label>
            <select id="f-type" onchange="toggleLength()"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-200">
                @foreach(\App\Enums\DatagridColumnType::options() as $val => $lbl)
                <option value="{{ $val }}" {{ $column->type->value === $val ? 'selected' : '' }}>{{ $lbl }}</option>
                @endforeach
            </select>
            <div id="err-type" class="hidden text-xs text-red-600 mt-1"></div>
        </div>

        <div id="block-length" class="{{ $column->type->hasLength() ? '' : 'hidden' }} mb-4">
            <label class="block text-xs font-semibold text-gray-700 mb-1">
                Longueur max <span class="font-normal text-gray-400">(nombre de caractères)</span>
            </label>
            <input id="f-length" type="number" min="1" max="65535" value="{{ $column->length ?? '' }}" placeholder="255"
                   class="w-32 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-200">
        </div>

        <div>
            <label class="block text-xs font-semibold text-gray-700 mb-1">
                Ordre d'affichage <span class="font-normal text-gray-400">(0 = en premier)</span>
            </label>
            <input id="f-sort" type="number" min="0" value="{{ $column->sort_order }}"
                   class="w-24 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-200">
        </div>
    </div>

    {{-- ── Bloc 3 : Onglet ─────────────────────────────────────────────── --}}
    <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm mb-4">
        <h2 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-4 pb-2 border-b">③ Onglet dans la fiche</h2>

        <div class="flex gap-3">
            <label id="card-main"
                   class="flex-1 flex items-center gap-3 p-3 rounded-lg border-2 cursor-pointer transition-colors"
                   style="border-color:{{ ($column->tab ?? 'main') === 'main' ? 'var(--pd-navy,#1e3a5f)' : '#e5e7eb' }};">
                <input type="radio" name="tab" id="tab-main" value="main"
                       {{ ($column->tab ?? 'main') === 'main' ? 'checked' : '' }}
                       onchange="selectTab('main')"
                       class="accent-blue-800">
                <div>
                    <div class="text-sm font-semibold text-gray-800">Données principales</div>
                    <div class="text-xs text-gray-400">Premier onglet, toujours visible</div>
                </div>
            </label>
            <label id="card-extra"
                   class="flex-1 flex items-center gap-3 p-3 rounded-lg border-2 cursor-pointer transition-colors"
                   style="border-color:{{ ($column->tab ?? 'main') === 'extra' ? '#7c3aed' : '#e5e7eb' }};">
                <input type="radio" name="tab" id="tab-extra" value="extra"
                       {{ ($column->tab ?? 'main') === 'extra' ? 'checked' : '' }}
                       onchange="selectTab('extra')"
                       style="accent-color:#7c3aed;">
                <div>
                    <div class="text-sm font-semibold text-gray-800">Complémentaires</div>
                    <div class="text-xs text-gray-400">Affiché si au moins une colonne</div>
                </div>
            </label>
        </div>
    </div>

    {{-- ── Bloc 4 : Options ────────────────────────────────────────────── --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm mb-6 overflow-hidden">
        <h2 class="text-xs font-bold text-gray-400 uppercase tracking-wider px-5 py-3 border-b bg-gray-50">④ Options</h2>

        <label class="flex items-center justify-between px-5 py-4 cursor-pointer border-b hover:bg-gray-50 transition-colors">
            <div>
                <div class="text-sm font-semibold text-gray-800">Champ obligatoire</div>
                <div class="text-xs text-gray-400">L'utilisateur doit renseigner ce champ avant d'enregistrer</div>
            </div>
            <input id="f-required" type="checkbox" {{ $column->required ? 'checked' : '' }}
                   class="w-4 h-4 cursor-pointer accent-blue-800">
        </label>

        <label class="flex items-center justify-between px-5 py-4 cursor-pointer border-b hover:bg-gray-50 transition-colors">
            <div>
                <div class="text-sm font-semibold text-gray-800">Visible par défaut</div>
                <div class="text-xs text-gray-400">Colonne affichée dans la grille sans action de l'utilisateur</div>
            </div>
            <input id="f-visible" type="checkbox" {{ $column->visible_by_default ? 'checked' : '' }}
                   class="w-4 h-4 cursor-pointer accent-blue-800">
        </label>

        <label class="flex items-center justify-between px-5 py-4 cursor-pointer hover:bg-gray-50 transition-colors">
            <div>
                <div class="text-sm font-semibold text-gray-800">Donnée RGPD sensible</div>
                <div class="text-xs text-gray-400">Badge RGPD affiché dans la fiche, tracé dans l'audit log</div>
            </div>
            <input id="f-rgpd" type="checkbox" {{ $column->is_rgpd_sensitive ? 'checked' : '' }}
                   class="w-4 h-4 cursor-pointer" style="accent-color:#dc2626;">
        </label>

    </div>


    {{-- ── Bloc fuzzy (NOM_PERSONNE) ────────────────────────────────── --}}
    <div id="block-fuzzy"
         class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm mb-4
                 {{ $column->type->value !== 'nom_personne' ? 'hidden' : '' }}">
        <h2 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3">⑤ Recherche floue</h2>
        <label class="flex items-center gap-3 cursor-pointer">
            <input id="f-fuzzy" type="checkbox" {{ $column->fuzzy_search ? 'checked' : '' }}
                   class="w-4 h-4 cursor-pointer accent-blue-800">
            <div>
                <div class="text-sm font-semibold text-gray-800">Activer la recherche floue (Levenshtein)</div>
                <div class="text-xs text-gray-400">
                    Tolère les variantes orthographiques (Dupond/Dupont, distance ≤ 2).
                    Active aussi la détection de doublons à l'import.
                    Désactivé par défaut pour préserver les performances.
                </div>
            </div>
        </label>
    </div>

    {{-- ── Actions ──────────────────────────────────────────────────────── --}}
    <div class="flex items-center justify-between gap-3 flex-wrap pb-6">
        <button onclick="saveColumn()" type="button"
                style="background:var(--sa-primary,#1e3a5f);"
                class="px-6 py-2 text-white rounded-lg text-sm font-semibold cursor-pointer border-0">
            ✓ Enregistrer les modifications
        </button>
        <form method="POST"
              action="{{ route('super-admin.datagrids.columns.destroy', [$org, $table->id, $column->id]) }}"
              onsubmit="return confirm('Supprimer la colonne « {{ $column->name }} » et toutes ses données ?')">
            @csrf @method('DELETE')
            <button type="submit"
                    class="px-4 py-2 border border-red-300 rounded-lg text-sm font-semibold text-red-600 bg-red-50 cursor-pointer">
                🗑 Supprimer cette colonne
            </button>
        </form>
    </div>

</div>

<script>
(function () {
    var hasLengthTypes = @json(
        collect(\App\Enums\DatagridColumnType::cases())
            ->filter(fn ($c) => $c->hasLength())
            ->map(fn ($c) => $c->value)
            ->values()
    );

    window.toggleLength = function () {
        var t = document.getElementById('f-type').value;
        var el = document.getElementById('block-length');
        el.classList.toggle('hidden', !hasLengthTypes.includes(t));
        // Afficher la case fuzzy uniquement pour NOM_PERSONNE
        var blockFuzzy = document.getElementById('block-fuzzy');
        if (blockFuzzy) { blockFuzzy.classList.toggle('hidden', t !== 'nom_personne'); }
    };

    window.selectTab = function (val) {
        document.getElementById('card-main').style.borderColor  =
            val === 'main'  ? 'var(--pd-navy,#1e3a5f)' : '#e5e7eb';
        document.getElementById('card-extra').style.borderColor =
            val === 'extra' ? '#7c3aed' : '#e5e7eb';
    };

    window.saveColumn = function () {
        clearErrors();
        var url   = '{{ route('super-admin.datagrids.columns.update', [$org, $table->id, $column->id]) }}';
        var token = '{{ csrf_token() }}';
        var len   = document.getElementById('f-length').value;
        var tab   = document.querySelector('input[name="tab"]:checked')?.value || 'main';

        fetch(url, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token },
            body: JSON.stringify({
                name:               document.getElementById('f-name').value,
                label:              document.getElementById('f-label').value,
                type:               document.getElementById('f-type').value,
                length:             len !== '' ? parseInt(len) : null,
                sort_order:         parseInt(document.getElementById('f-sort').value) || 0,
                required:           document.getElementById('f-required').checked,
                visible_by_default: document.getElementById('f-visible').checked,
                is_rgpd_sensitive:  document.getElementById('f-rgpd').checked,
                tab:                tab,
                fuzzy_search:       document.getElementById('f-fuzzy') ? document.getElementById('f-fuzzy').checked : false,
            }),
        })
        .then(function (r) {
            if (r.ok) {
                showBadge('ok');
            } else {
                r.json().then(function (e) {
                    if (e.errors) {
                        Object.entries(e.errors).forEach(function ([field, msgs]) {
                            var el = document.getElementById('err-' + field.replace('_', '-'));
                            if (el) { el.textContent = msgs[0]; el.classList.remove('hidden'); }
                        });
                        showBadge('err', e.message || 'Erreur de validation.');
                    } else {
                        showBadge('err', e.error || 'Une erreur est survenue.');
                    }
                });
            }
        });
    };

    function showBadge(type, msg) {
        var ok = document.getElementById('badge-ok'), err = document.getElementById('badge-err');
        ok.classList.add('hidden'); err.classList.add('hidden');
        if (type === 'ok') {
            ok.classList.remove('hidden');
            setTimeout(function () { ok.classList.add('hidden'); }, 3000);
        } else { err.textContent = msg; err.classList.remove('hidden'); }
    }

    function clearErrors() {
        document.querySelectorAll('[id^="err-"]').forEach(function (el) {
            el.classList.add('hidden'); el.textContent = '';
        });
        document.getElementById('badge-err').classList.add('hidden');
    }
}());
</script>
@endsection
