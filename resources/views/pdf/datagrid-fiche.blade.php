<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<style>
    body { font-family: serif; font-size: 11px; color: #1a1a1a; margin: 20px; }
    h1 { font-size: 16px; font-weight: bold; border-bottom: 2px solid #1e3a5f; padding-bottom: 6px; margin-bottom: 16px; color: #1e3a5f; }
    .meta { font-size: 10px; color: #666; margin-bottom: 16px; }
    table { width: 100%; border-collapse: collapse; }
    tr:nth-child(even) { background: #f5f7fa; }
    td { padding: 6px 10px; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
    td.label { font-weight: bold; width: 35%; color: #374151; }
    td.value { color: #1a1a1a; }
    .rgpd { display: inline-block; background: #fef3c7; border: 1px solid #fcd34d; border-radius: 3px; font-size: 9px; color: #92400e; padding: 1px 4px; margin-left: 4px; }
    .footer { margin-top: 20px; font-size: 9px; color: #999; text-align: right; border-top: 1px solid #e2e8f0; padding-top: 6px; }
</style>
</head>
<body>
    <h1>{{ $table->label }}</h1>
    <div class="meta">Fiche #{{ $row['id'] ?? '—' }} — Imprimée le {{ now()->format('d/m/Y à H:i') }}</div>

    <table>
        @foreach($columns as $col)
        @if($col->name === 'id') @continue @endif
        @php $val = $row[$col->name] ?? null; @endphp
        <tr>
            <td class="label">
                {{ $col->label }}
                @if($col->is_rgpd_sensitive) <span class="rgpd">RGPD</span> @endif
            </td>
            <td class="value">
                @if($val === null || $val === '')
                    —
                @elseif($col->type->value === 'boolean')
                    {{ in_array($val, ['1', 1, 'true', 'oui'], false) ? ($col->label_true ?? 'Oui') : ($col->label_false ?? 'Non') }}
                @elseif($col->type->value === 'date')
                    {{ preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $val, $m) ? $m[3].'/'.$m[2].'/'.$m[1] : $val }}
                @else
                    {{ $val }}
                @endif
            </td>
        </tr>
        @endforeach
    </table>

    <div class="footer">Pladigit — {{ config('app.name') }}</div>
</body>
</html>
