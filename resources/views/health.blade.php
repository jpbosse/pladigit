<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statut système — Pladigit</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --navy:    #1E3A5F;
            --accent:  #3B9AE1;
            --success: #2ECC71;
            --danger:  #E74C3C;
            --warning: #F39C12;
            --bg:      #F0F4FA;
            --surface: #FFFFFF;
            --text:    #1a2535;
            --muted:   #6b7c96;
            --border:  #dde5f0;
        }

        body {
            font-family: 'Sora', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* ── Header ── */
        .header {
            background: var(--navy);
            padding: 20px 40px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .header-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
        }
        .header-logo {
            width: 36px;
            height: 36px;
            background: var(--accent);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 16px;
            color: white;
        }
        .header-title {
            font-size: 15px;
            font-weight: 700;
            color: white;
        }
        .header-sub {
            font-size: 11px;
            color: rgba(255,255,255,0.5);
            font-weight: 400;
        }
        .header-ts {
            font-family: 'JetBrains Mono', monospace;
            font-size: 11px;
            color: rgba(255,255,255,0.4);
        }

        /* ── Main ── */
        .main {
            flex: 1;
            max-width: 860px;
            margin: 0 auto;
            padding: 40px 24px;
            width: 100%;
        }

        /* ── Statut global ── */
        .status-banner {
            border-radius: 16px;
            padding: 28px 32px;
            margin-bottom: 32px;
            display: flex;
            align-items: center;
            gap: 20px;
            border: 2px solid;
        }
        .status-banner.ok {
            background: #f0fdf4;
            border-color: var(--success);
        }
        .status-banner.degraded {
            background: #fef2f2;
            border-color: var(--danger);
        }
        .status-icon {
            width: 52px;
            height: 52px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            flex-shrink: 0;
        }
        .status-banner.ok    .status-icon { background: #dcfce7; }
        .status-banner.degraded .status-icon { background: #fee2e2; }
        .status-title {
            font-size: 22px;
            font-weight: 800;
            letter-spacing: -.3px;
        }
        .status-banner.ok    .status-title { color: #16a34a; }
        .status-banner.degraded .status-title { color: #dc2626; }
        .status-desc {
            font-size: 13px;
            color: var(--muted);
            margin-top: 3px;
        }
        .status-dot-anim {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-left: auto;
            flex-shrink: 0;
        }
        .status-banner.ok .status-dot-anim {
            background: var(--success);
            box-shadow: 0 0 0 0 rgba(46,204,113,0.4);
            animation: pulse-green 2.5s infinite;
        }
        .status-banner.degraded .status-dot-anim {
            background: var(--danger);
            box-shadow: 0 0 0 0 rgba(231,76,60,0.4);
            animation: pulse-red 1s infinite;
        }
        @keyframes pulse-green {
            0%   { box-shadow: 0 0 0 0 rgba(46,204,113,0.5); }
            70%  { box-shadow: 0 0 0 10px rgba(46,204,113,0); }
            100% { box-shadow: 0 0 0 0 rgba(46,204,113,0); }
        }
        @keyframes pulse-red {
            0%   { box-shadow: 0 0 0 0 rgba(231,76,60,0.5); }
            70%  { box-shadow: 0 0 0 10px rgba(231,76,60,0); }
            100% { box-shadow: 0 0 0 0 rgba(231,76,60,0); }
        }

        /* ── Grille checks ── */
        .checks-title {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--muted);
            margin-bottom: 14px;
        }
        .checks-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 16px;
            margin-bottom: 32px;
        }
        .check-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 22px 24px;
            transition: transform .15s, box-shadow .15s;
        }
        .check-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 24px rgba(30,58,95,0.08);
        }
        .check-card.err {
            border-color: var(--danger);
            background: #fff8f8;
        }
        .check-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 14px;
        }
        .check-name {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            font-weight: 600;
            color: var(--text);
        }
        .check-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            background: var(--bg);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 15px;
        }
        .badge {
            font-size: 10px;
            font-weight: 700;
            padding: 3px 9px;
            border-radius: 20px;
            text-transform: uppercase;
            letter-spacing: .5px;
        }
        .badge.ok  { background: #dcfce7; color: #16a34a; }
        .badge.err { background: #fee2e2; color: #dc2626; }
        .check-message {
            font-size: 12px;
            color: var(--muted);
            font-family: 'JetBrains Mono', monospace;
            line-height: 1.5;
        }

        /* Barre disque */
        .disk-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            margin: 12px 0;
        }
        .disk-stat {
            background: var(--bg);
            border-radius: 8px;
            padding: 8px 10px;
        }
        .disk-stat-label {
            font-size: 10px;
            color: var(--muted);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .5px;
        }
        .disk-stat-value {
            font-size: 16px;
            font-weight: 700;
            color: var(--text);
            font-family: 'Sora', sans-serif;
            margin-top: 2px;
        }
        .disk-bar-wrap {
            margin-top: 12px;
        }
        .disk-bar-labels {
            display: flex;
            justify-content: space-between;
            font-size: 10px;
            color: var(--muted);
            margin-bottom: 5px;
        }
        .disk-bar-track {
            height: 8px;
            background: var(--border);
            border-radius: 4px;
            overflow: hidden;
        }
        .disk-bar-fill {
            height: 100%;
            border-radius: 4px;
            transition: width .6s ease;
        }

        /* ── Footer ── */
        .footer {
            text-align: center;
            padding: 20px;
            font-size: 11px;
            color: var(--muted);
            border-top: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 16px;
        }
        .footer a {
            color: var(--accent);
            text-decoration: none;
        }
        .footer a:hover { text-decoration: underline; }

        /* ── Refresh countdown ── */
        .refresh-bar {
            height: 3px;
            background: var(--border);
            border-radius: 2px;
            overflow: hidden;
            margin-bottom: 32px;
        }
        .refresh-fill {
            height: 100%;
            background: var(--accent);
            border-radius: 2px;
            width: 100%;
            animation: countdown 30s linear forwards;
        }
        @keyframes countdown {
            from { width: 100%; }
            to   { width: 0%; }
        }
    </style>
</head>
<body>

<header class="header">
    <div class="header-brand">
        <div class="header-logo">P</div>
        <div>
            <div class="header-title">Pladigit — Statut système</div>
            <div class="header-sub">Les Bézots · Soullans (85)</div>
        </div>
    </div>
    <div class="header-ts" id="page-ts">
        {{ \Carbon\Carbon::parse($ts)->setTimezone('Europe/Paris')->format('d/m/Y à H:i:s') }}
    </div>
</header>

<main class="main">

    {{-- Barre de rafraîchissement --}}
    <div class="refresh-bar" title="Rafraîchissement automatique dans 30 s">
        <div class="refresh-fill" id="refresh-fill"></div>
    </div>

    {{-- Statut global --}}
    <div class="status-banner {{ $status }}">
        <div class="status-icon">
            {{ $healthy ? '✓' : '⚠' }}
        </div>
        <div>
            <div class="status-title">
                {{ $healthy ? 'Tous les systèmes sont opérationnels' : 'Dégradation détectée' }}
            </div>
            <div class="status-desc">
                {{ $healthy
                    ? 'Base de données, cache et disque fonctionnent normalement.'
                    : 'Un ou plusieurs services nécessitent votre attention.' }}
            </div>
        </div>
        <div class="status-dot-anim"></div>
    </div>

    {{-- Cartes checks --}}
    <div class="checks-title">Détail des vérifications</div>
    <div class="checks-grid">

        {{-- Base de données --}}
        @php $db = $checks['database']; @endphp
        <div class="check-card {{ $db['ok'] ? '' : 'err' }}">
            <div class="check-header">
                <div class="check-name">
                    <div class="check-icon">🗄️</div>
                    Base de données
                </div>
                <span class="badge {{ $db['ok'] ? 'ok' : 'err' }}">{{ $db['ok'] ? 'OK' : 'KO' }}</span>
            </div>
            <div class="check-message">{{ $db['message'] }}</div>
        </div>

        {{-- Redis --}}
        @php $redis = $checks['redis']; @endphp
        <div class="check-card {{ $redis['ok'] ? '' : 'err' }}">
            <div class="check-header">
                <div class="check-name">
                    <div class="check-icon">⚡</div>
                    Cache Redis
                </div>
                <span class="badge {{ $redis['ok'] ? 'ok' : 'err' }}">{{ $redis['ok'] ? 'OK' : 'KO' }}</span>
            </div>
            <div class="check-message">{{ $redis['message'] }}</div>
        </div>

        {{-- Disque --}}
        @php $disk = $checks['disk']; @endphp
        <div class="check-card {{ $disk['ok'] ? '' : 'err' }}">
            <div class="check-header">
                <div class="check-name">
                    <div class="check-icon">💾</div>
                    Disque
                </div>
                <span class="badge {{ $disk['ok'] ? 'ok' : 'err' }}">{{ $disk['ok'] ? 'OK' : 'KO' }}</span>
            </div>
            @if(isset($disk['free_gb']))
                <div class="disk-stats">
                    <div class="disk-stat">
                        <div class="disk-stat-label">Libre</div>
                        <div class="disk-stat-value">{{ $disk['free_gb'] }} Go</div>
                    </div>
                    <div class="disk-stat">
                        <div class="disk-stat-label">Utilisé</div>
                        <div class="disk-stat-value">{{ $disk['used_gb'] }} Go</div>
                    </div>
                </div>
                <div class="disk-bar-wrap">
                    <div class="disk-bar-labels">
                        <span>Utilisé — {{ $disk['used_percent'] }}%</span>
                        <span>Total — {{ $disk['total_gb'] }} Go</span>
                    </div>
                    <div class="disk-bar-track">
                        <div class="disk-bar-fill" style="
                            width: {{ $disk['used_percent'] }}%;
                            background: {{ $disk['free_percent'] < 10 ? '#E74C3C' : ($disk['free_percent'] < 25 ? '#F39C12' : '#2ECC71') }};
                        "></div>
                    </div>
                </div>
            @else
                <div class="check-message">{{ $disk['message'] }}</div>
            @endif
        </div>

    </div>

</main>

<footer class="footer">
    <span>Pladigit v2.0 · Phase 2</span>
    <span>·</span>
    <a href="/health" onclick="location.reload();return false;">↺ Rafraîchir</a>
    <span>·</span>
    <a href="/health?json=1">JSON brut</a>
    <span>·</span>
    <span id="next-refresh">Prochain rafraîchissement dans 30 s</span>
</footer>

<script>
// Compte à rebours + rafraîchissement automatique
let remaining = 30;
const nextEl = document.getElementById('next-refresh');

const interval = setInterval(() => {
    remaining--;
    if (nextEl) nextEl.textContent = `Prochain rafraîchissement dans ${remaining} s`;
    if (remaining <= 0) {
        clearInterval(interval);
        location.reload();
    }
}, 1000);
</script>

</body>
</html>
