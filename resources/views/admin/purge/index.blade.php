@extends('layouts.admin')
@section('title', 'Purge GED')

@section('admin-content')

<div style="max-width:860px;">

    <div style="margin-bottom:24px;">
        <h1 style="font-size:22px;font-weight:700;color:var(--pd-navy);margin:0 0 4px;">Purge GED</h1>
        <p style="font-size:13px;color:var(--pd-muted);margin:0;">
            Suppression définitive des documents supprimés et des versions archivées excédentaires.
            La purge automatique s'exécute chaque nuit à 02h30.
        </p>
    </div>

    @if(session('success'))
    <div style="background:#F0FDF4;border:0.5px solid #86EFAC;color:#065F46;border-radius:8px;padding:10px 16px;margin-bottom:20px;font-size:13px;display:flex;align-items:center;gap:8px;">
        ✓ {{ session('success') }}
    </div>
    @endif

    @if($errors->any())
    <div style="background:#FEF2F2;border:0.5px solid #FCA5A5;color:#991B1B;border-radius:8px;padding:10px 16px;margin-bottom:20px;font-size:13px;">
        {{ $errors->first() }}
    </div>
    @endif

    {{-- ── Configuration ────────────────────────────────────────────── --}}
    <form method="POST" action="{{ route('admin.purge.config.update') }}">
        @csrf @method('PUT')

        <div style="background:var(--pd-surface);border:0.5px solid var(--pd-border);border-radius:12px;margin-bottom:20px;overflow:hidden;">
            <div style="padding:14px 20px;background:var(--pd-surface2);border-bottom:0.5px solid var(--pd-border);display:flex;align-items:center;gap:10px;">
                <div style="width:32px;height:32px;border-radius:8px;background:rgba(30,58,95,0.1);display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0;">⚙️</div>
                <div>
                    <div style="font-size:14px;font-weight:700;color:var(--pd-navy);">Configuration de la purge automatique</div>
                    <div style="font-size:11px;color:var(--pd-muted);">Laissez vide pour désactiver la purge automatique de la catégorie correspondante</div>
                </div>
            </div>
            <div style="padding:20px;display:grid;grid-template-columns:1fr 1fr;gap:24px;">

                {{-- Documents supprimés --}}
                <div>
                    <label class="pd-label" style="display:flex;align-items:center;gap:6px;margin-bottom:6px;">
                        <span style="font-size:16px;">🗑</span> Documents supprimés
                    </label>
                    <div style="display:flex;align-items:center;gap:10px;">
                        <input type="number" name="ged_deleted_retention_days"
                               value="{{ old('ged_deleted_retention_days', $settings->ged_deleted_retention_days) }}"
                               min="1" max="3650" placeholder="—"
                               class="pd-input" style="width:100px;">
                        <span style="font-size:12px;color:var(--pd-muted);">jours après suppression</span>
                    </div>
                    <div style="font-size:11px;color:var(--pd-muted);margin-top:6px;line-height:1.5;">
                        Les documents placés dans la corbeille depuis plus de N jours sont définitivement supprimés de la base de données.
                        Les fichiers physiques ont déjà été supprimés lors de la mise en corbeille.
                    </div>
                </div>

                {{-- Versions archivées --}}
                <div>
                    <label class="pd-label" style="display:flex;align-items:center;gap:6px;margin-bottom:6px;">
                        <span style="font-size:16px;">📋</span> Versions archivées
                    </label>
                    <div style="display:flex;align-items:center;gap:10px;">
                        <input type="number" name="ged_versions_max_count"
                               value="{{ old('ged_versions_max_count', $settings->ged_versions_max_count) }}"
                               min="1" max="100" placeholder="—"
                               class="pd-input" style="width:100px;">
                        <span style="font-size:12px;color:var(--pd-muted);">versions max par document</span>
                    </div>
                    <div style="font-size:11px;color:var(--pd-muted);margin-top:6px;line-height:1.5;">
                        Les versions les plus anciennes au-delà du plafond sont supprimées (base de données + fichier physique).
                        La version courante n'est jamais affectée.
                    </div>
                </div>

            </div>
            <div style="padding:12px 20px;border-top:0.5px solid var(--pd-border);display:flex;justify-content:flex-end;">
                <button type="submit" class="pd-btn pd-btn-primary pd-btn-sm">Enregistrer la configuration</button>
            </div>
        </div>

    </form>

    {{-- ── Purge manuelle ───────────────────────────────────────────── --}}
    <div x-data="purgeManager()" style="background:var(--pd-surface);border:0.5px solid var(--pd-border);border-radius:12px;overflow:hidden;">
        <div style="padding:14px 20px;background:var(--pd-surface2);border-bottom:0.5px solid var(--pd-border);display:flex;align-items:center;gap:10px;">
            <div style="width:32px;height:32px;border-radius:8px;background:rgba(220,38,38,0.1);display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0;">🧹</div>
            <div>
                <div style="font-size:14px;font-weight:700;color:var(--pd-navy);">Purge manuelle</div>
                <div style="font-size:11px;color:var(--pd-muted);">Déclenchez une purge immédiate selon la configuration ci-dessus</div>
            </div>
        </div>
        <div style="padding:20px;">

            {{-- Bouton aperçu --}}
            <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
                <button class="pd-btn pd-btn-sm" @click="preview()" :disabled="_loading">
                    <span x-show="!_loading">🔍 Aperçu</span>
                    <span x-show="_loading">⏳ Analyse…</span>
                </button>
                <button class="pd-btn pd-btn-sm pd-btn-danger" @click="run()"
                        :disabled="_loading || !_previewed"
                        title="Lancez d'abord un aperçu">
                    <span x-show="!_running">🗑 Lancer la purge</span>
                    <span x-show="_running">⏳ Purge en cours…</span>
                </button>
                <span x-show="!_previewed && !_loading" style="font-size:12px;color:var(--pd-muted);">
                    Lancez un aperçu avant d'exécuter la purge.
                </span>
            </div>

            {{-- Résultat aperçu --}}
            <div x-show="_previewed" x-cloak style="margin-top:20px;">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">

                    <div style="padding:16px;border:0.5px solid var(--pd-border);border-radius:8px;">
                        <div style="font-size:11px;font-weight:600;color:var(--pd-muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px;">Documents supprimés</div>
                        <div style="font-size:28px;font-weight:700;color:var(--pd-navy);" x-text="_stats.deleted_docs"></div>
                        <div style="font-size:12px;color:var(--pd-muted);margin-top:4px;">
                            enregistrement(s) à hard-supprimer
                            <span x-show="_stats.deleted_docs_size > 0">
                                · <span x-text="formatSize(_stats.deleted_docs_size)"></span> de versions archivées récupérées
                            </span>
                        </div>
                        <div x-show="_stats.deleted_docs === 0" style="font-size:12px;color:#059669;margin-top:4px;">Rien à purger</div>
                    </div>

                    <div style="padding:16px;border:0.5px solid var(--pd-border);border-radius:8px;">
                        <div style="font-size:11px;font-weight:600;color:var(--pd-muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px;">Versions excédentaires</div>
                        <div style="font-size:28px;font-weight:700;color:var(--pd-navy);" x-text="_stats.excess_versions"></div>
                        <div style="font-size:12px;color:var(--pd-muted);margin-top:4px;">
                            version(s) à supprimer
                            <span x-show="_stats.excess_versions_size > 0">
                                · <span x-text="formatSize(_stats.excess_versions_size)"></span> récupérés
                            </span>
                        </div>
                        <div x-show="_stats.excess_versions === 0" style="font-size:12px;color:#059669;margin-top:4px;">Rien à purger</div>
                    </div>

                </div>
            </div>

            {{-- Résultat après purge --}}
            <div x-show="_done" x-cloak
                 style="margin-top:16px;padding:12px 16px;background:#F0FDF4;border:0.5px solid #86EFAC;color:#065F46;border-radius:8px;font-size:13px;">
                ✓ Purge exécutée —
                <strong x-text="_stats.deleted_docs"></strong> document(s) supprimé(s),
                <strong x-text="_stats.excess_versions"></strong> version(s) archivée(s) purgée(s).
            </div>

            {{-- Erreur --}}
            <div x-show="_error" x-cloak
                 style="margin-top:16px;padding:12px 16px;background:#FEF2F2;border:0.5px solid #FCA5A5;color:#991B1B;border-radius:8px;font-size:13px;"
                 x-text="_error">
            </div>

        </div>

        <div style="padding:12px 20px;border-top:0.5px solid var(--pd-border);font-size:12px;color:var(--pd-muted);">
            Prochaine purge automatique : chaque nuit à <strong>02h30</strong>
            @if($settings->ged_deleted_retention_days === null && $settings->ged_versions_max_count === null)
                — <span style="color:var(--pd-warning, #b45309);">aucun paramètre configuré, la purge automatique est désactivée</span>
            @endif
        </div>
    </div>

