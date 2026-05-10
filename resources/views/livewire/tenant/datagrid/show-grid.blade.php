<div>

{{-- ── Barre haute ──────────────────────────────────────────────────────── --}}
<div style="display:flex;align-items:center;gap:12px;padding:20px 32px;border-bottom:1px solid var(--pd-border);flex-wrap:wrap;">
    <h1 style="font-size:18px;font-weight:700;color:var(--pd-text);margin:0;flex:1;min-width:160px;">
        {{ $table->label }}
    </h1>

    {{-- Vues sauvegardées --}}
    @if($savedViews->count())
    <select wire:model.live="activeViewId"
            style="padding:6px 10px;border:1px solid var(--pd-border);border-radius:7px;font-size:12px;color:var(--pd-text);background:var(--pd-bg);">
        <option value="">— Choisir une vue —</option>
        @foreach($savedViews as $sv)
        <option value="{{ $sv->id }}" {{ $activeViewId === $sv->id ? 'selected' : '' }}>{{ $sv->name }}</option>
        @endforeach
    </select>
    @endif

    {{-- Sauvegarder la vue courante --}}
    <div style="display:flex;gap:6px;align-items:center;">
        <input wire:model="newViewName" type="text" placeholder="Nom de la vue…"
               style="padding:6px 10px;border:1px solid var(--pd-border);border-radius:7px;font-size:12px;width:140px;color:var(--pd-text);background:var(--pd-bg);">
        <button wire:click="saveCurrentView"
                style="padding:6px 12px;background:var(--pd-navy);color:#fff;border:none;border-radius:7px;font-size:12px;font-weight:600;cursor:pointer;">
            Sauvegarder
        </button>
    </div>

    {{-- Nouvelle ligne --}}
    @if($userPerms['can_write'])
    <button wire:click="openAdd"
            style="padding:6px 14px;background:var(--pd-navy);color:#fff;border:none;border-radius:7px;font-size:12px;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:5px;white-space:nowrap;">
        <span style="font-size:15px;line-height:1;">+</span> Nouvelle ligne
    </button>
    @endif

    {{-- Export Excel --}}
    <button wire:click="exportExcel"
            style="padding:6px 14px;background:#16a34a;color:#fff;border:none;border-radius:7px;font-size:12px;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:5px;white-space:nowrap;">
        <span style="font-size:13px;">↓</span> Export Excel
    </button>

    {{-- Export ODS --}}
    <button wire:click="exportOds"
            style="padding:6px 14px;background:#0891b2;color:#fff;border:none;border-radius:7px;font-size:12px;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:5px;white-space:nowrap;">
        <span style="font-size:13px;">↓</span> Export ODS
    </button>

    {{-- Effacer filtres --}}
    @if(count(array_filter($filters, fn($v) => $v !== '' && $v !== null)))
    <button wire:click="clearFilters"
            style="padding:6px 12px;border:1px solid #fca5a5;border-radius:7px;font-size:12px;color:#dc2626;background:#fef2f2;cursor:pointer;">
        Effacer filtres
    </button>
    @endif

    {{-- Sélecteur de colonnes --}}
    <div style="position:relative;">
        <button wire:click="toggleColumnPicker"
                style="padding:6px 12px;border:1px solid var(--pd-border);border-radius:7px;font-size:12px;
                       color:var(--pd-text);background:var(--pd-bg);cursor:pointer;display:flex;align-items:center;gap:5px;">
            <span>⊞</span> Colonnes
            @php $hiddenCount = $columns->whereNotIn('id', $visibleColumns)->count(); @endphp
            @if($hiddenCount > 0)
            <span style="background:var(--pd-navy);color:#fff;border-radius:10px;font-size:10px;
                         padding:1px 6px;font-weight:600;">{{ $hiddenCount }}</span>
            @endif
        </button>

        @if($showColumnPicker)
        <div style="position:absolute;right:0;top:calc(100% + 6px);z-index:100;
                    background:var(--pd-surface);border:0.5px solid var(--pd-border);
                    border-radius:10px;box-shadow:0 4px 16px rgba(0,0,0,.12);
                    min-width:220px;overflow:hidden;">
            <div style="padding:10px 14px;border-bottom:0.5px solid var(--pd-border);
                        font-size:11px;font-weight:600;color:var(--pd-muted);
                        text-transform:uppercase;letter-spacing:.04em;">
                Colonnes affichées
            </div>
            @foreach($columns as $col)
            <label style="display:flex;align-items:center;gap:10px;padding:8px 14px;
                          cursor:pointer;font-size:12px;color:var(--pd-text);
                          border-bottom:0.5px solid var(--pd-border);"
                   onmouseover="this.style.background='var(--pd-bg2)'"
                   onmouseout="this.style.background='transparent'">
                <input type="checkbox"
                       wire:click="toggleColumn({{ $col->id }})"
                       {{ in_array($col->id, $visibleColumns) ? 'checked' : '' }}
                       style="width:14px;height:14px;cursor:pointer;">
                <span>{{ $col->label }}</span>
                @if(! $col->visible_by_default)
                <span style="font-size:10px;color:var(--pd-muted);margin-left:auto;font-style:italic;">masquée</span>
                @endif
            </label>
            @endforeach
            <div style="padding:8px 14px;display:flex;justify-content:space-between;gap:8px;">
                <button wire:click="showAllColumns"
                        style="flex:1;padding:5px 8px;font-size:11px;border:0.5px solid var(--pd-border);
                               border-radius:6px;background:var(--pd-bg2);color:var(--pd-muted);cursor:pointer;">
                    Tout afficher
                </button>
                <button wire:click="resetColumnsToDefault"
                        style="flex:1;padding:5px 8px;font-size:11px;border:0.5px solid var(--pd-border);
                               border-radius:6px;background:var(--pd-bg2);color:var(--pd-muted);cursor:pointer;">
                    Par défaut
                </button>
            </div>
        </div>
        @endif
    </div>
