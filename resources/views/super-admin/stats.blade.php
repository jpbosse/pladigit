@extends('layouts.super-admin')
@section('title', 'Statistiques plateforme')

@section('content')
@php
    $fmtBytes = function(int $b): string {
        if ($b >= 1073741824) return round($b / 1073741824, 2).' Go';
        if ($b >= 1048576)    return round($b / 1048576, 1).' Mo';
        return round($b / 1024, 0).' Ko';
    };
@endphp

<div style="margin-bottom:28px;">
    <h1 style="font-family:'Sora',sans-serif;font-size:22px;font-weight:700;color:var(--pd-text);margin-bottom:4px;">
        Statistiques plateforme
    </h1>
    <p style="font-size:13px;color:var(--pd-muted);">
        Stockage agrégé — tous modules confondus — {{ $totals['orgs'] }} organisation(s) hébergée(s)
    </p>
</div>

{{-- ── Cartes récap ──────────────────────────────────────────────── --}}
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:14px;margin-bottom:32px;">

    <div style="background:var(--pd-surface);border:1px solid var(--pd-border);border-radius:12px;padding:18px 20px;">
        <div style="font-size:11px;color:var(--pd-muted);font-weight:600;text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px;">Organisations</div>
        <div style="font-family:'Sora',sans-serif;font-size:28px;font-weight:700;color:var(--pd-text);">{{ $totals['orgs'] }}</div>
        <div style="font-size:12px;color:var(--pd-muted);margin-top:4px;">hébergées</div>
    </div>

    <div style="background:var(--pd-surface);border:1px solid var(--pd-border);border-radius:12px;padding:18px 20px;">
        <div style="font-size:11px;color:var(--pd-muted);font-weight:600;text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px;">Utilisateurs</div>
        <div style="font-family:'Sora',sans-serif;font-size:28px;font-weight:700;color:var(--pd-text);">{{ number_format($totals['users']) }}</div>
        <div style="font-size:12px;color:var(--pd-muted);margin-top:4px;">tous tenants</div>
    </div>

    <div style="background:var(--pd-surface);border:1px solid var(--sa-primary);border-radius:12px;padding:18px 20px;">
        <div style="font-size:11px;color:var(--pd-muted);font-weight:600;text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px;">Stockage total</div>
        <div style="font-family:'Sora',sans-serif;font-size:24px;font-weight:700;color:var(--sa-primary);">{{ $fmtBytes($totals['total_bytes']) }}</div>
        <div style="font-size:12px;color:var(--pd-muted);margin-top:4px;">tous modules</div>
    </div>

    <div style="background:var(--pd-surface);border:1px solid var(--pd-border);border-radius:12px;padding:18px 20px;">
        <div style="font-size:11px;color:var(--pd-muted);font-weight:600;text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px;">📷 Photothèque</div>
        <div style="font-family:'Sora',sans-serif;font-size:24px;font-weight:700;color:#2ECC71;">{{ $fmtBytes($totals['bytes_media']) }}</div>
        <div style="font-size:12px;color:var(--pd-muted);margin-top:4px;">tous tenants</div>
    </div>

    <div style="background:var(--pd-surface);border:1px solid var(--pd-border);border-radius:12px;padding:18px 20px;">
        <div style="font-size:11px;color:var(--pd-muted);font-weight:600;text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px;">📁 GED</div>
        <div style="font-family:'Sora',sans-serif;font-size:24px;font-weight:700;color:#3B9AE1;">{{ $fmtBytes($totals['bytes_ged']) }}</div>
        <div style="font-size:12px;color:var(--pd-muted);margin-top:4px;">Phase 5</div>
    </div>

    <div style="background:var(--pd-surface);border:1px solid var(--pd-border);border-radius:12px;padding:18px 20px;">
        <div style="font-size:11px;color:var(--pd-muted);font-weight:600;text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px;">💬 Chat</div>
        <div style="font-family:'Sora',sans-serif;font-size:24px;font-weight:700;color:#E8A838;">{{ $fmtBytes($totals['bytes_chat']) }}</div>
        <div style="font-size:12px;color:var(--pd-muted);margin-top:4px;">Phase 9 — estimé</div>
    </div>

</div>

