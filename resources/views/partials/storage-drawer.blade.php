{{-- ══════════ DRAWER ESPACE DE STOCKAGE ══════════
     Déclenché par : #pd-storage-open (topbar)
     CSS : .pd-storage-drawer / .pd-drawer-* / .pd-quota-* / .pd-ms-*
     JS  : openStorage() / closeStorage() dans app.blade.php
--}}
@php
    $storageByModule      = $storageByModule ?? [];
    $storageQuotaMb       = $storageQuotaMb ?? 10240;
    $storageUsedMb        = $storageUsedMb ?? 0;
    $storageUsedPct       = $storageUsedPct ?? 0;
    $storageGrowthPerMonth= $storageGrowthPerMonth ?? 0;
    $storageTopUsers      = $storageTopUsers ?? collect();
    $storagePerOrg        = $storagePerOrg ?? [];
    $storageUsedBytes     = ($storageByModule['media'] ?? 0) + ($storageByModule['ged'] ?? 0);
    $quotaGo    = round($storageQuotaMb / 1024, 1);
    $usedGo     = round($storageUsedBytes / 1024 / 1024 / 1024, 1);
    $freeGo     = max(0, round($quotaGo - $usedGo, 1));
    $pct        = $storageUsedPct;
    $isAdmin    = $isAdmin ?? (auth()->user()?->role && \App\Enums\UserRole::tryFrom(auth()->user()->role)?->atLeast(\App\Enums\UserRole::ADMIN));
    try { $settingsRoute = $settingsRoute ?? route('admin.settings.media'); } catch (\Throwable) { $settingsRoute = '#'; }
    $barColor   = $pct > 80 ? '#E74C3C' : ($pct > 60 ? '#F39C12' : '#2ECC71');

    $modules = [
        [
            'icon'  => '📷', 'name' => 'Photothèque',
            'color' => '#2ECC71', 'bg' => 'rgba(46,204,113,0.15)',
            'bytes' => $storageByModule['media']       ?? 0,
            'sub'   => number_format($storageByModule['media_count'] ?? 0) . ' fichiers',
        ],
        [
            'icon'  => '📁', 'name' => 'GED / Documents',
            'color' => '#3B9AE1', 'bg' => 'rgba(59,154,225,0.15)',
            'bytes' => $storageByModule['ged']         ?? 0,
            'sub'   => number_format($storageByModule['ged_count']   ?? 0) . ' fichiers',
        ],
        [
            'icon'  => '🗄',  'name' => 'ERP DataGrid',
            'color' => '#E74C3C', 'bg' => 'rgba(231,76,60,0.15)',
            'bytes' => $storageByModule['erp']         ?? 0,
            'sub'   => number_format($storageByModule['erp_tables']  ?? 0) . ' tables · ' .
                       number_format($storageByModule['erp_rows']    ?? 0) . ' lignes',
        ],
        [
            'icon'  => '💬', 'name' => 'Chat',
            'color' => '#E8A838', 'bg' => 'rgba(232,168,56,0.15)',
            'bytes' => $storageByModule['chat']        ?? 0,
            'sub'   => number_format($storageByModule['chat_files']  ?? 0) . ' fichiers',
        ],
    ];
    $totalBytes = max(1, $storageUsedBytes);
@endphp

{{-- Overlay --}}
<div class="pd-drawer-overlay" id="pd-storage-overlay"
     onclick="closeStorage()"></div>