</div>

{{-- ── Badges filtres actifs ────────────────────────────────────────────── --}}
@if(count(array_filter($filters, fn($v) => $v !== '')))
<div style="display:flex;flex-wrap:wrap;gap:6px;padding:10px 32px;border-bottom:1px solid var(--pd-border);">
    @foreach($filters as $col => $val)
    @if($val !== '')
    <span style="display:inline-flex;align-items:center;gap:6px;padding:3px 10px;background:color-mix(in srgb,var(--pd-navy) 10%,transparent);border:1px solid color-mix(in srgb,var(--pd-navy) 20%,transparent);border-radius:20px;font-size:11px;color:var(--pd-text);">
        {{ $col }} : {{ $val }}
        <button wire:click="applyFilter('{{ $col }}', '')"
                style="background:none;border:none;cursor:pointer;color:var(--pd-muted);font-size:13px;line-height:1;padding:0;">×</button>
    </span>
    @endif
    @endforeach
</div>
@endif

{{-- ── Tableau ───────────────────────────────────────────────────────────── --}}
<div style="padding:24px 32px;">
    <div style="overflow-x:auto;border:1px solid var(--pd-border);border-radius:10px;">
        <table style="width:100%;border-collapse:collapse;font-size:12px;">
            <thead>
                <tr style="background:var(--pd-bg2,#f8f9fb);">
                    @foreach($columns->whereIn('id', $visibleColumns) as $col)
                    <th wire:click="sortBy('{{ $col->name }}')"
                        style="padding:10px 12px;text-align:left;font-weight:600;color:var(--pd-text);border-bottom:1px solid var(--pd-border);cursor:pointer;white-space:nowrap;user-select:none;">
                        {{ $col->label }}
                        @if($sortColumn === $col->name)
                            {{ $sortDirection === 'asc' ? '↑' : '↓' }}
                        @endif
                    </th>
                    @endforeach
                    <th style="padding:10px 8px;width:48px;min-width:48px;max-width:48px;border-bottom:1px solid var(--pd-border);position:sticky;right:0;background:var(--pd-bg2,#f8f9fb);z-index:2;"></th>
                </tr>
                {{-- Ligne filtres --}}
                <tr style="background:var(--pd-bg);">
                    @foreach($columns->whereIn('id', $visibleColumns) as $col)
                    <td style="padding:6px 8px;border-bottom:1px solid var(--pd-border);">
                        @if($col->type === \App\Enums\DatagridColumnType::BOOLEAN)
                            <select wire:model.live="filters.{{ $col->name }}"
                                    style="width:100%;padding:4px 6px;border:1px solid var(--pd-border);border-radius:5px;font-size:11px;background:var(--pd-bg);color:var(--pd-text);">
                                <option value="">Tous</option>
                                <option value="1">{{ $col->label_true ?? 'Oui' }}</option>
                                <option value="0">{{ $col->label_false ?? 'Non' }}</option>
                            </select>
                        @elseif($col->type === \App\Enums\DatagridColumnType::SELECT)
                            @php $hasClosedOpts = is_array($col->options) && count($col->options) > 0; @endphp
                            @if($hasClosedOpts)
                                {{-- Liste fermée → select --}}
                                <select wire:model.live="filters.{{ $col->name }}"
                                        style="width:100%;padding:4px 6px;border:1px solid var(--pd-border);border-radius:5px;font-size:11px;background:var(--pd-bg);color:var(--pd-text);">
                                    <option value="">Tous</option>
                                    @foreach($col->options as $optVal)
                                    <option value="{{ $optVal }}">{{ $optVal }}</option>
                                    @endforeach
                                </select>
                            @else
                                {{-- Liste ouverte → input + datalist --}}
                                @php $filterDlId = 'fdl_'.$col->name; @endphp
                                <input wire:model.live.debounce.300ms="filters.{{ $col->name }}"
                                       type="text"
                                       list="{{ $filterDlId }}"
                                       placeholder="Filtrer…"
                                       style="width:100%;padding:4px 8px;border:1px solid var(--pd-border);border-radius:5px;font-size:11px;box-sizing:border-box;background:var(--pd-bg);">
                                @if(isset($distinctValues[$col->name]) && count($distinctValues[$col->name]))
                                <datalist id="{{ $filterDlId }}">
                                    @foreach($distinctValues[$col->name] as $dv)
                                    <option value="{{ $dv }}">
                                    @endforeach
                                </datalist>
                                @endif
                            @endif
                        @elseif($col->type === \App\Enums\DatagridColumnType::DATE)
                            <div style="display:flex;gap:3px;align-items:center;">
                                <input type="date"
                                       wire:model.live.debounce.300ms="filters.{{ $col->name }}_from"
                                       placeholder="Du"
                                       style="flex:1;padding:3px 5px;border:1px solid var(--pd-border);border-radius:5px;font-size:10px;background:var(--pd-bg);color:var(--pd-text);">
                                <span style="font-size:10px;color:var(--pd-muted);">→</span>
                                <input type="date"
                                       wire:model.live.debounce.300ms="filters.{{ $col->name }}_to"
                                       placeholder="Au"
                                       style="flex:1;padding:3px 5px;border:1px solid var(--pd-border);border-radius:5px;font-size:10px;background:var(--pd-bg);color:var(--pd-text);">
                            </div>
                        @elseif($col->type === \App\Enums\DatagridColumnType::NUMBER)
                            <div style="display:flex;gap:3px;align-items:center;">
                                <input type="number" step="any"
                                       wire:model.live.debounce.300ms="filters.{{ $col->name }}_min"
                                       placeholder="Min"
                                       style="flex:1;padding:3px 5px;border:1px solid var(--pd-border);border-radius:5px;font-size:10px;background:var(--pd-bg);color:var(--pd-text);">
                                <span style="font-size:10px;color:var(--pd-muted);">→</span>
                                <input type="number" step="any"
                                       wire:model.live.debounce.300ms="filters.{{ $col->name }}_max"
                                       placeholder="Max"
                                       style="flex:1;padding:3px 5px;border:1px solid var(--pd-border);border-radius:5px;font-size:10px;background:var(--pd-bg);color:var(--pd-text);">
                            </div>
                        @else
                            <input wire:model.live.debounce.300ms="filters.{{ $col->name }}"
                                   type="text"
                                   placeholder="Filtrer…"
                                   style="width:100%;padding:4px 8px;border:1px solid var(--pd-border);border-radius:5px;font-size:11px;box-sizing:border-box;background:var(--pd-bg);">
                        @endif
                    </td>
                    @endforeach
                    <td style="padding:6px 8px;width:48px;min-width:48px;max-width:48px;border-bottom:1px solid var(--pd-border);position:sticky;right:0;background:var(--pd-bg);z-index:2;"></td>
                </tr>
            </thead>
            <tbody>
                @forelse($this->rows as $row)
                @php $row = (array) $row; @endphp
                <tr wire:key="row-{{ $row['id'] ?? 0 }}"
                    style="border-bottom:1px solid var(--pd-border);transition:background 0.1s;"
                    onmouseover="this.style.background='color-mix(in srgb,var(--pd-navy) 4%,transparent)'"
                    onmouseout="this.style.background=''">
                    @foreach($columns->whereIn('id', $visibleColumns) as $col)
                    @php $val = $row[$col->name] ?? null; @endphp
                    <td style="padding:9px 12px;color:var(--pd-text);">
                        @if($val === null || $val === '')
                            <span style="color:var(--pd-muted);font-style:italic;">—</span>
                        @elseif($col->type === \App\Enums\DatagridColumnType::BOOLEAN)
                            @if(in_array($val, ['1', 1, 'true', 'oui'], false))
                                <span style="display:inline-flex;align-items:center;gap:5px;">
                                    <span style="display:inline-flex;align-items:center;justify-content:center;width:18px;height:18px;border-radius:50%;background:#dcfce7;color:#16a34a;font-size:11px;flex-shrink:0;">✓</span>
                                    <span style="font-size:12px;color:#16a34a;">{{ $col->label_true ?? 'Oui' }}</span>
                                </span>
                            @else
                                <span style="display:inline-flex;align-items:center;gap:5px;">
                                    <span style="display:inline-flex;align-items:center;justify-content:center;width:18px;height:18px;border-radius:50%;background:#fee2e2;color:#dc2626;font-size:11px;flex-shrink:0;">✕</span>
                                    <span style="font-size:12px;color:#dc2626;">{{ $col->label_false ?? 'Non' }}</span>
                                </span>
                            @endif
                        @elseif($col->type === \App\Enums\DatagridColumnType::DATE)
                            @if(preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $val, $m))
                                {{ $m[3] }}/{{ $m[2] }}/{{ $m[1] }}
                            @else
                                {{ $val }}
                            @endif
                        @elseif($col->type === \App\Enums\DatagridColumnType::PHONE)
                            @php
                                $phone = preg_replace('/\D/', '', $val);
                                $formatted = $phone;
                                if (strlen($phone) === 10) {
                                    $formatted = implode(' ', str_split($phone, 2));
                                } elseif (str_starts_with($val, '+')) {
                                    $formatted = $val;
                                }
                            @endphp
                            <a href="tel:{{ $val }}" style="color:var(--pd-text);text-decoration:none;">{{ $formatted }}</a>
                        @elseif($col->type === \App\Enums\DatagridColumnType::SIRET)
                            @php
                                $s = str_pad(preg_replace('/\D/', '', $val), 14, '0', STR_PAD_LEFT);
                                $formatted = strlen($s) === 14
                                    ? substr($s,0,3).' '.substr($s,3,3).' '.substr($s,6,3).' '.substr($s,9,5)
                                    : $val;
                            @endphp
                            <span style="font-family:monospace;font-size:11px;">{{ $formatted }}</span>
                        @elseif($col->type === \App\Enums\DatagridColumnType::EMAIL)
                            <a href="mailto:{{ $val }}" style="color:var(--pd-navy);text-decoration:none;">{{ $val }}</a>
                        @elseif($col->type === \App\Enums\DatagridColumnType::NUMBER)
                            <span style="font-variant-numeric:tabular-nums;">{{ rtrim(rtrim(number_format((float)$val, 4, ',', ' '), '0'), ',') }}</span>
                        @elseif($col->type === \App\Enums\DatagridColumnType::SELECT && is_array($col->options) && count($col->options) === 2)
                            <span style="display:inline-flex;align-items:center;justify-content:center;
                                padding:2px 10px;border-radius:20px;font-size:11px;font-weight:500;
                                background:color-mix(in srgb,var(--pd-navy) 8%,transparent);
                                color:var(--pd-navy);">
                                {{ $val }}
                            </span>
                        @elseif($col->type === \App\Enums\DatagridColumnType::SELECT && is_array($col->options) && count($col->options) > 2)
                            <span style="display:inline-flex;align-items:center;justify-content:center;
                                padding:2px 10px;border-radius:20px;font-size:11px;font-weight:500;
                                background:color-mix(in srgb,var(--pd-navy) 8%,transparent);
                                color:var(--pd-navy);">
                                {{ $val }}
                            </span>
                        @elseif($col->type === \App\Enums\DatagridColumnType::CHEMIN_FICHIER)
                            @php
                                $ext = strtolower(pathinfo($val, PATHINFO_EXTENSION));
                                $icon = match(true) {
                                    in_array($ext, ['pdf'])                               => '📄',
                                    in_array($ext, ['jpg','jpeg','png','gif','webp','svg']) => '🖼️',
                                    in_array($ext, ['doc','docx','odt'])                  => '📝',
                                    in_array($ext, ['xls','xlsx','ods','csv'])            => '📊',
                                    in_array($ext, ['zip','tar','gz','7z','rar'])         => '🗜️',
                                    in_array($ext, ['mp4','avi','mov','mkv'])             => '🎬',
                                    in_array($ext, ['mp3','wav','ogg','flac'])            => '🎵',
                                    default                                               => '📎',
                                };
                                $filename = basename($val);
                            @endphp
                            <span title="{{ $val }}"
                                  style="display:inline-flex;align-items:center;gap:5px;font-size:12px;color:var(--pd-text);">
                                <span>{{ $icon }}</span>
                                <span style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-family:monospace;font-size:11px;color:var(--pd-muted);">{{ $filename }}</span>
                            </span>
                        @else
                            {{ $val }}
                        @endif
                    </td>
                    @endforeach
                    <td style="padding:6px 8px;text-align:center;width:48px;min-width:48px;max-width:48px;position:sticky;right:0;background:var(--pd-bg);z-index:1;">
                        <button wire:click="openEdit({{ $row['id'] ?? 0 }})"
                                title="Modifier"
                                style="padding:4px 6px;background:none;border:none;cursor:pointer;font-size:15px;line-height:1;color:var(--pd-muted);border-radius:5px;"
                                onmouseover="this.style.background='var(--pd-bg2)'"
                                onmouseout="this.style.background='none'">
                            ✏️
                        </button>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="{{ $columns->whereIn('id', $visibleColumns)->count() }}"
                        style="padding:32px;text-align:center;color:var(--pd-muted);">
                        Aucune ligne trouvée.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    <div style="margin-top:16px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;">
        <span style="font-size:12px;color:var(--pd-muted);">
            Affichage de {{ $this->rows->firstItem() ?? 0 }} à {{ $this->rows->lastItem() ?? 0 }}
            sur {{ $this->rows->total() }} résultat{{ $this->rows->total() > 1 ? 's' : '' }}
        </span>
        <div>{{ $this->rows->links() }}</div>
    </div>
