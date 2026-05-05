@extends('layouts.super-admin')
@section('title', 'Grille — ' . $table->label)

@section('content')
<div style="margin-bottom:20px;">
    <a href="{{ route('super-admin.datagrids.index') }}"
       style="font-size:12px;color:var(--pd-muted);text-decoration:none;">
        ← Toutes les grilles
    </a>
    <div style="display:flex;align-items:baseline;gap:14px;margin-top:8px;">
        <h1 style="font-family:'Sora',sans-serif;font-size:20px;font-weight:700;color:var(--pd-text);margin:0;">
            {{ $table->label }}
        </h1>
        <span style="font-family:monospace;font-size:12px;color:var(--pd-muted);">{{ $org->name }}</span>
    </div>
    <div style="font-family:monospace;font-size:12px;color:var(--pd-muted);margin-top:2px;">
        {{ $table->mysql_table }}
    </div>
</div>

@if(session('success'))
<div style="padding:10px 16px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;
            color:#166534;font-size:13px;margin-bottom:18px;">
    {{ session('success') }}
</div>
@endif

{{-- Métadonnées --}}
<div style="background:var(--pd-surface);border:1px solid var(--pd-border);border-radius:12px;padding:20px;margin-bottom:20px;">
    <div style="font-size:13px;font-weight:600;color:var(--pd-text);margin-bottom:14px;">Paramètres</div>
    <form id="form-table"
          onsubmit="
            event.preventDefault();
            var f = this;
            fetch('{{ route('super-admin.datagrids.update', [$org, $table->id]) }}', {
                method: 'PATCH',
                headers: {'Content-Type':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}','Accept':'application/json'},
                body: JSON.stringify({label: f.label.value, description: f.description.value, has_rgpd: f.has_rgpd.checked})
            }).then(r => r.json()).then(d => {
                if (d.success) { var s = f.querySelector('[data-saved]'); s.style.display='inline'; setTimeout(() => s.style.display='none', 2000); }
            });
          ">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px;">
            <div>
                <label style="font-size:12px;color:var(--pd-muted);display:block;margin-bottom:4px;">Label</label>
                <input name="label" type="text" value="{{ $table->label }}"
                       style="width:100%;padding:7px 10px;border:1px solid var(--pd-border);border-radius:7px;font-size:13px;box-sizing:border-box;">
            </div>
            <div>
                <label style="font-size:12px;color:var(--pd-muted);display:block;margin-bottom:4px;">Description</label>
                <input name="description" type="text" value="{{ $table->description }}"
                       style="width:100%;padding:7px 10px;border:1px solid var(--pd-border);border-radius:7px;font-size:13px;box-sizing:border-box;">
            </div>
        </div>
        <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
            <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer;">
                <input name="has_rgpd" type="checkbox" {{ $table->has_rgpd ? 'checked' : '' }}>
                Données RGPD (active le journal d'audit)
            </label>
            <button type="submit"
                    style="padding:7px 16px;background:var(--sa-primary,#1e3a5f);color:#fff;border:none;
                           border-radius:7px;font-size:13px;font-weight:600;cursor:pointer;">
                Enregistrer
            </button>
            <span data-saved style="display:none;font-size:12px;color:#16a34a;font-weight:600;">✓ Sauvegardé</span>
            <form method="POST"
                  action="{{ route('super-admin.datagrids.destroy', [$org, $table->id]) }}"
                  style="margin-left:auto;"
                  onsubmit="return confirm('Supprimer la grille « {{ $table->label }} » et toutes ses données ?')">
                @csrf @method('DELETE')
                <button type="submit"
                        style="padding:7px 14px;border:1px solid #fca5a5;border-radius:7px;
                               font-size:13px;font-weight:600;color:#dc2626;background:#fef2f2;cursor:pointer;">
                    Supprimer cette grille
                </button>
            </form>
        </div>
    </form>
</div>

{{-- Colonnes --}}
<div style="background:var(--pd-surface);border:1px solid var(--pd-border);border-radius:12px;padding:20px;">
    <div style="font-size:13px;font-weight:600;color:var(--pd-text);margin-bottom:14px;">
        Colonnes ({{ $columns->count() }})
    </div>

    @if($columns->isEmpty())
    <p style="font-size:13px;color:var(--pd-muted);">Aucune colonne.</p>
    @else
    <div style="border:1px solid var(--pd-border);border-radius:8px;overflow:hidden;">
        <table style="width:100%;border-collapse:collapse;font-size:12px;">
            <thead>
                <tr style="background:var(--pd-bg,#f8f9fb);">
                    <th style="padding:9px 14px;text-align:left;font-weight:600;color:var(--pd-muted);border-bottom:1px solid var(--pd-border);">Nom</th>
                    <th style="padding:9px 14px;text-align:left;font-weight:600;color:var(--pd-muted);border-bottom:1px solid var(--pd-border);">Label</th>
                    <th style="padding:9px 14px;text-align:left;font-weight:600;color:var(--pd-muted);border-bottom:1px solid var(--pd-border);">Type</th>
                    <th style="padding:9px 14px;text-align:center;font-weight:600;color:var(--pd-muted);border-bottom:1px solid var(--pd-border);">Requis</th>
                    <th style="padding:9px 14px;border-bottom:1px solid var(--pd-border);"></th>
                </tr>
            </thead>
            <tbody>
                @foreach($columns as $col)
                <tr style="border-bottom:1px solid var(--pd-border);">
                    <td style="padding:9px 14px;font-family:monospace;color:var(--sa-primary,#1e3a5f);font-weight:600;">{{ $col->name }}</td>
                    <td style="padding:9px 14px;color:var(--pd-text);">{{ $col->label }}</td>
                    <td style="padding:9px 14px;color:var(--pd-muted);">{{ $col->type->value }}</td>
                    <td style="padding:9px 14px;text-align:center;">{{ $col->required ? '✓' : '' }}</td>
                    <td style="padding:9px 14px;text-align:right;">
                        <form method="POST"
                              action="{{ route('super-admin.datagrids.columns.destroy', [$org, $table->id, $col->id]) }}"
                              style="display:inline;"
                              onsubmit="return confirm('Supprimer la colonne « {{ $col->name }} » ?')">
                            @csrf @method('DELETE')
                            <button type="submit"
                                    style="padding:4px 10px;border:1px solid #fca5a5;border-radius:5px;
                                           font-size:11px;color:#dc2626;background:#fef2f2;cursor:pointer;">
                                Supprimer
                            </button>
                        </form>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>

@endsection
