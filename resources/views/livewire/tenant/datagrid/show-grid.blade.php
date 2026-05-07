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

    {{-- Pagination --}}
    <div style="display:flex;align-items:center;gap:6px;">
        <span style="font-size:12px;color:var(--pd-muted);">Lignes :</span>
        <select wire:model.live="perPage"
                style="padding:5px 8px;border:1px solid var(--pd-border);border-radius:7px;font-size:12px;color:var(--pd-text);background:var(--pd-bg);">
            <option value="10">10</option>
            <option value="20">20</option>
            <option value="50">50</option>
        </select>
    </div>

    {{-- Effacer filtres --}}
    @if(count(array_filter($filters, fn($v) => $v !== '' && $v !== null)))
    <button wire:click="clearFilters"
            style="padding:6px 12px;border:1px solid #fca5a5;border-radius:7px;font-size:12px;color:#dc2626;background:#fef2f2;cursor:pointer;">
        Effacer filtres
    </button>
    @endif
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
                    @foreach($columns->where('visible_by_default', true) as $col)
                    <th wire:click="sortBy('{{ $col->name }}')"
                        style="padding:10px 12px;text-align:left;font-weight:600;color:var(--pd-text);border-bottom:1px solid var(--pd-border);cursor:pointer;white-space:nowrap;user-select:none;">
                        {{ $col->label }}
                        @if($sortColumn === $col->name)
                            {{ $sortDirection === 'asc' ? '↑' : '↓' }}
                        @endif
                    </th>
                    @endforeach
                </tr>
                {{-- Ligne filtres --}}
                <tr style="background:var(--pd-bg);">
                    @foreach($columns->where('visible_by_default', true) as $col)
                    <td style="padding:6px 8px;border-bottom:1px solid var(--pd-border);">
                        @if($col->type === \App\Enums\DatagridColumnType::BOOLEAN)
                            <select wire:model.live="filters.{{ $col->name }}"
                                    style="width:100%;padding:4px 6px;border:1px solid var(--pd-border);border-radius:5px;font-size:11px;background:var(--pd-bg);color:var(--pd-text);">
                                <option value="">Tous</option>
                                <option value="1">Oui</option>
                                <option value="0">Non</option>
                            </select>
                        @elseif($col->type === \App\Enums\DatagridColumnType::SELECT && isset($distinctValues[$col->name]) && count($distinctValues[$col->name]))
                            <select wire:model.live="filters.{{ $col->name }}"
                                    style="width:100%;padding:4px 6px;border:1px solid var(--pd-border);border-radius:5px;font-size:11px;background:var(--pd-bg);color:var(--pd-text);">
                                <option value="">Tous</option>
                                @foreach($distinctValues[$col->name] as $dv)
                                <option value="{{ $dv }}">{{ $dv }}</option>
                                @endforeach
                            </select>
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
                </tr>
            </thead>
            <tbody>
                @forelse($this->rows as $row)
                @php $row = (array) $row; @endphp
                <tr wire:click="openEdit({{ $row['id'] ?? 0 }})"
                    style="border-bottom:1px solid var(--pd-border);cursor:pointer;transition:background 0.1s;"
                    onmouseover="this.style.background='color-mix(in srgb,var(--pd-navy) 4%,transparent)'"
                    onmouseout="this.style.background=''">
                    @foreach($columns->where('visible_by_default', true) as $col)
                    @php $val = $row[$col->name] ?? null; @endphp
                    <td style="padding:9px 12px;color:var(--pd-text);">
                        @if($val === null || $val === '')
                            <span style="color:var(--pd-muted);font-style:italic;">—</span>
                        @elseif($col->type === \App\Enums\DatagridColumnType::BOOLEAN)
                            @if(in_array($val, ['1', 1, 'true', 'oui'], false))
                                <span title="Oui" style="display:inline-flex;align-items:center;justify-content:center;width:20px;height:20px;border-radius:50%;background:#dcfce7;color:#16a34a;font-size:13px;">✓</span>
                            @else
                                <span title="Non" style="display:inline-flex;align-items:center;justify-content:center;width:20px;height:20px;border-radius:50%;background:#fee2e2;color:#dc2626;font-size:13px;">✕</span>
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
                        @else
                            {{ $val }}
                        @endif
                    </td>
                    @endforeach
                </tr>
                @empty
                <tr>
                    <td colspan="{{ $columns->where('visible_by_default', true)->count() }}"
                        style="padding:32px;text-align:center;color:var(--pd-muted);">
                        Aucune ligne trouvée.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    <div style="margin-top:16px;">
        {{ $this->rows->links() }}
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

                @if($col->type === \App\Enums\DatagridColumnType::BOOLEAN)
                    <select wire:model="editForm.{{ $col->name }}"
                            @if(!$userPerms['can_write']) disabled @endif
                            style="width:100%;padding:7px 10px;border:1px solid var(--pd-border);border-radius:7px;font-size:13px;background:var(--pd-bg);color:var(--pd-text);">
                        <option value="">— Choisir —</option>
                        <option value="1">Oui</option>
                        <option value="0">Non</option>
                    </select>

                @elseif($col->type === \App\Enums\DatagridColumnType::SELECT && isset($distinctValues[$col->name]) && count($distinctValues[$col->name]))
                    <select wire:model="editForm.{{ $col->name }}"
                            @if(!$userPerms['can_write']) disabled @endif
                            style="width:100%;padding:7px 10px;border:1px solid var(--pd-border);border-radius:7px;font-size:13px;background:var(--pd-bg);color:var(--pd-text);">
                        <option value="">— Choisir —</option>
                        @foreach($distinctValues[$col->name] as $dv)
                        <option value="{{ $dv }}">{{ $dv }}</option>
                        @endforeach
                    </select>

                @elseif($col->type === \App\Enums\DatagridColumnType::DATE)
                    <input type="date"
                           wire:model="editForm.{{ $col->name }}"
                           @if(!$userPerms['can_write']) disabled @endif
                           style="width:100%;padding:7px 10px;border:1px solid var(--pd-border);border-radius:7px;font-size:13px;background:var(--pd-bg);color:var(--pd-text);box-sizing:border-box;">

                @elseif($col->type === \App\Enums\DatagridColumnType::NUMBER)
                    <input type="number" step="any"
                           wire:model="editForm.{{ $col->name }}"
                           @if(!$userPerms['can_write']) disabled @endif
                           style="width:100%;padding:7px 10px;border:1px solid var(--pd-border);border-radius:7px;font-size:13px;background:var(--pd-bg);color:var(--pd-text);box-sizing:border-box;">

                @elseif($col->type === \App\Enums\DatagridColumnType::EMAIL)
                    <input type="email"
                           wire:model="editForm.{{ $col->name }}"
                           @if(!$userPerms['can_write']) disabled @endif
                           style="width:100%;padding:7px 10px;border:1px solid var(--pd-border);border-radius:7px;font-size:13px;background:var(--pd-bg);color:var(--pd-text);box-sizing:border-box;">

                @elseif($col->type === \App\Enums\DatagridColumnType::PHONE)
                    <input type="tel"
                           wire:model="editForm.{{ $col->name }}"
                           @if(!$userPerms['can_write']) disabled @endif
                           style="width:100%;padding:7px 10px;border:1px solid var(--pd-border);border-radius:7px;font-size:13px;background:var(--pd-bg);color:var(--pd-text);box-sizing:border-box;">

                @elseif($col->type === \App\Enums\DatagridColumnType::SIRET)
                    <input type="text" maxlength="14"
                           wire:model="editForm.{{ $col->name }}"
                           @if(!$userPerms['can_write']) disabled @endif
                           style="width:100%;padding:7px 10px;border:1px solid var(--pd-border);border-radius:7px;font-size:13px;font-family:monospace;background:var(--pd-bg);color:var(--pd-text);box-sizing:border-box;">

                @else
                    <input type="text"
                           wire:model="editForm.{{ $col->name }}"
                           @if(!$userPerms['can_write']) disabled @endif
                           style="width:100%;padding:7px 10px;border:1px solid var(--pd-border);border-radius:7px;font-size:13px;background:var(--pd-bg);color:var(--pd-text);box-sizing:border-box;">
                @endif

                @error("editForm.{$col->name}")
                    <span style="display:block;margin-top:3px;font-size:11px;color:#dc2626;">{{ $message }}</span>
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

</div>
