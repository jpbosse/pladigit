@extends('layouts.app')
@section('title', 'Tableau de bord')

@section('content')
@php
    $user = Auth::user();
    $role = \App\Enums\UserRole::tryFrom($user->role ?? '');
    $isAdmin         = $role?->atLeast(\App\Enums\UserRole::ADMIN)        ?? false;
    $isDgs           = $role?->atLeast(\App\Enums\UserRole::DGS)          ?? false;
    $isAtLeastResp   = $role?->atLeast(\App\Enums\UserRole::RESP_SERVICE) ?? false;
    $isRespDirection = $role === \App\Enums\UserRole::RESP_DIRECTION;
    $isRespService   = $role === \App\Enums\UserRole::RESP_SERVICE;
    $isSimpleUser    = ! $isAtLeastResp;
    $isSuperAdmin    = session('super_admin_logged_in');
@endphp

{{-- ── Hero banner ───────────────────────────────────────────────── --}}
<div style="background:linear-gradient(135deg,var(--pd-navy-dark) 0%,var(--pd-navy) 55%,var(--pd-navy-light) 100%);
            padding:32px 40px 40px;position:relative;overflow:hidden;">
    <svg style="position:absolute;right:0;top:0;bottom:0;width:50%;height:100%;opacity:.07;pointer-events:none;"
         viewBox="0 0 500 200" fill="none" preserveAspectRatio="xMidYMid slice">
        <circle cx="400" cy="100" r="140" stroke="white" stroke-width="1"/>
        <circle cx="400" cy="100" r="100" stroke="white" stroke-width="0.7"/>
        <circle cx="400" cy="100" r="60"  stroke="white" stroke-width="0.5"/>
        @for($gx = 0; $gx < 10; $gx++)
        @for($gy = 0; $gy < 6; $gy++)
        <circle cx="{{ 20 + $gx * 40 }}" cy="{{ 20 + $gy * 40 }}" r="1.5" fill="white"/>
        @endfor
        @endfor
        <line x1="0"   y1="200" x2="500" y2="0"   stroke="white" stroke-width="0.5"/>
        <line x1="100" y1="200" x2="500" y2="40"  stroke="white" stroke-width="0.3"/>
    </svg>

    <div style="position:relative;z-index:1;display:flex;align-items:center;gap:20px;flex-wrap:wrap;">
        <div style="width:64px;height:64px;border-radius:16px;flex-shrink:0;background:#fff;
                    display:flex;align-items:center;justify-content:center;box-shadow:0 6px 20px rgba(0,0,0,0.3);">
            @if($user->avatar_path)
                <img src="{{ asset('storage/'.$user->avatar_path) }}" style="width:100%;height:100%;border-radius:14px;object-fit:cover;">
            @else
                <img src="{{ asset('img/logo.png') }}" style="width:56px;height:56px;object-fit:contain;display:block;">
            @endif
        </div>

        <div style="flex:1;min-width:200px;">
            <div style="font-family:'Sora',sans-serif;font-size:22px;font-weight:700;color:#fff;margin-bottom:4px;">
                Bonjour, {{ explode(' ', $user->name)[0] }} 👋
            </div>
            <div style="font-size:13px;color:rgba(255,255,255,0.55);">
                {{ now()->locale('fr')->isoFormat('dddd D MMMM YYYY') }}
                @if($org) · {{ $org->name }} @endif
            </div>
        </div>

        <div style="display:flex;flex-wrap:wrap;gap:8px;">
            <span style="background:rgba(46,204,113,0.2);border:1px solid rgba(46,204,113,0.3);
                         color:#4dd890;font-size:12px;font-weight:500;
                         padding:5px 12px;border-radius:20px;display:flex;align-items:center;gap:6px;">
                <span style="width:6px;height:6px;border-radius:50%;background:#2ECC71;animation:pd-pulse 2s infinite;flex-shrink:0;"></span>
                Connecté
            </span>
            @if($user->last_login_at)
            <span style="background:rgba(255,255,255,0.1);border:1px solid rgba(255,255,255,0.15);
                         color:rgba(255,255,255,0.7);font-size:12px;padding:5px 12px;border-radius:20px;">
                🕐 {{ $user->last_login_at->locale('fr')->diffForHumans() }}
            </span>
            @endif
            <span style="background:{{ $user->totp_enabled ? 'rgba(46,204,113,0.2)' : 'rgba(232,168,56,0.2)' }};
                         border:1px solid {{ $user->totp_enabled ? 'rgba(46,204,113,0.3)' : 'rgba(232,168,56,0.3)' }};
                         color:{{ $user->totp_enabled ? '#4dd890' : '#E8A838' }};
                         font-size:12px;padding:5px 12px;border-radius:20px;">
                {{ $user->totp_enabled ? '🔒 2FA activée' : '⚠ 2FA désactivée' }}
            </span>
            <span style="background:rgba(59,154,225,0.2);border:1px solid rgba(59,154,225,0.3);
                         color:#5bb3f5;font-size:12px;padding:5px 12px;border-radius:20px;">
                {{ $role?->label() ?? 'Utilisateur' }}
            </span>
        </div>
    </div>