{{-- ── Tableau par organisation ──────────────────────────────────── --}}
<div style="background:var(--pd-surface);border:1px solid var(--pd-border);border-radius:14px;overflow:hidden;">
    <div style="padding:18px 24px;border-bottom:1px solid var(--pd-border);display:flex;align-items:center;justify-content:space-between;">
        <h2 style="font-family:'Sora',sans-serif;font-size:15px;font-weight:700;color:var(--pd-text);margin:0;">
            Détail par organisation
        </h2>
        <span style="font-size:12px;color:var(--pd-muted);">
            Mis à jour en temps réel — {{ now()->locale('fr')->isoFormat('D MMM YYYY HH:mm') }}
        </span>
    </div>

    <div style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;font-size:13px;">
            <thead>
                <tr style="background:var(--pd-bg);border-bottom:1px solid var(--pd-border);">
                    <th style="text-align:left;padding:12px 16px;color:var(--pd-muted);font-weight:600;font-size:11px;text-transform:uppercase;letter-spacing:.4px;">Organisation</th>
                    <th style="text-align:left;padding:12px 16px;color:var(--pd-muted);font-weight:600;font-size:11px;text-transform:uppercase;letter-spacing:.4px;">Plan</th>
                    <th style="text-align:left;padding:12px 16px;color:var(--pd-muted);font-weight:600;font-size:11px;text-transform:uppercase;letter-spacing:.4px;">Statut</th>
                    <th style="text-align:right;padding:12px 16px;color:var(--pd-muted);font-weight:600;font-size:11px;text-transform:uppercase;letter-spacing:.4px;">Utilisateurs</th>
                    <th style="text-align:right;padding:12px 16px;color:#2ECC71;font-weight:600;font-size:11px;text-transform:uppercase;letter-spacing:.4px;">📷 Photo</th>
                    <th style="text-align:right;padding:12px 16px;color:#3B9AE1;font-weight:600;font-size:11px;text-transform:uppercase;letter-spacing:.4px;">📁 GED</th>
                    <th style="text-align:right;padding:12px 16px;color:#E8A838;font-weight:600;font-size:11px;text-transform:uppercase;letter-spacing:.4px;">💬 Chat</th>
                    <th style="padding:12px 16px;color:var(--pd-muted);font-weight:600;font-size:11px;text-transform:uppercase;letter-spacing:.4px;min-width:220px;">Total / Quota</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $row)
                @php
                    $statusColor = match($row['status']) {
                        'active'    => '#2ECC71',
                        'suspended' => '#e74c3c',
                        default     => '#E8A838',
                    };
                    $statusLabel = match($row['status']) {
                        'active'    => 'Actif',
                        'suspended' => 'Suspendu',
                        default     => 'En attente',
                    };
                    $barColor = $row['quota_pct'] > 80
                        ? '#e74c3c'
                        : ($row['quota_pct'] > 60 ? '#E8A838' : '#2ECC71');
                @endphp
                <tr style="border-bottom:1px solid var(--pd-border);transition:background .15s;"
                    onmouseover="this.style.background='var(--pd-bg)'"
                    onmouseout="this.style.background=''">
                    <td style="padding:14px 16px;">
                        <a href="{{ route('super-admin.organizations.show', $row['id']) }}"
                           style="font-weight:600;color:var(--sa-primary);text-decoration:none;display:block;"
                           onmouseover="this.style.textDecoration='underline'"
                           onmouseout="this.style.textDecoration='none'">
                            {{ $row['name'] }}
                        </a>
                        <div style="font-size:11px;color:var(--pd-muted);font-family:monospace;margin-top:2px;">
                            {{ $row['slug'] }}.pladigit.fr
                        </div>
                    </td>
                    <td style="padding:14px 16px;">
                        @php
                            $planColors = ['communautaire'=>['bg'=>'rgba(107,114,128,0.12)','color'=>'#6b7280'],'assistance'=>['bg'=>'rgba(59,130,246,0.12)','color'=>'#3b82f6'],'enterprise'=>['bg'=>'rgba(139,92,246,0.12)','color'=>'#8b5cf6']];
                            $pc = $planColors[$row['plan']] ?? $planColors['communautaire'];
                        @endphp
                        <span style="font-size:11px;padding:3px 9px;border-radius:12px;font-weight:600;
                                     background:{{ $pc['bg'] }};color:{{ $pc['color'] }};">
                            {{ ucfirst($row['plan']) }}
                        </span>
                    </td>
                    <td style="padding:14px 16px;">
                        <span style="font-size:12px;color:{{ $statusColor }};font-weight:600;display:flex;align-items:center;gap:5px;">
                            <span style="width:6px;height:6px;border-radius:50%;background:{{ $statusColor }};flex-shrink:0;"></span>
                            {{ $statusLabel }}
                        </span>
                    </td>
                    <td style="padding:14px 16px;text-align:right;font-weight:600;color:var(--pd-text);">
                        {{ number_format($row['user_count']) }}
                    </td>
                    <td style="padding:14px 16px;text-align:right;color:var(--pd-muted);font-size:12px;">
                        {{ $fmtBytes($row['bytes_media']) }}
                    </td>
                    <td style="padding:14px 16px;text-align:right;color:var(--pd-muted);font-size:12px;">
                        {{ $fmtBytes($row['bytes_ged']) }}
                    </td>
                    <td style="padding:14px 16px;text-align:right;color:var(--pd-muted);font-size:12px;">
                        {{ $fmtBytes($row['bytes_chat']) }}
                    </td>
                    <td style="padding:14px 16px;">
                        <div style="display:flex;align-items:center;gap:10px;">
                            <div style="flex:1;background:var(--pd-bg);border-radius:4px;height:7px;overflow:hidden;min-width:80px;">
                                <div style="height:100%;border-radius:4px;background:{{ $barColor }};
                                            width:{{ $row['quota_pct'] }}%;transition:width .6s ease;"></div>
                            </div>
                            <span style="font-size:11px;color:var(--pd-muted);white-space:nowrap;min-width:100px;text-align:right;">
                                {{ $fmtBytes($row['total_bytes']) }}
                                <span style="color:var(--pd-border);">/</span>
                                {{ round($row['quota_mb'] / 1024, 0) }} Go
                                <span style="color:{{ $barColor }};font-weight:600;">({{ $row['quota_pct'] }}%)</span>
                            </span>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" style="padding:40px;text-align:center;color:var(--pd-muted);">
                        Aucune organisation hébergée.
                    </td>
                </tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr style="border-top:2px solid var(--pd-border);background:var(--pd-bg);">
                    <td colspan="3" style="padding:14px 16px;font-weight:700;color:var(--pd-text);font-family:'Sora',sans-serif;">
                        Total plateforme
                    </td>
                    <td style="padding:14px 16px;text-align:right;font-weight:700;color:var(--pd-text);">
                        {{ number_format($totals['users']) }}
                    </td>
                    <td style="padding:14px 16px;text-align:right;font-weight:700;color:#2ECC71;font-size:12px;">
                        {{ $fmtBytes($totals['bytes_media']) }}
                    </td>
                    <td style="padding:14px 16px;text-align:right;font-weight:700;color:#3B9AE1;font-size:12px;">
                        {{ $fmtBytes($totals['bytes_ged']) }}
                    </td>
                    <td style="padding:14px 16px;text-align:right;font-weight:700;color:#E8A838;font-size:12px;">
                        {{ $fmtBytes($totals['bytes_chat']) }}
                    </td>
                    <td style="padding:14px 16px;font-weight:700;color:var(--sa-primary);font-family:'Sora',sans-serif;font-size:14px;">
                        {{ $fmtBytes($totals['total_bytes']) }}
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

