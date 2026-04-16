@extends('layouts.app')

@section('content')
@php
    $adminNav = [
        [
            'group' => 'Organisation',
            'icon'  => '<svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
            'items' => [
                [
                    'label' => 'Utilisateurs',
                    'route' => 'admin.users.index',
                    'match' => 'admin.users.*',
                    'icon'  => '<svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
                ],
                [
                    'label' => 'Hiérarchie',
                    'route' => 'admin.departments.index',
                    'match' => 'admin.departments.*',
                    'icon'  => '<svg viewBox="0 0 24 24"><circle cx="12" cy="5" r="2"/><line x1="12" y1="7" x2="12" y2="11"/><line x1="12" y1="11" x2="5" y2="15"/><line x1="12" y1="11" x2="19" y2="15"/><circle cx="5" cy="17" r="2"/><circle cx="19" cy="17" r="2"/></svg>',
                ],
            ],
        ],
        [
            'group' => 'Apparence',
            'icon'  => '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M8 14s1.5 2 4 2 4-2 4-2"/><line x1="9" y1="9" x2="9.01" y2="9"/><line x1="15" y1="9" x2="15.01" y2="9"/></svg>',
            'items' => [
                [
                    'label' => 'Personnalisation',
                    'route' => 'admin.settings.branding',
                    'match' => 'admin.settings.branding*',
                    'icon'  => '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M8 14s1.5 2 4 2 4-2 4-2"/><line x1="9" y1="9" x2="9.01" y2="9"/><line x1="15" y1="9" x2="15.01" y2="9"/></svg>',
                ],
            ],
        ],
        [
            'group' => 'Photothèque',
            'icon'  => '<svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>',
            'items' => [
                [
                    'label' => 'NAS',
                    'route' => 'admin.settings.nas',
                    'match' => 'admin.settings.nas*',
                    'icon'  => '<svg viewBox="0 0 24 24"><rect x="2" y="2" width="20" height="8" rx="2"/><rect x="2" y="14" width="20" height="8" rx="2"/><line x1="6" y1="6" x2="6.01" y2="6"/><line x1="6" y1="18" x2="6.01" y2="18"/></svg>',
                ],
                [
                    'label' => 'Paramètres',
                    'route' => 'admin.settings.media',
                    'match' => 'admin.settings.media*',
                    'icon'  => '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14"/><path d="M4.93 4.93a10 10 0 0 0 0 14.14"/></svg>',
                ],
            ],
        ],
        [
            'group'  => 'GED',
            'module' => 'ged',
            'icon'   => '<svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>',
            'items'  => [
                [
                    'label' => 'NAS',
                    'route' => 'admin.settings.ged',
                    'match' => 'admin.settings.ged*',
                    'icon'  => '<svg viewBox="0 0 24 24"><rect x="2" y="2" width="20" height="8" rx="2"/><rect x="2" y="14" width="20" height="8" rx="2"/><line x1="6" y1="6" x2="6.01" y2="6"/><line x1="6" y1="18" x2="6.01" y2="18"/></svg>',
                ],
                [
                    'label' => 'Gouvernance',
                    'route' => 'admin.ged.index',
                    'match' => 'admin.ged.*',
                    'icon'  => '<svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>',
                ],
                [
                    'label' => 'Purge',
                    'route' => 'admin.purge.index',
                    'match' => 'admin.purge.*',
                    'icon'  => '<svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>',
                ],
                [
                    'label' => 'Intégrité',
                    'route' => 'ged.integrity.index',
                    'match' => 'ged.integrity.*',
                    'icon'  => '<svg viewBox="0 0 24 24"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>',
                ],
                [
                    'label' => 'Collabora',
                    'route' => 'admin.settings.collabora',
                    'match' => 'admin.settings.collabora*',
                    'icon'  => '<svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>',
                ],
            ],
        ],
        [
            'group' => 'Connexions',
            'icon'  => '<svg viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>',
            'items' => [
                [
                    'label' => 'LDAP',
                    'route' => 'admin.settings.ldap',
                    'match' => 'admin.settings.ldap*',
                    'icon'  => '<svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>',
                ],
                [
                    'label' => 'SMTP',
                    'route' => 'admin.settings.smtp',
                    'match' => 'admin.settings.smtp*',
                    'icon'  => '<svg viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>',
                ],
                [
                    'label' => 'Visioconférence',
                    'route' => 'admin.settings.visio',
                    'match' => 'admin.settings.visio*',
                    'icon'  => '<svg viewBox="0 0 24 24"><polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2"/></svg>',
                ],
            ],
        ],
        [
            'group'  => 'Projets',
            'module' => 'projects',
            'icon'   => '<svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>',
            'items'  => [
                [
                    'label' => 'Réaffectation',
                    'route' => 'admin.projects.reassign.index',
                    'match' => 'admin.projects.*',
                    'icon'  => '<svg viewBox="0 0 24 24"><path d="M17 1l4 4-4 4"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><path d="M7 23l-4-4 4-4"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg>',
                ],
            ],
        ],
        [
            'group' => 'Sécurité',
            'icon'  => '<svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>',
            'items' => [
                [
                    'label' => 'Sécurité',
                    'route' => 'admin.settings.security',
                    'match' => 'admin.settings.security*',
                    'icon'  => '<svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>',
                ],
                [
                    'label' => 'Audit',
                    'route' => 'admin.audit.index',
                    'match' => 'admin.audit.*',
                    'icon'  => '<svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>',
                ],
                [
                    'label' => 'Sauvegarde',
                    'route' => 'admin.settings.backup',
                    'match' => 'admin.settings.backup*',
                    'icon'  => '<svg viewBox="0 0 24 24"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg>',
                ],
            ],
        ],
    ];

    // Entrée Démo — uniquement pour l'organisation "demo"
    if (app(\App\Services\TenantManager::class)->current()?->slug === 'demo') {
        $adminNav[] = [
            'group' => 'Démo',
            'icon'  => '<svg viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>',
            'items' => [
                [
                    'label' => 'Données démo',
                    'route' => 'admin.demo.index',
                    'match' => 'admin.demo.*',
                    'icon'  => '<svg viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>',
                ],
            ],
        ];
    }

    // Déterminer le groupe actif et le label de la page active
    $activeLabel = null;
    $activeGroup = null;
    foreach ($adminNav as $group) {
        foreach ($group['items'] as $item) {
            if (request()->routeIs($item['match'])) {
                $activeLabel = ['label' => $item['label'], 'group' => $group['group']];
                $activeGroup = $group['group'];
                break 2;
            }
        }
    }