</div>

@push('scripts')
<script>
function purgeManager() {
    return {
        _loading: false,
        _running: false,
        _previewed: false,
        _done: false,
        _error: '',
        _stats: { deleted_docs: 0, deleted_docs_size: 0, excess_versions: 0, excess_versions_size: 0 },

        async preview() {
            this._loading = true;
            this._error = '';
            this._done = false;
            try {
                const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
                const resp = await fetch('{{ route('admin.purge.preview') }}', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                });
                const data = await resp.json();
                if (!resp.ok) { this._error = data.message || 'Erreur lors de l\'analyse.'; return; }
                this._stats = data.stats;
                this._previewed = true;
            } catch {
                this._error = 'Erreur réseau lors de l\'analyse.';
            } finally {
                this._loading = false;
            }
        },

        async run() {
            if (!confirm('Confirmer la purge définitive ? Cette action est irréversible.')) return;
            this._running = true;
            this._loading = true;
            this._error = '';
            try {
                const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
                const resp = await fetch('{{ route('admin.purge.run') }}', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                });
                const data = await resp.json();
                if (!resp.ok) { this._error = data.message || 'Erreur lors de la purge.'; return; }
                this._stats = data.stats;
                this._done = true;
                this._previewed = false;
            } catch {
                this._error = 'Erreur réseau lors de la purge.';
            } finally {
                this._running = false;
                this._loading = false;
            }
        },

        formatSize(bytes) {
            if (!bytes) return '0 o';
            if (bytes < 1024) return bytes + ' o';
            if (bytes < 1024 * 1024) return Math.round(bytes / 1024 * 10) / 10 + ' Ko';
            return (Math.round(bytes / 1024 / 1024 * 10) / 10).toString().replace('.', ',') + ' Mo';
        },
    };
}
</script>
@endpush

@endsection