{{-- ── Santé du système ───────────────────────────────────────────── --}}
<div style="margin-top:32px;">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
        <div>
            <h2 style="font-family:'Sora',sans-serif;font-size:16px;font-weight:700;color:var(--pd-text);margin:0;">
                Santé du système
            </h2>
            <p style="font-size:12px;color:var(--pd-muted);margin:2px 0 0;">Infrastructure — base de données, cache, disque</p>
        </div>
        <div style="display:flex;align-items:center;gap:10px;">
            <span id="health-ts" style="font-size:11px;color:var(--pd-muted);"></span>
            <button id="health-refresh" type="button"
                    onclick="loadHealth()"
                    style="padding:6px 14px;border:1px solid var(--pd-border);border-radius:8px;background:var(--pd-surface);font-size:12px;color:var(--pd-text);cursor:pointer;">
                ↺ Rafraîchir
            </button>
            <a href="/health" target="_blank"
               style="padding:6px 14px;border:1px solid var(--pd-border);border-radius:8px;background:var(--pd-surface);font-size:12px;color:var(--pd-text);text-decoration:none;">
                JSON brut ↗
            </a>
        </div>
    </div>

    <div id="health-cards" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:14px;">
        {{-- Chargement initial --}}
        <div style="background:var(--pd-surface);border:1px solid var(--pd-border);border-radius:12px;padding:20px;grid-column:1/-1;text-align:center;color:var(--pd-muted);font-size:13px;">
            Chargement…
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
function loadHealth() {
    const cards = document.getElementById('health-cards');
    const ts    = document.getElementById('health-ts');
    const btn   = document.getElementById('health-refresh');
    btn.textContent = '↺ …';
    btn.disabled = true;

    fetch('/health', { headers: { 'Accept': 'application/json' } })
        .then(r => r.json())
        .then(data => {
            const statusColor = data.status === 'ok' ? '#2ECC71' : '#E74C3C';
            const statusLabel = data.status === 'ok' ? '✓ Opérationnel' : '⚠ Dégradé';

            ts.textContent = 'Mis à jour : ' + new Date(data.ts).toLocaleTimeString('fr-FR');

            let html = '';

            // Carte statut global
            html += `<div style="background:var(--pd-surface);border:2px solid ${statusColor};border-radius:12px;padding:20px;display:flex;flex-direction:column;gap:6px;">
                <div style="font-size:11px;color:var(--pd-muted);font-weight:600;text-transform:uppercase;letter-spacing:.5px;">Statut global</div>
                <div style="font-size:22px;font-weight:700;color:${statusColor};font-family:'Sora',sans-serif;">${statusLabel}</div>
            </div>`;

            // Cartes par check
            const icons = { database: '🗄️', redis: '⚡', disk: '💾' };
            const labels = { database: 'Base de données', redis: 'Cache Redis', disk: 'Disque' };

            for (const [key, check] of Object.entries(data.checks)) {
                const ok    = check.ok;
                const color = ok ? '#2ECC71' : '#E74C3C';
                const icon  = icons[key] ?? '🔧';
                const label = labels[key] ?? key;

                let detail = check.message ?? '';
                if (key === 'disk' && check.free_gb !== undefined) {
                    detail = `${check.free_gb} Go libres (${check.free_percent}%)`;
                }

                html += `<div style="background:var(--pd-surface);border:1px solid ${ok ? 'var(--pd-border)' : color};border-radius:12px;padding:20px;">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
                        <div style="font-size:11px;color:var(--pd-muted);font-weight:600;text-transform:uppercase;letter-spacing:.5px;">${icon} ${label}</div>
                        <span style="font-size:11px;font-weight:700;color:${color};background:${color}18;padding:2px 8px;border-radius:20px;">${ok ? 'OK' : 'KO'}</span>
                    </div>
                    <div style="font-size:12px;color:var(--pd-muted);">${detail}</div>
                    ${key === 'disk' && check.free_percent !== undefined ? `
                    <div style="margin-top:10px;height:6px;background:var(--pd-border);border-radius:3px;overflow:hidden;">
                        <div style="height:100%;width:${100 - check.free_percent}%;background:${check.free_percent < 20 ? '#E74C3C' : check.free_percent < 40 ? '#E8A838' : '#2ECC71'};border-radius:3px;transition:width .3s;"></div>
                    </div>
                    <div style="font-size:10px;color:var(--pd-muted);margin-top:4px;">Espace utilisé : ${100 - check.free_percent}%</div>` : ''}
                </div>`;
            }

            cards.innerHTML = html;
        })
        .catch(() => {
            cards.innerHTML = '<div style="background:var(--pd-surface);border:1px solid #E74C3C;border-radius:12px;padding:20px;grid-column:1/-1;text-align:center;color:#E74C3C;font-size:13px;">⚠ Impossible de contacter l\'endpoint /health</div>';
        })
        .finally(() => {
            btn.textContent = '↺ Rafraîchir';
            btn.disabled = false;
        });
}

// Chargement automatique au démarrage
loadHealth();

// Rafraîchissement automatique toutes les 60 secondes
setInterval(loadHealth, 60000);
</script>
@endpush
