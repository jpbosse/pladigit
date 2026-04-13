@extends('layouts.admin')
@section('title', 'Gestion des données de démonstration')

@section('admin-content')

{{-- En-tête --}}
<div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:24px;">
    <div>
        <h1 style="font-family:'Sora',sans-serif;font-size:20px;font-weight:700;color:var(--pd-text);margin:0 0 4px;">
            Gestion des données de démonstration
        </h1>
        <p style="font-size:13px;color:var(--pd-muted);margin:0;">
            Sources utilisées pour la remise à zéro automatique à minuit.
            Ces dossiers ne sont <strong>jamais effacés</strong> — ils sont uniquement copiés lors du reset.
        </p>
    </div>
</div>

{{-- Messages --}}
@if(session('success'))
<div style="background:rgba(39,174,96,0.1);border:1.5px solid rgba(39,174,96,0.3);border-radius:10px;padding:12px 16px;margin-bottom:20px;color:#1e8449;font-size:13px;">
    ✅ {{ session('success') }}
</div>
@endif
@if($errors->any())
<div style="background:rgba(231,76,60,0.1);border:1.5px solid rgba(231,76,60,0.3);border-radius:10px;padding:12px 16px;margin-bottom:20px;color:#c0392b;font-size:13px;">
    @foreach($errors->all() as $e) <div>⚠️ {{ $e }}</div> @endforeach
</div>
@endif

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px;">

    {{-- ── Photos ─────────────────────────────────────── --}}
    <div style="background:var(--pd-surface);border:1.5px solid var(--pd-border);border-radius:14px;padding:20px;box-shadow:var(--pd-shadow);">
        <h2 style="font-size:15px;font-weight:700;color:var(--pd-text);margin:0 0 4px;">
            📷 Photos sources
        </h2>
        <p style="font-size:12px;color:var(--pd-muted);margin:0 0 16px;">
            Copiées dans l'album « Fête de la commune 2025 » au reset.
        </p>

        {{-- Upload --}}
        <form method="POST" action="{{ route('admin.demo.photos.upload') }}" enctype="multipart/form-data"
              style="margin-bottom:16px;">
            @csrf
            <label style="display:block;font-size:12px;font-weight:600;color:var(--pd-muted);margin-bottom:6px;">
                Ajouter des photos (jpg, png, webp — max 10 Mo chacune)
            </label>
            <div style="display:flex;gap:8px;align-items:center;">
                <input type="file" name="photos[]" multiple accept=".jpg,.jpeg,.png,.webp"
                       style="flex:1;font-size:12px;color:var(--pd-text);background:var(--pd-bg);
                              border:1.5px solid var(--pd-border);border-radius:8px;padding:6px 10px;cursor:pointer;">
                <button type="submit"
                        style="padding:7px 14px;border-radius:8px;border:none;cursor:pointer;font-size:12px;font-weight:600;
                               background:var(--pd-accent);color:#fff;white-space:nowrap;transition:opacity 0.15s;"
                        onmouseover="this.style.opacity='0.85'" onmouseout="this.style.opacity='1'">
                    Envoyer
                </button>
            </div>
        </form>

        {{-- Liste --}}
        @if($photos)
        <div style="max-height:260px;overflow-y:auto;display:flex;flex-direction:column;gap:4px;">
            @forelse($photos as $f)
            <div style="display:flex;justify-content:space-between;align-items:center;
                        padding:6px 10px;border-radius:8px;background:var(--pd-bg);
                        border:1px solid var(--pd-border);font-size:12px;">
                <span style="color:var(--pd-text);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:200px;"
                      title="{{ $f['name'] }}">{{ $f['name'] }}</span>
                <div style="display:flex;align-items:center;gap:8px;flex-shrink:0;">
                    <span style="color:var(--pd-muted);">{{ $f['size'] }}</span>
                    <form method="POST" action="{{ route('admin.demo.file.delete') }}" style="margin:0;"
                          onsubmit="return confirm('Supprimer {{ addslashes($f['name']) }} ?')">
                        @csrf @method('DELETE')
                        <input type="hidden" name="type" value="photo">
                        <input type="hidden" name="path" value="{{ $f['path'] }}">
                        <button type="submit"
                                style="background:none;border:none;cursor:pointer;font-size:13px;padding:2px 4px;color:var(--pd-muted);transition:color 0.15s;"
                                onmouseover="this.style.color='#e74c3c'" onmouseout="this.style.color='var(--pd-muted)'">
                            🗑
                        </button>
                    </form>
                </div>
            </div>
            @empty
            <p style="font-size:12px;color:var(--pd-muted);text-align:center;padding:12px 0;">Aucune photo source.</p>
            @endforelse
        </div>
        @endif
    </div>

    {{-- ── Documents GED ────────────────────────────── --}}
    <div style="background:var(--pd-surface);border:1.5px solid var(--pd-border);border-radius:14px;padding:20px;box-shadow:var(--pd-shadow);">
        <h2 style="font-size:15px;font-weight:700;color:var(--pd-text);margin:0 0 4px;">
            📁 Documents GED sources
        </h2>
        <p style="font-size:12px;color:var(--pd-muted);margin:0 0 16px;">
            Copiés dans la GED démo au reset. Organisez-les par sous-dossier.
        </p>

        {{-- Upload --}}
        <form method="POST" action="{{ route('admin.demo.ged.upload') }}" enctype="multipart/form-data"
              style="margin-bottom:16px;">
            @csrf
            <div style="display:flex;gap:8px;margin-bottom:8px;">
                <div style="flex:1;">
                    <label style="display:block;font-size:12px;font-weight:600;color:var(--pd-muted);margin-bottom:4px;">
                        Sous-dossier (optionnel)
                    </label>
                    <input type="text" name="subfolder" placeholder="ex: Délibérations"
                           style="width:100%;font-size:12px;color:var(--pd-text);background:var(--pd-bg);
                                  border:1.5px solid var(--pd-border);border-radius:8px;padding:6px 10px;box-sizing:border-box;">
                </div>
            </div>
            <label style="display:block;font-size:12px;font-weight:600;color:var(--pd-muted);margin-bottom:6px;">
                Documents (pdf, doc, docx, odt, xls, xlsx, ods, txt — max 20 Mo)
            </label>
            <div style="display:flex;gap:8px;align-items:center;">
                <input type="file" name="docs[]" multiple accept=".pdf,.doc,.docx,.odt,.xls,.xlsx,.ods,.txt"
                       style="flex:1;font-size:12px;color:var(--pd-text);background:var(--pd-bg);
                              border:1.5px solid var(--pd-border);border-radius:8px;padding:6px 10px;cursor:pointer;">
                <button type="submit"
                        style="padding:7px 14px;border-radius:8px;border:none;cursor:pointer;font-size:12px;font-weight:600;
                               background:var(--pd-accent);color:#fff;white-space:nowrap;transition:opacity 0.15s;"
                        onmouseover="this.style.opacity='0.85'" onmouseout="this.style.opacity='1'">
                    Envoyer
                </button>
            </div>
        </form>

        {{-- Arbre GED --}}
        <div style="max-height:260px;overflow-y:auto;">
            @if($gedTree)
                @foreach($gedTree as $node)
                    @include('admin.demo.partials.ged-node', ['node' => $node, 'depth' => 0])
                @endforeach
            @else
                <p style="font-size:12px;color:var(--pd-muted);text-align:center;padding:12px 0;">Aucun document source.</p>
            @endif
        </div>
    </div>

