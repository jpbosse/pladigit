{{-- ── Modal édition / suppression ─────────────────────────────────────────
     Composant : App\Livewire\Tenant\Datagrid\EditRowModal
     Onglets   : Données principales | Informations complémentaires (si colonnes tab='extra')
                 | Historique
     Futur     : Documents (Bloc 6.2 — module GED) — stub présent, rendu conditionnel
──────────────────────────────────────────────────────────────────────────── --}}

<div>
@if($rowId !== null)
<div wire:click.self="closeEdit"
     style="position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:1000;
            display:flex;align-items:center;justify-content:center;padding:20px;">
    <div style="background:var(--pd-bg);border-radius:12px;box-shadow:0 20px 60px rgba(0,0,0,0.25);
                width:100%;max-width:600px;max-height:92vh;display:flex;flex-direction:column;">

        {{-- ── En-tête ──────────────────────────────────────────────────── --}}
        <div style="display:flex;align-items:center;justify-content:space-between;
                    padding:18px 24px;border-bottom:1px solid var(--pd-border);">
            <h2 style="margin:0;font-size:15px;font-weight:700;color:var(--pd-text);">
                Modifier la fiche
            </h2>
            <button wire:click="closeEdit"
                    style="background:none;border:none;cursor:pointer;color:var(--pd-muted);
                           font-size:20px;line-height:1;padding:0;">×</button>
        </div>

        {{-- ── Onglets ────────────────────────────────────────────────────── --}}
        <div style="display:flex;gap:0;border-bottom:1px solid var(--pd-border);padding:0 24px;">

            {{-- Données principales — toujours présent --}}
            <button wire:click="switchTab('main')"
                    style="padding:10px 16px;font-size:12px;font-weight:600;border:none;
                           background:none;cursor:pointer;border-bottom:2px solid {{ $activeTab === 'main' ? 'var(--pd-navy)' : 'transparent' }};
                           color:{{ $activeTab === 'main' ? 'var(--pd-navy)' : 'var(--pd-muted)' }};
                           margin-bottom:-1px;transition:color .15s;">
                Données
            </button>

            {{-- Informations complémentaires — conditionnel (colonnes tab='extra') --}}
            @if($hasExtra)
            <button wire:click="switchTab('extra')"
                    style="padding:10px 16px;font-size:12px;font-weight:600;border:none;
                           background:none;cursor:pointer;border-bottom:2px solid {{ $activeTab === 'extra' ? 'var(--pd-navy)' : 'transparent' }};
                           color:{{ $activeTab === 'extra' ? 'var(--pd-navy)' : 'var(--pd-muted)' }};
                           margin-bottom:-1px;transition:color .15s;">
                Complémentaires
            </button>
            @endif

            {{-- Historique — toujours présent --}}
            <button wire:click="switchTab('history')"
                    style="padding:10px 16px;font-size:12px;font-weight:600;border:none;
                           background:none;cursor:pointer;border-bottom:2px solid {{ $activeTab === 'history' ? 'var(--pd-navy)' : 'transparent' }};
                           color:{{ $activeTab === 'history' ? 'var(--pd-navy)' : 'var(--pd-muted)' }};
                           margin-bottom:-1px;transition:color .15s;">
                Historique
            </button>

            {{-- Documents — stub Bloc 6.2 : s'affichera quand le module GED sera actif --}}
            {{-- @if(auth()->user()->tenant->moduleEnabled(\App\Enums\ModuleKey::GED))
            <button wire:click="switchTab('docs')" ...>Documents</button>
            @endif --}}

        </div>

        {{-- ── Corps ─────────────────────────────────────────────────────── --}}
        <div style="overflow-y:auto;padding:20px 24px;flex:1;">

            {{-- ── Onglet : Données principales ─────────────────────────── --}}
            @if($activeTab === 'main')
            <div style="display:flex;flex-direction:column;gap:14px;">
                @foreach($mainColumns as $col)
                    @include('livewire.tenant.datagrid._partials.edit-field', [
                        'col'        => $col,
                        'formPrefix' => 'editForm',
                        'formValues' => $editForm,
                        'canWrite'   => $userPerms['can_write'],
                        'rowId'      => $rowId,
                    ])
                @endforeach
            </div>
            @endif

            {{-- ── Onglet : Informations complémentaires ────────────────── --}}
            @if($activeTab === 'extra')
            <div style="display:flex;flex-direction:column;gap:14px;">
                @if($extraColumns->isEmpty())
                    <p style="color:var(--pd-muted);font-size:13px;text-align:center;padding:24px 0;">
                        Aucune information complémentaire configurée.
                    </p>
                @else
                    @foreach($extraColumns as $col)
                        @include('livewire.tenant.datagrid._partials.edit-field', [
                            'col'        => $col,
                            'formPrefix' => 'editForm',
                            'formValues' => $editForm,
                            'canWrite'   => $userPerms['can_write'],
                            'rowId'      => $rowId,
                        ])
                    @endforeach
                @endif
            </div>
            @endif

            {{-- ── Onglet : Historique ───────────────────────────────────── --}}
            @if($activeTab === 'history')
            <div>
                @if(empty($historyEntries))
                    <p style="color:var(--pd-muted);font-size:13px;text-align:center;padding:24px 0;">
                        Aucune modification enregistrée pour cette fiche.
                    </p>
                @else
                    <div style="display:flex;flex-direction:column;gap:0;">
                        @foreach($historyEntries as $entry)
                        <div style="padding:10px 0;border-bottom:1px solid var(--pd-border);
                                    display:flex;gap:12px;align-items:flex-start;">
                            {{-- Icône action --}}
                            <div style="flex-shrink:0;width:28px;height:28px;border-radius:50%;
                                        display:flex;align-items:center;justify-content:center;font-size:12px;
                                        background:{{ $entry['action'] === 'delete' ? '#fee2e2' : '#dbeafe' }};
                                        color:{{ $entry['action'] === 'delete' ? '#dc2626' : '#1d4ed8' }};">
                                {{ $entry['action'] === 'delete' ? '🗑' : '✏' }}
                            </div>
                            {{-- Détail --}}
                            <div style="flex:1;min-width:0;">
                                <div style="display:flex;align-items:center;justify-content:space-between;
                                            gap:8px;margin-bottom:3px;">
                                    <span style="font-size:12px;font-weight:600;color:var(--pd-text);">
                                        {{ $entry['user'] }}
                                    </span>
                                    <span style="font-size:11px;color:var(--pd-muted);white-space:nowrap;">
                                        {{ $entry['date'] }}
                                    </span>
                                </div>
                                @if($entry['column_name'])
                                <div style="font-size:11px;color:var(--pd-muted);">
                                    Champ <strong style="color:var(--pd-text);">{{ $entry['column_name'] }}</strong> :
                                    @if($entry['old_value'] !== null)
                                    <span style="text-decoration:line-through;opacity:.6;">{{ $entry['old_value'] }}</span>
                                    →
                                    @endif
                                    <span style="color:var(--pd-text);">{{ $entry['new_value'] ?? '—' }}</span>
                                </div>
                                @else
                                <div style="font-size:11px;color:var(--pd-muted);">
                                    @if($entry['action'] === 'delete')
                                        Ligne supprimée
                                    @else
                                        Ligne créée
                                    @endif
                                </div>
                                @endif
                            </div>
                        </div>
                        @endforeach
                    </div>
                @endif
            </div>
            @endif

            {{-- ── Stub onglet Documents (Bloc 6.2) ─────────────────────── --}}
            {{-- @if($activeTab === 'docs') ... @endif --}}

        </div>

        {{-- ── Pied ───────────────────────────────────────────────────────── --}}
        <div style="display:flex;align-items:center;justify-content:space-between;
                    padding:16px 24px;border-top:1px solid var(--pd-border);gap:8px;flex-wrap:wrap;">

            {{-- Supprimer (gauche) --}}
            @if($userPerms['can_delete'] && $activeTab !== 'history')
            <button wire:click="deleteRow"
                    wire:confirm="Supprimer définitivement cette ligne ? Cette action est irréversible."
                    style="padding:8px 14px;background:#fef2f2;border:1px solid #fca5a5;
                           border-radius:7px;font-size:12px;font-weight:600;color:#dc2626;cursor:pointer;">
                Supprimer
            </button>
            @else
            <span></span>
            @endif

            {{-- Actions droite --}}
            <div style="display:flex;align-items:center;gap:8px;">
                @if($userPerms['can_export'] ?? false)
                <a href="{{ route('datagrid.pdf.fiche', ['table' => $table->id, 'rowId' => $rowId, 'cols' => '']) }}"
                   target="_blank"
                   style="padding:8px 16px;background:#dc2626;color:#fff;border:none;border-radius:7px;
                          font-size:12px;font-weight:600;cursor:pointer;display:inline-flex;
                          align-items:center;gap:5px;text-decoration:none;">
                    ↓ PDF fiche
                </a>
                @endif
                <button wire:click="closeEdit"
                        style="padding:8px 16px;border:1px solid var(--pd-border);border-radius:7px;
                               font-size:12px;font-weight:600;color:var(--pd-text);
                               background:var(--pd-bg);cursor:pointer;">
                    Annuler
                </button>
                @if($userPerms['can_write'] && $activeTab !== 'history')
                <button wire:click="saveEdit"
                        style="padding:8px 16px;background:var(--pd-navy);color:#fff;border:none;
                               border-radius:7px;font-size:12px;font-weight:600;cursor:pointer;">
                    Enregistrer
                </button>
                @endif
            </div>
        </div>

    </div>
</div>
@endif
</div>