{{-- Drawer --}}
<aside class="pd-storage-drawer" id="pd-storage-drawer">

    {{-- En-tête --}}
    <div class="pd-drawer-head">
        <div class="pd-drawer-head-icon">🗄</div>
        <div>
            <h3>Espace de stockage</h3>
            <p>{{ $org->name ?? '—' }} — mis à jour à {{ now()->format('H\hi') }}</p>
        </div>
        <button class="pd-drawer-close" onclick="closeStorage()">✕</button>
    </div>

    {{-- Onglets --}}
    <div class="pd-drawer-tabs">
        <div class="pd-drawer-tab active" data-tab="global">Vue globale</div>
        <div class="pd-drawer-tab" data-tab="top">Top utilisateurs</div>
        @if(isset($storagePerOrg) && count($storagePerOrg))
        <div class="pd-drawer-tab" data-tab="par_org">Par organisation</div>
        @endif
    </div>

    {{-- Corps --}}
    <div class="pd-drawer-body">

        {{-- ── TAB : Vue globale ─────────────────────────────── --}}
        <div class="pd-drawer-panel active" id="pd-panel-global">

            {{-- Quota total --}}
            <div class="pd-quota-card">
                <div class="pd-quota-label">QUOTA TOTAL PLATEFORME</div>
                <div class="pd-quota-nums">
                    <span class="pd-quota-used">{{ $usedGo }} Go</span>
                    <span class="pd-quota-total">/ {{ $quotaGo }} Go</span>
                </div>
                <div class="pd-quota-track">
                    <div class="pd-quota-fill"
                         style="width:{{ $pct }}%;background:linear-gradient(90deg,{{ $barColor }},{{ $barColor }}cc);"></div>
                </div>
                <div class="pd-quota-meta">
                    <span>{{ $pct }}% utilisé</span>
                    <span class="pd-quota-free">
                        @if($freeGo > 0) ▲ {{ $freeGo }} Go libres @else ⚠ Quota dépassé @endif
                    </span>
                </div>
            </div>

            {{-- Répartition par module --}}
            <div class="pd-drawer-section">Répartition par module</div>

            @foreach($modules as $mod)
            @php
                $modGo  = round($mod['bytes'] / 1024 / 1024 / 1024, 1);
                $modPct = $totalBytes > 0 ? round($mod['bytes'] / $totalBytes * 100) : 0;
            @endphp
            <div class="pd-ms-row">
                <div class="pd-ms-icon" style="background:{{ $mod['bg'] }};">{{ $mod['icon'] }}</div>
                <div class="pd-ms-info">
                    <div class="pd-ms-name">{{ $mod['name'] }}</div>
                    <div class="pd-ms-track">
                        <div class="pd-ms-fill"
                             style="width:{{ $modPct }}%;background:{{ $mod['color'] }};"></div>
                    </div>
                    <div class="pd-ms-meta">
                        <span>{{ $mod['sub'] }}</span>
                        <span>{{ $modPct }}% du total</span>
                    </div>
                </div>
                <div class="pd-ms-size">{{ $modGo }} Go</div>
            </div>
            @endforeach

            {{-- Estimation --}}
            @if(isset($storageGrowthPerMonth) && $storageGrowthPerMonth > 0 && $freeGo > 0)
            @php $monthsLeft = max(1, (int) floor($freeGo / $storageGrowthPerMonth)); @endphp
            <div style="margin-top:20px;background:#fff8ec;border:1px solid #f0c06088;
                         border-radius:12px;padding:14px 18px;display:flex;align-items:center;gap:12px;">
                <span style="font-size:24px;">⏱</span>
                <div>
                    <div style="font-size:11px;color:#c07830;font-weight:600;margin-bottom:2px;">
                        Estimation quota atteint dans
                    </div>
                    <div style="font-size:24px;font-weight:800;color:#9a5000;font-family:'Sora',sans-serif;">
                        ~{{ $monthsLeft }} mois
                    </div>
                    <div style="font-size:12px;color:#c07830;margin-top:1px;">
                        Au rythme actuel (+{{ $storageGrowthPerMonth }} Go/mois)
                    </div>
                </div>
            </div>
            @elseif($freeGo <= 0)
            <div style="margin-top:20px;background:#fff0f0;border:1px solid rgba(231,76,60,0.3);
                         border-radius:12px;padding:14px 18px;display:flex;align-items:center;gap:12px;">
                <span style="font-size:24px;">⚠️</span>
                <div>
                    <div style="font-size:13px;font-weight:700;color:#E74C3C;">Quota dépassé</div>
                    <div style="font-size:12px;color:#c0392b;margin-top:2px;">
                        Augmentez le quota depuis l'interface Super Admin.
                    </div>
                </div>
            </div>
            @endif

        </div>{{-- /panel global --}}

        {{-- ── TAB : Top utilisateurs ────────────────────────── --}}
        <div class="pd-drawer-panel" id="pd-panel-top">
            @if(isset($storageTopUsers) && count($storageTopUsers))

            <div class="pd-drawer-section">Volume uploadé (Photothèque)</div>

            @foreach($storageTopUsers as $i => $topUser)
            @php
                $topGo  = round($topUser['bytes'] / 1024 / 1024 / 1024, 2);
                $topPct = $totalBytes > 0 ? round($topUser['bytes'] / $totalBytes * 100) : 0;
                $colors = ['#3B9AE1','#2ECC71','#E8A838','#9B59B6','#E74C3C'];
                $c = $colors[$i % 5];
            @endphp
            <div class="pd-ms-row">
                <div style="width:28px;height:28px;border-radius:50%;flex-shrink:0;
                             background:{{ $c }}22;display:flex;align-items:center;
                             justify-content:center;font-size:12px;font-weight:700;color:{{ $c }};">
                    {{ $i + 1 }}
                </div>
                <div class="pd-ms-info">
                    <div class="pd-ms-name">{{ $topUser['name'] }}</div>
                    <div class="pd-ms-track">
                        <div class="pd-ms-fill" style="width:{{ $topPct }}%;background:{{ $c }};"></div>
                    </div>
                    <div class="pd-ms-meta">
                        <span>{{ number_format($topUser['count']) }} fichier{{ $topUser['count'] > 1 ? 's' : '' }}</span>
                        <span>{{ $topPct }}%</span>
                    </div>
                </div>
                <div class="pd-ms-size">{{ $topGo }} Go</div>
            </div>
            @endforeach

            @else
            <div style="text-align:center;padding:60px 20px;color:var(--pd-muted);">
                <div style="font-size:36px;margin-bottom:12px;">👤</div>
                <div style="font-size:14px;">Aucun fichier uploadé pour l'instant.</div>
            </div>
            @endif
        </div>{{-- /panel top --}}

        {{-- ── TAB : Par organisation (Super Admin) ──────────── --}}
        @if(isset($storagePerOrg) && count($storagePerOrg))
        <div class="pd-drawer-panel" id="pd-panel-par_org">
            <div class="pd-drawer-section">Utilisation par organisation</div>
            @foreach($storagePerOrg as $orgRow)
            @php
                $orgPct   = $orgRow['quota_mb'] > 0
                    ? min(100, round($orgRow['used_mb'] / $orgRow['quota_mb'] * 100)) : 0;
                $orgColor = $orgPct > 80 ? '#E74C3C' : ($orgPct > 60 ? '#F39C12' : '#2ECC71');
            @endphp
            <div class="pd-ms-row">
                <div class="pd-ms-icon" style="background:rgba(59,154,225,0.1);">🏢</div>
                <div class="pd-ms-info">
                    <div class="pd-ms-name">{{ $orgRow['name'] }}</div>
                    <div class="pd-ms-track">
                        <div class="pd-ms-fill" style="width:{{ $orgPct }}%;background:{{ $orgColor }};"></div>
                    </div>
                    <div class="pd-ms-meta">
                        <span>{{ $orgRow['users'] }} utilisateurs</span>
                        <span>{{ $orgPct }}%</span>
                    </div>
                </div>
                <div class="pd-ms-size" style="font-size:12px;min-width:70px;">
                    {{ round($orgRow['used_mb'] / 1024, 1) }}<br>
                    <span style="color:var(--pd-muted);font-weight:400;">/ {{ round($orgRow['quota_mb'] / 1024, 1) }} Go</span>
                </div>
            </div>
            @endforeach
        </div>
        @endif

    </div>{{-- /pd-drawer-body --}}

    {{-- Pied --}}
    @if($isAdmin)
    <div style="flex-shrink:0;padding:14px 24px;border-top:1px solid rgba(255,255,255,0.08);
                background:var(--pd-surface2);display:flex;gap:8px;">
        @php
            $settingsRoute = collect(['admin.settings.index','admin.settings','admin.index'])
                ->first(fn($r) => \Illuminate\Support\Facades\Route::has($r));
        @endphp
        @if($settingsRoute)
        <a href="{{ route($settingsRoute) }}"
           style="flex:1;text-align:center;padding:9px;border-radius:9px;
                  background:var(--pd-accent);color:#fff;font-size:13px;font-weight:600;
                  text-decoration:none;display:block;transition:opacity .2s;"
           onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'">
            Gérer les quotas
        </a>
        @endif
        <button onclick="closeStorage()"
                style="flex:1;padding:9px 16px;border-radius:9px;
                       border:1.5px solid var(--pd-border);
                       background:var(--pd-bg);color:var(--pd-muted);
                       font-size:13px;cursor:pointer;font-family:inherit;transition:background .2s;"
                onmouseover="this.style.background='var(--pd-bg2)'"
                onmouseout="this.style.background='var(--pd-bg)'">
            Fermer
        </button>
    </div>
    @endif

</aside>
