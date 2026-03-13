@extends('layouts.app')
@section('title', 'Tableau de bord')

@section('content')
@php
    $user = Auth::user();
    $role = \App\Enums\UserRole::tryFrom($user->role ?? '');
    $isAdmin    = $role?->atLeast(\App\Enums\UserRole::ADMIN)   ?? false;
    $isDgs      = $role?->atLeast(\App\Enums\UserRole::DGS)     ?? false;
    $isAtLeastResp = $role?->atLeast(\App\Enums\UserRole::RESP_SERVICE) ?? false;
@endphp

{{-- ── Hero banner ───────────────────────────────────────────────── --}}
<div style="background:linear-gradient(135deg,var(--pd-navy-dark) 0%,var(--pd-navy) 55%,var(--pd-navy-light) 100%);
            padding:32px 40px 40px;position:relative;overflow:hidden;">

    {{-- SVG abstrait overlay --}}
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
        {{-- Avatar hero --}}
        <div style="width:64px;height:64px;border-radius:16px;flex-shrink:0;
                    background:#fff;
                    display:flex;align-items:center;justify-content:center;
                    
                    box-shadow:0 6px 20px rgba(0,0,0,0.3);">
            @if($user->avatar_path)
                <img src="{{ asset('storage/'.$user->avatar_path) }}" style="width:100%;height:100%;border-radius:14px;object-fit:cover;">
            @else
                <img src="{{ asset('img/logo.png') }}" style="width:56px;height:56px;object-fit:contain;display:block;">
            @endif
        </div>

        {{-- Texte --}}
        <div style="flex:1;min-width:200px;">
            <div style="font-family:'Sora',sans-serif;font-size:22px;font-weight:700;color:#fff;margin-bottom:4px;">
                Bonjour, {{ explode(' ', $user->name)[0] }} 👋
            </div>
            <div style="font-size:13px;color:rgba(255,255,255,0.55);">
                {{ now()->locale('fr')->isoFormat('dddd D MMMM YYYY') }}
                @if($org) · {{ $org->name }} @endif
            </div>
        </div>

        {{-- Chips --}}
        <div style="display:flex;flex-wrap:wrap;gap:8px;">
            <span style="background:rgba(46,204,113,0.2);border:1px solid rgba(46,204,113,0.3);
                         color:#4dd890;font-size:12px;font-weight:500;
                         padding:5px 12px;border-radius:20px;display:flex;align-items:center;gap:6px;">
                <span style="width:6px;height:6px;border-radius:50%;background:#2ECC71;animation:pd-pulse 2s infinite;flex-shrink:0;"></span>
                Connecté
            </span>
            @if($user->last_login_at)
            <span style="background:rgba(255,255,255,0.1);border:1px solid rgba(255,255,255,0.15);
                         color:rgba(255,255,255,0.7);font-size:12px;
                         padding:5px 12px;border-radius:20px;">
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