@endphp

<div style="display:flex;min-height:calc(100vh - var(--pd-topbar-h));">

    {{-- ── Sidebar ─────────────────────────────────────────── --}}
    <aside style="width:220px;flex-shrink:0;
                  background:var(--pd-surface);
                  border-right:1px solid var(--pd-border);
                  position:sticky;top:var(--pd-topbar-h);
                  height:calc(100vh - var(--pd-topbar-h));
                  overflow-y:auto;
                  display:flex;flex-direction:column;
                  scrollbar-width:thin;
                  scrollbar-color:var(--pd-border) transparent;">

        {{-- En-tête sidebar --}}
        <div style="padding:20px 16px 12px;border-bottom:1px solid var(--pd-border);">
            <a href="{{ route('dashboard') }}"
               style="display:inline-flex;align-items:center;gap:6px;
                      font-size:12px;font-weight:500;color:var(--pd-muted);
                      text-decoration:none;transition:color 0.15s;"
               onmouseover="this.style.color='var(--pd-text)'"
               onmouseout="this.style.color='var(--pd-muted)'">
                <svg style="width:13px;height:13px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;" viewBox="0 0 24 24">
                    <polyline points="15 18 9 12 15 6"/>
                </svg>
                Tableau de bord
            </a>
            <div style="margin-top:10px;font-size:11px;font-weight:700;
                        letter-spacing:0.08em;text-transform:uppercase;
                        color:var(--pd-navy);opacity:0.5;">
                Administration
            </div>
        </div>

        {{-- Accordéons de navigation --}}
        <nav style="padding:6px 0;flex:1;" x-data="adminNav()">
            @foreach($adminNav as $groupIndex => $group)
            @php
                $groupModule = $group['module'] ?? null;
                if ($groupModule !== null) {
                    $moduleKey = \App\Enums\ModuleKey::tryFrom($groupModule);
                    if ($moduleKey === null || ! (app(\App\Services\TenantManager::class)->current()?->hasModule($moduleKey) ?? false)) {
                        continue;
                    }
                }
                $groupHasActive = collect($group['items'])->contains(fn($item) => request()->routeIs($item['match']));
            @endphp

            <div style="border-bottom:0.5px solid var(--pd-border);">

                {{-- Bouton accordéon --}}
                <button
                    @click="toggle('{{ $group['group'] }}')"
                    style="width:100%;display:flex;align-items:center;justify-content:space-between;
                           padding:10px 14px;background:transparent;border:none;cursor:pointer;
                           text-align:left;transition:background .15s;"
                    :style="isOpen('{{ $group['group'] }}') ? 'background:rgba(30,58,95,0.05)' : ''"
                    onmouseover="this.style.background='rgba(30,58,95,0.04)'"
                    onmouseout="this.style.background=isOpen('{{ $group['group'] }}') ? 'rgba(30,58,95,0.05)' : 'transparent'">

                    <div style="display:flex;align-items:center;gap:8px;">
                        <span style="display:flex;flex-shrink:0;{{ $groupHasActive ? 'color:var(--pd-navy)' : 'color:var(--pd-muted)' }}">
                            <svg style="width:13px;height:13px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round;"
                                 viewBox="0 0 24 24">{!! $group['icon'] !!}</svg>
                        </span>
                        <span style="font-size:12px;font-weight:{{ $groupHasActive ? '700' : '600' }};
                                     letter-spacing:0.03em;
                                     color:{{ $groupHasActive ? 'var(--pd-navy)' : 'var(--pd-muted)' }};">
                            {{ $group['group'] }}
                        </span>
                        @if($groupHasActive)
                        <span style="width:5px;height:5px;border-radius:50%;background:var(--pd-accent);flex-shrink:0;"></span>
                        @endif
                    </div>

                </button>

                {{-- Items accordéon --}}
                <div x-show="isOpen('{{ $group['group'] }}')"
                     x-collapse
                     style="padding-bottom:4px;">
                    @foreach($group['items'] as $item)
                    @php $isActive = request()->routeIs($item['match']); @endphp
                    <a href="{{ route($item['route']) }}"
                       style="display:flex;align-items:center;gap:8px;
                              padding:7px 14px 7px 34px;
                              font-size:12.5px;font-weight:{{ $isActive ? '600' : '450' }};
                              color:{{ $isActive ? 'var(--pd-navy)' : 'var(--pd-muted)' }};
                              background:{{ $isActive ? 'rgba(30,58,95,0.07)' : 'transparent' }};
                              text-decoration:none;
                              border-radius:6px;margin:1px 8px;
                              position:relative;
                              transition:background .15s,color .15s;"
                       onmouseover="if(!{{ $isActive ? 'true' : 'false' }}){this.style.background='rgba(30,58,95,0.04)';this.style.color='var(--pd-text)'}"
                       onmouseout="if(!{{ $isActive ? 'true' : 'false' }}){this.style.background='transparent';this.style.color='var(--pd-muted)'}">
                        @if($isActive)
                        <span style="position:absolute;left:8px;width:2.5px;height:16px;
                                     background:var(--pd-accent);border-radius:2px;"></span>
                        @endif
                        <span style="display:flex;flex-shrink:0;">
                            <svg style="width:13px;height:13px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round;"
                                 viewBox="0 0 24 24">{!! $item['icon'] !!}</svg>
                        </span>
                        {{ $item['label'] }}
                    </a>
                    @endforeach
                </div>

            </div>
            @endforeach
        </nav>

    </aside>

    {{-- ── Contenu principal ───────────────────────────────── --}}
    <main style="flex:1;min-width:0;padding:28px 32px;overflow-x:hidden;">

        {{-- Bandeau mobile uniquement --}}
        <div class="pd-admin-mobile-warning" style="display:none;align-items:center;gap:10px;background:rgba(243,156,18,0.12);border:1.5px solid rgba(243,156,18,0.4);border-radius:10px;padding:10px 14px;margin-bottom:16px;font-size:12.5px;color:var(--pd-text);">
            <span style="font-size:1.2rem;">📱</span>
            <span>L'interface d'administration est optimisée pour tablette et ordinateur. Certaines fonctionnalités peuvent être difficiles à utiliser sur un téléphone.</span>
        </div>

        {{-- Breadcrumb --}}
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:24px;">
            <span style="font-size:12px;color:var(--pd-muted);">Administration</span>
            @if($activeLabel)
            <svg style="width:12px;height:12px;fill:none;stroke:var(--pd-muted);stroke-width:2;" viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
            <span style="font-size:12px;color:var(--pd-muted);">{{ $activeLabel['group'] }}</span>
            <svg style="width:12px;height:12px;fill:none;stroke:var(--pd-muted);stroke-width:2;" viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
            <span style="font-size:12px;font-weight:600;color:var(--pd-text);">{{ $activeLabel['label'] }}</span>
            @endif
        </div>

        @yield('admin-content')

    </main>