</div>

{{-- ── Alerte 2FA ────────────────────────────────────────────────── --}}
@unless($user->totp_enabled)
<div style="padding:16px 40px;background:#fff9ee;border-bottom:1px solid #f0c060;">
    <div class="pd-alert-box">
        <div class="pd-alert-icon">⚠️</div>
        <div class="pd-alert-content" style="flex:1;">
            <h4>Double authentification non activée</h4>
            <p>Renforcez la sécurité de votre compte. En cas de compromission du mot de passe, le 2FA empêche tout accès non autorisé.</p>
        </div>
        <a href="{{ route('2fa.setup') }}"
           style="display:inline-block;padding:8px 18px;border-radius:9px;
                  background:var(--pd-gold);color:#fff;font-size:13px;font-weight:600;
                  text-decoration:none;flex-shrink:0;">
            Activer maintenant
        </a>
        <button class="pd-alert-close" type="button"
                onclick="this.closest('.pd-alert-box').parentElement.style.display='none'">✕</button>
    </div>
</div>
@endunless

{{-- ── Contenu principal ────────────────────────────────────────── --}}
<div style="padding:28px 40px;max-width:1400px;">

    {{-- ── Onglets (Admin/DGS/Président uniquement) ─────────────────── --}}
    @if($isDgs || $isSuperAdmin)
    <div style="display:flex;gap:4px;margin-bottom:28px;border-bottom:2px solid var(--pd-border);padding-bottom:0;">
        <button onclick="pdTab('overview')" id="tab-overview"
                style="padding:10px 20px;border:none;background:none;cursor:pointer;font-size:13px;font-weight:600;
                       color:var(--pd-accent);border-bottom:2px solid var(--pd-accent);margin-bottom:-2px;">
            📊 Vue d'ensemble
        </button>
        <button onclick="pdTab('storage')" id="tab-storage"
                style="padding:10px 20px;border:none;background:none;cursor:pointer;font-size:13px;font-weight:500;
                       color:var(--pd-muted);border-bottom:2px solid transparent;margin-bottom:-2px;">
            💾 Stockage détaillé
        </button>
        @if($isSuperAdmin)
        <button onclick="pdTab('platform')" id="tab-platform"
                style="padding:10px 20px;border:none;background:none;cursor:pointer;font-size:13px;font-weight:500;
                       color:var(--pd-muted);border-bottom:2px solid transparent;margin-bottom:-2px;">
            🌐 Plateforme
        </button>
        @endif
    </div>

    <script>
    function pdTab(name) {
        ['overview','storage','platform'].forEach(t => {
            const btn   = document.getElementById('tab-'+t);
            const panel = document.getElementById('panel-'+t);
            if (!btn || !panel) return;
            const active = t === name;
            btn.style.color       = active ? 'var(--pd-accent)' : 'var(--pd-muted)';
            btn.style.fontWeight  = active ? '600' : '500';
            btn.style.borderBottom = active ? '2px solid var(--pd-accent)' : '2px solid transparent';
            panel.style.display   = active ? 'block' : 'none';
        });
    }
    </script>
    @endif

    {{-- ══════════════════════════════════════════════════════════════ --}}
    {{-- PANEL 1 — Vue d'ensemble (tous les rôles) ───────────────── --}}
    {{-- ══════════════════════════════════════════════════════════════ --}}
    <div id="panel-overview">

        {{-- Modules --}}
        <div class="pd-section-header">
            <h2 class="pd-section-title">Modules</h2>
            <span class="pd-section-sub">{{ $org->plan === 'community' ? 'Offre Communautaire' : ucfirst($org->plan) }}</span>
        </div>
        @php
        $org = app(\App\Services\TenantManager::class)->current();
        $activeModules = [];
        if ($org?->hasModule(\App\Enums\ModuleKey::MEDIA)) {
            $activeModules[] = ['icon'=>'📷','name'=>'Photothèque','desc'=>'Albums, médias NAS, watermark, partage','color'=>'#2ECC71','bg'=>'rgba(46,204,113,0.1)','route'=>'media.albums.index'];
        }
        @endphp
        @if(count($activeModules) > 0)
        <div class="pd-module-grid" style="margin-bottom:32px;">
            @foreach($activeModules as $mod)
            <div class="pd-module-card"
                 style="--card-accent:{{ $mod['color'] }};cursor:pointer;"
                 onclick="window.location='{{ route($mod['route']) }}'">
                <div class="pd-module-badge active">Actif</div>
                <div class="pd-module-icon" style="background:{{ $mod['bg'] }};">{{ $mod['icon'] }}</div>
                <h3>{{ $mod['name'] }}</h3>
                <p>{{ $mod['desc'] }}</p>
            </div>
            @endforeach
        </div>
        @endif

        <div style="display:grid;grid-template-columns:1fr 300px;gap:20px;align-items:start;">
            <div>

                {{-- ── Stats Admin/DGS/Président (tenant entier) ─── --}}
                @if($isDgs)
                <div class="pd-section-header">
                    <h2 class="pd-section-title">Vue d'ensemble — {{ $org->name }}</h2>
                </div>
                <div class="pd-stats-grid" style="margin-bottom:24px;">
                    <div class="pd-stat-card">
                        <div class="pd-stat-label">Utilisateurs actifs</div>
                        <div class="pd-stat-value">{{ $activeUsers }}</div>
                        <div class="pd-stat-trend">/ {{ $org->max_users }} max</div>
                    </div>
                    <div class="pd-stat-card">
                        <div class="pd-stat-label">Comptes LDAP</div>
                        <div class="pd-stat-value">{{ $ldapUsers }}</div>
                        <div class="pd-stat-trend" style="color:var(--pd-accent);">annuaire AD</div>
                    </div>
                    <div class="pd-stat-card">
                        <div class="pd-stat-label">Directions</div>
                        <div class="pd-stat-value">{{ $totalDirections }}</div>
                        <div class="pd-stat-trend">{{ $totalServices }} services</div>
                    </div>
                    <div class="pd-stat-card">
                        <div class="pd-stat-label">Administrateurs</div>
                        <div class="pd-stat-value">{{ $adminUsers }}</div>
                        <div class="pd-stat-trend">comptes admin</div>
                    </div>
                    <div class="pd-stat-card">
                        <div class="pd-stat-label">Médias</div>
                        <div class="pd-stat-value">{{ number_format($mediaCount) }}</div>
                        <div class="pd-stat-trend">{{ $storageUsedMb }} Mo utilisés</div>
                    </div>
                    <div class="pd-stat-card">
                        <div class="pd-stat-label">Plan</div>
                        <div class="pd-stat-value" style="font-size:16px;padding-top:4px;">{{ ucfirst($org->plan) }}</div>
                        <div class="pd-stat-trend" style="color:var(--pd-muted);">AGPL-3.0</div>
                    </div>
                </div>

                {{-- ── Stats Resp. Direction / Resp. Service ──────── --}}
                @elseif($isAtLeastResp)
                <div class="pd-section-header">
                    <h2 class="pd-section-title">Mon périmètre</h2>
                    <span class="pd-section-sub">
                        {{ $isRespDirection ? 'Direction(s) gérée(s)' : 'Service(s) géré(s)' }}
                    </span>
                </div>
                <div class="pd-stats-grid" style="margin-bottom:24px;">
                    <div class="pd-stat-card">
                        <div class="pd-stat-label">Agents dans mon périmètre</div>
                        <div class="pd-stat-value">{{ $scopedActiveCount }}</div>
                        <div class="pd-stat-trend">/ {{ $scopedUserCount }} total</div>
                    </div>
                    <div class="pd-stat-card">
                        <div class="pd-stat-label">Médias uploadés</div>
                        <div class="pd-stat-value">{{ number_format($mediaCount) }}</div>
                        <div class="pd-stat-trend">par mon équipe</div>
                    </div>
                    <div class="pd-stat-card">
                        <div class="pd-stat-label">Stockage utilisé</div>
                        <div class="pd-stat-value" style="font-size:18px;">{{ $storageUsedMb }} Mo</div>
                        <div class="pd-stat-trend">par mon équipe</div>
                    </div>
                </div>

                {{-- ── Vue utilisateur simple ─────────────────────── --}}
                @else
                <div class="pd-section-header" style="margin-top:0;">
                    <h2 class="pd-section-title">Mon compte</h2>
                </div>
                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:12px;">
                    <div class="pd-stat-card">
                        <div class="pd-stat-label">Rôle</div>
                        <div style="font-family:'Sora',sans-serif;font-size:15px;font-weight:600;color:var(--pd-text);margin-top:4px;">{{ $role?->label() ?? 'Utilisateur' }}</div>
                    </div>
                    <div class="pd-stat-card">
                        <div class="pd-stat-label">Dernière connexion</div>
                        <div style="font-size:13px;font-weight:500;color:var(--pd-text);margin-top:4px;">{{ $user->last_login_at?->locale('fr')->diffForHumans() ?? 'Première connexion' }}</div>
                        @if($user->last_login_ip)<div style="font-size:11px;color:var(--pd-muted);margin-top:2px;">{{ $user->last_login_ip }}</div>@endif
                    </div>
                    <div class="pd-stat-card">
                        <div class="pd-stat-label">Mes médias</div>
                        <div style="font-size:22px;font-weight:700;color:var(--pd-text);margin-top:4px;">{{ number_format($mediaCount) }}</div>
                        <div style="font-size:12px;color:var(--pd-muted);margin-top:2px;">{{ $storageUsedMb }} Mo utilisés</div>
                    </div>
                    <div class="pd-stat-card">
                        <div class="pd-stat-label">Sécurité 2FA</div>
                        <div style="font-size:13px;font-weight:600;color:{{ $user->totp_enabled ? 'var(--pd-success)' : 'var(--pd-warning)' }};margin-top:4px;">
                            {{ $user->totp_enabled ? '✓ Activée' : '⚠ Non activée' }}
                        </div>
                        <a href="{{ route('2fa.setup') }}" style="font-size:11.5px;color:var(--pd-accent);margin-top:4px;display:block;">
                            {{ $user->totp_enabled ? 'Gérer' : 'Activer' }}
                        </a>
                    </div>
                </div>
                @endif

                {{-- ── Activité récente (DGS+ et Resp.) ──────────── --}}
                @if($isAtLeastResp && $recentAudit->count())
                <div class="pd-section-header" style="margin-top:24px;">
                    <h2 class="pd-section-title">Activité récente</h2>
                    <span class="pd-section-sub">
                        {{ $isDgs ? 'Tenant entier' : 'Mon périmètre' }}
                    </span>
                </div>
                <div class="pd-activity-card" style="margin-bottom:20px;">
                    @php
                    $actionMap = [
                        'user.login'                    => ['🔑','bg:rgba(59,154,225,0.1)','Connexion'],
                        'user.created'                  => ['➕','bg:rgba(46,204,113,0.1)','Utilisateur créé'],
                        'user.updated'                  => ['✏️','bg:rgba(59,154,225,0.08)','Utilisateur modifié'],
                        'user.deactivated'              => ['🚫','bg:rgba(231,76,60,0.1)','Utilisateur désactivé'],
                        'user.password_changed'         => ['🔒','bg:rgba(155,89,182,0.1)','Mot de passe changé'],
                        'user.password_reset'           => ['🔄','bg:rgba(232,168,56,0.1)','MDP réinitialisé'],
                        'department.created'            => ['🏢','bg:rgba(26,188,156,0.1)','Direction/Service créé'],
                        'department.updated'            => ['✏️','bg:rgba(59,154,225,0.08)','Structure modifiée'],
                        'user.backup_codes_regenerated' => ['🛡','bg:rgba(155,89,182,0.1)','Codes 2FA régénérés'],
                    ];
                    @endphp
                    @foreach($recentAudit->take(6) as $log)
                    @php [$ico,$bg,$label] = $actionMap[$log->action] ?? ['📌','bg:rgba(107,114,128,0.1)',$log->action]; @endphp
                    <div class="pd-activity-item">
                        <div class="pd-act-icon" style="{{ $bg }}">{{ $ico }}</div>
                        <div style="flex:1;min-width:0;">
                            <div class="pd-act-text"><strong>{{ $log->user_name }}</strong> — {{ $label }}</div>
                            <div class="pd-act-time">{{ \Carbon\Carbon::parse($log->created_at)->locale('fr')->diffForHumans() }}</div>
                        </div>
                    </div>
                    @endforeach
                    @if($isDgs && $recentAudit->count() > 6)
                    <div class="pd-act-more"><a href="{{ route('admin.audit.index') }}">Voir tout l'historique →</a></div>
                    @endif
                </div>
                @endif

                {{-- ── Dernières connexions (DGS+) ─────────────────── --}}
                @if($isDgs && $recentLogins->count())
                <div class="pd-section-header">
                    <h2 class="pd-section-title">Dernières connexions</h2>
                </div>
                <div class="pd-activity-card">
                    @foreach($recentLogins as $login)
                    <div class="pd-activity-item">
                        <div style="width:32px;height:32px;border-radius:9px;flex-shrink:0;
                                    background:linear-gradient(135deg,var(--pd-navy-light),var(--pd-accent));
                                    display:flex;align-items:center;justify-content:center;
                                    font-family:'Sora',sans-serif;font-size:12px;font-weight:700;color:#fff;">
                            {{ strtoupper(substr($login->name,0,2)) }}
                        </div>
                        <div style="flex:1;min-width:0;">
                            <div class="pd-act-text"><strong>{{ $login->name }}</strong></div>
                            @if($login->last_login_ip)
                            <div class="pd-act-time">{{ $login->last_login_ip }}</div>
                            @endif
                        </div>
                        <span style="font-size:11.5px;color:var(--pd-muted);white-space:nowrap;">
                            {{ $login->last_login_at->locale('fr')->diffForHumans() }}
                        </span>
                    </div>
                    @endforeach
                </div>
                @endif

            </div>

            {{-- ── Colonne droite : actions rapides + quotas ────── --}}
            <div>
                <div class="pd-section-header">
                    <h2 class="pd-section-title">Actions rapides</h2>
                </div>
                <div class="pd-quick-card">
                    <div class="pd-quick-header">Créer</div>
                    <a href="{{ route('media.albums.index') }}" class="pd-qa-btn">
                        <div class="pd-qa-icon" style="background:rgba(46,204,113,0.12);">📷</div>
                        Uploader des photos
                        <span class="pd-qa-arrow">→</span>
                    </a>
                    @if($isAdmin)
                    <a href="{{ route('admin.users.create') }}" class="pd-qa-btn">
                        <div class="pd-qa-icon" style="background:rgba(59,154,225,0.12);">👤</div>
                        Inviter un utilisateur
                        <span class="pd-qa-arrow">→</span>
                    </a>
                    @endif

                </div>

                @if($isAdmin)
                <div class="pd-quick-card">
                    <div class="pd-quick-header">Administration</div>
                    <a href="{{ route('admin.users.index') }}" class="pd-qa-btn">
                        <div class="pd-qa-icon" style="background:rgba(59,154,225,0.1);">👥</div>
                        Gérer les utilisateurs
                        <span class="pd-qa-arrow">→</span>
                    </a>
                    <a href="{{ route('admin.departments.index') }}" class="pd-qa-btn">
                        <div class="pd-qa-icon" style="background:rgba(26,188,156,0.1);">🏢</div>
                        Directions & Services
                        <span class="pd-qa-arrow">→</span>
                    </a>
                    <a href="{{ route('admin.settings.branding') }}" class="pd-qa-btn">
                        <div class="pd-qa-icon" style="background:rgba(232,168,56,0.1);">🎨</div>
                        Logo & couleurs
                        <span class="pd-qa-arrow">→</span>
                    </a>
                    <a href="{{ route('admin.settings.smtp') }}" class="pd-qa-btn">
                        <div class="pd-qa-icon" style="background:rgba(231,76,60,0.1);">📧</div>
                        Configuration SMTP
                        <span class="pd-qa-arrow">→</span>
                    </a>
                </div>
                @endif

                {{-- Plan & quota (stockage du périmètre) --}}
                <div class="pd-quick-card">
                    <div class="pd-quick-header">
                        {{ $isDgs ? 'Plan & utilisation tenant' : 'Mon utilisation' }}
                    </div>
                    <div style="padding:14px 16px;">
                        @if($isDgs)
                        <div style="margin-bottom:14px;">
                            <div style="display:flex;justify-content:space-between;align-items:baseline;margin-bottom:5px;">
                                <span style="font-size:12px;color:var(--pd-muted);font-weight:500;">Utilisateurs</span>
                                <span style="font-size:12px;color:var(--pd-muted);">{{ $activeUsers }} / {{ $org->max_users }}</span>
                            </div>
                            <div style="background:var(--pd-bg);border-radius:4px;height:6px;overflow:hidden;">
                                <div style="height:100%;border-radius:4px;background:linear-gradient(90deg,var(--pd-accent),var(--pd-navy));
                                            width:{{ min(100, round($activeUsers / max(1,$org->max_users) * 100)) }}%;transition:width .6s ease;"></div>
                            </div>
                        </div>
                        @endif

                        <div style="margin-bottom:14px;">
                            <div style="display:flex;justify-content:space-between;align-items:baseline;margin-bottom:5px;">
                                <span style="font-size:12px;color:var(--pd-muted);font-weight:500;">Stockage</span>
                                <span style="font-size:12px;color:var(--pd-muted);">
                                    {{ $storageUsedMb }} Mo
                                    @if($isDgs) / {{ round($storageQuotaMb / 1024, 1) }} Go @endif
                                </span>
                            </div>
                            <div style="background:var(--pd-bg);border-radius:4px;height:6px;overflow:hidden;">
                                @php $pct = $isDgs ? $storageUsedPct : min(100, round($storageUsedMb / max(1, $storageQuotaMb) * 100)); @endphp
                                <div style="height:100%;border-radius:4px;
                                            background:linear-gradient(90deg,
                                                {{ $pct > 80 ? 'var(--pd-danger)' : ($pct > 60 ? 'var(--pd-warning)' : 'var(--pd-success)') }},
                                                {{ $pct > 80 ? '#c0392b' : ($pct > 60 ? '#d68910' : '#27ae60') }});
                                            width:{{ $pct }}%;transition:width .6s ease;"></div>
                            </div>
                        </div>

                        <div style="display:flex;justify-content:space-between;padding-top:6px;border-top:1px solid var(--pd-border);">
                            <span style="font-size:12px;color:var(--pd-muted);">📷 {{ number_format($mediaCount) }} médias</span>
                            @if($isDgs)
                            <span style="font-size:12px;font-weight:600;color:var(--pd-text);">{{ ucfirst($org->plan) }}</span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>{{-- /panel-overview --}}

    {{-- ══════════════════════════════════════════════════════════════ --}}
    {{-- PANEL 2 — Stockage détaillé (DGS+ uniquement) ──────────── --}}
    {{-- ══════════════════════════════════════════════════════════════ --}}
    @if($isDgs || $isSuperAdmin)
    <div id="panel-storage" style="display:none;">

        <div class="pd-section-header">
            <h2 class="pd-section-title">Stockage détaillé — {{ $org->name }}</h2>
            <span class="pd-section-sub">Répartition par module</span>
        </div>

        {{-- Cartes par module --}}
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:16px;margin-bottom:28px;">
            @php
            $modules_storage = [
                ['label'=>'Photothèque','icon'=>'📷','bytes'=>$storageByModule['media'],'count'=>$storageByModule['media_count'],'color'=>'#2ECC71'],
                ['label'=>'Documents (GED)','icon'=>'📁','bytes'=>$storageByModule['ged'],'count'=>$storageByModule['ged_count'],'color'=>'#3B9AE1'],
                ['label'=>'ERP DataGrid','icon'=>'🗄','bytes'=>$storageByModule['erp'],'count'=>$storageByModule['erp_rows'],'color'=>'#1abc9c','suffix'=>'lignes'],
                ['label'=>'Chat (pièces jointes)','icon'=>'💬','bytes'=>$storageByModule['chat'],'count'=>$storageByModule['chat_files'],'color'=>'#E8A838'],
            ];
            $totalStorageBytes = array_sum(array_column($modules_storage,'bytes'));
            @endphp
            @foreach($modules_storage as $ms)
            @php
                $pct = $totalStorageBytes > 0 ? round($ms['bytes'] / $totalStorageBytes * 100) : 0;
                $mb  = round($ms['bytes'] / 1024 / 1024, 1);
            @endphp
            <div class="pd-stat-card" style="position:relative;overflow:hidden;">
                <div style="position:absolute;top:0;left:0;height:3px;width:{{ $pct }}%;background:{{ $ms['color'] }};border-radius:2px 0 0 0;"></div>
                <div style="font-size:22px;margin-bottom:8px;">{{ $ms['icon'] }}</div>
                <div class="pd-stat-label">{{ $ms['label'] }}</div>
                <div class="pd-stat-value" style="font-size:20px;">{{ $mb }} Mo</div>
                <div class="pd-stat-trend">{{ number_format($ms['count']) }} {{ $ms['suffix'] ?? 'fichiers' }} · {{ $pct }}%</div>
            </div>
            @endforeach
        </div>

        {{-- Top utilisateurs --}}
        @if(count($storageTopUsers))
        <div class="pd-section-header">
            <h2 class="pd-section-title">Top utilisateurs — Photothèque</h2>
        </div>
        <div class="pd-activity-card" style="margin-bottom:28px;">
            @foreach($storageTopUsers as $i => $tu)
            @php $mb = round($tu['bytes'] / 1024 / 1024, 1); @endphp
            <div class="pd-activity-item">
                <div style="width:28px;height:28px;border-radius:8px;flex-shrink:0;
                            background:linear-gradient(135deg,var(--pd-navy-light),var(--pd-accent));
                            display:flex;align-items:center;justify-content:center;
                            font-size:11px;font-weight:700;color:#fff;">
                    {{ $i + 1 }}
                </div>
                <div style="flex:1;min-width:0;">
                    <div class="pd-act-text"><strong>{{ $tu['name'] }}</strong></div>
                    <div class="pd-act-time">{{ number_format($tu['count']) }} fichiers</div>
                </div>
                <span style="font-size:12px;font-weight:600;color:var(--pd-text);">{{ $mb }} Mo</span>
            </div>
            @endforeach
        </div>
        @endif

        {{-- Croissance --}}
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:16px;">
            <div class="pd-stat-card">
                <div class="pd-stat-label">Croissance (30 j.)</div>
                <div class="pd-stat-value">{{ $storageGrowthPerMonth }} Go</div>
                <div class="pd-stat-trend">nouveaux uploads</div>
            </div>
            <div class="pd-stat-card">
                <div class="pd-stat-label">Quota utilisé</div>
                <div class="pd-stat-value">{{ $storageUsedPct }}%</div>
                <div class="pd-stat-trend">{{ $storageUsedMb }} Mo / {{ round($storageQuotaMb/1024,1) }} Go</div>
            </div>
        </div>

    </div>{{-- /panel-storage --}}

    {{-- ══════════════════════════════════════════════════════════════ --}}
    {{-- PANEL 3 — Plateforme (Super Admin uniquement) ───────────── --}}
    {{-- ══════════════════════════════════════════════════════════════ --}}
    @if($isSuperAdmin)
    <div id="panel-platform" style="display:none;">

        <div class="pd-section-header">
            <h2 class="pd-section-title">Organisations hébergées</h2>
            <span class="pd-section-sub">{{ count($storagePerOrg) }} organisation(s) — stockage total :
                {{ round(array_sum(array_column($storagePerOrg,'storage_bytes')) / 1024 / 1024 / 1024, 2) }} Go
            </span>
        </div>

        @if(count($storagePerOrg))
        @php
            $totalBytes      = array_sum(array_column($storagePerOrg, 'storage_bytes'));
            $totalUsers      = array_sum(array_column($storagePerOrg, 'user_count'));
            $totalBytesMedia = array_sum(array_column($storagePerOrg, 'bytes_media'));
            $totalBytesGed   = array_sum(array_column($storagePerOrg, 'bytes_ged'));
            $totalBytesChat  = array_sum(array_column($storagePerOrg, 'bytes_chat'));
        @endphp

        {{-- Cartes récap plateforme --}}
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:14px;margin-bottom:28px;">
            <div class="pd-stat-card">
                <div class="pd-stat-label">Organisations</div>
                <div class="pd-stat-value">{{ count($storagePerOrg) }}</div>
                <div class="pd-stat-trend">hébergées</div>
            </div>
            <div class="pd-stat-card">
                <div class="pd-stat-label">Utilisateurs total</div>
                <div class="pd-stat-value">{{ number_format($totalUsers) }}</div>
                <div class="pd-stat-trend">tous tenants</div>
            </div>
            <div class="pd-stat-card">
                <div class="pd-stat-label">Stockage total</div>
                <div class="pd-stat-value" style="font-size:18px;">
                    {{ $totalBytes >= 1073741824
                        ? round($totalBytes / 1073741824, 2).' Go'
                        : round($totalBytes / 1048576, 1).' Mo' }}
                </div>
                <div class="pd-stat-trend">tous modules confondus</div>
            </div>
            <div class="pd-stat-card">
                <div class="pd-stat-label">📷 Photothèque</div>
                <div class="pd-stat-value" style="font-size:18px;">{{ round($totalBytesMedia / 1048576, 1) }} Mo</div>
                <div class="pd-stat-trend" style="color:#2ECC71;">tous tenants</div>
            </div>
            <div class="pd-stat-card">
                <div class="pd-stat-label">📁 GED</div>
                <div class="pd-stat-value" style="font-size:18px;">{{ round($totalBytesGed / 1048576, 1) }} Mo</div>
                <div class="pd-stat-trend" style="color:#3B9AE1;">Phase 5</div>
            </div>
            <div class="pd-stat-card">
                <div class="pd-stat-label">💬 Chat</div>
                <div class="pd-stat-value" style="font-size:18px;">{{ round($totalBytesChat / 1048576, 1) }} Mo</div>
                <div class="pd-stat-trend" style="color:#E8A838;">Phase 9 — estimé</div>
            </div>
        </div>

        {{-- Tableau détail par organisation --}}
        <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;font-size:13px;">
                <thead>
                    <tr style="border-bottom:2px solid var(--pd-border);">
                        <th style="text-align:left;padding:10px 12px;color:var(--pd-muted);font-weight:600;">Organisation</th>
                        <th style="text-align:left;padding:10px 12px;color:var(--pd-muted);font-weight:600;">Plan</th>
                        <th style="text-align:left;padding:10px 12px;color:var(--pd-muted);font-weight:600;">Statut</th>
                        <th style="text-align:right;padding:10px 12px;color:var(--pd-muted);font-weight:600;">Utilisateurs</th>
                        <th style="text-align:right;padding:10px 12px;color:var(--pd-muted);font-weight:600;">📷 Photo</th>
                        <th style="text-align:right;padding:10px 12px;color:var(--pd-muted);font-weight:600;">📁 GED</th>
                        <th style="text-align:right;padding:10px 12px;color:var(--pd-muted);font-weight:600;">💬 Chat</th>
                        <th style="padding:10px 12px;color:var(--pd-muted);font-weight:600;min-width:200px;">Total / Quota</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($storagePerOrg as $row)
                    @php
                        $statusColor = match($row['status']) {
                            'active'    => 'var(--pd-success)',
                            'suspended' => 'var(--pd-danger)',
                            default     => 'var(--pd-warning)',
                        };
                        $statusLabel = match($row['status']) {
                            'active'    => 'Actif',
                            'suspended' => 'Suspendu',
                            default     => 'En attente',
                        };
                        $barColor = $row['quota_pct'] > 80
                            ? 'var(--pd-danger)'
                            : ($row['quota_pct'] > 60 ? 'var(--pd-warning)' : 'var(--pd-success)');
                        $fmtMb = fn(int $b) => round($b / 1048576, 1).' Mo';
                    @endphp
                    <tr style="border-bottom:1px solid var(--pd-border);">
                        <td style="padding:12px;">
                            <div style="font-weight:600;color:var(--pd-text);">{{ $row['name'] }}</div>
                            <div style="font-size:11px;color:var(--pd-muted);">{{ $row['slug'] }}.pladigit.fr</div>
                        </td>
                        <td style="padding:12px;">
                            <span style="font-size:11px;padding:3px 8px;border-radius:12px;
                                         background:rgba(59,154,225,0.1);color:var(--pd-accent);">
                                {{ ucfirst($row['plan']) }}
                            </span>
                        </td>
                        <td style="padding:12px;">
                            <span style="font-size:11px;color:{{ $statusColor }};font-weight:600;">
                                ● {{ $statusLabel }}
                            </span>
                        </td>
                        <td style="padding:12px;text-align:right;font-weight:600;">
                            {{ number_format($row['user_count']) }}
                        </td>
                        <td style="padding:12px;text-align:right;color:var(--pd-muted);font-size:12px;">
                            {{ $fmtMb($row['bytes_media']) }}
                        </td>
                        <td style="padding:12px;text-align:right;color:var(--pd-muted);font-size:12px;">
                            {{ $fmtMb($row['bytes_ged']) }}
                        </td>
                        <td style="padding:12px;text-align:right;color:var(--pd-muted);font-size:12px;">
                            {{ $fmtMb($row['bytes_chat']) }}
                        </td>
                        <td style="padding:12px;">
                            <div style="display:flex;align-items:center;gap:8px;">
                                <div style="flex:1;background:var(--pd-bg);border-radius:4px;height:6px;overflow:hidden;">
                                    <div style="height:100%;border-radius:4px;background:{{ $barColor }};
                                                width:{{ $row['quota_pct'] }}%;transition:width .6s ease;"></div>
                                </div>
                                <span style="font-size:11px;color:var(--pd-muted);white-space:nowrap;min-width:90px;text-align:right;">
                                    {{ $row['storage_mb'] }} Mo ({{ $row['quota_pct'] }}%)
                                </span>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr style="border-top:2px solid var(--pd-border);background:var(--pd-bg);">
                        <td colspan="3" style="padding:10px 12px;font-weight:700;color:var(--pd-text);">Total plateforme</td>
                        <td style="padding:10px 12px;text-align:right;font-weight:700;">
                            {{ number_format($totalUsers) }}
                        </td>
                        <td style="padding:10px 12px;text-align:right;font-weight:700;font-size:12px;">
                            {{ round($totalBytesMedia / 1048576, 1) }} Mo
                        </td>
                        <td style="padding:10px 12px;text-align:right;font-weight:700;font-size:12px;">
                            {{ round($totalBytesGed / 1048576, 1) }} Mo
                        </td>
                        <td style="padding:10px 12px;text-align:right;font-weight:700;font-size:12px;">
                            {{ round($totalBytesChat / 1048576, 1) }} Mo
                        </td>
                        <td style="padding:10px 12px;font-weight:700;">
                            {{ $totalBytes >= 1073741824
                                ? round($totalBytes / 1073741824, 2).' Go'
                                : round($totalBytes / 1048576, 1).' Mo' }}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
        @else
        <div style="text-align:center;padding:40px;color:var(--pd-muted);">
            Aucune organisation disponible.
        </div>
        @endif

    </div>{{-- /panel-platform --}}
    @endif
    @endif

</div>{{-- /padding --}}
@endsection
