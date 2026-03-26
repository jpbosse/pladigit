{{-- CSS commun à toutes les vues GED --}}
@once
<style>
/* ── Layout ──────────────────────────────────────────────── */
#ged-wrap {
    display: flex;
    height: calc(100vh - var(--pd-topbar-h) - var(--pd-footer-h));
    overflow: hidden;
}
/* ── Sidebar ─────────────────────────────────────────────── */
#ged-sidebar {
    width: 260px; flex-shrink: 0;
    border-right: 1px solid var(--pd-border);
    display: flex; flex-direction: column;
    background: var(--pd-surface2); overflow: hidden;
}
.ged-sidebar-header { padding: 12px 14px 10px; border-bottom: 1px solid var(--pd-border); }
.ged-new-btn {
    width: 100%; padding: 7px 12px;
    background: var(--pd-navy); color: #fff;
    border: none; border-radius: 8px; font-size: 12px; cursor: pointer;
    display: flex; align-items: center; justify-content: center; gap: 6px;
    text-decoration: none; transition: background .15s;
}
.ged-new-btn:hover { background: var(--pd-navy-light); }
.ged-nav { flex: 1; overflow-y: auto; padding: 4px 0; }
.ged-nav-section { padding: 8px 14px 3px; font-size: 10px; font-weight: 600; color: var(--pd-muted); text-transform: uppercase; letter-spacing: .5px; }
.ged-nav-item {
    display: flex; align-items: center; gap: 6px;
    padding: 3px 8px 3px 6px; min-height: 36px;
    cursor: pointer; color: var(--pd-muted);
    font-size: 12px; text-decoration: none;
    transition: background .1s, color .1s;
    border-right: 3px solid transparent;
    width: 100%; background: transparent; text-align: left;
    box-sizing: border-box; border-top: none; border-bottom: none; border-left: none;
}
.ged-nav-item:hover { background: var(--pd-surface); color: var(--pd-text); }
.ged-nav-item.active { background: var(--pd-surface); color: var(--pd-navy); font-weight: 600; border-right-color: var(--pd-accent); }
.ged-tree-toggle {
    width: 20px; height: 20px; flex-shrink: 0;
    background: none; border: none; cursor: pointer;
    color: var(--pd-muted); font-size: 10px; padding: 0;
    display: flex; align-items: center; justify-content: center;
    border-radius: 4px; transition: background .1s;
}
.ged-tree-toggle:hover { background: var(--pd-border); color: var(--pd-text); }
.ged-tree-toggle-ph { width: 20px; flex-shrink: 0; }
.ged-nav-name { flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 12px; color: inherit; }
.ged-nav-count { flex-shrink: 0; font-size: 10px; background: var(--pd-border); padding: 1px 6px; border-radius: 10px; color: var(--pd-muted); }
/* DnD sidebar */
.ged-nav-item.dnd-over {
    background: rgba(59,154,225,.12) !important;
    outline: 1px solid var(--pd-accent);
    outline-offset: -1px; border-radius: 6px;
    color: var(--pd-navy) !important;
}
.ged-nav-item.dnd-dragging { opacity: .45; }
.ged-dnd-root-zone {
    margin: 4px 8px; padding: 5px 8px;
    border: 1px dashed var(--pd-border); border-radius: 6px;
    font-size: 11px; color: var(--pd-muted); text-align: center;
    display: none; transition: all .1s;
}
.ged-dnd-root-zone.visible { display: block; }
.ged-dnd-root-zone.dnd-over { border-color: var(--pd-accent); color: var(--pd-navy); background: rgba(59,154,225,.08); }
/* ── Main ────────────────────────────────────────────────── */
#ged-main { flex: 1; display: flex; flex-direction: column; min-width: 0; overflow: hidden; }
#ged-header {
    height: 46px; flex-shrink: 0;
    border-bottom: 1px solid var(--pd-border);
    display: flex; align-items: center; padding: 0 16px; gap: 12px;
    background: var(--pd-surface);
}
.ged-breadcrumb { display: flex; align-items: center; gap: 5px; font-size: 12px; color: var(--pd-muted); white-space: nowrap; overflow: hidden; flex: 1; }
.ged-breadcrumb a { color: var(--pd-muted); text-decoration: none; }
.ged-breadcrumb a:hover { color: var(--pd-text); }
.ged-breadcrumb-sep { color: var(--pd-border); }
.ged-breadcrumb-current { color: var(--pd-text); font-weight: 600; }
.ged-header-right { display: flex; align-items: center; gap: 6px; }
#ged-content { flex: 1; overflow-y: auto; padding: 20px; }
/* ── Dossiers ─────────────────────────────────────────────── */
.ged-section-title { font-size: 11px; font-weight: 600; color: var(--pd-muted); text-transform: uppercase; letter-spacing: .5px; margin-bottom: 10px; }
.ged-folder-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 12px; margin-bottom: 8px; }
.ged-folder-card {
    border: 1px solid var(--pd-border); border-radius: 10px;
    background: var(--pd-surface); position: relative;
    transition: box-shadow .15s, transform .15s;
    overflow: hidden;
}
.ged-folder-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,.08); transform: translateY(-1px); }
.ged-folder-card-link { display: block; padding: 16px 12px 10px; text-decoration: none; }
.ged-folder-icon { font-size: 32px; text-align: center; position: relative; margin-bottom: 6px; }
.ged-private-badge { position: absolute; top: -2px; right: -4px; font-size: 12px; }
.ged-folder-name { font-size: 12px; font-weight: 600; color: var(--pd-text); text-align: center; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; margin-bottom: 3px; }
.ged-folder-meta { font-size: 10px; color: var(--pd-muted); text-align: center; }
.ged-folder-actions {
    position: absolute; top: 4px; right: 4px;
    display: none; gap: 2px; align-items: center;
}
.ged-folder-card:hover .ged-folder-actions { display: flex; }
.ged-action-btn {
    width: 22px; height: 22px; border: none; background: var(--pd-bg);
    border-radius: 4px; cursor: pointer; font-size: 12px;
    display: flex; align-items: center; justify-content: center;
    transition: background .1s;
}
.ged-action-btn:hover { background: var(--pd-border); }
.ged-action-delete:hover { background: #fee2e2; }
/* ── Documents table ──────────────────────────────────────── */
.ged-doc-table-wrap { overflow-x: auto; border: 1px solid var(--pd-border); border-radius: 8px; }
.ged-doc-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.ged-doc-table th { padding: 8px 12px; text-align: left; font-size: 11px; font-weight: 600; color: var(--pd-muted); background: var(--pd-surface2); border-bottom: 1px solid var(--pd-border); }
.ged-doc-table td { padding: 8px 12px; border-bottom: 1px solid var(--pd-border); }
.ged-doc-table tr:last-child td { border-bottom: none; }
.ged-doc-table tr:hover td { background: var(--pd-surface); }
/* ── Flash ────────────────────────────────────────────────── */
.ged-flash { padding: 10px 16px; font-size: 13px; border-left: 3px solid; margin: 0 20px 0; }
.ged-flash-success { background: #f0fdf4; border-color: #22c55e; color: #166534; }
.ged-flash-error   { background: #fef2f2; border-color: #ef4444; color: #991b1b; }
/* ── Empty state ─────────────────────────────────────────── */
.ged-empty { text-align: center; padding: 60px 20px; color: var(--pd-muted); }
/* ── Modales ──────────────────────────────────────────────── */
.ged-modal-backdrop {
    position: fixed; inset: 0; background: rgba(0,0,0,.35);
    display: flex; align-items: center; justify-content: center; z-index: 1000;
}
.ged-modal {
    background: var(--pd-surface); border-radius: 12px;
    width: 380px; max-width: 95vw;
    box-shadow: 0 20px 60px rgba(0,0,0,.2);
    overflow: hidden;
}
.ged-modal-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 14px 16px; border-bottom: 1px solid var(--pd-border);
    font-size: 14px; font-weight: 600; color: var(--pd-text);
}
.ged-modal-close { background: none; border: none; cursor: pointer; font-size: 16px; color: var(--pd-muted); padding: 2px 6px; border-radius: 4px; }
.ged-modal-close:hover { background: var(--pd-bg); }
.ged-modal-body { padding: 16px; }
.ged-modal-footer { display: flex; justify-content: flex-end; gap: 8px; padding: 12px 16px; border-top: 1px solid var(--pd-border); background: var(--pd-surface2); }
</style>
@endonce
