<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Organigramme — {{ app(App\Services\TenantManager::class)->current()?->name }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Segoe UI', system-ui, sans-serif;
            background: #fff; color: #1a2332;
            padding: 2rem 2.5rem; font-size: 13px;
        }
        .header {
            display: flex; align-items: flex-end; justify-content: space-between;
            padding-bottom: 1rem; border-bottom: 2px solid #1E3A5F; margin-bottom: 2rem;
        }
        .org-name { font-size: 1.3rem; font-weight: 700; color: #1E3A5F; letter-spacing: -0.02em; }
        .subtitle { font-size: 0.75rem; color: #9ca3af; margin-top: 2px; }
        .btn { display: inline-flex; align-items: center; gap: 5px; padding: 6px 14px; border-radius: 5px; font-size: 0.78rem; font-weight: 600; cursor: pointer; text-decoration: none; border: none; }
        .btn-primary { background: #1E3A5F; color: white; }
        .btn-outline { background: white; color: #1E3A5F; border: 1px solid #d1d5db; }

        .tree-wrap { overflow-x: auto; padding-bottom: 2rem; }
        .tree-container {
            display: flex; flex-direction: column; align-items: center;
            min-width: max-content; position: relative; padding: 0 2rem;
        }

        /* ── Nœuds ── */
        .node { border-radius: 8px; padding: 8px 16px; text-align: center; position: relative; z-index: 1; }
        .node-org {
            background: #1E3A5F; color: white; border-radius: 10px; padding: 10px 28px;
            font-weight: 700; font-size: 0.95rem; min-width: 220px;
            box-shadow: 0 4px 14px rgba(30,58,95,0.3);
        }
        .node-org .n-sub { font-size: 0.62rem; opacity: 0.55; margin-top: 3px; }
        .node-dgs {
            background: #0f3460; color: white; min-width: 200px;
            box-shadow: 0 2px 10px rgba(15,52,96,0.3);
        }
        .node-dir {
            background: #1e40af; color: white; min-width: 150px; max-width: 180px;
            box-shadow: 0 2px 8px rgba(30,64,175,0.2);
        }
        .node-svc {
            background: #eff6ff; border: 1.5px solid #bfdbfe;
            min-width: 120px; max-width: 155px;
        }
        .n-title     { font-weight: 700; font-size: 0.8rem; }
        .n-title-svc { font-weight: 600; font-size: 0.74rem; color: #1e40af; }
        .n-resp      { font-size: 0.62rem; opacity: 0.72; margin-top: 2px; }
        .n-resp-svc  { font-size: 0.6rem; color: #6b7280; margin-top: 2px; }
        .n-count     { font-size: 0.58rem; opacity: 0.5; margin-top: 1px; }
        .n-count-svc { font-size: 0.58rem; color: #9ca3af; margin-top: 1px; }

        /* ── Membres ── */
        .members-list { display: flex; flex-direction: column; align-items: center; gap: 2px; margin-top: 5px; }
        .chip {
            display: inline-flex; align-items: center; gap: 3px;
            padding: 2px 7px 2px 3px; border-radius: 99px; font-size: 0.64rem;
            border: 1px solid #e5e7eb; background: white; color: #374151; white-space: nowrap;
        }
        .chip.mgr { border-color: #fcd34d; background: #fffbeb; color: #92400e; font-weight: 600; }
        .chip-av {
            width: 14px; height: 14px; border-radius: 50%; background: #1E3A5F; color: white;
            font-size: 0.55rem; font-weight: 700;
            display: flex; align-items: center; justify-content: center; flex-shrink: 0;
        }
        .chip.mgr .chip-av { background: #d97706; }

        /* ── Layout ── */
        .level-row { display: flex; justify-content: center; align-items: flex-start; }
        .level-col { display: flex; flex-direction: column; align-items: center; padding: 0 0.75rem; }

        /* Rangée DGS avec ses services à gauche et droite */
        .dgs-row {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0;
        }
        .dgs-services-left  { display: flex; align-items: center; gap: 0; }
        .dgs-services-right { display: flex; align-items: center; gap: 0; }
        .dgs-center { display: flex; flex-direction: column; align-items: center; }

        /* Ligne horizontale reliant services DGS à la DGS */
        .h-connector { height: 2px; background: #64748b; min-width: 30px; }

        /* Légende */
        .legend {
            margin-top: 2rem; padding-top: 1rem; border-top: 1px solid #f3f4f6;
            display: flex; align-items: center; gap: 1.5rem; font-size: 0.68rem; color: #9ca3af;
        }

        @media print {
            body { padding: 1cm; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>

@php
    $tenant = app(App\Services\TenantManager::class)->current();
    $totalMembers = \App\Models\Tenant\User::on('tenant')->where('status','active')->count();
    $dgsServices  = $dgs ? $dgs->children->filter(fn($c) => $c->type === 'service') : collect();
    $dgsServicesLeft  = $dgsServices->take((int) ceil($dgsServices->count() / 2));
    $dgsServicesRight = $dgsServices->skip((int) ceil($dgsServices->count() / 2));
@endphp

<div class="header">
    <div>
        <div class="org-name">{{ $tenant?->name ?? 'Organisation' }}</div>
        <div class="subtitle">Organigramme · {{ now()->format('d/m/Y') }}</div>
    </div>
    <div class="no-print" style="display:flex;gap:8px">
        <a href="{{ route('admin.departments.index') }}" class="btn btn-outline">← Retour</a>
        <button onclick="window.print()" class="btn btn-primary">🖨 Imprimer</button>
    </div>
</div>

<div class="tree-wrap">
<div class="tree-container" id="tree">

    <svg id="connectors" style="position:absolute;top:0;left:0;width:100%;height:100%;pointer-events:none;overflow:visible;z-index:0"></svg>

    {{-- Niveau 1 : Organisation --}}
    <div class="node node-org" id="node-org">
        {{ $tenant?->name ?? 'Organisation' }}
        <div class="n-sub">{{ $totalMembers }} agent(s)</div>
    </div>

    <div style="height:44px"></div>

    @if(isset($dgs) && $dgs)

    {{-- Niveau 2 : Services DGS ←→ DGS ←→ Services DGS --}}
    <div class="dgs-row">

        {{-- Services à gauche --}}
        @if($dgsServicesLeft->count())
        <div class="dgs-services-left">
            @foreach($dgsServicesLeft as $service)
            <div class="level-col">
                <div class="node node-svc" id="svc-{{ $service->id }}">
                    <div class="n-title-svc">{{ $service->name }}</div>
                    @if($service->managers->count())
                        <div class="n-resp-svc">{{ $service->managers->pluck('name')->join(', ') }}</div>
                    @endif
                    <div class="n-count-svc">{{ $service->members->count() }}p.</div>
                </div>
                @if($service->members->count())
                <div class="members-list">
                    @foreach($service->members as $m)
                    <span class="chip {{ $m->pivot->is_manager ? 'mgr' : '' }}">
                        <span class="chip-av">{{ strtoupper(substr($m->name,0,1)) }}</span>
                        {{ $m->name }}@if($m->pivot->is_manager) ★@endif
                    </span>
                    @endforeach
                </div>
                @endif
            </div>
            @endforeach
            <div class="h-connector" id="hconn-left"></div>
        </div>
        @endif

        {{-- DGS au centre --}}
        <div class="dgs-center">
            <div class="node node-dgs" id="node-dgs">
                <div class="n-title">{{ $dgs->name }}</div>
                @if($dgs->managers->count())
                    <div class="n-resp">{{ $dgs->managers->pluck('name')->join(', ') }}</div>
                @endif
                <div class="n-count">{{ $dgs->members_count }}p.</div>
            </div>
        </div>

        {{-- Services à droite --}}
        @if($dgsServicesRight->count())
        <div class="dgs-services-right">
            <div class="h-connector" id="hconn-right"></div>
            @foreach($dgsServicesRight as $service)
            <div class="level-col">
                <div class="node node-svc" id="svc-{{ $service->id }}">
                    <div class="n-title-svc">{{ $service->name }}</div>
                    @if($service->managers->count())
                        <div class="n-resp-svc">{{ $service->managers->pluck('name')->join(', ') }}</div>
                    @endif
                    <div class="n-count-svc">{{ $service->members->count() }}p.</div>
                </div>
                @if($service->members->count())
                <div class="members-list">
                    @foreach($service->members as $m)
                    <span class="chip {{ $m->pivot->is_manager ? 'mgr' : '' }}">
                        <span class="chip-av">{{ strtoupper(substr($m->name,0,1)) }}</span>
                        {{ $m->name }}@if($m->pivot->is_manager) ★@endif
                    </span>
                    @endforeach
                </div>
                @endif
            </div>
            @endforeach
        </div>
        @endif

    </div>

    <div style="height:44px"></div>

    @endif

    {{-- Niveau 3 : Sous-directions + Directions racines --}}
    @php
        $allDirs = $subDirections->merge($directions);
    @endphp
    @if($allDirs->count())
    <div class="level-row" id="dir-row">
        @foreach($allDirs as $direction)
        <div class="level-col">
            <div class="node node-dir" id="dir-{{ $direction->id }}">
                <div class="n-title">{{ $direction->name }}</div>
                @if($direction->managers->count())
                    <div class="n-resp">{{ $direction->managers->pluck('name')->join(', ') }}</div>
                @endif
                <div class="n-count">{{ $direction->members_count }}p. · {{ $direction->children->count() }} svc</div>
            </div>

            @php $directMembers = $direction->members->filter(fn($m) => !$m->pivot->is_manager); @endphp
            @if($directMembers->count())
            <div class="members-list">
                @foreach($directMembers as $m)
                <span class="chip">
                    <span class="chip-av">{{ strtoupper(substr($m->name,0,1)) }}</span>
                    {{ $m->name }}
                </span>
                @endforeach
            </div>
            @endif

            @if($direction->children->count())
            <div style="height:28px"></div>
            <div class="level-row" id="svc-row-{{ $direction->id }}">
                @foreach($direction->children as $service)
                <div class="level-col">
                    <div class="node node-svc" id="svc-{{ $service->id }}">
                        <div class="n-title-svc">{{ $service->name }}</div>
                        @if($service->managers->count())
                            <div class="n-resp-svc">{{ $service->managers->pluck('name')->join(', ') }}</div>
                        @endif
                        <div class="n-count-svc">{{ $service->members->count() }}p.</div>
                    </div>
                    @if($service->members->count())
                    <div class="members-list">
                        @foreach($service->members as $m)
                        <span class="chip {{ $m->pivot->is_manager ? 'mgr' : '' }}">
                            <span class="chip-av">{{ strtoupper(substr($m->name,0,1)) }}</span>
                            {{ $m->name }}@if($m->pivot->is_manager) ★@endif
                        </span>
                        @endforeach
                    </div>
                    @endif
                </div>
                @endforeach
            </div>
            @endif
        </div>
        @endforeach
    </div>
    @endif

</div>
</div>

<div class="legend no-print">
    <span style="display:flex;align-items:center;gap:5px">
        <span class="chip mgr"><span class="chip-av" style="background:#d97706">A</span> Nom ★</span> Responsable
    </span>
    <span style="display:flex;align-items:center;gap:5px">
        <span class="chip"><span class="chip-av">A</span> Nom</span> Agent
    </span>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const svg   = document.getElementById('connectors');
    const tree  = document.getElementById('tree');
    const tRect = tree.getBoundingClientRect();

    function pt(el) {
        const r = el.getBoundingClientRect();
        return {
            cx:     r.left - tRect.left + r.width  / 2,
            cy:     r.top  - tRect.top  + r.height / 2,
            top:    r.top  - tRect.top,
            bottom: r.bottom - tRect.top,
            left:   r.left - tRect.left,
            right:  r.right - tRect.left,
        };
    }

    function curve(x1, y1, x2, y2, color, width) {
        const p = document.createElementNS('http://www.w3.org/2000/svg', 'path');
        const my = (y1 + y2) / 2;
        p.setAttribute('d', `M${x1},${y1} C${x1},${my} ${x2},${my} ${x2},${y2}`);
        p.setAttribute('stroke', color || '#94a3b8');
        p.setAttribute('stroke-width', width || '1.5');
        p.setAttribute('fill', 'none');
        svg.appendChild(p);
    }

    function hline(x1, y, x2, color) {
        const l = document.createElementNS('http://www.w3.org/2000/svg', 'line');
        l.setAttribute('x1', x1); l.setAttribute('y1', y);
        l.setAttribute('x2', x2); l.setAttribute('y2', y);
        l.setAttribute('stroke', color || '#64748b');
        l.setAttribute('stroke-width', '1.5');
        svg.appendChild(l);
    }

    const org = document.getElementById('node-org');
    const dgs = document.getElementById('node-dgs');

    // Org → DGS
    if (org && dgs) {
        const o = pt(org), d = pt(dgs);
        curve(o.cx, o.bottom, d.cx, d.top, '#475569', 2);
    }

    // Services DGS ←→ DGS : ligne horizontale au milieu des nœuds
    const dgsNode = dgs;
    if (dgsNode) {
        const dp = pt(dgsNode);
        const dgy = dp.cy; // ligne horizontale au centre vertical de la DGS

        // Services gauche
        document.querySelectorAll('.dgs-services-left .node-svc').forEach(s => {
            const sp = pt(s);
            hline(sp.right, dgy, dp.left, '#64748b');
            // Connecteur vertical du service vers la ligne horizontale
            const p = document.createElementNS('http://www.w3.org/2000/svg', 'line');
            p.setAttribute('x1', sp.cx); p.setAttribute('y1', sp.bottom);
            p.setAttribute('x2', sp.cx); p.setAttribute('y2', dgy);
            p.setAttribute('stroke', '#94a3b8'); p.setAttribute('stroke-width', '1.5');
            svg.appendChild(p);
            // Point de jonction
            const c = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
            c.setAttribute('cx', sp.cx); c.setAttribute('cy', dgy);
            c.setAttribute('r', '3'); c.setAttribute('fill', '#94a3b8');
            svg.appendChild(c);
        });

        // Services droite
        document.querySelectorAll('.dgs-services-right .node-svc').forEach(s => {
            const sp = pt(s);
            hline(dp.right, dgy, sp.left, '#64748b');
            const p = document.createElementNS('http://www.w3.org/2000/svg', 'line');
            p.setAttribute('x1', sp.cx); p.setAttribute('y1', sp.bottom);
            p.setAttribute('x2', sp.cx); p.setAttribute('y2', dgy);
            p.setAttribute('stroke', '#94a3b8'); p.setAttribute('stroke-width', '1.5');
            svg.appendChild(p);
            const c = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
            c.setAttribute('cx', sp.cx); c.setAttribute('cy', dgy);
            c.setAttribute('r', '3'); c.setAttribute('fill', '#94a3b8');
            svg.appendChild(c);
        });
    }

    // DGS → Directions
    const parent = dgs || org;
    if (parent) {
        const pp = pt(parent);
        document.querySelectorAll('#dir-row > .level-col > .node-dir').forEach(d => {
            const dp = pt(d);
            curve(pp.cx, pp.bottom, dp.cx, dp.top, '#3b82f6', 1.5);
        });
    }

    // Org → Directions (sans DGS)
    if (!dgs && org) {
        const op = pt(org);
        document.querySelectorAll('#dir-row > .level-col > .node-dir').forEach(d => {
            const dp = pt(d);
            curve(op.cx, op.bottom, dp.cx, dp.top, '#94a3b8', 1.5);
        });
    }

    // Directions → Services
    document.querySelectorAll('[id^="dir-"]').forEach(dir => {
        const id  = dir.id.replace('dir-', '');
        const row = document.getElementById('svc-row-' + id);
        if (!row) return;
        const dp = pt(dir);
        row.querySelectorAll('.node-svc').forEach(s => {
            const sp = pt(s);
            curve(dp.cx, dp.bottom, sp.cx, sp.top, '#bfdbfe', 1.5);
        });
    });
});
</script>

</body>
</html>
