@extends('layouts.super-admin')
@section('title', 'Colonne — ' . $column->name)

@section('content')
<div style="max-width:640px;">

    <div style="margin-bottom:24px;">
        <a href="{{ route('super-admin.datagrids.edit', [$org, $table->id]) }}"
           style="font-size:12px;color:var(--pd-muted);text-decoration:none;">
            ← Retour à la grille
        </a>
        <h1 style="font-family:'Sora',sans-serif;font-size:20px;font-weight:700;color:var(--pd-text);margin:8px 0 2px;">
            <span style="font-family:monospace;">{{ $column->name }}</span>
        </h1>
        <div style="font-size:12px;color:var(--pd-muted);">{{ $table->label }} — {{ $org->name }}</div>
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

    <div style="background:var(--pd-surface);border:1px solid var(--pd-border);border-radius:12px;padding:20px;margin-bottom:20px;">

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

        {{-- Ordre --}}
        <div style="margin-bottom:16px;">
            <label style="font-size:12px;color:var(--pd-muted);display:block;margin-bottom:4px;">Ordre d'affichage</label>
            <input id="f-sort" type="number" min="0" value="{{ $column->sort_order }}"
                   style="width:140px;padding:7px 10px;border:1px solid var(--pd-border);
                          border-radius:7px;font-size:13px;box-sizing:border-box;">
        </div>

        {{-- Onglet de la modal --}}
        <div style="margin-bottom:16px;">
            <label style="font-size:12px;color:var(--pd-muted);display:block;margin-bottom:4px;">Onglet dans la fiche</label>
            <select id="f-tab"
                    style="width:100%;padding:7px 10px;border:1px solid var(--pd-border);
                           border-radius:7px;font-size:13px;box-sizing:border-box;">
                <option value="main"  {{ ($column->tab ?? 'main') === 'main'  ? 'selected' : '' }}>Données principales</option>
                <option value="extra" {{ ($column->tab ?? 'main') === 'extra' ? 'selected' : '' }}>Informations complémentaires</option>
            </select>
            <div style="font-size:11px;color:var(--pd-muted);margin-top:3px;">
                L'onglet "Informations complémentaires" n'apparaît que si au moins une colonne y est affectée.
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
        </div>

        {{-- Actions --}}
        <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
            <button onclick="saveColumn()" type="button"
                    style="padding:8px 18px;background:var(--sa-primary,#1e3a5f);color:#fff;border:none;
                           border-radius:7px;font-size:13px;font-weight:600;cursor:pointer;">
                Enregistrer
            </button>
            <form method="POST"
                  action="{{ route('super-admin.datagrids.columns.destroy', [$org, $table->id, $column->id]) }}"
                  style="margin-left:auto;"
                  onsubmit="return confirm('Supprimer la colonne « {{ $column->name }} » et ses données ?')">
                @csrf @method('DELETE')
                <button type="submit"
                        style="padding:8px 14px;border:1px solid #fca5a5;border-radius:7px;
                               font-size:13px;font-weight:600;color:#dc2626;background:#fef2f2;cursor:pointer;">
                    Supprimer cette colonne
                </button>
            </form>
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
    };

    window.saveColumn = function () {
        clearErrors();
        var url    = '{{ route('super-admin.datagrids.columns.update', [$org, $table->id, $column->id]) }}';
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
                sort_order:          parseInt(document.getElementById('f-sort').value) || 0,
                required:            document.getElementById('f-required').checked,
                visible_by_default:  document.getElementById('f-visible').checked,
                is_rgpd_sensitive:   document.getElementById('f-rgpd').checked,
                tab:                 document.getElementById('f-tab').value,
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
            err.textContent   = msg;
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