</div>

{{-- ── Remise à zéro ───────────────────────────────────────── --}}
<div style="background:var(--pd-surface);border:1.5px solid rgba(231,76,60,0.35);border-radius:14px;padding:20px;box-shadow:var(--pd-shadow);">
    <h2 style="font-size:15px;font-weight:700;color:#c0392b;margin:0 0 6px;">
        🔄 Remise à zéro manuelle
    </h2>
    <p style="font-size:12px;color:var(--pd-muted);margin:0 0 14px;">
        Efface <strong>toutes les données</strong> de l'organisation démo (utilisateurs, projets, GED, photos, tâches…)
        puis les recrée à partir des sources ci-dessus.
        Cette action est également exécutée automatiquement <strong>chaque nuit à minuit</strong>.
    </p>
    <form method="POST" action="{{ route('admin.demo.reset') }}"
          onsubmit="return confirm('⚠️ Toutes les données démo seront effacées et recréées. Continuer ?')">
        @csrf
        <button type="submit"
                style="padding:9px 20px;border-radius:9px;border:none;cursor:pointer;font-size:13px;font-weight:700;
                       background:#e74c3c;color:#fff;transition:opacity 0.15s;"
                onmouseover="this.style.opacity='0.85'" onmouseout="this.style.opacity='1'">
            Lancer la remise à zéro maintenant
        </button>
    </form>
</div>

@endsection
