@extends('layouts.admin')

@section('title', 'Paramètres Photothèque')

@section('admin-content')
<div class="max-w-2xl mx-auto px-4">

    <div class="mb-6">
        <h1 class="text-xl font-bold text-gray-800">Paramètres Photothèque</h1>
        <p class="text-sm text-gray-500 mt-1">Configuration de l'affichage par défaut pour tous les utilisateurs.</p>
    </div>

    {{-- ── Bloc stockage ──────────────────────────────────────────────── --}}
    @php $quotaLabel = $quotaMb >= 1024 ? round($quotaMb / 1024, 1).' Go' : $quotaMb.' Mo'; @endphp
    <div class="mb-6 bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
            <h2 class="text-sm font-semibold text-gray-700">Espace de stockage</h2>
            <span style="font-size:12px;color:#6b7280;">{{ $usedMb }} Mo utilisés sur {{ $quotaLabel }}</span>
        </div>

        <div style="height:8px;background:#e5e7eb;border-radius:4px;overflow:hidden;margin-bottom:8px;">
            <div style="height:100%;border-radius:4px;width:{{ $usedPct }}%;background:{{ $usedPct >= 90 ? '#ef4444' : ($usedPct >= 70 ? '#f59e0b' : '#1E3A5F') }};transition:width .3s;"></div>
        </div>

        <div style="display:flex;justify-content:space-between;font-size:11px;color:#9ca3af;">
            <span>{{ $usedPct }}% utilisé</span>
            <span>{{ $freeMb }} Mo libres</span>
        </div>

        {{-- Info quota alloué --}}
        <div style="margin-top:10px;padding:8px 12px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;font-size:12px;color:#475569;">
            Le quota alloué à votre organisation est de <strong>{{ $quotaLabel }}</strong>.
            Ce plafond est fixé par l'administrateur plateforme Pladigit.
            Pour demander une augmentation, contactez-le directement.
        </div>

        @if($usedPct >= 90)
        <div style="margin-top:8px;padding:8px 12px;background:#fef2f2;border:1px solid #fecaca;border-radius:8px;font-size:12px;color:#b91c1c;">
            ⚠ Quota presque atteint ({{ $usedPct }}%). Contactez l'administrateur plateforme pour augmenter la limite.
        </div>
        @elseif($usedPct >= 80)
        <div style="margin-top:8px;padding:8px 12px;background:#fffbeb;border:1px solid #fde68a;border-radius:8px;font-size:12px;color:#92400e;">
            ⚠ Vous avez utilisé {{ $usedPct }}% de votre quota. Pensez à libérer de l'espace ou à contacter l'administrateur plateforme.
        </div>
        @endif
    </div>

    @if(session('success'))
        <div class="mb-4 p-3 bg-green-50 border border-green-200 text-green-800 rounded-lg text-sm">
            ✅ {{ session('success') }}
        </div>
    @endif

    <form method="POST" action="{{ route('admin.settings.media.update') }}" class="bg-white rounded-xl border border-gray-200 p-6 shadow-sm">
        @csrf @method('PUT')

        <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">
                Nombre de colonnes par défaut
            </label>
            <p class="text-xs text-gray-500 mb-3">
                Valeur initiale pour tous les utilisateurs. Chaque utilisateur peut modifier ce réglage depuis l'album (mémorisé dans son navigateur).
            </p>

            <div class="flex gap-3">
                @foreach([1 => '1 colonne', 2 => '2 colonnes', 3 => '3 colonnes', 4 => '4 colonnes', 5 => '5 colonnes', 6 => '6 colonnes'] as $val => $label)
                    <label class="flex flex-col items-center gap-2 cursor-pointer">
                        <input type="radio" name="media_default_cols" value="{{ $val }}"
                               {{ ($settings->media_default_cols ?? 3) == $val ? 'checked' : '' }}
                               class="sr-only peer">
                        <div class="w-16 h-14 rounded-lg border-2 border-gray-200 peer-checked:border-blue-600 peer-checked:bg-blue-50 flex items-center justify-center transition-all">
                            {{-- Miniature grille --}}
                            <div class="grid gap-0.5" style="grid-template-columns: repeat({{ min($val, 3) }}, 1fr); width: 36px;">
                                @for($i = 0; $i < min($val * 2, 9); $i++)
                                    <div class="bg-gray-300 peer-checked:bg-blue-300 rounded-sm" style="height: 10px;"></div>
                                @endfor
                            </div>
                        </div>
                        <span class="text-xs text-gray-600 font-medium">{{ $label }}</span>
                    </label>
                @endforeach
            </div>

            @error('media_default_cols')
                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>

        {{-- ── Seuil streaming ─────────────────────────────────────── --}}
        <div style="border-top:1px solid #f3f4f6;padding-top:24px;margin-bottom:24px;">
            <h2 style="font-size:14px;font-weight:600;color:#374151;margin-bottom:4px;">Seuil de streaming des images</h2>
            <p style="font-size:12px;color:#6b7280;margin-bottom:12px;">
                Au-delà de ce seuil, les images sont servies en streaming (chunks) plutôt que chargées entièrement en mémoire.
                Réduire cette valeur améliore la stabilité sur des serveurs avec peu de RAM.
                Mettre à <strong>0</strong> pour désactiver le streaming (déconseillé).
            </p>
            <div style="display:flex;align-items:center;gap:10px;">
                <input type="number"
                       name="media_stream_threshold_mb"
                       value="{{ old('media_stream_threshold_mb', $settings->media_stream_threshold_mb ?? 10) }}"
                       min="0" max="500" step="1"
                       class="pd-input" style="width:100px;">
                <span style="font-size:12px;color:#6b7280;">Mo (défaut : 10 Mo — 0 = désactivé)</span>
            </div>
            @error('media_stream_threshold_mb')
                <p style="color:#ef4444;font-size:11px;margin-top:4px;">{{ $message }}</p>
            @enderror
        </div>

        {{-- ── Watermark ────────────────────────────────────────────── --}}
        @php $wmEnabled = (bool)($settings->wm_enabled ?? false); @endphp
        <div style="border-top:1px solid #f3f4f6;padding-top:24px;margin-bottom:24px;">
            <h2 style="font-size:14px;font-weight:600;color:#374151;margin-bottom:4px;">Watermark sur les téléchargements</h2>
            <p style="font-size:12px;color:#6b7280;margin-bottom:16px;">
                Apposé à la volée uniquement lors du téléchargement. Le fichier original sur le NAS n'est jamais modifié.
            </p>

            {{-- Activer --}}
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:20px;">
                <input type="hidden" name="wm_enabled" value="0">
                <input type="checkbox" name="wm_enabled" value="1" id="wm_enabled"
                       {{ $wmEnabled ? 'checked' : '' }}
                       style="width:18px;height:18px;cursor:pointer;accent-color:#1E3A5F;"
                       onchange="document.getElementById('wm-options').style.display = this.checked ? 'block' : 'none'">
                <label for="wm_enabled" style="font-size:14px;color:#374151;font-weight:500;cursor:pointer;">
                    Activer le watermark
                </label>
            </div>

            <div id="wm-options" style="{{ $wmEnabled ? '' : 'display:none;' }}">

                {{-- Type --}}
                <div style="margin-bottom:16px;">
                    <p style="font-size:13px;font-weight:500;color:#374151;margin-bottom:8px;">Type</p>
                    <div style="display:flex;gap:24px;">
                        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                            <input type="radio" name="wm_type" value="text"
                                   {{ ($settings->wm_type ?? 'text') === 'text' ? 'checked' : '' }}
                                   style="width:16px;height:16px;accent-color:#1E3A5F;"
                                   onchange="document.getElementById('wm-text-field').style.display='block';document.getElementById('wm-logo-info').style.display='none'">
                            <span style="font-size:13px;color:#374151;">Texte</span>
                        </label>
                        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                            <input type="radio" name="wm_type" value="logo"
                                   {{ ($settings->wm_type ?? 'text') === 'logo' ? 'checked' : '' }}
                                   style="width:16px;height:16px;accent-color:#1E3A5F;"
                                   onchange="document.getElementById('wm-text-field').style.display='none';document.getElementById('wm-logo-info').style.display='block'">
                            <span style="font-size:13px;color:#374151;">Logo de l'organisation</span>
                        </label>
                    </div>
                </div>

                {{-- Texte --}}
                <div id="wm-text-field" style="margin-bottom:16px;{{ ($settings->wm_type ?? 'text') === 'logo' ? 'display:none;' : '' }}">
                    <label style="display:block;font-size:13px;font-weight:500;color:#374151;margin-bottom:4px;">Texte du watermark</label>
                    <input type="text" name="wm_text"
                           value="{{ $settings->wm_text ?? '© '.(app(\App\Services\TenantManager::class)->current()?->name ?? 'Organisation') }}"
                           maxlength="100"
                           placeholder="© Votre organisation"
                           style="width:100%;border:1px solid #d1d5db;border-radius:8px;padding:8px 12px;font-size:13px;box-sizing:border-box;">
                </div>

                {{-- Info logo --}}
                <div id="wm-logo-info" style="margin-bottom:16px;padding:10px 14px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;font-size:12px;color:#1d4ed8;{{ ($settings->wm_type ?? 'text') !== 'logo' ? 'display:none;' : '' }}">
                    Le logo configuré dans <a href="{{ route('admin.settings.branding') }}" style="text-decoration:underline;">Personnalisation visuelle</a> sera utilisé.
                    Si aucun logo n'est défini, le texte ci-dessus sera utilisé en fallback.
                </div>

                {{-- Position + Taille + Opacité --}}
                <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:16px;">
                    <div>
                        <label style="display:block;font-size:13px;font-weight:500;color:#374151;margin-bottom:4px;">Position</label>
                        <select name="wm_position" style="width:100%;border:1px solid #d1d5db;border-radius:8px;padding:8px 12px;font-size:13px;">
                            @foreach(['bottom-right' => 'Bas droite', 'bottom-left' => 'Bas gauche', 'bottom-center' => 'Bas centre', 'center' => 'Centre'] as $val => $lbl)
                                <option value="{{ $val }}" {{ ($settings->wm_position ?? 'bottom-right') === $val ? 'selected' : '' }}>{{ $lbl }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label style="display:block;font-size:13px;font-weight:500;color:#374151;margin-bottom:4px;">Taille</label>
                        <select name="wm_size" style="width:100%;border:1px solid #d1d5db;border-radius:8px;padding:8px 12px;font-size:13px;">
                            @foreach(['small' => 'Petite', 'medium' => 'Moyenne', 'large' => 'Grande'] as $val => $lbl)
                                <option value="{{ $val }}" {{ ($settings->wm_size ?? 'medium') === $val ? 'selected' : '' }}>{{ $lbl }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label style="display:block;font-size:13px;font-weight:500;color:#374151;margin-bottom:4px;">
                            Opacité : <span id="wm-opacity-val">{{ $settings->wm_opacity ?? 60 }}</span>%
                        </label>
                        <input type="range" name="wm_opacity"
                               min="10" max="100" step="5"
                               value="{{ $settings->wm_opacity ?? 60 }}"
                               style="width:100%;accent-color:#1E3A5F;"
                               oninput="document.getElementById('wm-opacity-val').textContent = this.value">
                    </div>
                </div>

            </div>{{-- /#wm-options --}}
        </div>

        <div class="flex justify-end">
            <button type="submit"
                    class="px-5 py-2 rounded-lg text-white text-sm font-medium hover:opacity-90 transition-opacity"
                    style="background-color: var(--color-primary, #1E3A5F);">
                Enregistrer
            </button>
        </div>
    </form>

</div>
@endsection
