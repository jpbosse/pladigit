<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<style>
    body { font-family: serif; font-size: 10px; color: #1a1a1a; margin: 20px; }
    h1 { font-size: 15px; font-weight: bold; border-bottom: 2px solid #1e3a5f; padding-bottom: 6px; margin-bottom: 8px; color: #1e3a5f; }
    .meta { font-size: 9px; color: #666; margin-bottom: 12px; }
    table { width: 100%; border-collapse: collapse; }
    thead tr { background: #1e3a5f; color: #fff; }
    thead td { padding: 6px 8px; font-weight: bold; font-size: 10px; }
    tbody tr:nth-child(even) { background: #f5f7fa; }
    tbody td { padding: 5px 8px; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
    .footer { margin-top: 16px; font-size: 9px; color: #999; text-align: right; border-top: 1px solid #e2e8f0; padding-top: 6px; }
</style>
</head>
<body>
    <h1>{{ $table->label }}</h1>
    <div class="meta">
        {{ $total }} résultat{{ $total > 1 ? 's' : '' }}
        @if($filtres) — Filtres actifs : {{ $filtres }} @endif
        — Imprimé le {{ now()->format('d/m/Y à H:i') }}
    </div>

    <table>
        <thead>
            <tr>
                @foreach($columns as $col)
                <td>{{ $col->label }}</td>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $row)
            @php $row = (array) $row; @endphp
            <tr>
                @foreach($columns as $col)
                @php $val = $row[$col->name] ?? null; @endphp
                <td>
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
                @endforeach
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">Pladigit — {{ config('app.name') }}</div>
</body>
</html>