{{-- ── Alerte 2FA (non-admin, inline) ──────────────────────────── --}}
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

    {{-- Grille modules --}}
    <div class="pd-section-header">
        <h2 class="pd-section-title">Modules</h2>
        <span class="pd-section-sub">{{ $org->plan === 'community' ? 'Offre Communautaire' : ucfirst($org->plan) }}</span>
    </div>

    @php
    $modules = [
        [
            'icon'  => '📁', 'name' => 'Documents',
            'desc'  => 'Gestion documentaire, versionning, workflows',
            'phase' => 5, 'color' => '#3B9AE1', 'bg' => 'rgba(59,154,225,0.1)',
            'route' => null,
        ],
        [
            'icon'  => '📷', 'name' => 'Photothèque',
            'desc'  => 'Albums, médias NAS, watermark, partage',
            'phase' => null, 'color' => '#2ECC71', 'bg' => 'rgba(46,204,113,0.1)',
            'route' => 'media.albums.index',
        ],
        [
            'icon'  => '💬', 'name' => 'Chat',
            'desc'  => 'Messagerie temps réel, canaux, 1:1',
            'phase' => 9, 'color' => '#E8A838', 'bg' => 'rgba(232,168,56,0.1)',
            'route' => null,
        ],
        [
            'icon'  => '📅', 'name' => 'Agenda',
            'desc'  => 'Événements, récurrence, export iCal',
            'phase' => 8, 'color' => '#9B59B6', 'bg' => 'rgba(155,89,182,0.1)',
            'route' => null,
        ],
        [
            'icon'  => '✅', 'name' => 'Projets',
            'desc'  => 'Kanban, tâches, Gantt, assignation',
            'phase' => 8, 'color' => '#E74C3C', 'bg' => 'rgba(231,76,60,0.1)',
            'route' => null,
        ],
        [
            'icon'  => '🗄', 'name' => 'ERP DataGrid',
            'desc'  => 'Tables no-code, audit trail, export CSV',
            'phase' => 7, 'color' => '#1abc9c', 'bg' => 'rgba(26,188,156,0.1)',
            'route' => null,
        ],
        [
            'icon'  => '📊', 'name' => 'Sondages',
            'desc'  => 'Formulaires, résultats temps réel',
            'phase' => 11, 'color' => '#e67e22', 'bg' => 'rgba(230,126,34,0.1)',
            'route' => null,
        ],
        [
            'icon'  => '📰', 'name' => 'Fil RSS',
            'desc'  => 'Actualités, flux externes, widget',
            'phase' => 10, 'color' => '#95a5a6', 'bg' => 'rgba(149,165,166,0.1)',
            'route' => null,
        ],
    ];
    @endphp

    <div class="pd-module-grid" style="margin-bottom:32px;">
        @foreach($modules as $mod)
        @php $isActive = $mod['route'] !== null; @endphp
        <div class="pd-module-card {{ $isActive ? '' : 'disabled' }}"
             style="--card-accent:{{ $mod['color'] }}; {{ $isActive ? 'cursor:pointer;' : '' }}"
             @if($isActive) onclick="window.location='{{ route($mod['route']) }}'" @endif>
            <div class="pd-module-badge {{ $isActive ? 'active' : 'soon' }}">
                {{ $isActive ? 'Actif' : 'Phase '.$mod['phase'] }}
            </div>
            <div class="pd-module-icon" style="background:{{ $mod['bg'] }};">
                {{ $mod['icon'] }}
            </div>
            <h3>{{ $mod['name'] }}</h3>
            <p>{{ $mod['desc'] }}</p>
        </div>
        @endforeach
    </div>

    {{-- Ligne infos : stats (gauche) + actions (droite) --}}
    <div style="display:grid;grid-template-columns:1fr 300px;gap:20px;align-items:start;">

        {{-- Colonne gauche : stats + activité --}}
        <div>
            {{-- Stats admin --}}
            @if($isAdmin)
            <div class="pd-section-header">
                <h2 class="pd-section-title">Vue d'ensemble</h2>
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
                    <div class="pd-stat-label">Tests CI/CD</div>
                    <div class="pd-stat-value" style="color:var(--pd-success);">221</div>
                    <div class="pd-stat-trend">✓ 3 checks verts</div>
                </div>
                <div class="pd-stat-card">
                    <div class="pd-stat-label">Plan</div>
                    <div class="pd-stat-value" style="font-size:16px;padding-top:4px;">{{ ucfirst($org->plan) }}</div>
                    <div class="pd-stat-trend" style="color:var(--pd-muted);">AGPL-3.0</div>
                </div>
            </div>
            @endif

            {{-- Activité récente --}}
            @if($isAdmin && $recentAudit->count())
            <div class="pd-section-header">
                <h2 class="pd-section-title">Activité récente</h2>
                <span class="pd-section-sub">depuis les logs d'audit</span>
            </div>
            <div class="pd-activity-card" style="margin-bottom:20px;">
                @php
                $actionMap = [
                    'user.login'                   => ['🔑','bg:rgba(59,154,225,0.1)',  'Connexion'],
                    'user.created'                 => ['➕','bg:rgba(46,204,113,0.1)',  'Utilisateur créé'],
                    'user.updated'                 => ['✏️','bg:rgba(59,154,225,0.08)', 'Utilisateur modifié'],
                    'user.deactivated'             => ['🚫','bg:rgba(231,76,60,0.1)',   'Utilisateur désactivé'],
                    'user.password_changed'        => ['🔒','bg:rgba(155,89,182,0.1)',  'Mot de passe changé'],
                    'user.password_reset'          => ['🔄','bg:rgba(232,168,56,0.1)',  'MDP réinitialisé'],
                    'department.created'           => ['🏢','bg:rgba(26,188,156,0.1)',  'Direction/Service créé'],
                    'department.updated'           => ['✏️','bg:rgba(59,154,225,0.08)', 'Structure modifiée'],
                    'user.backup_codes_regenerated'=> ['🛡','bg:rgba(155,89,182,0.1)',  'Codes 2FA régénérés'],
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
                @if($recentAudit->count() > 6)
                <div class="pd-act-more"><a href="{{ route('admin.audit.index') }}">Voir tout l'historique →</a></div>
                @endif
            </div>
            @endif

            {{-- Dernières connexions --}}
            @if($isAdmin && $recentLogins->count())
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

            {{-- Vue utilisateur simple --}}
            @if(!$isAdmin)
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
        </div>

        {{-- Colonne droite : actions rapides --}}
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
                <a href="#" class="pd-qa-btn" style="opacity:.5;cursor:not-allowed;" title="Phase 8">
                    <div class="pd-qa-icon" style="background:rgba(155,89,182,0.12);">📅</div>
                    Créer un événement
                    <span style="font-size:10px;color:var(--pd-muted);">Phase 8</span>
                </a>
                <a href="#" class="pd-qa-btn" style="opacity:.5;cursor:not-allowed;" title="Phase 5">
                    <div class="pd-qa-icon" style="background:rgba(59,154,225,0.12);">📄</div>
                    Nouveau document
                    <span style="font-size:10px;color:var(--pd-muted);">Phase 5</span>
                </a>
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

            {{-- Plan & quota --}}
            <div class="pd-quick-card">
                <div class="pd-quick-header">Plan & utilisation</div>
                <div style="padding:14px 16px;">

                    {{-- Utilisateurs --}}
                    <div style="margin-bottom:14px;">
                        <div style="display:flex;justify-content:space-between;align-items:baseline;margin-bottom:5px;">
                            <span style="font-size:12px;color:var(--pd-muted);font-weight:500;">Utilisateurs</span>
                            <span style="font-size:12px;color:var(--pd-muted);">{{ $activeUsers }} / {{ $org->max_users }}</span>
                        </div>
                        <div style="background:var(--pd-bg);border-radius:4px;height:6px;overflow:hidden;">
                            <div style="height:100%;border-radius:4px;background:linear-gradient(90deg,var(--pd-accent),var(--pd-navy));
                                        width:{{ min(100, round($activeUsers / max(1,$org->max_users) * 100)) }}%;
                                        transition:width .6s ease;"></div>
                        </div>
                    </div>

                    {{-- Stockage photothèque --}}
                    <div style="margin-bottom:14px;">
                        <div style="display:flex;justify-content:space-between;align-items:baseline;margin-bottom:5px;">
                            <span style="font-size:12px;color:var(--pd-muted);font-weight:500;">Stockage</span>
                            <span style="font-size:12px;color:var(--pd-muted);">
                                {{ $storageUsedMb }} Mo / {{ round($storageQuotaMb / 1024, 1) }} Go
                            </span>
                        </div>
                        <div style="background:var(--pd-bg);border-radius:4px;height:6px;overflow:hidden;">
                            <div style="height:100%;border-radius:4px;
                                        background:linear-gradient(90deg,
                                            {{ $storageUsedPct > 80 ? 'var(--pd-danger)' : ($storageUsedPct > 60 ? 'var(--pd-warning)' : 'var(--pd-success)') }},
                                            {{ $storageUsedPct > 80 ? '#c0392b' : ($storageUsedPct > 60 ? '#d68910' : '#27ae60') }});
                                        width:{{ $storageUsedPct }}%;transition:width .6s ease;"></div>
                        </div>
                    </div>

                    {{-- Médias --}}
                    <div style="display:flex;justify-content:space-between;padding-top:6px;border-top:1px solid var(--pd-border);">
                        <span style="font-size:12px;color:var(--pd-muted);">📷 {{ number_format($mediaCount) }} médias</span>
                        <span style="font-size:12px;font-weight:600;color:var(--pd-text);">{{ ucfirst($org->plan) }}</span>
                    </div>
                </div>
            </div>
        </div>

    </div>{{-- /grid --}}

</div>{{-- /padding --}}
@endsection