</div>

{{-- ── Modal édition / suppression ────────────────────────────────────────── --}}
@if($editingRowId !== null)
<div wire:click.self="closeEdit"
     style="position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:1000;display:flex;align-items:center;justify-content:center;padding:20px;">
    <div style="background:var(--pd-bg);border-radius:12px;box-shadow:0 20px 60px rgba(0,0,0,0.25);width:100%;max-width:560px;max-height:90vh;display:flex;flex-direction:column;">

        {{-- En-tête modal --}}
        <div style="display:flex;align-items:center;justify-content:space-between;padding:18px 24px;border-bottom:1px solid var(--pd-border);">
            <h2 style="margin:0;font-size:15px;font-weight:700;color:var(--pd-text);">
                Modifier la ligne #{{ $editingRowId }}
            </h2>
            <button wire:click="closeEdit"
                    style="background:none;border:none;cursor:pointer;color:var(--pd-muted);font-size:20px;line-height:1;padding:0;">×</button>
        </div>

        {{-- Corps : champs dynamiques --}}
        <div style="overflow-y:auto;padding:20px 24px;display:flex;flex-direction:column;gap:14px;">
            @foreach($columns as $col)
            @if($col->name === 'id')
                @continue
            @endif
            <div>
                <label style="display:block;font-size:12px;font-weight:600;color:var(--pd-text);margin-bottom:4px;">
                    {{ $col->label }}
                    @if($col->required)
                        <span style="color:#dc2626;">*</span>
                    @endif
                    @if($col->is_rgpd_sensitive)
                        <span title="Donnée RGPD sensible"
                              style="display:inline-block;margin-left:4px;padding:1px 5px;background:#fef3c7;border:1px solid #fcd34d;border-radius:4px;font-size:10px;color:#92400e;font-weight:500;">RGPD</span>
                    @endif
                </label>

                @php
                    $hasError = $errors->has("editForm.{$col->name}");
                    $fieldBorder = $hasError
                        ? 'border:2px solid #dc2626;'
                        : 'border:1px solid var(--pd-border);';
                    $baseStyle = "width:100%;padding:7px 10px;{$fieldBorder}border-radius:7px;font-size:13px;background:var(--pd-bg);color:var(--pd-text);box-sizing:border-box;";
                @endphp

                @if($col->type === \App\Enums\DatagridColumnType::BOOLEAN)
                    @php
                        $currentVal = $editForm[$col->name] ?? '';
                        $isTrue = in_array($currentVal, ['1', 1, true, 'true', 'oui'], false);
                        $labelTrue  = $col->label_true  ?? 'Oui';
                        $labelFalse = $col->label_false ?? 'Non';
                    @endphp
                    <div style="display:flex;align-items:center;gap:10px;">
                        {{-- Bouton Faux --}}
                        <button type="button"
                                @if($userPerms['can_write']) wire:click="$set('editForm.{{ $col->name }}', '0')" @endif
                                style="padding:7px 16px;border-radius:7px;font-size:13px;font-weight:600;cursor:{{ $userPerms['can_write'] ? 'pointer' : 'default' }};transition:all .15s;
                                       {{ ! $isTrue ? 'background:#fee2e2;color:#dc2626;border:2px solid #fca5a5;' : 'background:var(--pd-bg2);color:var(--pd-muted);border:1px solid var(--pd-border);' }}">
                            {{ $labelFalse }}
                        </button>

                        {{-- Toggle switch --}}
                        <div wire:click="{{ $userPerms['can_write'] ? '$set(\'editForm.'.$col->name.'\', \''.($isTrue ? '0' : '1').'\')' : '' }}"
                             style="position:relative;width:44px;height:24px;border-radius:12px;cursor:{{ $userPerms['can_write'] ? 'pointer' : 'default' }};transition:background .2s;
                                    background:{{ $isTrue ? '#16a34a' : '#d1d5db' }};">
                            <div style="position:absolute;top:3px;width:18px;height:18px;border-radius:50%;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.25);transition:left .2s;
                                        left:{{ $isTrue ? '23px' : '3px' }};"></div>
                        </div>

                        {{-- Bouton Vrai --}}
                        <button type="button"
                                @if($userPerms['can_write']) wire:click="$set('editForm.{{ $col->name }}', '1')" @endif
                                style="padding:7px 16px;border-radius:7px;font-size:13px;font-weight:600;cursor:{{ $userPerms['can_write'] ? 'pointer' : 'default' }};transition:all .15s;
                                       {{ $isTrue ? 'background:#dcfce7;color:#16a34a;border:2px solid #86efac;' : 'background:var(--pd-bg2);color:var(--pd-muted);border:1px solid var(--pd-border);' }}">
                            {{ $labelTrue }}
                        </button>
                    </div>
                    {{-- Champ caché pour Livewire --}}
                    <input type="hidden" wire:model="editForm.{{ $col->name }}">

                @elseif($col->type === \App\Enums\DatagridColumnType::SELECT)
                    @php
                        $opts = is_array($col->options) && count($col->options) ? $col->options : null;
                        $currentSelVal = $editForm[$col->name] ?? '';
                    @endphp
                    @if($opts && count($opts) === 2)
                        {{-- Toggle à deux valeurs --}}
                        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                            @foreach($opts as $optVal)
                            @php $isSelected = $currentSelVal === $optVal; @endphp
                            <button type="button"
                                    @if($userPerms['can_write']) wire:click="$set('editForm.{{ $col->name }}', '{{ $optVal }}')" @endif
                                    style="padding:7px 16px;border-radius:7px;font-size:13px;font-weight:600;
                                           cursor:{{ $userPerms['can_write'] ? 'pointer' : 'default' }};transition:all .15s;
                                           {{ $isSelected ? 'background:#dbeafe;color:#1d4ed8;border:2px solid #93c5fd;' : 'background:var(--pd-bg2);color:var(--pd-muted);border:1px solid var(--pd-border);' }}">
                                {{ $optVal }}
                            </button>
                            @endforeach
                        </div>
                        <input type="hidden" wire:model="editForm.{{ $col->name }}">

                    @elseif($opts && count($opts) > 2)
                        {{-- Dropdown liste fermée --}}
                        <select wire:model="editForm.{{ $col->name }}"
                                @if(!$userPerms['can_write']) disabled @endif
                                style="{{ $baseStyle }}">
                            <option value="">— Choisir —</option>
                            @foreach($opts as $optVal)
                            <option value="{{ $optVal }}">{{ $optVal }}</option>
                            @endforeach
                        </select>

                    @elseif(isset($distinctValues[$col->name]) && count($distinctValues[$col->name]))
                        {{-- Fallback : valeurs distinctes en base (ancienne grille sans options) --}}
                        <select wire:model="editForm.{{ $col->name }}"
                                @if(!$userPerms['can_write']) disabled @endif
                                style="{{ $baseStyle }}">
                            <option value="">— Choisir —</option>
                            @foreach($distinctValues[$col->name] as $dv)
                            <option value="{{ $dv }}">{{ $dv }}</option>
                            @endforeach
                        </select>

                    @else
                        {{-- Cas 3 : liste ouverte — input libre avec suggestions datalist --}}
                        @php $datalistId = 'dl_'.$col->name.'_'.$editingRowId; @endphp
                        <input type="text"
                               wire:model="editForm.{{ $col->name }}"
                               list="{{ $datalistId }}"
                               @if(!$userPerms['can_write']) disabled @endif
                               placeholder="Saisir ou choisir…"
                               style="{{ $baseStyle }}">
                        @if(isset($distinctValues[$col->name]) && count($distinctValues[$col->name]))
                        <datalist id="{{ $datalistId }}">
                            @foreach($distinctValues[$col->name] as $dv)
                            <option value="{{ $dv }}">
                            @endforeach
                        </datalist>
                        <span style="display:block;margin-top:3px;font-size:10px;color:var(--pd-muted);">
                            Saisie libre — {{ count($distinctValues[$col->name]) }} valeur(s) existante(s) proposées.
                        </span>
                        @else
                        <span style="display:block;margin-top:3px;font-size:10px;color:var(--pd-muted);">
                            Saisie libre.
                        </span>
                        @endif
                    @endif

                @elseif($col->type === \App\Enums\DatagridColumnType::DATE)
                    <input type="date"
                           wire:model="editForm.{{ $col->name }}"
                           @if(!$userPerms['can_write']) disabled @endif
                           style="{{ $baseStyle }}">
                    <span style="display:block;margin-top:3px;font-size:10px;color:var(--pd-muted);">Format : JJ/MM/AAAA</span>

                @elseif($col->type === \App\Enums\DatagridColumnType::NUMBER)
                    <input type="number" step="any"
                           wire:model="editForm.{{ $col->name }}"
                           placeholder="0"
                           @if(!$userPerms['can_write']) disabled @endif
                           style="{{ $baseStyle }}">

                @elseif($col->type === \App\Enums\DatagridColumnType::EMAIL)
                    <input type="email"
                           wire:model="editForm.{{ $col->name }}"
                           placeholder="exemple@domaine.fr"
                           @if(!$userPerms['can_write']) disabled @endif
                           style="{{ $baseStyle }}">

                @elseif($col->type === \App\Enums\DatagridColumnType::PHONE)
                    <input type="tel"
                           wire:model="editForm.{{ $col->name }}"
                           placeholder="06 12 34 56 78"
                           maxlength="{{ $col->length ?? 30 }}"
                           @if(!$userPerms['can_write']) disabled @endif
                           style="{{ $baseStyle }}">
                    <span style="display:block;margin-top:3px;font-size:10px;color:var(--pd-muted);">
                        Format : 06 12 34 56 78 ou +33 6 12 34 56 78
                    </span>

                @elseif($col->type === \App\Enums\DatagridColumnType::SIRET)
                    <input type="text"
                           wire:model="editForm.{{ $col->name }}"
                           placeholder="123 456 789 01234"
                           maxlength="14"
                           @if(!$userPerms['can_write']) disabled @endif
                           style="{{ $baseStyle }}font-family:monospace;">
                    <span style="display:block;margin-top:3px;font-size:10px;color:var(--pd-muted);">
                        14 chiffres sans espaces (SIREN 9 chiffres + NIC 5 chiffres)
                    </span>

                @elseif($col->type === \App\Enums\DatagridColumnType::POSTAL_CODE)
                    <input type="text"
                           wire:model="editForm.{{ $col->name }}"
                           placeholder="85300"
                           maxlength="10"
                           @if(!$userPerms['can_write']) disabled @endif
                           style="{{ $baseStyle }}font-family:monospace;max-width:120px;">

                @elseif($col->type === \App\Enums\DatagridColumnType::CHEMIN_FICHIER)
                    @php
                        $chVal = $editForm[$col->name] ?? '';
                        $chExt = strtolower(pathinfo($chVal, PATHINFO_EXTENSION));
                        $chIcon = match(true) {
                            in_array($chExt, ['pdf'])                               => '📄',
                            in_array($chExt, ['jpg','jpeg','png','gif','webp','svg']) => '🖼️',
                            in_array($chExt, ['doc','docx','odt'])                  => '📝',
                            in_array($chExt, ['xls','xlsx','ods','csv'])            => '📊',
                            in_array($chExt, ['zip','tar','gz','7z','rar'])         => '🗜️',
                            in_array($chExt, ['mp4','avi','mov','mkv'])             => '🎬',
                            in_array($chExt, ['mp3','wav','ogg','flac'])            => '🎵',
                            default                                                 => '📎',
                        };
                    @endphp
                    @if($chVal)
                    <div style="display:flex;align-items:center;gap:8px;padding:8px 10px;background:var(--pd-bg2);border:1px solid var(--pd-border);border-radius:7px;margin-bottom:6px;">
                        <span style="font-size:18px;">{{ $chIcon }}</span>
                        <span style="font-size:12px;font-family:monospace;color:var(--pd-text);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;flex:1;" title="{{ $chVal }}">{{ basename($chVal) }}</span>
                    </div>
                    @endif
                    <input type="text"
                           wire:model="editForm.{{ $col->name }}"
                           @if(!$userPerms['can_write']) disabled @endif
                           placeholder="/chemin/vers/fichier.pdf"
                           style="{{ $baseStyle }}font-family:monospace;font-size:11px;">
                    <span style="display:block;margin-top:3px;font-size:10px;color:var(--pd-muted);">Chemin complet du fichier sur le serveur ou réseau.</span>

                @else
                    <input type="text"
                           wire:model="editForm.{{ $col->name }}"
                           @if($col->length) maxlength="{{ $col->length }}" @endif
                           @if(!$userPerms['can_write']) disabled @endif
                           style="{{ $baseStyle }}">
                    @if($col->length)
                        <span style="display:block;margin-top:3px;font-size:10px;color:var(--pd-muted);">
                            Max {{ $col->length }} caractères
                        </span>
                    @endif
                @endif

                @error("editForm.{$col->name}")
                    <span style="display:flex;align-items:center;gap:4px;margin-top:4px;font-size:11px;color:#dc2626;">
                        <span>⚠</span> {{ $message }}
                    </span>
                @enderror
            </div>
            @endforeach
        </div>

        {{-- Pied modal --}}
        <div style="display:flex;align-items:center;justify-content:space-between;padding:16px 24px;border-top:1px solid var(--pd-border);gap:8px;">

            {{-- Bouton Supprimer (gauche) --}}
            @if($userPerms['can_delete'])
            <button wire:click="deleteRow"
                    wire:confirm="Supprimer définitivement cette ligne ? Cette action est irréversible."
                    style="padding:8px 14px;background:#fef2f2;border:1px solid #fca5a5;border-radius:7px;font-size:12px;font-weight:600;color:#dc2626;cursor:pointer;">
                Supprimer
            </button>
            @else
            <span></span>
            @endif

            {{-- Annuler + Enregistrer (droite) --}}
            <div style="display:flex;gap:8px;">
                <button wire:click="closeEdit"
                        style="padding:8px 16px;border:1px solid var(--pd-border);border-radius:7px;font-size:12px;font-weight:600;color:var(--pd-text);background:var(--pd-bg);cursor:pointer;">
                    Annuler
                </button>
                @if($userPerms['can_write'])
                <button wire:click="saveEdit"
                        style="padding:8px 16px;background:var(--pd-navy);color:#fff;border:none;border-radius:7px;font-size:12px;font-weight:600;cursor:pointer;">
                    Enregistrer
                </button>
                @endif
            </div>
        </div>
    </div>
