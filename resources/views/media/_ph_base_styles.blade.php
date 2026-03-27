{{-- CSS commun à toutes les vues de la photothèque (index, show…) --}}
@once
<style>
/* ── Layout général ─────────────────────────────────────── */
#ph-wrap {
    display: flex;
    height: calc(100vh - var(--pd-topbar-h) - var(--pd-footer-h));
    overflow: hidden;
}
/* ── Sidebar ────────────────────────────────────────────── */
#ph-sidebar {
    width: 260px; flex-shrink: 0;
    border-right: 1px solid var(--pd-border);
    display: flex; flex-direction: column;
    background: var(--pd-surface2); overflow: hidden;
}
.ph-sidebar-header { padding: 12px 14px 10px; border-bottom: 1px solid var(--pd-border); }
.ph-upload-btn {
    width: 100%; padding: 7px 12px;
    background: var(--pd-navy); color: #fff;
    border: none; border-radius: 8px; font-size: 12px; cursor: pointer;
    display: flex; align-items: center; justify-content: center; gap: 6px;
    text-decoration: none; transition: background .15s;
}
.ph-upload-btn:hover { background: var(--pd-navy-light); }
.ph-nav { flex: 1; overflow-y: auto; padding: 4px 0; }
.ph-nav-section { padding: 8px 14px 3px; font-size: 10px; font-weight: 600; color: var(--pd-muted); text-transform: uppercase; letter-spacing: .5px; }
.ph-nav-item {
    display: flex; align-items: center; gap: 6px;
    padding: 3px 8px 3px 6px; min-height: 38px;
    cursor: pointer; color: var(--pd-muted);
    font-size: 12px; text-decoration: none;
    transition: background .1s, color .1s;
    border-right: 3px solid transparent; border-top: none; border-bottom: none; border-left: none;
    width: 100%; background: transparent; text-align: left;
    box-sizing: border-box;
}
.ph-nav-item:hover { background: var(--pd-surface); color: var(--pd-text); }
.ph-nav-item.active { background: var(--pd-surface); color: var(--pd-navy); font-weight: 600; border-right-color: var(--pd-accent); }
.ph-nav-item:hover .ph-nav-addchild { display: flex !important; }
/* Toggle expand */
.ph-tree-toggle {
    width: 20px; height: 20px; flex-shrink: 0;
    background: none; border: none; cursor: pointer;
    color: var(--pd-muted); font-size: 10px; padding: 0;
    display: flex; align-items: center; justify-content: center;
    border-radius: 4px; transition: background .1s;
}
.ph-tree-toggle:hover { background: var(--pd-border); color: var(--pd-text); }
.ph-tree-toggle-ph { width: 20px; flex-shrink: 0; }
/* Vignette album dans la sidebar */
.ph-nav-thumb,
#ph-sidebar img.ph-nav-thumb {
    width: 30px !important;
    height: 30px !important;
    object-fit: cover !important;
    border-radius: 5px !important;
    flex-shrink: 0 !important;
    max-width: 30px !important;
    max-height: 30px !important;
}
.ph-nav-thumb-ph {
    width: 30px; height: 30px; border-radius: 5px; flex-shrink: 0;
    background: var(--pd-bg); border: 1px solid var(--pd-border);
    display: flex; align-items: center; justify-content: center;
    font-size: 14px;
}
/* Nom album */
.ph-nav-name {
    flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
    font-size: 12px; color: inherit;
}
.ph-nav-item.active .ph-nav-name { font-weight: 600; color: var(--pd-navy); }
/* Bouton ajout sous-album au survol */
.ph-nav-addchild {
    display: none; width: 18px; height: 18px; flex-shrink: 0;
    background: var(--pd-border); border: none; border-radius: 4px; cursor: pointer;
    align-items: center; justify-content: center; font-size: 11px;
    color: var(--pd-muted); text-decoration: none; transition: background .1s;
}
.ph-nav-addchild:hover { background: var(--pd-accent); color: #fff; }
.ph-nav-count { flex-shrink: 0; font-size: 10px; background: var(--pd-border); padding: 1px 6px; border-radius: 10px; color: var(--pd-muted); }
.ph-storage { padding: 10px 14px; border-top: 1px solid var(--pd-border); font-size: 11px; color: var(--pd-muted); }
.ph-storage-bar { height: 4px; background: var(--pd-border); border-radius: 2px; margin: 5px 0 3px; overflow: hidden; }
.ph-storage-fill { height: 100%; background: var(--pd-accent); border-radius: 2px; }
/* ── Main ───────────────────────────────────────────────── */
#ph-main { flex: 1; display: flex; flex-direction: column; min-width: 0; overflow: hidden; }
#ph-header {
    height: 46px; flex-shrink: 0;
    border-bottom: 1px solid var(--pd-border);
    display: flex; align-items: center; padding: 0 16px; gap: 12px;
    background: var(--pd-surface);
}
.ph-breadcrumb { display: flex; align-items: center; gap: 5px; font-size: 12px; color: var(--pd-muted); white-space: nowrap; overflow: hidden; }
.ph-breadcrumb a { color: var(--pd-muted); text-decoration: none; }
.ph-breadcrumb a:hover { color: var(--pd-text); }
.ph-breadcrumb .current { color: var(--pd-text); font-weight: 600; }
.ph-search-wrap {
    flex: 1; max-width: 280px;
    display: flex; align-items: center; gap: 6px;
    background: var(--pd-bg); border: 1px solid var(--pd-border);
    border-radius: 8px; padding: 0 10px; height: 30px;
}
.ph-search-wrap input { border: none; background: transparent; font-size: 12px; color: var(--pd-text); outline: none; width: 100%; }
.ph-header-right { margin-left: auto; display: flex; align-items: center; gap: 5px; }
.ph-hbtn {
    width: 28px; height: 28px;
    border: 1px solid var(--pd-border); border-radius: 7px;
    background: transparent; cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    color: var(--pd-muted); font-size: 12px; transition: all .15s;
    text-decoration: none;
}
.ph-hbtn:hover { background: var(--pd-bg); color: var(--pd-text); }
.ph-hbtn.active { background: var(--pd-navy); color: #fff; border-color: var(--pd-navy); }
.ph-vsep { width: 1px; height: 18px; background: var(--pd-border); flex-shrink: 0; }
/* ── Drag-and-drop sidebar ──────────────────────────────── */
.ph-nav-item.dnd-over {
    background: rgba(59,154,225,.12) !important;
    outline: 1px solid var(--pd-accent);
    outline-offset: -1px;
    border-radius: 6px;
    color: var(--pd-navy) !important;
}
.ph-nav-item.dnd-dragging { opacity: .45; }
.ph-dnd-root-zone {
    margin: 4px 8px; padding: 5px 8px;
    border: 1px dashed var(--pd-border); border-radius: 6px;
    font-size: 11px; color: var(--pd-muted); text-align: center;
    display: none; transition: all .1s;
}
.ph-dnd-root-zone.visible { display: block; }
.ph-dnd-root-zone.dnd-over { border-color: var(--pd-accent); color: var(--pd-navy); background: rgba(59,154,225,.08); }
@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
/* Badge tags sur les cartes grille */
.ph-tag-badge { position:absolute; top:6px; right:6px; background:rgba(55,48,163,.78); color:#fff; font-size:9px; padding:2px 6px; border-radius:10px; pointer-events:none; line-height:1.4; }
#ph-content { flex: 1; overflow-y: auto; padding: 20px; }
</style>
@endonce