</div>

@push('scripts')
<script>
function adminNav() {
    // Groupes ouverts par défaut : celui contenant la page active + tous si aucun actif
    const activeGroup = @json($activeGroup);
    const allGroups = @json(array_column($adminNav, 'group'));

    // On stocke l'état ouvert/fermé dans sessionStorage pour persistance pendant la session
    const storageKey = 'pd_admin_nav_open';
    let saved = {};
    try { saved = JSON.parse(sessionStorage.getItem(storageKey) || '{}'); } catch {}

    // État initial : si rien de sauvegardé, ouvrir le groupe actif (ou tous si aucun actif)
    const initial = {};
    allGroups.forEach(g => {
        if (g in saved) {
            initial[g] = saved[g];
        } else {
            initial[g] = activeGroup ? (g === activeGroup) : true;
        }
    });
    // Toujours garder le groupe actif ouvert
    if (activeGroup) initial[activeGroup] = true;

    return {
        open: initial,

        isOpen(group) {
            return this.open[group] ?? false;
        },

        toggle(group) {
            // Le groupe actif ne peut pas être fermé
            if (group === activeGroup && this.open[group]) return;
            this.open[group] = !this.open[group];
            try { sessionStorage.setItem(storageKey, JSON.stringify(this.open)); } catch {}
        },
    };
}
</script>
@endpush

@endsection
