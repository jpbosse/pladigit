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

    {{-- Exports — conditionnés à can_export --}}
    @if($userPerms['can_export'])
    <button wire:click="exportExcel"
            style="padding:6px 14px;background:#16a34a;color:#fff;border:none;border-radius:7px;font-size:12px;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:5px;white-space:nowrap;">
        <span style="font-size:13px;">↓</span> Export Excel
    </button>
    <button wire:click="exportOds"
            style="padding:6px 14px;background:#0891b2;color:#fff;border:none;border-radius:7px;font-size:12px;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:5px;white-space:nowrap;">
        <span style="font-size:13px;">↓</span> Export ODS
    </button>
    <a href="{{ route('datagrid.pdf.liste', ['table' => $table->id, 'cols' => implode(',', $visibleColumns), 'filters' => json_encode($filters)]) }}"
       target="_blank"
       style="padding:6px 14px;background:#dc2626;color:#fff;border:none;border-radius:7px;font-size:12px;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:5px;white-space:nowrap;text-decoration:none;">
        <span style="font-size:13px;">↓</span> PDF liste (100 lignes max)
    </a>
    @endif

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
            <span style="background:var(--pd-navy);color:#fff;border-radius:10px;font-size:10px;padding:1px 6px;font-weight:600;">{{ $hiddenCount }}</span>
            @endif
        </button>

        @if($showColumnPicker)
        <div style="position:absolute;right:0;top:calc(100% + 6px);z-index:100;
                    background:var(--pd-surface);border:0.5px solid var(--pd-border);
                    border-radius:10px;box-shadow:0 4px 16px rgba(0,0,0,.12);min-width:220px;overflow:hidden;">
            <div style="padding:10px 14px;border-bottom:0.5px solid var(--pd-border);
                        font-size:11px;font-weight:600;color:var(--pd-muted);text-transform:uppercase;letter-spacing:.04em;">
                Colonnes affichées
            </div>
            @foreach($columns as $col)
            <label style="display:flex;align-items:center;gap:10px;padding:8px 14px;cursor:pointer;font-size:12px;color:var(--pd-text);border-bottom:0.5px solid var(--pd-border);"
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
                        style="flex:1;padding:5px 8px;font-size:11px;border:0.5px solid var(--pd-border);border-radius:6px;background:var(--pd-bg2);color:var(--pd-muted);cursor:pointer;">
                    Tout afficher
                </button>
                <button wire:click="resetColumnsToDefault"
                        style="flex:1;padding:5px 8px;font-size:11px;border:0.5px solid var(--pd-border);border-radius:6px;background:var(--pd-bg2);color:var(--pd-muted);cursor:pointer;">
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
                                <select wire:model.live="filters.{{ $col->name }}"
                                        style="width:100%;padding:4px 6px;border:1px solid var(--pd-border);border-radius:5px;font-size:11px;background:var(--pd-bg);color:var(--pd-text);">
                                    <option value="">Tous</option>
                                    @foreach($col->options as $optVal)
                                    <option value="{{ $optVal }}">{{ $optVal }}</option>
                                    @endforeach
                                </select>
                            @else
                                @php $filterDlId = 'fdl_'.$col->name; @endphp
                                <input wire:model.live.debounce.300ms="filters.{{ $col->name }}"
                                       type="text" list="{{ $filterDlId }}" placeholder="Filtrer…"
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
                                <input type="date" wire:model.live.debounce.300ms="filters.{{ $col->name }}_from" placeholder="Du"
                                       style="flex:1;padding:3px 5px;border:1px solid var(--pd-border);border-radius:5px;font-size:10px;background:var(--pd-bg);color:var(--pd-text);">
                                <span style="font-size:10px;color:var(--pd-muted);">→</span>
                                <input type="date" wire:model.live.debounce.300ms="filters.{{ $col->name }}_to" placeholder="Au"
                                       style="flex:1;padding:3px 5px;border:1px solid var(--pd-border);border-radius:5px;font-size:10px;background:var(--pd-bg);color:var(--pd-text);">
                            </div>
                        @elseif($col->type === \App\Enums\DatagridColumnType::NUMBER)
                            <div style="display:flex;gap:3px;align-items:center;">
                                <input type="number" step="any" wire:model.live.debounce.300ms="filters.{{ $col->name }}_min" placeholder="Min"
                                       style="flex:1;padding:3px 5px;border:1px solid var(--pd-border);border-radius:5px;font-size:10px;background:var(--pd-bg);color:var(--pd-text);">
                                <span style="font-size:10px;color:var(--pd-muted);">→</span>
                                <input type="number" step="any" wire:model.live.debounce.300ms="filters.{{ $col->name }}_max" placeholder="Max"
                                       style="flex:1;padding:3px 5px;border:1px solid var(--pd-border);border-radius:5px;font-size:10px;background:var(--pd-bg);color:var(--pd-text);">
                            </div>
                        @else
                            <input wire:model.live.debounce.300ms="filters.{{ $col->name }}" type="text" placeholder="Filtrer…"
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
                                $formatted = strlen($phone) === 10 ? implode(' ', str_split($phone, 2)) : (str_starts_with($val, '+') ? $val : $phone);
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
                            <span title="{{ $val }}" style="display:inline-flex;align-items:center;gap:5px;font-size:12px;color:var(--pd-text);">
                                <span>{{ $icon }}</span>
                                <span style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-family:monospace;font-size:11px;color:var(--pd-muted);">{{ basename($val) }}</span>
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

{{-- ── Composants enfants ────────────────────────────────────────────────── --}}

{{-- Modal édition — onglets Données / Complémentaires / Historique --}}
@livewire('tenant.datagrid.edit-row-modal', [
    'table'          => $table,
    'userPerms'      => $userPerms,
    'distinctValues' => $distinctValues,
], key('edit-modal-'.$table->id))

{{-- Note: userPerms contient can_write, can_delete, can_export --}}

{{-- Modal ajout — stub prêt (AddRowModal à créer, Bloc 2.4 migré) --}}
{{-- @livewire('tenant.datagrid.add-row-modal', [...], key('add-modal-'.$table->id)) --}}

</div>
