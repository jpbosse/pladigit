<div style="max-width:1180px;margin:32px auto;padding:0 20px;">
<div class="dg-import-flex" style="display:flex;gap:24px;align-items:flex-start;">

{{-- ── Sidebar gauche — grilles existantes ────────────────────── --}}
<div class="dg-import-sidebar" style="width:280px;flex-shrink:0;position:sticky;top:24px;">
    <div style="background:var(--pd-surface);border:0.5px solid var(--pd-border);
                border-radius:12px;overflow:hidden;">
        <div style="padding:11px 16px;border-bottom:0.5px solid var(--pd-border);
                    display:flex;align-items:center;justify-content:space-between;">
            <span style="font-size:11px;font-weight:600;color:var(--pd-muted);
                         text-transform:uppercase;letter-spacing:.05em;">
                Grilles existantes
            </span>
            <span style="font-size:11px;background:var(--pd-bg2);color:var(--pd-muted);
                         padding:1px 8px;border-radius:10px;">
                {{ count($existingGrids) }}
            </span>
        </div>
        <div style="max-height:62vh;overflow-y:auto;">
            @forelse($existingGrids as $grid)
            <div style="padding:9px 16px;border-bottom:0.5px solid var(--pd-border);">
                <div style="font-size:12px;font-weight:600;color:var(--pd-text);
                             white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                    {{ $grid['label'] }}
                </div>
                <div style="font-size:10px;color:var(--pd-muted);font-family:monospace;
                             margin-top:2px;">
                    {{ $grid['name'] }}
                </div>
                <div style="font-size:10px;color:var(--pd-muted);margin-top:2px;">
                    {{ $grid['columns_count'] }} col.
                </div>
            </div>
            @empty
            <div style="padding:20px;text-align:center;font-size:12px;
                         color:var(--pd-muted);font-style:italic;">
                Aucune grille définie
            </div>
            @endforelse
        </div>
    </div>
</div>

