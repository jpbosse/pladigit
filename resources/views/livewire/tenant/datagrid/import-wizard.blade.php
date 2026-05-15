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
    @php
        $hasUnmatched = count($unmatchedColumns) > 0;
        if ($importMode === 'update') {
            $stepLabels  = $hasUnmatched
                ? [1 => 'Fichier', 2 => 'Correspondance', 3 => 'Import']
                : [1 => 'Fichier', 2 => 'Import'];
            $displayStep = (! $hasUnmatched && $step === 3) ? 2 : $step;
            $totalSteps  = $hasUnmatched ? 3 : 2;
        } else {
            $stepLabels  = [1 => 'Fichier', 2 => 'Colonnes', 3 => 'Import'];
            $displayStep = $step;
            $totalSteps  = 3;
        }
    @endphp
    <div style="display:flex;align-items:center;gap:0;margin-bottom:28px;">
        @foreach($stepLabels as $n => $label)
        <div style="display:flex;align-items:center;flex:1;">
            <div style="display:flex;align-items:center;gap:8px;">
                <div style="width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;
                            font-size:12px;font-weight:700;flex-shrink:0;
                            background:{{ $displayStep >= $n ? 'var(--pd-navy)' : 'var(--pd-bg2)' }};
                            color:{{ $displayStep >= $n ? '#fff' : 'var(--pd-muted)' }};">
                    {{ $displayStep > $n ? '✓' : $n }}
                </div>
                <span style="font-size:13px;font-weight:{{ $displayStep === $n ? '600' : '400' }};
                             color:{{ $displayStep === $n ? 'var(--pd-text)' : 'var(--pd-muted)' }};">
                    {{ $label }}
                </span>
            </div>
            @if($n < $totalSteps)
            <div style="flex:1;height:1px;background:var(--pd-border);margin:0 12px;"></div>
            @endif
        </div>
        @endforeach
    </div>

    {{-- ══════════════════════════════════════════════════════════════ --}}
    {{-- ÉTAPE 1 — Upload + choix du mode                            --}}
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

            {{-- Mode d'import --}}
            <div style="margin-bottom:20px;padding:16px;background:var(--pd-bg2);border-radius:10px;">
                <div style="font-size:12px;font-weight:600;color:var(--pd-text);margin-bottom:12px;
                            text-transform:uppercase;letter-spacing:.04em;">
                    Que souhaitez-vous faire ?
                </div>

                <label style="display:flex;align-items:flex-start;gap:10px;font-size:13px;cursor:pointer;margin-bottom:12px;">
                    <input type="radio" wire:model.live="importMode" name="importMode" value="new"
                           style="margin-top:2px;width:15px;height:15px;cursor:pointer;flex-shrink:0;">
                    <span>
                        <strong>Créer une nouvelle grille</strong>
                        <span style="display:block;font-size:11px;color:var(--pd-muted);margin-top:2px;">
                            Importer un fichier Excel et créer une nouvelle table de données
                        </span>
                    </span>
                </label>

                <label style="display:flex;align-items:flex-start;gap:10px;font-size:13px;cursor:pointer;">
                    <input type="radio" wire:model.live="importMode" name="importMode" value="update"
                           style="margin-top:2px;width:15px;height:15px;cursor:pointer;flex-shrink:0;">
                    <span>
                        <strong>Mettre à jour une grille existante</strong>
                        <span style="display:block;font-size:11px;color:var(--pd-muted);margin-top:2px;">
                            Ajouter ou remplacer des données dans une grille déjà créée
                        </span>
                    </span>
                </label>

                @if($importMode === 'update')
                <div style="margin-top:16px;padding-top:16px;border-top:0.5px solid var(--pd-border);">

                    @error('targetTableId')
                    <div style="padding:8px 12px;background:#FEE2E2;color:#991B1B;border-radius:6px;margin-bottom:10px;font-size:12px;">
                        ⚠ {{ $message }}
                    </div>
                    @enderror

                    <div style="margin-bottom:14px;">
                        <label style="font-size:12px;font-weight:600;color:var(--pd-text);display:block;margin-bottom:6px;">
                            Grille cible
                        </label>
                        @if(count($existingGrids) === 0)
                        <div style="font-size:12px;color:var(--pd-muted);font-style:italic;">
                            Aucune grille disponible — créez-en une d'abord.
                        </div>
                        @else
                        <select wire:model="targetTableId"
                                style="width:100%;padding:7px 10px;border:0.5px solid var(--pd-border);
                                       border-radius:7px;font-size:13px;background:var(--pd-bg);color:var(--pd-text);">
                            <option value="">— Choisir une grille —</option>
                            @foreach($existingGrids as $grid)
                            <option value="{{ $grid['id'] }}">{{ $grid['label'] }} ({{ $grid['columns_count'] }} col.)</option>
                            @endforeach
                        </select>
                        @endif

                        <div style="margin-top:10px;">
                            <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer;">
                                <input type="checkbox" wire:model="fileHasHeader"
                                       style="width:15px;height:15px;cursor:pointer;">
                                Mon fichier Excel contient une ligne d'en-tête (noms de colonnes)
                            </label>
                        </div>
                    </div>

                    <div>
                        <div style="font-size:12px;font-weight:600;color:var(--pd-text);margin-bottom:8px;">
                            Mode de mise à jour
                        </div>
                        <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer;margin-bottom:8px;">
                            <input type="radio" wire:model="updateMode" name="updateMode" value="append"
                                   style="width:15px;height:15px;cursor:pointer;">
                            <span>
                                <strong>Ajouter aux données existantes</strong>
                                <span style="font-size:11px;color:var(--pd-muted);margin-left:6px;">(INSERT — lignes actuelles conservées)</span>
                            </span>
                        </label>
                        <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer;">
                            <input type="radio" wire:model="updateMode" name="updateMode" value="replace"
                                   style="width:15px;height:15px;cursor:pointer;">
                            <span>
                                <strong>Remplacer toutes les données</strong>
                                <span style="font-size:11px;color:#dc2626;margin-left:6px;">(TRUNCATE + INSERT — toutes les lignes actuelles sont effacées)</span>
                            </span>
                        </label>
                    </div>
                </div>
                @endif
            </div>

            {{-- Fichier --}}
            <div class="pd-form-group">
                <label class="pd-label pd-label-req">Fichier (.xlsx / .xls / .csv / .ods)</label>
                <input type="file" wire:model="file" accept=".xlsx,.xls,.csv,.ods"
                       id="datagrid-file-input"
                       style="display:block;width:100%;padding:8px 10px;border:0.5px solid var(--pd-border);
                              border-radius:8px;font-size:13px;color:var(--pd-text);
                              background:var(--pd-bg);cursor:pointer;"
                       onchange="checkFileSize(this)">
                <div id="datagrid-file-size-error"
                     style="display:none;padding:8px 12px;background:#FEE2E2;color:#991B1B;
                            border-radius:6px;margin-top:6px;font-size:12px;">
                </div>
                <div style="font-size:11px;color:var(--pd-muted);margin-top:5px;">
                    Taille maximale : 10 Mo — pour les fichiers plus volumineux, découpez-les en plusieurs fichiers.
                </div>
                <script>
                function checkFileSize(input) {
                    var maxBytes = 10 * 1024 * 1024; // 10 Mo
                    var err = document.getElementById('datagrid-file-size-error');
                    if (input.files && input.files[0] && input.files[0].size > maxBytes) {
                        var sizeMo = (input.files[0].size / 1024 / 1024).toFixed(1);
                        err.textContent = '❌ Ce fichier fait ' + sizeMo + ' Mo — la taille maximale est de 10 Mo. Découpez-le en plusieurs fichiers et importez-les séparément.';
                        err.style.display = 'block';
                        input.value = '';
                        // Réinitialiser le composant Livewire
                        @this.set('file', null);
                    } else {
                        err.style.display = 'none';
                    }
                }
                </script>
            </div>

            <div style="background:var(--pd-bg2);border-radius:8px;padding:12px 14px;
                        font-size:12px;color:var(--pd-muted);line-height:1.7;margin-bottom:20px;">
                <strong style="color:var(--pd-text);">Format attendu :</strong><br>
                • La ligne 1 contient les <strong>en-têtes de colonnes</strong> (ex : Nom, Prénom, Email…).<br>
                • Les lignes suivantes contiennent les données à importer.<br>
                • Formats acceptés : <strong>.xlsx, .xls, .csv, .ods</strong><br>
                @if($importMode === 'update')
                • Les en-têtes doivent correspondre aux noms de colonnes de la grille cible.
                @else
                • Les colonnes seront proposées à la configuration à l'étape suivante.
                @endif
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
    {{-- ÉTAPE 2 — Mapping colonnes (mode 'update') ou config (mode 'new') --}}
    {{-- ══════════════════════════════════════════════════════════════ --}}
    @if($step === 2 && $importMode === 'update')
    <div style="background:var(--pd-surface);border:0.5px solid var(--pd-border);border-radius:14px;overflow:hidden;">

        <div style="background:var(--pd-navy);padding:18px 24px;">
            <div style="font-size:16px;font-weight:700;color:#fff;">Correspondance des colonnes</div>
            <div style="font-size:12px;color:rgba(255,255,255,.65);margin-top:3px;">
                {{ count($unmatchedColumns) }} colonne(s) du fichier ne correspondent pas à la grille — définissez leur correspondance.
            </div>
        </div>

        <div style="padding:24px;">

            <div style="background:var(--pd-bg2);border-radius:8px;padding:12px 14px;
                        font-size:12px;color:var(--pd-muted);line-height:1.7;margin-bottom:20px;">
                Les colonnes dont le nom correspond exactement à la grille sont mappées automatiquement.
                Pour les autres, choisissez la colonne de destination ou sélectionnez <strong>« — Ne pas importer —»</strong>.
            </div>

            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:13px;">
                    <thead>
                        <tr style="border-bottom:1.5px solid var(--pd-border);">
                            <th style="text-align:left;padding:8px 10px;color:var(--pd-muted);font-weight:600;white-space:nowrap;">
                                Colonne dans le fichier Excel
                            </th>
                            <th style="text-align:left;padding:8px 10px;color:var(--pd-muted);font-weight:600;">
                                Colonne de destination dans la grille
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($unmatchedColumns as $unmatched)
                        <tr style="border-bottom:0.5px solid var(--pd-border);">
                            <td style="padding:8px 10px;font-size:12px;color:var(--pd-text);font-family:monospace;white-space:nowrap;">
                                {{ $unmatched['header'] }}
                            </td>
                            <td style="padding:6px 10px;">
                                <select wire:model="columnMapping.{{ $unmatched['index'] }}"
                                        style="width:100%;min-width:200px;padding:6px 10px;
                                               border:0.5px solid var(--pd-border);border-radius:7px;
                                               font-size:12px;background:var(--pd-bg);color:var(--pd-text);">
                                    <option value="">— Ne pas importer cette colonne —</option>
                                    @foreach($gridColumns as $gridCol)
                                    <option value="{{ $gridCol['name'] }}">{{ $gridCol['label'] }} ({{ $gridCol['name'] }})</option>
                                    @endforeach
                                </select>
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
                <button wire:click="confirmMapping"
                        style="padding:9px 22px;background:var(--pd-navy);color:#fff;border:none;
                               border-radius:9px;font-size:13px;font-weight:600;cursor:pointer;">
                    Confirmer et continuer →
                </button>
            </div>
        </div>
    </div>
    @endif

    @if($step === 2 && $importMode === 'new')
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

            {{-- Cartes colonnes --}}
            <div style="display:flex;flex-direction:column;gap:10px;">
                @foreach($columns as $i => $col)
                @php $colType = $col['type'] ?? ''; @endphp
                <div style="border:0.5px solid var(--pd-border);border-radius:10px;overflow:hidden;">

                    {{-- En-tête de la carte --}}
                    <div style="display:flex;align-items:center;justify-content:space-between;
                                padding:8px 14px;background:var(--pd-bg2);
                                border-bottom:0.5px solid var(--pd-border);">
                        <span style="font-size:11px;font-family:monospace;color:var(--pd-muted);font-weight:600;">
                            {{ $col['header'] }}
                        </span>
                        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:11px;color:var(--pd-muted);">
                            <input type="checkbox"
                                   wire:model="columns.{{ $i }}.required"
                                   style="width:13px;height:13px;cursor:pointer;">
                            Obligatoire
                        </label>
                    </div>

                    {{-- Corps de la carte --}}
                    <div style="padding:12px 14px;display:flex;flex-direction:column;gap:10px;">

                        {{-- Ligne 1 : Libellé + Nom technique --}}
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;align-items:start;">

                            {{-- Libellé --}}
                            <div>
                                <div style="font-size:10px;font-weight:600;color:var(--pd-muted);
                                            text-transform:uppercase;letter-spacing:.04em;margin-bottom:4px;">
                                    Libellé affiché
                                </div>
                                <input type="text"
                                       wire:model="columns.{{ $i }}.label"
                                       class="pd-input"
                                       style="width:100%;padding:5px 8px;font-size:12px;">
                                @error("columns.{$i}.label")
                                <div style="font-size:10px;color:#991B1B;margin-top:2px;">{{ $message }}</div>
                                @enderror
                            </div>

                            {{-- Nom technique --}}
                            <div>
                                <div style="font-size:10px;font-weight:600;color:var(--pd-muted);
                                            text-transform:uppercase;letter-spacing:.04em;margin-bottom:4px;">
                                    Nom technique
                                </div>
                                <input type="text"
                                       wire:model="columns.{{ $i }}.name"
                                       class="pd-input"
                                       style="width:100%;padding:5px 8px;font-size:11px;font-family:monospace;">
                                @error("columns.{$i}.name")
                                <div style="font-size:10px;color:#991B1B;margin-top:2px;">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        {{-- Ligne 2 : Type + Options --}}
                        <div style="display:grid;grid-template-columns:160px 1fr;gap:12px;align-items:start;">

                        {{-- Type --}}
                        <div>
                            <div style="font-size:10px;font-weight:600;color:var(--pd-muted);
                                        text-transform:uppercase;letter-spacing:.04em;margin-bottom:4px;">
                                Type
                            </div>
                            <select wire:model.live="columns.{{ $i }}.type"
                                    style="width:100%;padding:5px 8px;border:0.5px solid var(--pd-border);
                                           border-radius:6px;font-size:12px;
                                           background:var(--pd-bg);color:var(--pd-text);">
                                @foreach($columnTypes as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            @error("columns.{$i}.type")
                            <div style="font-size:10px;color:#991B1B;margin-top:2px;">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- Options (booléen ou select) --}}
                        <div>
                            <div style="font-size:10px;font-weight:600;color:var(--pd-muted);
                                        text-transform:uppercase;letter-spacing:.04em;margin-bottom:4px;">
                                Options
                            </div>

                            @if($colType === \App\Enums\DatagridColumnType::BOOLEAN->value)
                            {{-- Aperçu des valeurs détectées --}}
                            @php $samples = $sampleValues[$col['index']] ?? []; @endphp
                            @if(count($samples) > 0)
                            <div style="margin-bottom:6px;padding:5px 8px;background:#FEF3C7;
                                        border-radius:6px;font-size:10px;color:#92400e;line-height:1.6;">
                                Valeurs détectées dans le fichier :
                                @foreach($samples as $sv)
                                <code style="background:#FDE68A;padding:1px 4px;border-radius:3px;margin-left:3px;">{{ $sv }}</code>
                                @endforeach
                                <span style="display:block;margin-top:2px;color:#b45309;">
                                    → Indiquez ci-dessous quelle valeur correspond à <strong>Vrai</strong> et laquelle à <strong>Faux</strong>.
                                    Si ce n'est pas une valeur binaire, préférez le type <strong>Liste</strong>.
                                </span>
                            </div>
                            @endif
                            <div style="display:flex;flex-direction:column;gap:4px;">
                                <div style="display:flex;align-items:center;gap:6px;">
                                    <span style="font-size:10px;color:#16a34a;font-weight:600;width:32px;flex-shrink:0;">Vrai</span>
                                    <input type="text"
                                           wire:model="columns.{{ $i }}.label_true"
                                           placeholder="ex: Occupé"
                                           maxlength="50"
                                           style="flex:1;padding:4px 7px;border:0.5px solid var(--pd-border);
                                                  border-radius:5px;font-size:11px;
                                                  background:var(--pd-bg);color:var(--pd-text);">
                                </div>
                                <div style="display:flex;align-items:center;gap:6px;">
                                    <span style="font-size:10px;color:#dc2626;font-weight:600;width:32px;flex-shrink:0;">Faux</span>
                                    <input type="text"
                                           wire:model="columns.{{ $i }}.label_false"
                                           placeholder="ex: Libre"
                                           maxlength="50"
                                           style="flex:1;padding:4px 7px;border:0.5px solid var(--pd-border);
                                                  border-radius:5px;font-size:11px;
                                                  background:var(--pd-bg);color:var(--pd-text);">
                                </div>
                            </div>

                            @elseif($colType === \App\Enums\DatagridColumnType::SELECT->value)
                            <div>
                                @php
                                    $samples   = $sampleValues[$col['index']] ?? [];
                                    $rawOpts   = $col['options_raw'] ?? '';
                                    $countOpts = $rawOpts !== ''
                                        ? count(array_filter(array_map('trim', explode(',', $rawOpts))))
                                        : 0;
                                @endphp
                                @if(count($samples) > 0 && $rawOpts === '')
                                <div style="margin-bottom:6px;padding:5px 8px;background:#EFF6FF;
                                            border-radius:6px;font-size:10px;color:#1e40af;line-height:1.6;">
                                    Valeurs détectées :
                                    @foreach($samples as $sv)
                                    <code style="background:#DBEAFE;padding:1px 4px;border-radius:3px;margin-left:3px;">{{ $sv }}</code>
                                    @endforeach
                                </div>
                                @endif
                                <input type="text"
                                       wire:model="columns.{{ $i }}.options_raw"
                                       placeholder="ex: M,F  ou  Actif,Inactif,Suspendu"
                                       style="width:100%;padding:4px 7px;border:0.5px solid var(--pd-border);
                                              border-radius:5px;font-size:11px;
                                              background:var(--pd-bg);color:var(--pd-text);">
                                <div style="font-size:10px;color:var(--pd-muted);margin-top:3px;">
                                    Valeurs séparées par des virgules — ou <strong style="color:var(--pd-text);">laisser vide</strong> pour récupérer automatiquement toutes les valeurs distinctes présentes dans les enregistrements.
                                    @if($countOpts === 2)
                                    <span style="color:#16a34a;">→ Toggle ({{ $countOpts }} valeurs).</span>
                                    @elseif($countOpts > 2)
                                    <span style="color:var(--pd-navy);">→ Liste déroulante ({{ $countOpts }} valeurs).</span>
                                    @endif
                                </div>
                                @error("columns.{$i}.options_raw")
                                <div style="font-size:10px;color:#991B1B;margin-top:2px;">{{ $message }}</div>
                                @enderror
                            </div>

                            @else
                            <span style="font-size:11px;color:var(--pd-muted);">—</span>
                            @endif
                        </div>

                        </div>{{-- /ligne 2 --}}
                    </div>{{-- /corps --}}
                </div>{{-- /carte --}}
                @endforeach
            </div>{{-- /cartes --}}

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
    @endif {{-- fin mode 'new' --}}

    {{-- ══════════════════════════════════════════════════════════════ --}}
    {{-- ÉTAPE 3 — Confirmation et lancement                         --}}
    {{-- ══════════════════════════════════════════════════════════════ --}}
    {{-- ══════════════════════════════════════════════════════════════ --}}
    {{-- ÉTAPE 3b — Avertissement doublons (3.3) ──────────────────────── --}}
    {{-- ══════════════════════════════════════════════════════════════ --}}
    @if($showDuplicateStep)
    @php
        $totalWarnings = count($internalDuplicates) + count($externalDuplicates);
    @endphp
    <div style="background:var(--pd-surface);border:0.5px solid var(--pd-border);border-radius:14px;overflow:hidden;">

        <div style="background:#92400e;padding:18px 24px;">
            <div style="font-size:16px;font-weight:700;color:#fff;">
                ⚠ {{ $totalWarnings }} ressemblance(s) détectée(s) dans le fichier
            </div>
            <div style="font-size:12px;color:rgba(255,255,255,.75);margin-top:3px;">
                Vérifiez les lignes ci-dessous avant d'importer. Vous pouvez corriger votre fichier
                ou importer quand même si ces ressemblances sont intentionnelles.
            </div>
        </div>

        <div style="padding:24px;">

            <div style="padding:12px 16px;background:#fffbeb;border:0.5px solid #fcd34d;
                        border-radius:8px;font-size:12px;color:#92400e;line-height:1.7;margin-bottom:20px;">
                La détection est basée sur la similarité orthographique (Levenshtein ≤ 2).
                <strong>Pladigit ne peut pas décider à votre place</strong> — deux personnes homonymes
                ou une même personne avec deux rôles différents sont des cas légitimes.
                Vérifiez votre fichier source et corrigez-le si nécessaire.
            </div>

            {{-- ── Doublons internes au fichier ───────────────────────────── --}}
            @if(!empty($internalDuplicates))
            <div style="margin-bottom:20px;">
                <div style="font-size:12px;font-weight:700;color:var(--pd-text);margin-bottom:8px;
                             display:flex;align-items:center;gap:8px;">
                    <span style="display:inline-flex;align-items:center;justify-content:center;
                                 width:20px;height:20px;border-radius:50%;background:#fee2e2;
                                 color:#dc2626;font-size:10px;font-weight:700;">!</span>
                    Lignes similaires dans le fichier ({{ count($internalDuplicates) }})
                </div>
                <div style="border:0.5px solid var(--pd-border);border-radius:8px;overflow:hidden;">
                    <table style="width:100%;border-collapse:collapse;font-size:12px;">
                        <thead>
                            <tr style="background:var(--pd-bg2);">
                                <th style="padding:8px 12px;text-align:left;font-weight:600;color:var(--pd-muted);border-bottom:0.5px solid var(--pd-border);">Ligne A</th>
                                <th style="padding:8px 12px;text-align:left;font-weight:600;color:var(--pd-muted);border-bottom:0.5px solid var(--pd-border);">Ligne B</th>
                                <th style="padding:8px 12px;text-align:center;font-weight:600;color:var(--pd-muted);border-bottom:0.5px solid var(--pd-border);">Dist.</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($internalDuplicates as $dup)
                            <tr style="border-bottom:0.5px solid var(--pd-border);">
                                <td style="padding:8px 12px;vertical-align:top;">
                                    <div style="font-size:10px;color:var(--pd-muted);margin-bottom:3px;">ligne {{ $dup['index_a'] }}</div>
                                    <div style="font-weight:600;color:var(--pd-text);font-size:12px;">{{ $dup['value_a'] }}</div>
                                    @if(!empty($dup['row_a_context']))
                                    <div style="margin-top:4px;display:flex;flex-wrap:wrap;gap:4px;">
                                        @foreach($dup['row_a_context'] as $ctx)
                                        @if($ctx['value'] !== $dup['value_a'])
                                        <span style="font-size:11px;color:var(--pd-muted);background:var(--pd-bg2);
                                                     padding:1px 6px;border-radius:4px;white-space:nowrap;">
                                            {{ $ctx['label'] }} : {{ $ctx['value'] }}
                                        </span>
                                        @endif
                                        @endforeach
                                    </div>
                                    @endif
                                </td>
                                <td style="padding:8px 12px;vertical-align:top;">
                                    <div style="font-size:10px;color:var(--pd-muted);margin-bottom:3px;">ligne {{ $dup['index_b'] }}</div>
                                    <div style="font-weight:600;color:var(--pd-text);font-size:12px;">{{ $dup['value_b'] }}</div>
                                    @if(!empty($dup['row_b_context']))
                                    <div style="margin-top:4px;display:flex;flex-wrap:wrap;gap:4px;">
                                        @foreach($dup['row_b_context'] as $ctx)
                                        @if($ctx['value'] !== $dup['value_b'])
                                        <span style="font-size:11px;color:var(--pd-muted);background:var(--pd-bg2);
                                                     padding:1px 6px;border-radius:4px;white-space:nowrap;">
                                            {{ $ctx['label'] }} : {{ $ctx['value'] }}
                                        </span>
                                        @endif
                                        @endforeach
                                    </div>
                                    @endif
                                </td>
                                <td style="padding:8px 12px;text-align:center;vertical-align:top;">
                                    <span style="display:inline-flex;align-items:center;justify-content:center;
                                                 width:20px;height:20px;border-radius:50%;font-size:10px;font-weight:700;
                                                 background:{{ $dup['distance'] === 0 ? '#fee2e2' : '#fef3c7' }};
                                                 color:{{ $dup['distance'] === 0 ? '#dc2626' : '#92400e' }};">
                                        {{ $dup['distance'] === 0 ? '=' : $dup['distance'] }}
                                    </span>
                                    @if(!empty($dup['column_label']))
                                    <div style="font-size:10px;color:var(--pd-muted);margin-top:3px;">{{ $dup['column_label'] }}</div>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @endif

            {{-- ── Ressemblances avec des fiches existantes ────────────────── --}}
            @if(!empty($externalDuplicates))
            <div style="margin-bottom:20px;">
                <div style="font-size:12px;font-weight:700;color:var(--pd-text);margin-bottom:8px;
                             display:flex;align-items:center;gap:8px;">
                    <span style="display:inline-flex;align-items:center;justify-content:center;
                                 width:20px;height:20px;border-radius:50%;background:#fef3c7;
                                 color:#92400e;font-size:10px;font-weight:700;">~</span>
                    Ressemblances avec des fiches existantes ({{ count($externalDuplicates) }})
                </div>
                <div style="border:0.5px solid var(--pd-border);border-radius:8px;overflow:hidden;">
                    <table style="width:100%;border-collapse:collapse;font-size:12px;">
                        <thead>
                            <tr style="background:var(--pd-bg2);">
                                <th style="padding:8px 12px;text-align:left;font-weight:600;color:var(--pd-muted);border-bottom:0.5px solid var(--pd-border);">Ligne du fichier</th>
                                <th style="padding:8px 12px;text-align:left;font-weight:600;color:var(--pd-muted);border-bottom:0.5px solid var(--pd-border);">Fiche existante proche</th>
                                <th style="padding:8px 12px;text-align:center;font-weight:600;color:var(--pd-muted);border-bottom:0.5px solid var(--pd-border);">Dist.</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($externalDuplicates as $dup)
                            <tr style="border-bottom:0.5px solid var(--pd-border);">
                                <td style="padding:8px 12px;vertical-align:top;">
                                    <div style="font-size:10px;color:var(--pd-muted);margin-bottom:3px;">ligne {{ $dup['import_index'] }}</div>
                                    <div style="font-weight:600;color:var(--pd-text);font-size:12px;">{{ $dup['import_value'] }}</div>
                                    @if(!empty($dup['import_row_context']))
                                    <div style="margin-top:4px;display:flex;flex-wrap:wrap;gap:4px;">
                                        @foreach($dup['import_row_context'] as $ctx)
                                        @if($ctx['value'] !== $dup['import_value'])
                                        <span style="font-size:11px;color:var(--pd-muted);background:var(--pd-bg2);
                                                     padding:1px 6px;border-radius:4px;white-space:nowrap;">
                                            {{ $ctx['label'] }} : {{ $ctx['value'] }}
                                        </span>
                                        @endif
                                        @endforeach
                                    </div>
                                    @endif
                                </td>
                                <td style="padding:8px 12px;vertical-align:top;">
                                    <div style="font-weight:600;color:var(--pd-text);font-size:12px;">{{ $dup['existing_value'] }}</div>
                                    @if(!empty($dup['column_label']))
                                    <div style="font-size:10px;color:var(--pd-muted);margin-top:2px;">col. {{ $dup['column_label'] }}</div>
                                    @endif
                                </td>
                                <td style="padding:8px 12px;text-align:center;vertical-align:top;">
                                    <span style="display:inline-flex;align-items:center;justify-content:center;
                                                 width:20px;height:20px;border-radius:50%;font-size:10px;font-weight:700;
                                                 background:{{ $dup['distance'] === 1 ? '#fee2e2' : '#fef3c7' }};
                                                 color:{{ $dup['distance'] === 1 ? '#dc2626' : '#92400e' }};">
                                        {{ $dup['distance'] }}
                                    </span>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @endif

            {{-- ── Actions ─────────────────────────────────────────────────── --}}
            <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
                <button wire:click="backFromDuplicates"
                        style="padding:9px 18px;border:0.5px solid var(--pd-border);border-radius:9px;
                               font-size:13px;color:var(--pd-muted);background:var(--pd-bg);cursor:pointer;">
                    ← Corriger le fichier
                </button>
                <button wire:click="importDespiteDuplicates"
                        style="padding:9px 22px;background:var(--pd-navy);color:#fff;border:none;
                               border-radius:9px;font-size:13px;font-weight:600;cursor:pointer;">
                    Importer quand même →
                </button>
            </div>

        </div>
    </div>
    @endif

    {{-- ══════════════════════════════════════════════════════════════ --}}
    {{-- ÉTAPE 3 — Confirmation et lancement du job ───────────────────── --}}
    {{-- ══════════════════════════════════════════════════════════════ --}}
    @if($step === 3)
    @php
        $targetGrid  = $importMode === 'update' && $targetTableId
            ? collect($existingGrids)->firstWhere('id', $targetTableId)
            : null;
        $jobRunning  = in_array($jobStatus, ['pending', 'running']);
        $jobDone     = $jobStatus === 'done' || $importedTableId;
        $jobError    = $jobStatus === 'error';
        $jobProgress = $jobTotal > 0 ? round($jobProcessed / $jobTotal * 100) : 0;
    @endphp

    {{-- Polling actif uniquement quand le job tourne --}}
    @if($jobRunning)
    <div wire:poll.2000ms="pollProgress"></div>
    @endif

    <div style="background:var(--pd-surface);border:0.5px solid var(--pd-border);border-radius:14px;overflow:hidden;">

        <div style="background:var(--pd-navy);padding:18px 24px;">
            <div style="font-size:16px;font-weight:700;color:#fff;">
                @if($jobDone) Import terminé
                @elseif($jobRunning) Import en cours…
                @else Confirmation de l'import
                @endif
            </div>
            <div style="font-size:12px;color:rgba(255,255,255,.65);margin-top:3px;">
                @if($jobDone) Les données sont disponibles dans la grille.
                @elseif($jobRunning) Vous pouvez naviguer — l'import continue en arrière-plan.
                @else Vérifiez les paramètres avant de lancer l'import.
                @endif
            </div>
        </div>

        <div style="padding:24px;">

            @if($errorMessage || $jobError)
            <div style="padding:12px 14px;background:#FEE2E2;color:#991B1B;border-radius:8px;
                        margin-bottom:16px;font-size:12px;line-height:1.6;">
                ⚠ <strong>Erreur lors de l'import :</strong><br>{{ $errorMessage }}
            </div>
            @endif

            {{-- ── Succès ──────────────────────────────────────── --}}
            @if($jobDone)
            <div style="text-align:center;padding:24px 0;">
                <div style="font-size:40px;margin-bottom:12px;">✅</div>
                <div style="font-size:17px;font-weight:700;color:var(--pd-text);margin-bottom:6px;">
                    Import terminé avec succès
                </div>
                <div style="font-size:13px;color:var(--pd-muted);">
                    {{ number_format($importedRows, 0, ',', ' ') }} ligne(s) importée(s)
                    @if($importMode === 'update' && $targetGrid)
                    dans la grille <strong>{{ $targetGrid['label'] }}</strong>
                    @else
                    dans la grille <strong>{{ $tableLabel }}</strong>
                    @endif
                </div>
                @if($importedTableId)
                <div style="margin-top:20px;">
                    <a href="{{ route('datagrid.show', $importedTableId) }}"
                       style="padding:9px 22px;background:var(--pd-navy);color:#fff;text-decoration:none;
                              border-radius:9px;font-size:13px;font-weight:600;">
                        Voir la grille →
                    </a>
                </div>
                @endif
            </div>

            {{-- ── Import en cours — barre de progression ─────── --}}
            @elseif($jobRunning)
            <div style="padding:16px 0;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                    <span style="font-size:13px;font-weight:600;color:var(--pd-text);">
                        Traitement en cours…
                    </span>
                    <span style="font-size:12px;color:var(--pd-muted);">
                        @if($jobTotal > 0)
                        {{ number_format($jobProcessed, 0, ',', ' ') }} / {{ number_format($jobTotal, 0, ',', ' ') }} lignes
                        @else
                        Initialisation…
                        @endif
                    </span>
                </div>

                {{-- Barre de progression --}}
                <div style="background:var(--pd-bg2);border-radius:99px;height:10px;overflow:hidden;">
                    <div style="height:100%;border-radius:99px;background:var(--pd-navy);
                                transition:width .4s ease;width:{{ $jobTotal > 0 ? $jobProgress : 0 }}%;">
                    </div>
                </div>

                @if($jobTotal > 0)
                <div style="font-size:11px;color:var(--pd-muted);margin-top:6px;text-align:right;">
                    {{ $jobProgress }} %
                </div>
                @endif

                <div style="margin-top:20px;padding:12px 14px;background:var(--pd-bg2);border-radius:8px;
                            font-size:12px;color:var(--pd-muted);line-height:1.6;">
                    💡 L'import tourne en arrière-plan. Vous pouvez naviguer dans l'application
                    et revenir consulter cette page pour voir l'avancement.
                </div>
            </div>

            {{-- ── Récapitulatif avant lancement ──────────────── --}}
            @elseif($importMode === 'new')

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
                            @if($col['required'])<span style="color:#991B1B;">*</span>@endif
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
                • Importer les lignes de données en arrière-plan (progression visible en temps réel)
            </div>

            {{-- Visibilité par défaut --}}
            <div style="margin-bottom:20px;padding:16px;background:var(--pd-bg2);border-radius:10px;">
                <div style="font-size:12px;font-weight:600;color:var(--pd-text);margin-bottom:12px;
                            text-transform:uppercase;letter-spacing:.04em;">
                    Visibilité initiale de la grille
                </div>

                <label style="display:flex;align-items:flex-start;gap:10px;font-size:13px;cursor:pointer;margin-bottom:10px;">
                    <input type="radio" wire:model="defaultVisibility" name="defaultVisibility" value="public"
                           style="margin-top:2px;width:15px;height:15px;cursor:pointer;flex-shrink:0;">
                    <span>
                        <strong>Publique</strong>
                        <span style="display:block;font-size:11px;color:var(--pd-muted);margin-top:2px;">
                            Tous les agents peuvent consulter et exporter la grille
                        </span>
                    </span>
                </label>

                <label style="display:flex;align-items:flex-start;gap:10px;font-size:13px;cursor:pointer;margin-bottom:10px;">
                    <input type="radio" wire:model="defaultVisibility" name="defaultVisibility" value="restricted"
                           style="margin-top:2px;width:15px;height:15px;cursor:pointer;flex-shrink:0;">
                    <span>
                        <strong>Restreinte</strong>
                        <span style="display:block;font-size:11px;color:var(--pd-muted);margin-top:2px;">
                            Aucun droit par défaut — l'administrateur configure les accès manuellement
                        </span>
                    </span>
                </label>

                <label style="display:flex;align-items:flex-start;gap:10px;font-size:13px;cursor:pointer;">
                    <input type="radio" wire:model="defaultVisibility" name="defaultVisibility" value="private"
                           style="margin-top:2px;width:15px;height:15px;cursor:pointer;flex-shrink:0;">
                    <span>
                        <strong>Privée</strong>
                        <span style="display:block;font-size:11px;color:var(--pd-muted);margin-top:2px;">
                            Accès explicitement refusé aux agents — visible uniquement par les administrateurs
                        </span>
                    </span>
                </label>
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
                    <span wire:loading wire:target="runImport">Préparation…</span>
                </button>
            </div>

            @else
            {{-- ── Récapitulatif mode 'update' ─────────────────── --}}
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:20px;">
                <div style="background:var(--pd-bg2);border-radius:8px;padding:14px;">
                    <div style="font-size:11px;color:var(--pd-muted);font-weight:600;text-transform:uppercase;letter-spacing:.04em;margin-bottom:6px;">
                        Grille cible
                    </div>
                    @if($targetGrid)
                    <div style="font-size:14px;font-weight:600;color:var(--pd-text);">{{ $targetGrid['label'] }}</div>
                    <div style="font-size:11px;color:var(--pd-muted);margin-top:2px;font-family:monospace;">
                        {{ $targetGrid['name'] }}
                    </div>
                    <div style="font-size:11px;color:var(--pd-muted);margin-top:2px;">
                        {{ $targetGrid['columns_count'] }} colonne(s)
                    </div>
                    @endif
                </div>

                <div style="background:var(--pd-bg2);border-radius:8px;padding:14px;">
                    <div style="font-size:11px;color:var(--pd-muted);font-weight:600;text-transform:uppercase;letter-spacing:.04em;margin-bottom:6px;">
                        Mode
                    </div>
                    @if($updateMode === 'replace')
                    <div style="font-size:13px;font-weight:600;color:#dc2626;">Remplacement complet</div>
                    <div style="font-size:11px;color:var(--pd-muted);margin-top:4px;">
                        Toutes les lignes existantes seront effacées avant l'import.
                    </div>
                    @else
                    <div style="font-size:13px;font-weight:600;color:#16a34a;">Ajout incrémental</div>
                    <div style="font-size:11px;color:var(--pd-muted);margin-top:4px;">
                        Les nouvelles lignes seront ajoutées aux données existantes.
                    </div>
                    @endif
                    @php
                        $mappedCount  = collect($columnMapping)->filter(fn ($v) => $v !== '')->count();
                        $ignoredCount = collect($columnMapping)->filter(fn ($v) => $v === '')->count();
                    @endphp
                    <div style="font-size:11px;color:var(--pd-muted);margin-top:6px;">
                        {{ $mappedCount }} colonne(s) importée(s)
                        @if($ignoredCount > 0)
                        · <span style="color:#d97706;">{{ $ignoredCount }} ignorée(s)</span>
                        @endif
                    </div>
                </div>
            </div>

            @if($updateMode === 'replace')
            <div style="background:#FEF2F2;border:0.5px solid #FECACA;border-radius:8px;
                        padding:12px 14px;font-size:12px;color:#991B1B;margin-bottom:20px;line-height:1.7;">
                ⚠ <strong>Attention :</strong> toutes les lignes actuelles de la grille
                <strong>{{ $targetGrid['label'] ?? '' }}</strong> seront supprimées avant l'import.
                Cette opération est irréversible.
            </div>
            @else
            <div style="background:#EFF6FF;border:0.5px solid #BFDBFE;border-radius:8px;
                        padding:12px 14px;font-size:12px;color:#1e40af;margin-bottom:20px;line-height:1.7;">
                Les lignes du fichier Excel seront ajoutées aux données existantes de la grille
                <strong>{{ $targetGrid['label'] ?? '' }}</strong>.
            </div>
            @endif

            {{-- Note recherche floue --}}
            @if(!empty($fuzzyColumnLabels))
            <div style="background:#eff6ff;border:0.5px solid #bfdbfe;border-radius:8px;
                        padding:12px 14px;font-size:12px;color:#1e40af;margin-bottom:16px;line-height:1.7;">
                🔍 <strong>Analyse de doublons activée</strong> — une recherche floue (Levenshtein ≤ 2)
                sera effectuée sur {{ count($fuzzyColumnLabels) > 1 ? 'les colonnes' : 'la colonne' }} :
                <strong>{{ implode(', ', $fuzzyColumnLabels) }}</strong>.
                Les correspondances suspectes vous seront présentées avant le lancement.
            </div>
            @endif

            <div style="display:flex;justify-content:space-between;">
                <button wire:click="{{ count($unmatchedColumns) > 0 ? 'backToStep2' : 'backToStep1' }}"
                        style="padding:9px 18px;background:var(--pd-bg2);color:var(--pd-muted);
                               border:0.5px solid var(--pd-border);border-radius:9px;
                               font-size:13px;cursor:pointer;">
                    ← Retour
                </button>
                <button wire:click="runImport" wire:loading.attr="disabled"
                        style="padding:9px 22px;background:#16a34a;color:#fff;border:none;
                               border-radius:9px;font-size:13px;font-weight:600;cursor:pointer;">
                    <span wire:loading.remove wire:target="runImport">Lancer l'import</span>
                    <span wire:loading wire:target="runImport">Préparation…</span>
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
