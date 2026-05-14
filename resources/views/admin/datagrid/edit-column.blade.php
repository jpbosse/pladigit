@extends('layouts.admin')
@section('title', 'Colonne — ' . $column->name)

@section('admin-content')
<div style="padding:32px 40px;max-width:640px;">

    <div style="margin-bottom:24px;">
        <div style="display:flex;align-items:center;justify-content:space-between;">
            <a href="{{ route('admin.datagrid.edit', $table) }}"
               style="font-size:12px;color:var(--pd-muted);text-decoration:none;">
                ← Retour à la grille
            </a>
            <form method="POST"
                  action="{{ route('datagrid.columns.destroy', [$table, $column]) }}"
                  onsubmit="return confirm('Supprimer la colonne « {{ $column->name }} » et ses données ?')">
                @csrf @method('DELETE')
                <button type="submit"
                        style="padding:4px 12px;border:1px solid #fca5a5;border-radius:6px;
                               font-size:12px;font-weight:500;color:#dc2626;background:#fef2f2;
                               cursor:pointer;">
                    Supprimer la colonne
                </button>
            </form>
        </div>
        <h1 style="font-size:20px;font-weight:700;color:var(--pd-text);margin:8px 0 2px;">
            <span style="font-family:monospace;">{{ $column->name }}</span>
        </h1>
        <div style="font-size:12px;color:var(--pd-muted);">{{ $table->label }}</div>
    </div>

    {{-- Bloc feedback --}}
    <div id="badge-ok"
         style="display:none;padding:10px 16px;background:#f0fdf4;border:1px solid #bbf7d0;
                border-radius:8px;color:#166534;font-size:13px;font-weight:600;margin-bottom:16px;
                transition:opacity .4s;">
        ✓ Enregistré
    </div>
    <div id="badge-err"
         style="display:none;padding:10px 16px;background:#fef2f2;border:1px solid #fca5a5;
                border-radius:8px;color:#dc2626;font-size:13px;margin-bottom:16px;">
    </div>

    {{-- Bandeau de confirmation conversion forcée --}}
    <div id="badge-warn"
         style="display:none;padding:14px 16px;background:#fffbeb;border:1px solid #fcd34d;
                border-radius:8px;color:#92400e;font-size:13px;margin-bottom:16px;line-height:1.6;">
        <div id="badge-warn-msg" style="margin-bottom:10px;"></div>
        <div style="display:flex;gap:8px;">
            <button onclick="saveColumn(true)" type="button"
                    style="padding:6px 14px;background:#d97706;color:#fff;border:none;
                           border-radius:6px;font-size:12px;font-weight:600;cursor:pointer;">
                Confirmer la conversion
            </button>
            <button onclick="document.getElementById('badge-warn').style.display='none'" type="button"
                    style="padding:6px 14px;background:#f3f4f6;color:#374151;border:1px solid #d1d5db;
                           border-radius:6px;font-size:12px;cursor:pointer;">
                Annuler
            </button>
        </div>
    </div>

    <div style="background:var(--pd-bg);border:1px solid var(--pd-border);border-radius:10px;padding:20px;margin-bottom:20px;">

        {{-- Nom technique --}}
        <div style="margin-bottom:16px;">
            <label style="font-size:12px;color:var(--pd-muted);display:block;margin-bottom:4px;">Nom technique (colonne MySQL)</label>
            <input id="f-name" type="text" value="{{ $column->name }}"
                   pattern="[a-z][a-z0-9_]*"
                   style="width:100%;padding:7px 10px;border:1px solid var(--pd-border);
                          border-radius:7px;font-size:13px;font-family:monospace;box-sizing:border-box;">
            <div id="err-name" style="display:none;font-size:11px;color:#dc2626;margin-top:3px;"></div>
            <div style="font-size:11px;color:var(--pd-muted);margin-top:3px;">
                Attention : renommer la colonne MySQL est irréversible sans sauvegarde.
            </div>
        </div>

        {{-- Label --}}
        <div style="margin-bottom:16px;">
            <label style="font-size:12px;color:var(--pd-muted);display:block;margin-bottom:4px;">Label</label>
            <input id="f-label" type="text" value="{{ $column->label }}"
                   style="width:100%;padding:7px 10px;border:1px solid var(--pd-border);
                          border-radius:7px;font-size:13px;box-sizing:border-box;">
            <div id="err-label" style="display:none;font-size:11px;color:#dc2626;margin-top:3px;"></div>
        </div>

        {{-- Type --}}
        <div style="margin-bottom:16px;">
            <label style="font-size:12px;color:var(--pd-muted);display:block;margin-bottom:4px;">Type</label>
            <select id="f-type"
                    onchange="toggleLength()"
                    style="width:100%;padding:7px 10px;border:1px solid var(--pd-border);
                           border-radius:7px;font-size:13px;box-sizing:border-box;">
                @foreach(\App\Enums\DatagridColumnType::options() as $val => $lbl)
                <option value="{{ $val }}" {{ $column->type->value === $val ? 'selected' : '' }}>{{ $lbl }}</option>
                @endforeach
            </select>
            <div id="err-type" style="display:none;font-size:11px;color:#dc2626;margin-top:3px;"></div>
        </div>

        {{-- Longueur (conditionnelle) --}}
        <div id="block-length"
             style="margin-bottom:16px;display:{{ $column->type->hasLength() ? 'block' : 'none' }};">
            <label style="font-size:12px;color:var(--pd-muted);display:block;margin-bottom:4px;">Longueur max</label>
            <input id="f-length" type="number" min="1" max="65535" value="{{ $column->length }}"
                   style="width:100%;padding:7px 10px;border:1px solid var(--pd-border);
                          border-radius:7px;font-size:13px;box-sizing:border-box;">
        </div>

        {{-- Labels Oui/Non pour BOOLEAN --}}
        <div id="block-boolean"
             style="margin-bottom:16px;display:{{ $column->type === \App\Enums\DatagridColumnType::BOOLEAN ? 'block' : 'none' }};">
            <label style="font-size:12px;color:var(--pd-muted);display:block;margin-bottom:8px;">Labels Vrai / Faux</label>
            <div style="display:flex;gap:10px;">
                <div style="flex:1;">
                    <label style="font-size:11px;color:var(--pd-muted);display:block;margin-bottom:3px;">Libellé Vrai</label>
                    <input id="f-label-true" type="text" value="{{ $column->label_true ?? 'Oui' }}"
                           placeholder="Oui"
                           style="width:100%;padding:7px 10px;border:1px solid var(--pd-border);border-radius:7px;font-size:13px;box-sizing:border-box;">
                </div>
                <div style="flex:1;">
                    <label style="font-size:11px;color:var(--pd-muted);display:block;margin-bottom:3px;">Libellé Faux</label>
                    <input id="f-label-false" type="text" value="{{ $column->label_false ?? 'Non' }}"
                           placeholder="Non"
                           style="width:100%;padding:7px 10px;border:1px solid var(--pd-border);border-radius:7px;font-size:13px;box-sizing:border-box;">
                </div>
            </div>
        </div>

        {{-- Options pour SELECT --}}
        <div id="block-options"
             style="margin-bottom:16px;display:{{ $column->type === \App\Enums\DatagridColumnType::SELECT ? 'block' : 'none' }};">
            <label style="font-size:12px;color:var(--pd-muted);display:block;margin-bottom:4px;">
                Valeurs possibles
                <span style="font-weight:normal;"> — une par ligne. Laisser vide = saisie libre.</span>
            </label>
            <textarea id="f-options" rows="5"
                      placeholder="Valeur 1&#10;Valeur 2&#10;Valeur 3"
                      style="width:100%;padding:7px 10px;border:1px solid var(--pd-border);border-radius:7px;font-size:13px;font-family:monospace;box-sizing:border-box;resize:vertical;">{{ is_array($column->options) ? implode("
", $column->options) : '' }}</textarea>
            <span style="display:block;margin-top:3px;font-size:11px;color:var(--pd-muted);">
                2 valeurs = toggle, 3+ = dropdown. Vide = champ texte libre avec suggestions.
            </span>
        </div>

        {{-- Ordre --}}
        <div style="margin-bottom:16px;">
            <label style="font-size:12px;color:var(--pd-muted);display:block;margin-bottom:4px;">Ordre d'affichage</label>
            <input id="f-sort" type="number" min="0" value="{{ $column->sort_order }}"
                   style="width:140px;padding:7px 10px;border:1px solid var(--pd-border);
                          border-radius:7px;font-size:13px;box-sizing:border-box;">
        </div>

        {{-- Onglet dans la fiche --}}
        <div style="margin-bottom:16px;">
            <label style="font-size:12px;color:var(--pd-muted);display:block;margin-bottom:8px;">Onglet dans la fiche</label>
            <div style="display:flex;gap:10px;">
                <label id="card-main"
                       style="flex:1;display:flex;align-items:center;gap:10px;padding:10px 14px;
                              border:2px solid {{ ($column->tab ?? 'main') === 'main' ? 'var(--pd-navy)' : 'var(--pd-border)' }};
                              border-radius:8px;cursor:pointer;">
                    <input type="radio" name="tab" id="tab-main" value="main"
                           {{ ($column->tab ?? 'main') === 'main' ? 'checked' : '' }}
                           onchange="selectTab('main')"
                           style="accent-color:var(--pd-navy);">
                    <div>
                        <div style="font-size:13px;font-weight:600;color:var(--pd-text);">Données principales</div>
                        <div style="font-size:11px;color:var(--pd-muted);">Premier onglet, toujours visible</div>
                    </div>
                </label>
                <label id="card-extra"
                       style="flex:1;display:flex;align-items:center;gap:10px;padding:10px 14px;
                              border:2px solid {{ ($column->tab ?? 'main') === 'extra' ? '#7c3aed' : 'var(--pd-border)' }};
                              border-radius:8px;cursor:pointer;">
                    <input type="radio" name="tab" id="tab-extra" value="extra"
                           {{ ($column->tab ?? 'main') === 'extra' ? 'checked' : '' }}
                           onchange="selectTab('extra')"
                           style="accent-color:#7c3aed;">
                    <div>
                        <div style="font-size:13px;font-weight:600;color:var(--pd-text);">Complémentaires</div>
                        <div style="font-size:11px;color:var(--pd-muted);">Affiché si au moins une colonne</div>
                    </div>
                </label>
            </div>
        </div>

        {{-- Cases à cocher --}}
        <div style="display:flex;flex-direction:column;gap:10px;margin-bottom:20px;">
            <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer;">
                <input id="f-required" type="checkbox" {{ $column->required ? 'checked' : '' }}>
                Champ obligatoire
            </label>
            <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer;">
                <input id="f-visible" type="checkbox" {{ $column->visible_by_default ? 'checked' : '' }}>
                Visible par défaut
            </label>
            <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer;">
                <input id="f-rgpd" type="checkbox" {{ $column->is_rgpd_sensitive ? 'checked' : '' }}>
                Donnée RGPD sensible
            </label>
            <label id="row-fuzzy" style="display:{{ $column->type->value === 'nom_personne' ? 'flex' : 'none' }};align-items:center;gap:8px;font-size:13px;cursor:pointer;">
                <input id="f-fuzzy" type="checkbox" {{ $column->fuzzy_search ? 'checked' : '' }}>
                <span>
                    Recherche floue (Levenshtein)
                    <span style="display:block;font-size:11px;color:var(--pd-muted);">
                        Tolère les variantes orthographiques (Dupond/Dupont). Active aussi la
                        détection de doublons à l'import.
                    </span>
                </span>
            </label>
        </div>

        {{-- Actions --}}
        <div>
            <button onclick="saveColumn()" type="button"
                    style="padding:8px 18px;background:var(--pd-navy);color:#fff;border:none;
                           border-radius:7px;font-size:13px;font-weight:600;cursor:pointer;">
                Enregistrer
            </button>
        </div>

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
        document.getElementById('block-length').style.display =
            hasLengthTypes.includes(t) ? 'block' : 'none';
        document.getElementById('block-boolean').style.display =
            t === 'boolean' ? 'block' : 'none';
        document.getElementById('block-options').style.display =
            t === 'select' ? 'block' : 'none';
        // Afficher la case fuzzy uniquement pour NOM_PERSONNE
        var rowFuzzy = document.getElementById('row-fuzzy');
        if (rowFuzzy) { rowFuzzy.style.display = t === 'nom_personne' ? 'flex' : 'none'; }
    };

    window.selectTab = function (val) {
        document.getElementById('card-main').style.borderColor  =
            val === 'main'  ? 'var(--pd-navy)' : 'var(--pd-border)';
        document.getElementById('card-extra').style.borderColor =
            val === 'extra' ? '#7c3aed' : 'var(--pd-border)';
    };

    window.saveColumn = function (force) {
        clearErrors();
        var url    = '{{ route('admin.datagrid.columns.update', [$table, $column]) }}';
        var token  = '{{ csrf_token() }}';
        var length = document.getElementById('f-length').value;

        fetch(url, {
            method: 'PATCH',
            headers: {
                'Content-Type':  'application/json',
                'Accept':        'application/json',
                'X-CSRF-TOKEN':  token,
            },
            body: JSON.stringify({
                name:                document.getElementById('f-name').value,
                label:               document.getElementById('f-label').value,
                type:                document.getElementById('f-type').value,
                length:              length !== '' ? parseInt(length) : null,
                label_true:          document.getElementById('f-label-true').value || null,
                label_false:         document.getElementById('f-label-false').value || null,
                options:             (function() {
                    var raw = document.getElementById('f-options').value.trim();
                    if (!raw) return null;
                    return raw.split('\n').map(s => s.trim()).filter(s => s.length > 0);
                })(),
                sort_order:          parseInt(document.getElementById('f-sort').value) || 0,
                required:            document.getElementById('f-required').checked,
                visible_by_default:  document.getElementById('f-visible').checked,
                is_rgpd_sensitive:   document.getElementById('f-rgpd').checked,
                tab:                 document.querySelector('input[name="tab"]:checked')?.value || 'main',
                fuzzy_search:        document.getElementById('f-fuzzy') ? document.getElementById('f-fuzzy').checked : false,
                force:               force === true,
            }),
        })
        .then(function (r) {
            if (r.ok) {
                document.getElementById('badge-warn').style.display = 'none';
                showBadge('ok');
            } else {
                r.json().then(function (e) {
                    if (e.forceable && e.warning) {
                        // Conversion possible mais nécessite confirmation
                        document.getElementById('badge-warn-msg').textContent = '⚠ ' + e.warning;
                        document.getElementById('badge-warn').style.display = 'block';
                        document.getElementById('badge-err').style.display  = 'none';
                    } else if (e.errors) {
                        Object.entries(e.errors).forEach(function ([field, msgs]) {
                            var el = document.getElementById('err-' + field.replace('_', '-'));
                            if (el) { el.textContent = msgs[0]; el.style.display = 'block'; }
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
        var ok  = document.getElementById('badge-ok');
        var err = document.getElementById('badge-err');
        ok.style.display  = 'none';
        err.style.display = 'none';

        if (type === 'ok') {
            ok.style.opacity = '1';
            ok.style.display = 'block';
            setTimeout(function () {
                ok.style.opacity = '0';
                setTimeout(function () { ok.style.display = 'none'; ok.style.opacity = '1'; }, 400);
            }, 3000);
        } else {
            err.textContent  = msg;
            err.style.display = 'block';
        }
    }

    function clearErrors() {
        document.querySelectorAll('[id^="err-"]').forEach(function (el) {
            el.style.display = 'none';
            el.textContent   = '';
        });
        document.getElementById('badge-err').style.display = 'none';
    }
}());
</script>
@endsection
