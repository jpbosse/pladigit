<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Organigramme — {{ app(App\Services\TenantManager::class)->current()?->name }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', system-ui, sans-serif; background: #f8fafc; color: #1a2332; padding: 2rem 2.5rem; font-size: 13px; }
        .header { display: flex; align-items: flex-end; justify-content: space-between; padding-bottom: 1rem; border-bottom: 2px solid #1E3A5F; margin-bottom: 1.5rem; }
        .org-name { font-size: 1.3rem; font-weight: 700; color: #1E3A5F; }
        .subtitle  { font-size: 0.75rem; color: #9ca3af; margin-top: 2px; }
        .actions   { display: flex; gap: 8px; align-items: center; }
        .btn { display: inline-flex; align-items: center; gap: 5px; padding: 6px 14px; border-radius: 6px; font-size: 0.78rem; font-weight: 600; cursor: pointer; text-decoration: none; border: none; transition: opacity .15s; }
        .btn:hover { opacity: .85; }
        .btn-primary { background: #1E3A5F; color: white; }
        .btn-outline  { background: white; color: #1E3A5F; border: 1px solid #d1d5db; }
        .btn-sm { padding: 4px 10px; font-size: 0.72rem; }
        .search-bar { display: flex; align-items: center; gap: 8px; margin-bottom: 1.2rem; }
        .search-input { padding: 7px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 0.82rem; width: 280px; outline: none; }
        .search-input:focus { border-color: #1E3A5F; }
        .search-count { font-size: 0.72rem; color: #9ca3af; }
        .legend { display: flex; gap: 12px; margin-bottom: 1.5rem; flex-wrap: wrap; }
        .legend-item { display: flex; align-items: center; gap: 5px; font-size: 0.72rem; color: #6b7280; }
        .legend-dot { width: 10px; height: 10px; border-radius: 3px; flex-shrink: 0; }
        .stats-bar { display: flex; gap: 16px; margin-bottom: 1.2rem; font-size: 0.75rem; color: #6b7280; }
        .stat strong { color: #1E3A5F; font-size: 0.9rem; }

        .tree { list-style: none; }
        .tree-root { padding: 0; }
        .tree-item { position: relative; }
        .tree-item > .tree-children { margin-left: 20px; border-left: 2px solid #e2e8f0; }
        .node-row { display: flex; align-items: center; padding: 3px 0; }
        .node-row::before { content: ''; display: block; width: 16px; height: 2px; background: #e2e8f0; flex-shrink: 0; }
        .tree-root > .tree-item > .node-row::before { display: none; }

        .node-card { display: inline-flex; align-items: center; gap: 8px; padding: 6px 12px; border-radius: 7px; cursor: pointer; transition: all .15s; border: 1.5px solid transparent; user-select: none; }
        .node-card:hover { filter: brightness(0.95); }
        .node-card.highlighted { outline: 3px solid #fbbf24; outline-offset: 2px; }
        .node-card.dimmed { opacity: 0.25; }
        .node-card.no-children .toggle-icon { visibility: hidden; }
        .node-card.transversal { border-style: dashed !important; }

        .node-card[data-label="président"] { background: #1E3A5F; color: white; border-color: #1E3A5F; }
        .node-card[data-label="dgs"]       { background: #0f3460; color: white; border-color: #0f3460; }
        .node-card[data-label="pôle"]      { background: #6d28d9; color: white; border-color: #5b21b6; }
        .node-card[data-label="direction"] { background: #1e40af; color: white; border-color: #1e3a8a; }
        .node-card[data-label="service"]   { background: #eff6ff; color: #1e3a8a; border-color: #bfdbfe; }
        .node-card[data-label="bureau"],
        .node-card[data-label="cellule"]   { background: #f0f9ff; color: #0c4a6e; border-color: #bae6fd; }

        .toggle-icon { font-size: 0.65rem; width: 16px; height: 16px; display: flex; align-items: center; justify-content: center; border-radius: 3px; background: rgba(255,255,255,0.2); flex-shrink: 0; transition: transform .2s; }
        .node-card[data-label="service"] .toggle-icon,
        .node-card[data-label="bureau"]  .toggle-icon { background: rgba(30,64,175,0.15); }
        .toggle-icon.open { transform: rotate(90deg); }

        .node-label { font-size: 0.68rem; font-weight: 600; opacity: 0.7; text-transform: uppercase; letter-spacing: 0.04em; }
        .node-name  { font-size: 0.84rem; font-weight: 700; }
        .node-meta  { font-size: 0.63rem; opacity: 0.65; margin-top: 1px; }
        .badge-transversal { font-size: 0.58rem; padding: 1px 5px; border-radius: 99px; background: rgba(255,255,255,0.2); border: 1px solid rgba(255,255,255,0.35); margin-left: 4px; }

        .node-members { display: none; margin-left: 52px; margin-bottom: 4px; flex-wrap: wrap; gap: 4px; }
        .node-members.visible { display: flex; }
        .chip { display: inline-flex; align-items: center; gap: 3px; padding: 2px 7px 2px 3px; border-radius: 99px; font-size: 0.62rem; border: 1px solid #e5e7eb; background: white; color: #374151; }
        .chip.mgr { border-color: #fcd34d; background: #fffbeb; color: #92400e; font-weight: 600; }
        .chip-av { width: 14px; height: 14px; border-radius: 50%; background: #1E3A5F; color: white; font-size: 0.5rem; font-weight: 700; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .chip.mgr .chip-av { background: #d97706; }

        .tree-children { display: none; }
        .tree-children.open { display: block; }

        @media print {
            body { background: white; padding: 1cm; }
            .no-print { display: none !important; }
            .tree-children { display: block !important; }
            .toggle-icon { display: none; }
        }
    </style>
</head>
<body>
@php
    $tenant       = app(App\Services\TenantManager::class)->current();
    $totalMembers = \App\Models\Tenant\User::on('tenant')->where('status','active')->count();
    $totalDepts   = \App\Models\Tenant\Department::on('tenant')->count();
    $tree = \App\Models\Tenant\Department::on('tenant')
        ->whereNull('parent_id')
        ->with('allChildren.members', 'allChildren.managers', 'members', 'managers')
        ->orderBy('sort_order')->orderBy('name')
        ->get();
@endphp
<div class="header">
    <div>
        <div class="org-name">{{ $tenant?->name ?? 'Organisation' }}</div>
        <div class="subtitle">Organigramme · {{ now()->format('d/m/Y') }}</div>
    </div>
    <div class="actions no-print">
        <button class="btn btn-outline btn-sm" onclick="expandAll()">⊞ Tout déplier</button>
        <button class="btn btn-outline btn-sm" onclick="collapseAll()">⊟ Tout replier</button>
        <a href="{{ route('admin.departments.index') }}" class="btn btn-outline">← Retour</a>
        <button onclick="window.print()" class="btn btn-primary">🖨 Imprimer</button>
    </div>
</div>
<div class="stats-bar">
    <div class="stat"><strong>{{ $totalDepts }}</strong> entités</div>
    <div class="stat"><strong>{{ $totalMembers }}</strong> agents</div>
</div>
<div class="search-bar no-print">
    <input type="text" class="search-input" id="search" placeholder="🔍 Rechercher..." oninput="doSearch(this.value)">
    <span class="search-count" id="search-count"></span>
    <button class="btn btn-outline btn-sm" onclick="clearSearch()">✕</button>
</div>
<div class="legend no-print">
    <div class="legend-item"><div class="legend-dot" style="background:#1E3A5F"></div> Président</div>
    <div class="legend-item"><div class="legend-dot" style="background:#0f3460"></div> DGS</div>
    <div class="legend-item"><div class="legend-dot" style="background:#6d28d9"></div> Pôle</div>
    <div class="legend-item"><div class="legend-dot" style="background:#1e40af"></div> Direction</div>
    <div class="legend-item"><div class="legend-dot" style="background:#bfdbfe;border:1px solid #93c5fd"></div> Service</div>
    <div class="legend-item"><div class="legend-dot" style="border:2px dashed #94a3b8"></div> Transversal</div>
</div>
<ul class="tree tree-root" id="tree">
    @include('admin.departments.partials.dept-node', ['nodes' => $tree, 'depth' => 0])
</ul>
<script>
function toggle(el) {
    const item     = el.closest('.tree-item');
    const children = item.querySelector(':scope > .tree-children');
    const icon     = el.querySelector('.toggle-icon');
    if (!children) return;
    const isOpen = children.classList.toggle('open');
    icon?.classList.toggle('open', isOpen);
}
function expandAll() {
    document.querySelectorAll('.tree-children').forEach(el => el.classList.add('open'));
    document.querySelectorAll('.toggle-icon').forEach(el => el.classList.add('open'));
}
function collapseAll() {
    document.querySelectorAll('.tree-children').forEach(el => el.classList.remove('open'));
    document.querySelectorAll('.toggle-icon').forEach(el => el.classList.remove('open'));
}
function doSearch(query) {
    const q = query.trim().toLowerCase();
    const allCards = document.querySelectorAll('.node-card');
    const countEl  = document.getElementById('search-count');
    if (!q) {
        allCards.forEach(c => c.classList.remove('highlighted', 'dimmed'));
        document.querySelectorAll('.tree-children').forEach(el => el.classList.remove('open'));
        document.querySelectorAll('.toggle-icon').forEach(el => el.classList.remove('open'));
        countEl.textContent = '';
        return;
    }
    let found = 0;
    allCards.forEach(card => {
        const name  = card.querySelector('.node-name')?.textContent.toLowerCase() || '';
        const label = card.querySelector('.node-label')?.textContent.toLowerCase() || '';
        const matches = name.includes(q) || label.includes(q);
        card.classList.toggle('highlighted', matches);
        card.classList.toggle('dimmed', !matches);
        if (matches) {
            found++;
            let parent = card.closest('.tree-children');
            while (parent) {
                parent.classList.add('open');
                parent.closest('.tree-item')?.querySelector(':scope > .node-row .toggle-icon')?.classList.add('open');
                parent = parent.parentElement?.closest('.tree-children');
            }
        }
    });
    countEl.textContent = found ? `${found} résultat(s)` : 'Aucun résultat';
}
function clearSearch() {
    document.getElementById('search').value = '';
    doSearch('');
}
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.tree-root > .tree-item > .tree-children').forEach(el => el.classList.add('open'));
    document.querySelectorAll('.tree-root > .tree-item > .node-row .toggle-icon').forEach(el => el.classList.add('open'));
});
</script>
</body>
</html>
