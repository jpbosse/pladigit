<div>

{{-- ── Barre haute ──────────────────────────────────────────────────────── --}}
<div style="display:flex;align-items:center;gap:12px;padding:20px 32px;border-bottom:1px solid var(--pd-border);flex-wrap:wrap;">
    <h1 style="font-size:18px;font-weight:700;color:var(--pd-text);margin:0;flex:1;min-width:160px;">
        {{ $table->label }}
    </h1>

    {{-- Vues sauvegardées --}}
    @if($savedViews->count())
    <select wire:change="loadView($event.target.value)"
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

    {{-- Effacer filtres --}}
    @if(count($filters))
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
                        <input wire:model.live.debounce.300ms="filters.{{ $col->name }}"
                               type="text"
                               placeholder="Filtrer…"
                               style="width:100%;padding:4px 8px;border:1px solid var(--pd-border);border-radius:5px;font-size:11px;box-sizing:border-box;background:var(--pd-bg);">
                    </td>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse($this->rows as $row)
                @php $row = (array) $row; @endphp
                <tr style="border-bottom:1px solid var(--pd-border);">
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

</div>