</div>
@endif

{{-- ── Modal ajout ──────────────────────────────────────────────────────────── --}}
@if($addingRow)
<div wire:click.self="closeAdd"
     style="position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:1000;display:flex;align-items:center;justify-content:center;padding:20px;">
    <div style="background:var(--pd-bg);border-radius:12px;box-shadow:0 20px 60px rgba(0,0,0,0.25);width:100%;max-width:560px;max-height:90vh;display:flex;flex-direction:column;">

        {{-- En-tête --}}
        <div style="display:flex;align-items:center;justify-content:space-between;padding:18px 24px;border-bottom:1px solid var(--pd-border);">
            <h2 style="margin:0;font-size:15px;font-weight:700;color:var(--pd-text);">
                Nouvelle ligne
            </h2>
            <button wire:click="closeAdd"
                    style="background:none;border:none;cursor:pointer;color:var(--pd-muted);font-size:20px;line-height:1;padding:0;">×</button>
        </div>

        {{-- Corps : champs dynamiques --}}
        <div style="overflow-y:auto;padding:20px 24px;display:flex;flex-direction:column;gap:14px;">
            @foreach($columns as $col)
            @if($col->name === 'id')
                @continue
            @endif
            <div>
                <label style="display:block;font-size:12px;font-weight:600;color:var(--pd-text);margin-bottom:4px;">
                    {{ $col->label }}
                    @if($col->required)
                        <span style="color:#dc2626;">*</span>
                    @endif
                    @if($col->is_rgpd_sensitive)
                        <span title="Donnée RGPD sensible"
                              style="display:inline-block;margin-left:4px;padding:1px 5px;background:#fef3c7;border:1px solid #fcd34d;border-radius:4px;font-size:10px;color:#92400e;font-weight:500;">RGPD</span>
                    @endif
                </label>

                @php
                    $hasError = $errors->has("addForm.{$col->name}");
                    $fieldBorder = $hasError ? 'border:2px solid #dc2626;' : 'border:1px solid var(--pd-border);';
                    $baseStyle = "width:100%;padding:7px 10px;{$fieldBorder}border-radius:7px;font-size:13px;background:var(--pd-bg);color:var(--pd-text);box-sizing:border-box;";
                @endphp

                @if($col->type === \App\Enums\DatagridColumnType::BOOLEAN)
                    @php
                        $currentVal = $addForm[$col->name] ?? '0';
                        $isTrue = in_array($currentVal, ['1', 1, true, 'true', 'oui'], false);
                        $labelTrue  = $col->label_true  ?? 'Oui';
                        $labelFalse = $col->label_false ?? 'Non';
                    @endphp
                    <div style="display:flex;align-items:center;gap:10px;">
                        <button type="button" wire:click="$set('addForm.{{ $col->name }}', '0')"
                                style="padding:7px 16px;border-radius:7px;font-size:13px;font-weight:600;cursor:pointer;transition:all .15s;
                                       {{ ! $isTrue ? 'background:#fee2e2;color:#dc2626;border:2px solid #fca5a5;' : 'background:var(--pd-bg2);color:var(--pd-muted);border:1px solid var(--pd-border);' }}">
                            {{ $labelFalse }}
                        </button>
                        <div wire:click="$set('addForm.{{ $col->name }}', '{{ $isTrue ? '0' : '1' }}')"
                             style="position:relative;width:44px;height:24px;border-radius:12px;cursor:pointer;transition:background .2s;background:{{ $isTrue ? '#16a34a' : '#d1d5db' }};">
                            <div style="position:absolute;top:3px;width:18px;height:18px;border-radius:50%;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.25);transition:left .2s;left:{{ $isTrue ? '23px' : '3px' }};"></div>
                        </div>
                        <button type="button" wire:click="$set('addForm.{{ $col->name }}', '1')"
                                style="padding:7px 16px;border-radius:7px;font-size:13px;font-weight:600;cursor:pointer;transition:all .15s;
                                       {{ $isTrue ? 'background:#dcfce7;color:#16a34a;border:2px solid #86efac;' : 'background:var(--pd-bg2);color:var(--pd-muted);border:1px solid var(--pd-border);' }}">
                            {{ $labelTrue }}
                        </button>
                    </div>
                    <input type="hidden" wire:model="addForm.{{ $col->name }}">

                @elseif($col->type === \App\Enums\DatagridColumnType::SELECT)
                    @php $opts = is_array($col->options) && count($col->options) ? $col->options : null; @endphp
                    @if($opts && count($opts) === 2)
                        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                            @foreach($opts as $optVal)
                            @php $isSelected = ($addForm[$col->name] ?? '') === $optVal; @endphp
                            <button type="button" wire:click="$set('addForm.{{ $col->name }}', '{{ $optVal }}')"
                                    style="padding:7px 16px;border-radius:7px;font-size:13px;font-weight:600;cursor:pointer;transition:all .15s;
                                           {{ $isSelected ? 'background:#dbeafe;color:#1d4ed8;border:2px solid #93c5fd;' : 'background:var(--pd-bg2);color:var(--pd-muted);border:1px solid var(--pd-border);' }}">
                                {{ $optVal }}
                            </button>
                            @endforeach
                        </div>
                        <input type="hidden" wire:model="addForm.{{ $col->name }}">
                    @elseif($opts && count($opts) > 2)
                        <select wire:model="addForm.{{ $col->name }}" style="{{ $baseStyle }}">
                            <option value="">— Choisir —</option>
                            @foreach($opts as $optVal)
                            <option value="{{ $optVal }}">{{ $optVal }}</option>
                            @endforeach
                        </select>
                    @else
                        <input type="text" wire:model="addForm.{{ $col->name }}" list="adl_{{ $col->name }}"
                               placeholder="Saisir ou choisir…" style="{{ $baseStyle }}">
                        @if(isset($distinctValues[$col->name]) && count($distinctValues[$col->name]))
                        <datalist id="adl_{{ $col->name }}">
                            @foreach($distinctValues[$col->name] as $dv)
                            <option value="{{ $dv }}">
                            @endforeach
                        </datalist>
                        @endif
                    @endif

                @elseif($col->type === \App\Enums\DatagridColumnType::DATE)
                    <input type="date" wire:model="addForm.{{ $col->name }}" style="{{ $baseStyle }}">

                @elseif($col->type === \App\Enums\DatagridColumnType::NUMBER)
                    <input type="number" step="any" wire:model="addForm.{{ $col->name }}" placeholder="0" style="{{ $baseStyle }}">

                @elseif($col->type === \App\Enums\DatagridColumnType::EMAIL)
                    <input type="email" wire:model="addForm.{{ $col->name }}" placeholder="exemple@domaine.fr" style="{{ $baseStyle }}">

                @elseif($col->type === \App\Enums\DatagridColumnType::PHONE)
                    <input type="tel" wire:model="addForm.{{ $col->name }}" placeholder="06 12 34 56 78"
                           maxlength="{{ $col->length ?? 30 }}" style="{{ $baseStyle }}">

                @elseif($col->type === \App\Enums\DatagridColumnType::SIRET)
                    <input type="text" wire:model="addForm.{{ $col->name }}" placeholder="123 456 789 01234"
                           maxlength="14" style="{{ $baseStyle }}font-family:monospace;">

                @elseif($col->type === \App\Enums\DatagridColumnType::POSTAL_CODE)
                    <input type="text" wire:model="addForm.{{ $col->name }}" placeholder="85300"
                           maxlength="10" style="{{ $baseStyle }}font-family:monospace;max-width:120px;">

                @elseif($col->type === \App\Enums\DatagridColumnType::CHEMIN_FICHIER)
                    <input type="text" wire:model="addForm.{{ $col->name }}"
                           placeholder="/chemin/vers/fichier.pdf"
                           style="{{ $baseStyle }}font-family:monospace;font-size:11px;">
                    <span style="display:block;margin-top:3px;font-size:10px;color:var(--pd-muted);">Chemin complet du fichier sur le serveur ou réseau.</span>

                @else
                    <input type="text" wire:model="addForm.{{ $col->name }}"
                           @if($col->length) maxlength="{{ $col->length }}" @endif
                           style="{{ $baseStyle }}">
                @endif

                @error("addForm.{$col->name}")
                    <span style="display:flex;align-items:center;gap:4px;margin-top:4px;font-size:11px;color:#dc2626;">
                        <span>⚠</span> {{ $message }}
                    </span>
                @enderror
            </div>
            @endforeach
        </div>

        {{-- Pied modal --}}
        <div style="display:flex;align-items:center;justify-content:flex-end;padding:16px 24px;border-top:1px solid var(--pd-border);gap:8px;">
            <button wire:click="closeAdd"
                    style="padding:8px 16px;border:1px solid var(--pd-border);border-radius:7px;font-size:12px;font-weight:600;color:var(--pd-text);background:var(--pd-bg);cursor:pointer;">
                Annuler
            </button>
            <button wire:click="saveAdd"
                    style="padding:8px 16px;background:var(--pd-navy);color:#fff;border:none;border-radius:7px;font-size:12px;font-weight:600;cursor:pointer;">
                Enregistrer
            </button>
        </div>
    </div>
</div>
@endif

</div>