{{-- ── Zone wizard droite ───────────────────────────────────────── --}}
<div style="flex:1;min-width:0;">

    {{-- ── Fil d'Ariane ─────────────────────────────────────────── --}}
    <div style="margin-bottom:20px;font-size:12px;color:var(--pd-muted);">
        <a href="{{ route('dashboard') }}" style="color:var(--pd-muted);text-decoration:none;">Tableau de bord</a>
        <span style="margin:0 6px;">›</span>
        <span style="color:var(--pd-text);">Import DataGrid</span>
    </div>

    {{-- ── Indicateur d'étapes ───────────────────────────────────── --}}
    <div style="display:flex;align-items:center;gap:0;margin-bottom:28px;">
        @foreach([1 => 'Fichier', 2 => 'Colonnes', 3 => 'Import'] as $n => $label)
        <div style="display:flex;align-items:center;flex:1;">
            <div style="display:flex;align-items:center;gap:8px;">
                <div style="width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;
                            font-size:12px;font-weight:700;flex-shrink:0;
                            background:{{ $step >= $n ? 'var(--pd-navy)' : 'var(--pd-bg2)' }};
                            color:{{ $step >= $n ? '#fff' : 'var(--pd-muted)' }};">
                    {{ $step > $n ? '✓' : $n }}
                </div>
                <span style="font-size:13px;font-weight:{{ $step === $n ? '600' : '400' }};
                             color:{{ $step === $n ? 'var(--pd-text)' : 'var(--pd-muted)' }};">
                    {{ $label }}
                </span>
            </div>
            @if($n < 3)
            <div style="flex:1;height:1px;background:var(--pd-border);margin:0 12px;"></div>
            @endif
        </div>
        @endforeach
    </div>

    {{-- ══════════════════════════════════════════════════════════════ --}}
    {{-- ÉTAPE 1 — Upload                                            --}}
    {{-- ══════════════════════════════════════════════════════════════ --}}
    @if($step === 1)
    <div style="background:var(--pd-surface);border:0.5px solid var(--pd-border);border-radius:14px;overflow:hidden;">

        <div style="background:var(--pd-navy);padding:18px 24px;">
            <div style="font-size:16px;font-weight:700;color:#fff;">Importer un fichier Excel</div>
            <div style="font-size:12px;color:rgba(255,255,255,.65);margin-top:3px;">
                La première ligne doit contenir les noms de colonnes.
            </div>
        </div>

        <div style="padding:24px;">
            @error('file')
            <div style="padding:10px 14px;background:#FEE2E2;color:#991B1B;border-radius:8px;margin-bottom:16px;font-size:12px;">
                ⚠ {{ $message }}
            </div>
            @enderror

            <div class="pd-form-group">
                <label class="pd-label pd-label-req">Fichier Excel (.xlsx / .xls)</label>
                <input type="file" wire:model="file" accept=".xlsx,.xls"
                       style="display:block;width:100%;padding:8px 10px;border:0.5px solid var(--pd-border);
                              border-radius:8px;font-size:13px;color:var(--pd-text);
                              background:var(--pd-bg);cursor:pointer;">
                <div style="font-size:11px;color:var(--pd-muted);margin-top:5px;">
                    Taille maximale : 10 Mo
                </div>
            </div>

            <div style="background:var(--pd-bg2);border-radius:8px;padding:12px 14px;
                        font-size:12px;color:var(--pd-muted);line-height:1.7;margin-bottom:20px;">
                <strong style="color:var(--pd-text);">Format attendu :</strong><br>
                • La ligne 1 contient les <strong>en-têtes de colonnes</strong> (ex : Nom, Prénom, Email…).<br>
                • Les lignes suivantes contiennent les données à importer.<br>
                • Les colonnes seront proposées à la configuration à l'étape suivante.
            </div>

            <div style="display:flex;justify-content:flex-end;">
                <button wire:click="uploadFile" wire:loading.attr="disabled"
                        style="padding:9px 22px;background:var(--pd-navy);color:#fff;border:none;
                               border-radius:9px;font-size:13px;font-weight:600;cursor:pointer;">
                    <span wire:loading.remove wire:target="uploadFile">Analyser le fichier →</span>
                    <span wire:loading wire:target="uploadFile">Lecture en cours…</span>
                </button>
            </div>
        </div>
    </div>
    @endif

    {{-- ══════════════════════════════════════════════════════════════ --}}
    {{-- ÉTAPE 2 — Configuration des colonnes                        --}}
    {{-- ══════════════════════════════════════════════════════════════ --}}
    @if($step === 2)
    <div style="background:var(--pd-surface);border:0.5px solid var(--pd-border);border-radius:14px;overflow:hidden;">

        <div style="background:var(--pd-navy);padding:18px 24px;">
            <div style="font-size:16px;font-weight:700;color:#fff;">
                Configuration de la grille
            </div>
            <div style="font-size:12px;color:rgba(255,255,255,.65);margin-top:3px;">
                {{ count($columns) }} colonne(s) détectée(s) — définissez le type et les options.
            </div>
        </div>

        <div style="padding:24px;">

            {{-- Erreurs globales --}}
            @if($errors->any())
            <div style="padding:10px 14px;background:#FEE2E2;color:#991B1B;border-radius:8px;
                        margin-bottom:16px;font-size:12px;line-height:1.7;">
                @foreach($errors->all() as $e)
                <div>⚠ {{ $e }}</div>
                @endforeach
            </div>
            @endif

            {{-- Informations de la grille --}}
            <div style="margin-bottom:20px;padding:16px;background:var(--pd-bg2);border-radius:10px;">
                <div style="font-size:12px;font-weight:600;color:var(--pd-text);margin-bottom:12px;
                            text-transform:uppercase;letter-spacing:.04em;">
                    Informations de la grille
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                    <div class="pd-form-group" style="margin:0;">
                        <label class="pd-label pd-label-req">Libellé</label>
                        <input type="text" wire:model.live.debounce.400ms="tableLabel"
                               class="pd-input" placeholder="Ex : Liste des associations"
                               style="width:100%;">
                        @error('tableLabel')
                        <div style="font-size:11px;color:#991B1B;margin-top:3px;">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="pd-form-group" style="margin:0;">
                        <label class="pd-label pd-label-req">Nom technique (table MySQL)</label>
                        <input type="text" wire:model="tableName"
                               class="pd-input" placeholder="Ex : associations"
                               style="width:100%;font-family:monospace;font-size:11px;">
                        @error('tableName')
                        <div style="font-size:11px;color:#991B1B;margin-top:3px;">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="pd-form-group" style="margin-top:12px;margin-bottom:0;">
                    <label class="pd-label">Description (optionnelle)</label>
                    <input type="text" wire:model="tableDescription"
                           class="pd-input" placeholder="Description courte de cette grille"
                           style="width:100%;font-size:11px;color:var(--pd-muted);">
                </div>

                <div style="margin-top:12px;display:flex;align-items:center;gap:8px;">
                    <input type="checkbox" wire:model="hasRgpd" id="hasRgpd"
                           style="width:15px;height:15px;cursor:pointer;">
                    <label for="hasRgpd" style="font-size:12px;color:var(--pd-text);cursor:pointer;">
                        Contient des données personnelles (RGPD — active l'audit trail)
                    </label>
                </div>
            </div>

            {{-- Tableau des colonnes --}}
            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:13px;">
                    <thead>
                        <tr style="border-bottom:1.5px solid var(--pd-border);">
                            <th style="text-align:left;padding:8px 10px;color:var(--pd-muted);font-weight:600;white-space:nowrap;">
                                En-tête Excel
                            </th>
                            <th style="text-align:left;padding:8px 10px;color:var(--pd-muted);font-weight:600;">
                                Libellé affiché
                            </th>
                            <th style="text-align:left;padding:8px 10px;color:var(--pd-muted);font-weight:600;white-space:nowrap;">
                                Nom technique
                            </th>
                            <th style="text-align:left;padding:8px 10px;color:var(--pd-muted);font-weight:600;">
                                Type
                            </th>
                            <th style="text-align:center;padding:8px 10px;color:var(--pd-muted);font-weight:600;">
                                Requis
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($columns as $i => $col)
                        <tr style="border-bottom:0.5px solid var(--pd-border);">

                            <td style="padding:8px 10px;color:var(--pd-muted);font-size:12px;white-space:nowrap;">
                                {{ $col['header'] }}
                            </td>

                            <td style="padding:6px 10px;">
                                <input type="text"
                                       wire:model="columns.{{ $i }}.label"
                                       class="pd-input"
                                       style="width:100%;min-width:120px;padding:5px 8px;font-size:12px;">
                                @error("columns.{$i}.label")
                                <div style="font-size:10px;color:#991B1B;">{{ $message }}</div>
                                @enderror
                            </td>

                            <td style="padding:6px 10px;">
                                <input type="text"
                                       wire:model="columns.{{ $i }}.name"
                                       class="pd-input"
                                       style="width:100%;min-width:100px;padding:5px 8px;
                                              font-size:11px;font-family:monospace;">
                                @error("columns.{$i}.name")
                                <div style="font-size:10px;color:#991B1B;">{{ $message }}</div>
                                @enderror
                            </td>

                            <td style="padding:6px 10px;">
                                <select wire:model="columns.{{ $i }}.type"
                                        style="width:100%;min-width:130px;padding:5px 8px;
                                               border:0.5px solid var(--pd-border);border-radius:6px;
                                               font-size:12px;background:var(--pd-bg);color:var(--pd-text);">
                                    @foreach($columnTypes as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error("columns.{$i}.type")
                                <div style="font-size:10px;color:#991B1B;">{{ $message }}</div>
                                @enderror
                            </td>

                            <td style="padding:6px 10px;text-align:center;">
                                <input type="checkbox"
                                       wire:model="columns.{{ $i }}.required"
                                       style="width:15px;height:15px;cursor:pointer;">
                            </td>

                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div style="display:flex;justify-content:space-between;margin-top:20px;">
                <button wire:click="backToStep1"
                        style="padding:9px 18px;background:var(--pd-bg2);color:var(--pd-muted);
                               border:0.5px solid var(--pd-border);border-radius:9px;
                               font-size:13px;cursor:pointer;">
                    ← Retour
                </button>
                <button wire:click="confirmColumns"
                        style="padding:9px 22px;background:var(--pd-navy);color:#fff;border:none;
                               border-radius:9px;font-size:13px;font-weight:600;cursor:pointer;">
                    Vérifier et continuer →
                </button>
            </div>
        </div>
    </div>
    @endif

    {{-- ══════════════════════════════════════════════════════════════ --}}
    {{-- ÉTAPE 3 — Confirmation et lancement                         --}}
    {{-- ══════════════════════════════════════════════════════════════ --}}
    @if($step === 3)
    <div style="background:var(--pd-surface);border:0.5px solid var(--pd-border);border-radius:14px;overflow:hidden;">

        <div style="background:var(--pd-navy);padding:18px 24px;">
            <div style="font-size:16px;font-weight:700;color:#fff;">Confirmation de l'import</div>
            <div style="font-size:12px;color:rgba(255,255,255,.65);margin-top:3px;">
                Vérifiez les paramètres avant de lancer l'import.
            </div>
        </div>

        <div style="padding:24px;">

            @if($errorMessage)
            <div style="padding:12px 14px;background:#FEE2E2;color:#991B1B;border-radius:8px;
                        margin-bottom:16px;font-size:12px;line-height:1.6;">
                ⚠ <strong>Erreur lors de l'import :</strong><br>{{ $errorMessage }}
            </div>
            @endif

            @if($importedTableId)
            {{-- ── Succès ──────────────────────────────────── --}}
            <div style="text-align:center;padding:24px 0;">
                <div style="font-size:40px;margin-bottom:12px;">✅</div>
                <div style="font-size:17px;font-weight:700;color:var(--pd-text);margin-bottom:6px;">
                    Import terminé avec succès
                </div>
                <div style="font-size:13px;color:var(--pd-muted);">
                    {{ $importedRows }} ligne(s) importée(s) dans la grille
                    <strong>{{ $tableLabel }}</strong>
                </div>
            </div>
            @else
            {{-- ── Récapitulatif ────────────────────────────── --}}
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:20px;">
                <div style="background:var(--pd-bg2);border-radius:8px;padding:14px;">
                    <div style="font-size:11px;color:var(--pd-muted);font-weight:600;text-transform:uppercase;letter-spacing:.04em;margin-bottom:6px;">
                        Grille
                    </div>
                    <div style="font-size:14px;font-weight:600;color:var(--pd-text);">{{ $tableLabel }}</div>
                    <div style="font-size:11px;color:var(--pd-muted);margin-top:2px;font-family:monospace;">
                        table : {{ $tableName }}
                    </div>
                    @if($tableDescription)
                    <div style="font-size:12px;color:var(--pd-muted);margin-top:4px;">{{ $tableDescription }}</div>
                    @endif
                    @if($hasRgpd)
                    <div style="margin-top:6px;font-size:11px;color:#d97706;background:#FEF3C7;
                                padding:2px 8px;border-radius:10px;display:inline-block;">
                        RGPD activé
                    </div>
                    @endif
                </div>

                <div style="background:var(--pd-bg2);border-radius:8px;padding:14px;">
                    <div style="font-size:11px;color:var(--pd-muted);font-weight:600;text-transform:uppercase;letter-spacing:.04em;margin-bottom:6px;">
                        Colonnes
                    </div>
                    <div style="font-size:14px;font-weight:600;color:var(--pd-text);">
                        {{ count($columns) }} colonne(s)
                    </div>
                    <div style="font-size:11px;color:var(--pd-muted);margin-top:4px;line-height:1.8;">
                        @foreach($columns as $col)
                        <div>
                            <span style="font-family:monospace;">{{ $col['name'] }}</span>
                            <span style="color:var(--pd-accent);">({{ $columnTypes[$col['type']] ?? $col['type'] }})</span>
                            @if($col['required'])
                            <span style="color:#991B1B;">*</span>
                            @endif
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div style="background:#EFF6FF;border:0.5px solid #BFDBFE;border-radius:8px;
                        padding:12px 14px;font-size:12px;color:#1e40af;margin-bottom:20px;line-height:1.7;">
                Cette action va :<br>
                • Créer l'entrée <strong>{{ $tableLabel }}</strong> dans le registre des grilles DataGrid<br>
                • Créer la table MySQL <code style="font-family:monospace;">{{ $tableName }}</code> dans la base tenant<br>
                • Importer les lignes de données depuis le fichier Excel
            </div>

            <div style="display:flex;justify-content:space-between;">
                <button wire:click="backToStep2"
                        style="padding:9px 18px;background:var(--pd-bg2);color:var(--pd-muted);
                               border:0.5px solid var(--pd-border);border-radius:9px;
                               font-size:13px;cursor:pointer;">
                    ← Modifier les colonnes
                </button>
                <button wire:click="runImport" wire:loading.attr="disabled"
                        style="padding:9px 22px;background:#16a34a;color:#fff;border:none;
                               border-radius:9px;font-size:13px;font-weight:600;cursor:pointer;">
                    <span wire:loading.remove wire:target="runImport">Lancer l'import</span>
                    <span wire:loading wire:target="runImport">Import en cours…</span>
                </button>
            </div>
            @endif

        </div>
    </div>
    @endif

</div>{{-- /zone wizard --}}
</div>{{-- /flex --}}

<style>
@media (max-width: 800px) {
    .dg-import-flex { flex-direction: column !important; }
    .dg-import-sidebar { width: 100% !important; position: static !important; }
}
</style>
</div>{{-- /wrapper --}}
