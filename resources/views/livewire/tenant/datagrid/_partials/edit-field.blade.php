{{-- ── Partial : rendu d'un champ de formulaire selon son DatagridColumnType ──
     Variables attendues :
       $col        : DatagridColumn
       $formPrefix : 'editForm' | 'addForm'
       $formValues : array (valeurs courantes)
       $canWrite   : bool
       $rowId      : int|null (pour les ids uniques — null en mode ajout)
     Utilisé par : edit-row-modal.blade.php, add-row-modal.blade.php
──────────────────────────────────────────────────────────────────────────── --}}

@php
    $uid       = $rowId ?? 'new';
    $val       = $formValues[$col->name] ?? '';
    $hasError  = $errors->has("{$formPrefix}.{$col->name}");
    $fieldBorder = $hasError ? 'border:2px solid #dc2626;' : 'border:1px solid var(--pd-border);';
    $baseStyle = "width:100%;padding:7px 10px;{$fieldBorder}border-radius:7px;font-size:13px;background:var(--pd-bg);color:var(--pd-text);box-sizing:border-box;";
    $disabled  = ! $canWrite ? 'disabled' : '';
@endphp

<div>
    {{-- Label --}}
    <label style="display:block;font-size:12px;font-weight:600;color:var(--pd-text);margin-bottom:4px;">
        {{ $col->label }}
        @if($col->required)
            <span style="color:#dc2626;">*</span>
        @endif
        @if($col->is_rgpd_sensitive)
            <span title="Donnée RGPD sensible"
                  style="display:inline-block;margin-left:4px;padding:1px 5px;background:#fef3c7;
                         border:1px solid #fcd34d;border-radius:4px;font-size:10px;color:#92400e;font-weight:500;">RGPD</span>
        @endif
    </label>

    {{-- ── BOOLEAN ──────────────────────────────────────────────────────── --}}
    @if($col->type === \App\Enums\DatagridColumnType::BOOLEAN)
        @php
            $isTrue     = in_array($val, ['1', 1, true, 'true', 'oui'], false);
            $labelTrue  = $col->label_true  ?? 'Oui';
            $labelFalse = $col->label_false ?? 'Non';
        @endphp
        <div style="display:flex;align-items:center;gap:10px;">
            <button type="button"
                    @if($canWrite) wire:click="$set('{{ $formPrefix }}.{{ $col->name }}', '0')" @endif
                    style="padding:7px 16px;border-radius:7px;font-size:13px;font-weight:600;
                           cursor:{{ $canWrite ? 'pointer' : 'default' }};transition:all .15s;
                           {{ ! $isTrue ? 'background:#fee2e2;color:#dc2626;border:2px solid #fca5a5;' : 'background:var(--pd-bg2);color:var(--pd-muted);border:1px solid var(--pd-border);' }}">
                {{ $labelFalse }}
            </button>
            <div wire:click="{{ $canWrite ? '$set(\''.$formPrefix.'.'.$col->name.'\', \''.($isTrue ? '0' : '1').'\')' : '' }}"
                 style="position:relative;width:44px;height:24px;border-radius:12px;
                        cursor:{{ $canWrite ? 'pointer' : 'default' }};transition:background .2s;
                        background:{{ $isTrue ? '#16a34a' : '#d1d5db' }};">
                <div style="position:absolute;top:3px;width:18px;height:18px;border-radius:50%;
                            background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.25);transition:left .2s;
                            left:{{ $isTrue ? '23px' : '3px' }};"></div>
            </div>
            <button type="button"
                    @if($canWrite) wire:click="$set('{{ $formPrefix }}.{{ $col->name }}', '1')" @endif
                    style="padding:7px 16px;border-radius:7px;font-size:13px;font-weight:600;
                           cursor:{{ $canWrite ? 'pointer' : 'default' }};transition:all .15s;
                           {{ $isTrue ? 'background:#dcfce7;color:#16a34a;border:2px solid #86efac;' : 'background:var(--pd-bg2);color:var(--pd-muted);border:1px solid var(--pd-border);' }}">
                {{ $labelTrue }}
            </button>
        </div>
        <input type="hidden" wire:model="{{ $formPrefix }}.{{ $col->name }}">

    {{-- ── SELECT ───────────────────────────────────────────────────────── --}}
    @elseif($col->type === \App\Enums\DatagridColumnType::SELECT)
        @php
            $opts = is_array($col->options) && count($col->options) ? $col->options : null;
        @endphp
        @if($opts && count($opts) === 2)
            {{-- Toggle 2 valeurs --}}
            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                @foreach($opts as $optVal)
                @php $isSelected = $val === $optVal; @endphp
                <button type="button"
                        @if($canWrite) wire:click="$set('{{ $formPrefix }}.{{ $col->name }}', '{{ $optVal }}')" @endif
                        style="padding:7px 16px;border-radius:7px;font-size:13px;font-weight:600;
                               cursor:{{ $canWrite ? 'pointer' : 'default' }};transition:all .15s;
                               {{ $isSelected ? 'background:#dbeafe;color:#1d4ed8;border:2px solid #93c5fd;' : 'background:var(--pd-bg2);color:var(--pd-muted);border:1px solid var(--pd-border);' }}">
                    {{ $optVal }}
                </button>
                @endforeach
            </div>
            <input type="hidden" wire:model="{{ $formPrefix }}.{{ $col->name }}">

        @elseif($opts && count($opts) > 2)
            {{-- Dropdown liste fermée --}}
            <select wire:model="{{ $formPrefix }}.{{ $col->name }}"
                    {{ $disabled }}
                    style="{{ $baseStyle }}">
                <option value="">— Choisir —</option>
                @foreach($opts as $optVal)
                <option value="{{ $optVal }}">{{ $optVal }}</option>
                @endforeach
            </select>

        @else
            {{-- Liste ouverte avec suggestions --}}
            @php $dlId = 'dl_'.$col->name.'_'.$uid; @endphp
            <input type="text"
                   wire:model="{{ $formPrefix }}.{{ $col->name }}"
                   list="{{ $dlId }}"
                   {{ $disabled }}
                   placeholder="Saisir ou choisir…"
                   style="{{ $baseStyle }}">
            @if(isset($distinctValues[$col->name]) && count($distinctValues[$col->name]))
            <datalist id="{{ $dlId }}">
                @foreach($distinctValues[$col->name] as $dv)
                <option value="{{ $dv }}">
                @endforeach
            </datalist>
            <span style="display:block;margin-top:3px;font-size:10px;color:var(--pd-muted);">
                Saisie libre — {{ count($distinctValues[$col->name]) }} valeur(s) existante(s) proposées.
            </span>
            @else
            <span style="display:block;margin-top:3px;font-size:10px;color:var(--pd-muted);">Saisie libre.</span>
            @endif
        @endif

    {{-- ── DATE ─────────────────────────────────────────────────────────── --}}
    @elseif($col->type === \App\Enums\DatagridColumnType::DATE)
        <input type="date"
               wire:model="{{ $formPrefix }}.{{ $col->name }}"
               {{ $disabled }}
               style="{{ $baseStyle }}">
        <span style="display:block;margin-top:3px;font-size:10px;color:var(--pd-muted);">Format : JJ/MM/AAAA</span>

    {{-- ── NUMBER ───────────────────────────────────────────────────────── --}}
    @elseif($col->type === \App\Enums\DatagridColumnType::NUMBER)
        <input type="number" step="any"
               wire:model="{{ $formPrefix }}.{{ $col->name }}"
               placeholder="0"
               {{ $disabled }}
               style="{{ $baseStyle }}">

    {{-- ── EMAIL ───────────────────────────────────────────────────────── --}}
    @elseif($col->type === \App\Enums\DatagridColumnType::EMAIL)
        <input type="email"
               wire:model="{{ $formPrefix }}.{{ $col->name }}"
               placeholder="exemple@domaine.fr"
               {{ $disabled }}
               style="{{ $baseStyle }}">

    {{-- ── PHONE ───────────────────────────────────────────────────────── --}}
    @elseif($col->type === \App\Enums\DatagridColumnType::PHONE)
        <input type="tel"
               wire:model="{{ $formPrefix }}.{{ $col->name }}"
               placeholder="06 12 34 56 78"
               maxlength="{{ $col->length ?? 30 }}"
               {{ $disabled }}
               style="{{ $baseStyle }}">
        <span style="display:block;margin-top:3px;font-size:10px;color:var(--pd-muted);">
            Format : 06 12 34 56 78 ou +33 6 12 34 56 78
        </span>

    {{-- ── SIRET ───────────────────────────────────────────────────────── --}}
    @elseif($col->type === \App\Enums\DatagridColumnType::SIRET)
        <input type="text"
               wire:model="{{ $formPrefix }}.{{ $col->name }}"
               placeholder="123 456 789 01234"
               maxlength="14"
               {{ $disabled }}
               style="{{ $baseStyle }}font-family:monospace;">
        <span style="display:block;margin-top:3px;font-size:10px;color:var(--pd-muted);">
            14 chiffres sans espaces (SIREN 9 chiffres + NIC 5 chiffres)
        </span>

    {{-- ── CODE POSTAL ─────────────────────────────────────────────────── --}}
    @elseif($col->type === \App\Enums\DatagridColumnType::POSTAL_CODE)
        <input type="text"
               wire:model="{{ $formPrefix }}.{{ $col->name }}"
               placeholder="85300"
               maxlength="10"
               {{ $disabled }}
               style="{{ $baseStyle }}font-family:monospace;max-width:120px;">

    {{-- ── CHEMIN FICHIER ──────────────────────────────────────────────── --}}
    @elseif($col->type === \App\Enums\DatagridColumnType::CHEMIN_FICHIER)
        @php
            $ext  = strtolower(pathinfo($val, PATHINFO_EXTENSION));
            $icon = match(true) {
                in_array($ext, ['pdf'])                                => '📄',
                in_array($ext, ['jpg','jpeg','png','gif','webp','svg'])=> '🖼️',
                in_array($ext, ['doc','docx','odt'])                   => '📝',
                in_array($ext, ['xls','xlsx','ods','csv'])             => '📊',
                in_array($ext, ['zip','tar','gz','7z','rar'])          => '🗜️',
                in_array($ext, ['mp4','avi','mov','mkv'])              => '🎬',
                in_array($ext, ['mp3','wav','ogg','flac'])             => '🎵',
                default                                                => '📎',
            };
        @endphp
        @if($val)
        <div style="display:flex;align-items:center;gap:8px;padding:8px 10px;
                    background:var(--pd-bg2);border:1px solid var(--pd-border);
                    border-radius:7px;margin-bottom:6px;">
            <span style="font-size:18px;">{{ $icon }}</span>
            <span style="font-size:12px;font-family:monospace;color:var(--pd-text);
                         overflow:hidden;text-overflow:ellipsis;white-space:nowrap;flex:1;"
                  title="{{ $val }}">{{ basename($val) }}</span>
        </div>
        @endif
        <input type="text"
               wire:model="{{ $formPrefix }}.{{ $col->name }}"
               {{ $disabled }}
               placeholder="/chemin/vers/fichier.pdf"
               style="{{ $baseStyle }}font-family:monospace;font-size:11px;">
        <span style="display:block;margin-top:3px;font-size:10px;color:var(--pd-muted);">
            Chemin complet du fichier sur le serveur ou réseau.
        </span>

    {{-- ── NOM_PERSONNE (Bloc 3.1 — même rendu que TEXT pour l'instant) ── --}}
    @elseif($col->type === \App\Enums\DatagridColumnType::NOM_PERSONNE)
        <input type="text"
               wire:model="{{ $formPrefix }}.{{ $col->name }}"
               @if($col->length) maxlength="{{ $col->length }}" @endif
               {{ $disabled }}
               style="{{ $baseStyle }}">
        <span style="display:block;margin-top:3px;font-size:10px;color:var(--pd-muted);">
            Nom de personne — recherche floue activée.
        </span>

    {{-- ── RELATION (Bloc 4 — lecture seule pour l'instant) ──────────── --}}
    @elseif($col->type === \App\Enums\DatagridColumnType::RELATION)
        <div style="padding:7px 10px;border:1px solid var(--pd-border);border-radius:7px;
                    font-size:13px;background:var(--pd-bg2);color:var(--pd-muted);">
            {{ $val ?: '—' }}
            <span style="font-size:10px;color:var(--pd-muted);margin-left:6px;">(relation — lecture seule)</span>
        </div>

    {{-- ── TEXT (défaut) ───────────────────────────────────────────────── --}}
    @else
        <input type="text"
               wire:model="{{ $formPrefix }}.{{ $col->name }}"
               @if($col->length) maxlength="{{ $col->length }}" @endif
               {{ $disabled }}
               style="{{ $baseStyle }}">
        @if($col->length)
        <span style="display:block;margin-top:3px;font-size:10px;color:var(--pd-muted);">
            Max {{ $col->length }} caractères
        </span>
        @endif
    @endif

    {{-- Message d'erreur --}}
    @error("{$formPrefix}.{$col->name}")
        <span style="display:flex;align-items:center;gap:4px;margin-top:4px;font-size:11px;color:#dc2626;">
            <span>⚠</span> {{ $message }}
        </span>
    @enderror
</div>
