@extends('layouts.app')
@section('title', 'Intégrité des données — Photothèque')

@push('styles')
@include('media._ph_base_styles')
<style>
.int-layout { display:flex; min-height:calc(100vh - 56px); }
.int-content { flex:1; min-width:0; padding:20px 24px 40px; max-width:1200px; }

.int-header { display:flex; align-items:flex-start; justify-content:space-between; flex-wrap:wrap; gap:12px; margin-bottom:24px; }
.int-title   { font-size:20px; font-weight:700; color:var(--pd-text); }
.int-subtitle{ font-size:12px; color:var(--pd-muted); margin-top:3px; }

.int-scan-btn {
    display:inline-flex; align-items:center; gap:7px;
    padding:9px 18px; background:var(--pd-accent); color:#fff;
    border:none; border-radius:8px; font-size:13px; font-weight:600; cursor:pointer;
}
.int-scan-btn:disabled { opacity:.55; cursor:default; }
.int-scan-btn.spinning svg { animation:spin .8s linear infinite; }
@keyframes spin { to { transform:rotate(360deg); } }
.int-scanned-at { font-size:11px; color:var(--pd-muted); margin-top:6px; }

.int-tabs { display:flex; gap:4px; border-bottom:2px solid var(--pd-border); margin-bottom:20px; flex-wrap:wrap; }
.int-tab {
    padding:8px 14px; font-size:12px; font-weight:600; color:var(--pd-muted);
    cursor:pointer; border-bottom:2px solid transparent; margin-bottom:-2px;
    display:flex; align-items:center; gap:5px;
}
.int-tab.active { color:var(--pd-accent); border-bottom-color:var(--pd-accent); }
.int-badge {
    font-size:10px; font-weight:700; padding:1px 5px; border-radius:99px; line-height:1.4; color:#fff;
    background:#e74c3c;
}
.int-badge.ok   { background:#22c55e; }
.int-badge.warn { background:#f59e0b; }

.int-empty { text-align:center; padding:60px 20px; color:var(--pd-muted); font-size:13px; }
.int-empty svg { opacity:.3; margin:0 auto 12px; display:block; }
.int-empty strong { display:block; font-size:15px; color:var(--pd-text); margin-bottom:6px; }

.int-bar {
    display:flex; align-items:center; gap:10px; padding:10px 14px;
    background:var(--pd-surface); border:1px solid var(--pd-border);
    border-radius:8px; margin-bottom:14px; flex-wrap:wrap;
}
.int-bar-count { font-size:12px; color:var(--pd-muted); flex:1; }

.int-table { width:100%; border-collapse:collapse; font-size:12px; }
.int-table th {
    text-align:left; padding:8px 10px; background:var(--pd-surface);
    color:var(--pd-muted); font-weight:600; font-size:11px;
    text-transform:uppercase; letter-spacing:.04em; border-bottom:1px solid var(--pd-border);
}
.int-table td { padding:8px 10px; border-bottom:1px solid var(--pd-border); vertical-align:middle; color:var(--pd-text); }
.int-table tr:hover td { background:var(--pd-surface); }
.int-table .mono { font-family:monospace; font-size:11px; color:var(--pd-muted); word-break:break-all; }
.int-checkbox { width:16px; height:16px; cursor:pointer; accent-color:var(--pd-accent); }

.int-btn {
    display:inline-flex; align-items:center; gap:5px; padding:5px 12px;
    border-radius:6px; font-size:12px; font-weight:600; cursor:pointer; border:1px solid transparent;
}
.int-btn:disabled { opacity:.4; cursor:default; }
.int-btn-danger   { background:#fef2f2; color:#e74c3c; border-color:#fca5a5; }
.int-btn-danger:hover:not(:disabled)   { background:#fee2e2; }
.int-btn-warning  { background:#fffbeb; color:#b45309; border-color:#fcd34d; }
.int-btn-warning:hover:not(:disabled)  { background:#fef3c7; }
.int-btn-secondary{ background:var(--pd-surface); color:var(--pd-text); border-color:var(--pd-border); }
.int-btn-secondary:hover:not(:disabled){ background:var(--pd-border); }

.int-alert {
    display:flex; align-items:flex-start; gap:10px; padding:10px 14px;
    border-radius:8px; font-size:12px; margin-bottom:16px; line-height:1.5;
}
.int-alert svg { flex-shrink:0; margin-top:1px; }
.int-alert-success{ background:#f0fdf4; color:#15803d; border:1px solid #bbf7d0; }
.int-alert-error  { background:#fef2f2; color:#b91c1c; border:1px solid #fca5a5; }
.int-alert-warning{ background:#fffbeb; color:#92400e; border:1px solid #fcd34d; }
.int-alert-info   { background:#eff6ff; color:#1d4ed8; border:1px solid #bfdbfe; }

.int-modal-backdrop {
    position:fixed; inset:0; background:rgba(0,0,0,.5);
    display:flex; align-items:center; justify-content:center; z-index:200;
}
.int-modal {
    background:var(--pd-bg); border-radius:12px; padding:24px;
    width:420px; max-width:94vw; box-shadow:0 20px 60px rgba(0,0,0,.3);
}
.int-modal h3 { font-size:16px; font-weight:700; margin-bottom:8px; }
.int-modal p  { font-size:13px; color:var(--pd-muted); margin-bottom:20px; line-height:1.5; }
.int-modal-actions { display:flex; gap:10px; justify-content:flex-end; }
</style>
@endpush

@section('content')
<div class="int-layout" x-data="integrityManager()" x-init="init()">

    @include('media._ph_sidebar', [
        'albumTree'     => $albumTree,
        'activeAlbumId' => null,
        'ancestorIds'   => [],
        'totalAlbums'   => null,
    ])

    <div class="int-content">

        {{-- Header --}}
        <div class="int-header">
            <div>
                <div class="int-title">Intégrité des données</div>
                <div class="int-subtitle">Cohérence entre le NAS physique et la base de données</div>
            </div>
            <div style="text-align:right">
                <button class="int-scan-btn" :class="{ spinning: scanning }" :disabled="scanning" @click="startScan()">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M21 2v6h-6M3 12a9 9 0 0115-6.7L21 8M3 22v-6h6M21 12a9 9 0 01-15 6.7L3 16"/></svg>
                    <span x-text="scanning ? 'Scan en cours…' : 'Lancer le scan'"></span>
                </button>
                <div class="int-scanned-at" x-show="scannedAt" x-text="'Dernier scan : ' + scannedAt"></div>
            </div>
        </div>

        {{-- Flash --}}
        <template x-if="message">
            <div class="int-alert" :class="'int-alert-' + messageType">
                <span x-text="message" style="flex:1"></span>
                <button @click="message=null" style="background:none;border:none;cursor:pointer;opacity:.6;font-size:14px">✕</button>
            </div>
        </template>

        {{-- État initial --}}
        <template x-if="!scannedAt && !scanning">
            <div class="int-empty">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                <strong>Aucun scan effectué</strong>
                Cliquez sur « Lancer le scan » pour vérifier la cohérence NAS / base de données.
            </div>
        </template>

        {{-- Spinner --}}
        <template x-if="scanning">
            <div class="int-empty">
                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" style="animation:spin .8s linear infinite"><path d="M21 2v6h-6M3 12a9 9 0 0115-6.7L21 8M3 22v-6h6M21 12a9 9 0 01-15 6.7L3 16"/></svg>
                <strong style="margin-top:12px">Analyse en cours…</strong>
                Vérification de chaque fichier sur le NAS.
            </div>
        </template>

        {{-- Résultats --}}
        <template x-if="scannedAt && !scanning">
            <div>

                <div class="int-tabs">
                    <div class="int-tab" :class="{active:tab==='db'}"     @click="tab='db'">
                        Orphelins actifs
                        <span class="int-badge" :class="{ok:dbOrphans.length===0}" x-text="dbOrphans.length"></span>
                    </div>
                    <div class="int-tab" :class="{active:tab==='soft'}"   @click="tab='soft'">
                        Items supprimés
                        <span class="int-badge warn" :class="{ok:softItems.length===0}" x-text="softItems.length"></span>
                    </div>
                    <div class="int-tab" :class="{active:tab==='albums'}" @click="tab='albums'">
                        Albums NAS
                        <span class="int-badge warn" :class="{ok:orphanAlbums.length===0}" x-text="orphanAlbums.length"></span>
                    </div>
                    <div class="int-tab" :class="{active:tab==='softAlbums'}" @click="tab='softAlbums'">
                        Albums supprimés
                        <span class="int-badge warn" :class="{ok:softAlbums.length===0}" x-text="softAlbums.length"></span>
                    </div>
                    <div class="int-tab" :class="{active:tab==='links'}"  @click="tab='links'">
                        Liens orphelins
                        <span class="int-badge warn" :class="{ok:orphanLinks.length===0}" x-text="orphanLinks.length"></span>
                    </div>
                    <div class="int-tab" :class="{active:tab==='nas'}"    @click="tab='nas'">
                        Orphelins NAS
                        <span class="int-badge" :class="{ok:nasOrphans.length===0}" x-text="nasOrphans.length"></span>
                    </div>
                    <div class="int-tab" :class="{active:tab==='dbDups'}" @click="tab='dbDups'">
                        Doublons BDD
                        <span class="int-badge warn" :class="{ok:dbDuplicates.length===0}" x-text="dbDuplicates.length"></span>
                    </div>
                </div>

                {{-- ── Orphelins actifs ────────────────────── --}}
                <div x-show="tab==='db'">
                    <template x-if="dbOrphans.length===0">
                        <div class="int-empty"><svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg><strong>Aucun orphelin actif</strong>Tous les enregistrements actifs ont un fichier correspondant sur le NAS.</div>
                    </template>
                    <template x-if="dbOrphans.length>0">
                        <div>
                            <div class="int-alert int-alert-error"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg><span x-text="dbOrphans.length+' enregistrement(s) actif(s) pointent vers des fichiers absents du NAS.'"></span></div>
                            <div class="int-bar">
                                <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:12px"><input type="checkbox" class="int-checkbox" @change="toggleAll('selDb',dbOrphans,'id',$event.target.checked)"> Tout sélectionner</label>
                                <span class="int-bar-count" x-text="selDb.length+' sélectionné(s)'"></span>
                                <button class="int-btn int-btn-danger" :disabled="selDb.length===0" @click="openConfirm('db')"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6M10 11v6M14 11v6M9 6V4h6v2"/></svg> Supprimer</button>
                            </div>
                            <table class="int-table">
                                <thead><tr><th style="width:30px"></th><th>Fichier</th><th>Album</th><th>Taille</th><th>Ajouté le</th><th>Chemin NAS</th></tr></thead>
                                <tbody>
                                    <template x-for="item in dbOrphans" :key="item.id">
                                        <tr><td><input type="checkbox" class="int-checkbox" :checked="selDb.includes(item.id)" @change="toggle('selDb',item.id)"></td><td style="font-weight:600" x-text="item.file_name"></td><td x-text="item.album_name"></td><td x-text="fmt(item.file_size)"></td><td x-text="item.created_at"></td><td class="mono" x-text="item.file_path"></td></tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </template>
                </div>

                {{-- ── Items soft-deleted ──────────────────── --}}
                <div x-show="tab==='soft'">
                    <template x-if="softItems.length===0">
                        <div class="int-empty"><svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg><strong>Aucun item supprimé</strong>Pas d'enregistrement en attente de purge définitive.</div>
                    </template>
                    <template x-if="softItems.length>0">
                        <div>
                            <div class="int-alert int-alert-warning"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg><span x-text="softItems.length+' item(s) supprimés logiquement par le sync NAS. Purgez-les définitivement pour libérer la base de données.'"></span></div>
                            <div class="int-bar">
                                <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:12px"><input type="checkbox" class="int-checkbox" @change="toggleAll('selSoft',softItems,'id',$event.target.checked)"> Tout sélectionner</label>
                                <span class="int-bar-count" x-text="selSoft.length+' sélectionné(s)'"></span>
                                <button class="int-btn int-btn-warning" :disabled="selSoft.length===0" @click="openConfirm('soft')"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6M10 11v6M14 11v6M9 6V4h6v2"/></svg> Purge définitive</button>
                            </div>
                            <table class="int-table">
                                <thead><tr><th style="width:30px"></th><th>Fichier</th><th>Album</th><th>Taille</th><th>Supprimé le</th><th>Chemin NAS</th></tr></thead>
                                <tbody>
                                    <template x-for="item in softItems" :key="item.id">
                                        <tr><td><input type="checkbox" class="int-checkbox" :checked="selSoft.includes(item.id)" @change="toggle('selSoft',item.id)"></td><td style="font-weight:600" x-text="item.file_name"></td><td x-text="item.album_name"></td><td x-text="fmt(item.file_size)"></td><td x-text="item.deleted_at"></td><td class="mono" x-text="item.file_path"></td></tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </template>
                </div>

                {{-- ── Albums NAS orphelins ────────────────── --}}
                <div x-show="tab==='albums'">
                    <template x-if="orphanAlbums.length===0">
                        <div class="int-empty"><svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg><strong>Aucun album NAS orphelin</strong>Tous les albums liés au NAS ont un dossier correspondant.</div>
                    </template>
                    <template x-if="orphanAlbums.length>0">
                        <div>
                            <div class="int-alert int-alert-warning"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M22 19a2 2 0 01-2 2H4a2 2 0 01-2-2V5a2 2 0 012-2h5l2 3h9a2 2 0 012 2z"/></svg><span x-text="orphanAlbums.length+' album(s) pointent vers un dossier NAS inexistant.'"></span></div>
                            <div class="int-bar">
                                <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:12px"><input type="checkbox" class="int-checkbox" @change="toggleAll('selAlbums',orphanAlbums.filter(a=>a.items_count===0),'id',$event.target.checked)"> Tout sélectionner (vides)</label>
                                <span class="int-bar-count" x-text="selAlbums.length+' sélectionné(s)'"></span>
                                <button class="int-btn int-btn-warning" :disabled="selAlbums.length===0" @click="openConfirm('albums')"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6M10 11v6M14 11v6M9 6V4h6v2"/></svg> Supprimer les vides</button>
                            </div>
                            <table class="int-table">
                                <thead><tr><th style="width:30px"></th><th>Album</th><th>Photos</th><th>Chemin NAS</th></tr></thead>
                                <tbody>
                                    <template x-for="album in orphanAlbums" :key="album.id">
                                        <tr><td><input type="checkbox" class="int-checkbox" :disabled="album.items_count>0" :checked="selAlbums.includes(album.id)" @change="toggle('selAlbums',album.id)"></td><td style="font-weight:600" x-text="album.name"></td><td><span x-text="album.items_count"></span><template x-if="album.items_count>0"><span style="color:#e74c3c;font-size:10px;margin-left:4px">non supprimable</span></template></td><td class="mono" x-text="album.nas_path"></td></tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </template>
                </div>

                {{-- ── Albums soft-deleted ─────────────────── --}}
                <div x-show="tab==='softAlbums'">
                    <template x-if="softAlbums.length===0">
                        <div class="int-empty"><svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg><strong>Aucun album supprimé</strong>Pas d'album en attente de purge définitive.</div>
                    </template>
                    <template x-if="softAlbums.length>0">
                        <div>
                            <div class="int-alert int-alert-warning"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg><span x-text="softAlbums.length+' album(s) supprimés logiquement. Purgez-les définitivement pour nettoyer la base de données.'"></span></div>
                            <div class="int-bar">
                                <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:12px"><input type="checkbox" class="int-checkbox" @change="toggleAll('selSoftAlbums',softAlbums,'id',$event.target.checked)"> Tout sélectionner</label>
                                <span class="int-bar-count" x-text="selSoftAlbums.length+' sélectionné(s)'"></span>
                                <button class="int-btn int-btn-warning" :disabled="selSoftAlbums.length===0" @click="openConfirm('softAlbums')"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6M10 11v6M14 11v6M9 6V4h6v2"/></svg> Purge définitive</button>
                            </div>
                            <table class="int-table">
                                <thead><tr><th style="width:30px"></th><th>Album</th><th>Photos (incl. supprimées)</th><th>Supprimé le</th><th>Chemin NAS</th></tr></thead>
                                <tbody>
                                    <template x-for="album in softAlbums" :key="album.id">
                                        <tr><td><input type="checkbox" class="int-checkbox" :checked="selSoftAlbums.includes(album.id)" @change="toggle('selSoftAlbums',album.id)"></td><td style="font-weight:600" x-text="album.name"></td><td x-text="album.items_count"></td><td x-text="album.deleted_at"></td><td class="mono" x-text="album.nas_path"></td></tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </template>
                </div>

                {{-- ── Liens & Tags orphelins ──────────────── --}}
                <div x-show="tab==='links'">
                    <template x-if="orphanLinks.length===0">
                        <div class="int-empty"><svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg><strong>Aucun lien orphelin</strong>Tous les liens de partage pointent vers des albums actifs.</div>
                    </template>

                    {{-- Liens de partage --}}
                    <template x-if="orphanLinks.length>0">
                        <div style="margin-bottom:28px">
                            <div class="int-alert int-alert-warning"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M10 13a5 5 0 007.54.54l3-3a5 5 0 00-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 00-7.54-.54l-3 3a5 5 0 007.07 7.07l1.71-1.71"/></svg><span x-text="orphanLinks.length+' lien(s) de partage pointent vers des albums supprimés.'"></span></div>
                            <div class="int-bar">
                                <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:12px"><input type="checkbox" class="int-checkbox" @change="toggleAll('selLinks',orphanLinks,'id',$event.target.checked)"> Tout sélectionner</label>
                                <span class="int-bar-count" x-text="selLinks.length+' sélectionné(s)'"></span>
                                <button class="int-btn int-btn-warning" :disabled="selLinks.length===0" @click="openConfirm('links')"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6M10 11v6M14 11v6M9 6V4h6v2"/></svg> Supprimer les liens</button>
                            </div>
                            <table class="int-table">
                                <thead><tr><th style="width:30px"></th><th>Token</th><th>Album ID</th><th>Expire le</th><th>Créé le</th></tr></thead>
                                <tbody>
                                    <template x-for="link in orphanLinks" :key="link.id">
                                        <tr><td><input type="checkbox" class="int-checkbox" :checked="selLinks.includes(link.id)" @change="toggle('selLinks',link.id)"></td><td class="mono" x-text="link.token"></td><td x-text="link.album_id"></td><td x-text="link.expires_at"></td><td x-text="link.created_at"></td></tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </template>

                </div>

                {{-- ── Doublons BDD (même file_path, plusieurs enregistrements) ── --}}
                <div x-show="tab==='dbDups'">
                    <template x-if="dbDuplicates.length===0">
                        <div class="int-empty"><svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg><strong>Aucun doublon BDD</strong>Aucun fichier n'a plusieurs enregistrements pointant vers le même chemin NAS.</div>
                    </template>
                    <template x-if="dbDuplicates.length>0">
                        <div>
                            <div class="int-alert int-alert-info"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg><span x-text="dbDuplicates.length+' enregistrement(s) en double pointent vers le même fichier physique. Seule la base de données sera modifiée (le fichier NAS est conservé).'"></span></div>
                            <div class="int-bar">
                                <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:12px"><input type="checkbox" class="int-checkbox" @change="toggleAll('selDbDups',dbDuplicates,'id',$event.target.checked)"> Tout sélectionner</label>
                                <span class="int-bar-count" x-text="selDbDups.length+' sélectionné(s)'"></span>
                                <button class="int-btn int-btn-warning" :disabled="selDbDups.length===0" @click="openConfirm('dbDups')"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6M10 11v6M14 11v6M9 6V4h6v2"/></svg> Dédupliquer (BDD uniquement)</button>
                            </div>
                            <table class="int-table">
                                <thead><tr><th style="width:30px"></th><th>Fichier</th><th>Album</th><th>Chemin NAS</th><th>Ajouté le</th><th>Copies</th></tr></thead>
                                <tbody>
                                    <template x-for="item in dbDuplicates" :key="item.id">
                                        <tr><td><input type="checkbox" class="int-checkbox" :checked="selDbDups.includes(item.id)" @change="toggle('selDbDups',item.id)"></td><td style="font-weight:600" x-text="item.file_name"></td><td x-text="item.album_name"></td><td class="mono" x-text="item.file_path"></td><td x-text="item.created_at"></td><td x-text="item.copies+'× même chemin'"></td></tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </template>
                </div>

                {{-- ── Orphelins NAS ───────────────────────── --}}
                <div x-show="tab==='nas'">
                    <template x-if="nasOrphans.length===0">
                        <div class="int-empty"><svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg><strong>Aucun orphelin NAS</strong>Tous les fichiers du NAS ont un enregistrement en base de données.</div>
                    </template>
                    <template x-if="nasOrphans.length>0">
                        <div>
                            <div class="int-alert int-alert-info"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg><span x-text="nasOrphans.length+' fichier(s) sur le NAS sans enregistrement BDD. Lancez une sync NAS pour les importer.'"></span></div>
                            <table class="int-table">
                                <thead><tr><th>Fichier</th><th>Chemin NAS</th><th>Taille</th><th>Modifié le</th></tr></thead>
                                <tbody>
                                    <template x-for="(f,i) in nasOrphans" :key="i">
                                        <tr><td style="font-weight:600" x-text="f.name"></td><td class="mono" x-text="f.path"></td><td x-text="fmt(f.size)"></td><td x-text="fmtTs(f.mtime)"></td></tr>
                                    </template>
                                </tbody>
                            </table>
                            <div style="margin-top:14px">
                                <a href="{{ route('media.sync') }}" onclick="return confirm('Lancer la synchronisation NAS ?')" class="int-btn int-btn-secondary" style="text-decoration:none">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M21 2v6h-6M3 12a9 9 0 0115-6.7L21 8M3 22v-6h6M21 12a9 9 0 01-15 6.7L3 16"/></svg>
                                    Lancer la synchronisation NAS
                                </a>
                            </div>
                        </div>
                    </template>
                </div>

            </div>
        </template>
    </div>

    {{-- Modal --}}
    <template x-if="showConfirm">
        <div class="int-modal-backdrop" @click.self="showConfirm=false">
            <div class="int-modal">
                <h3 x-text="confirmTitle"></h3>
                <p x-html="confirmBody"></p>
                <div class="int-modal-actions">
                    <button class="int-btn int-btn-secondary" @click="showConfirm=false">Annuler</button>
                    <button class="int-btn" :class="confirmAction==='db'?'int-btn-danger':'int-btn-warning'" @click="doAction()" :disabled="acting">
                        <span x-text="acting?'En cours…':confirmLabel"></span>
                    </button>
                </div>
            </div>
        </div>
    </template>

</div>
@endsection

@push('scripts')
<script>
function integrityManager() {
    return {
        tab:'db', scanning:false, scannedAt:null,
        dbOrphans:[], softItems:[], orphanAlbums:[], softAlbums:[], orphanLinks:[], nasOrphans:[], dbDuplicates:[],
        selDb:[], selSoft:[], selAlbums:[], selSoftAlbums:[], selLinks:[], selDbDups:[],
        showConfirm:false, confirmAction:null, confirmTitle:'', confirmBody:'', confirmLabel:'', acting:false,
        message:null, messageType:'info',

        init() {},

        async startScan() {
            this.scanning = true; this.message = null;
            ['selDb','selSoft','selAlbums','selSoftAlbums','selLinks','selDbDups'].forEach(k => this[k]=[]);

            try {
                const res  = await fetch('{{ route("media.integrity.scan") }}', {
                    method:'POST', headers:{'X-CSRF-TOKEN':'{{ csrf_token() }}','Accept':'application/json'},
                });
                if (!res.ok) throw new Error('HTTP '+res.status);
                const d = await res.json();
                this.dbOrphans    = d.db_orphans          ?? [];
                this.softItems    = d.soft_deleted_items  ?? [];
                this.orphanAlbums = d.orphan_albums       ?? [];
                this.softAlbums   = d.soft_deleted_albums ?? [];
                this.orphanLinks  = d.orphan_share_links  ?? [];
                this.nasOrphans   = d.nas_orphans         ?? [];
                this.dbDuplicates = d.db_duplicates       ?? [];
                this.scannedAt    = d.scanned_at;
                // Basculer vers le premier onglet avec anomalies
                if (this.dbDuplicates.length>0)  this.tab='dbDups';
                if (this.orphanLinks.length>0)   this.tab='links';
                if (this.softAlbums.length>0)    this.tab='softAlbums';
                if (this.orphanAlbums.length>0)  this.tab='albums';
                if (this.softItems.length>0)     this.tab='soft';
                if (this.dbOrphans.length>0)     this.tab='db';
            } catch(e) {
                this.message='Erreur lors du scan : '+e.message; this.messageType='error';
                this.scannedAt=new Date().toLocaleString('fr-FR');
            } finally { this.scanning=false; }
        },

        toggle(list, id) {
            this[list] = this[list].includes(id) ? this[list].filter(i=>i!==id) : [...this[list], id];
        },
        toggleAll(list, items, key, checked) { this[list] = checked ? items.map(i=>i[key]) : []; },

        openConfirm(action) {
            this.confirmAction = action;
            const counts = {db:this.selDb.length, soft:this.selSoft.length, albums:this.selAlbums.length, softAlbums:this.selSoftAlbums.length, links:this.selLinks.length, dbDups:this.selDbDups.length};
            const n = counts[action];
            const msgs = {
                db:         ['Supprimer les enregistrements actifs', `Suppression de <strong>${n}</strong> enregistrement(s) actif(s) — leurs fichiers sont déjà absents du NAS. Action irréversible.`, 'Confirmer la suppression'],
                soft:       ['Purge définitive des items supprimés', `Suppression <strong>définitive</strong> de ${n} item(s) déjà supprimés logiquement. Action irréversible.`, 'Purge définitive'],
                albums:     ['Supprimer les albums NAS orphelins', `Suppression de <strong>${n}</strong> album(s) vide(s) dont le dossier NAS n'existe plus. Action irréversible.`, 'Supprimer les albums'],
                softAlbums: ['Purge définitive des albums supprimés', `Suppression <strong>définitive</strong> de ${n} album(s) déjà supprimés logiquement. Action irréversible.`, 'Purge définitive'],
                links:      ['Supprimer les liens de partage orphelins', `Suppression de <strong>${n}</strong> lien(s) de partage dont l'album source n'existe plus. Action irréversible.`, 'Supprimer les liens'],
                dbDups:     ['Dédupliquer les enregistrements BDD', `Suppression de <strong>${n}</strong> enregistrement(s) en double. Le fichier physique NAS est conservé — seuls les enregistrements surnuméraires sont supprimés. Action irréversible.`, 'Dédupliquer'],
            };
            [this.confirmTitle, this.confirmBody, this.confirmLabel] = msgs[action];
            this.showConfirm = true;
        },

        async doAction() {
            this.acting = true;
            const cfg = {
                db:         { url:'{{ route("media.integrity.purge") }}',               body:{ item_ids: this.selDb },         listKey:'dbOrphans',    selKey:'selDb',         idKey:'id' },
                soft:       { url:'{{ route("media.integrity.purge-soft") }}',         body:{ item_ids: this.selSoft },        listKey:'softItems',    selKey:'selSoft',       idKey:'id' },
                albums:     { url:'{{ route("media.integrity.purge-albums") }}',       body:{ album_ids: this.selAlbums },     listKey:'orphanAlbums', selKey:'selAlbums',     idKey:'id' },
                softAlbums: { url:'{{ route("media.integrity.purge-soft-albums") }}',  body:{ album_ids: this.selSoftAlbums }, listKey:'softAlbums',   selKey:'selSoftAlbums', idKey:'id' },
                links:      { url:'{{ route("media.integrity.purge-share-links") }}',  body:{ link_ids: this.selLinks },       listKey:'orphanLinks',  selKey:'selLinks',      idKey:'id' },
                dbDups:     { url:'{{ route("media.integrity.purge-db-duplicates") }}',body:{ item_ids: this.selDbDups },      listKey:'dbDuplicates', selKey:'selDbDups',     idKey:'id' },
            }[this.confirmAction];

            try {
                const res  = await fetch(cfg.url, {
                    method:'POST',
                    headers:{'X-CSRF-TOKEN':'{{ csrf_token() }}','Content-Type':'application/json','Accept':'application/json'},
                    body: JSON.stringify(cfg.body),
                });
                const data = await res.json();
                if (!res.ok) throw new Error(data.message ?? 'Erreur serveur');
                const purged = new Set(this[cfg.selKey]);
                this[cfg.listKey] = this[cfg.listKey].filter(i => !purged.has(i[cfg.idKey]));
                this[cfg.selKey]  = [];
                this.showConfirm  = false;
                this.message      = (data.deleted ?? 0)+' élément(s) supprimé(s).';
                this.messageType  = 'success';
            } catch(e) {
                this.showConfirm=false; this.message='Erreur : '+e.message; this.messageType='error';
            } finally { this.acting=false; }
        },

        fmt(b) {
            if (!b) return '—';
            if (b<1024) return b+' o';
            if (b<1048576) return (b/1024).toFixed(1)+' Ko';
            return (b/1048576).toFixed(1)+' Mo';
        },
        fmtTs(ts) {
            if (!ts) return '—';
            return new Date(ts*1000).toLocaleDateString('fr-FR',{day:'2-digit',month:'2-digit',year:'numeric'});
        },
    };
}
</script>
@endpush
