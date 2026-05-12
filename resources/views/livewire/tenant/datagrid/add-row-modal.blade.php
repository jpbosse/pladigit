{{-- ── Modal ajout d'une nouvelle ligne ────────────────────────────────────
     Composant : App\Livewire\Tenant\Datagrid\AddRowModal
     Réutilise  : _partials/edit-field.blade.php (formPrefix='addForm')
──────────────────────────────────────────────────────────────────────────── --}}

<div>
@if($open)
<div wire:click.self="closeAdd"
     style="position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:1000;
            display:flex;align-items:center;justify-content:center;padding:20px;">
    <div style="background:var(--pd-bg);border-radius:12px;box-shadow:0 20px 60px rgba(0,0,0,0.25);
                width:100%;max-width:560px;max-height:90vh;display:flex;flex-direction:column;">

        {{-- En-tête --}}
        <div style="display:flex;align-items:center;justify-content:space-between;
                    padding:18px 24px;border-bottom:1px solid var(--pd-border);">
            <h2 style="margin:0;font-size:15px;font-weight:700;color:var(--pd-text);">
                Nouvelle ligne
            </h2>
            <button wire:click="closeAdd"
                    style="background:none;border:none;cursor:pointer;color:var(--pd-muted);
                           font-size:20px;line-height:1;padding:0;">×</button>
        </div>

        {{-- Corps --}}
        <div style="overflow-y:auto;padding:20px 24px;display:flex;flex-direction:column;gap:14px;">
            @foreach($columns as $col)
                @include('livewire.tenant.datagrid._partials.edit-field', [
                    'col'        => $col,
                    'formPrefix' => 'addForm',
                    'formValues' => $addForm,
                    'canWrite'   => true,
                    'rowId'      => null,
                ])
            @endforeach
        </div>

        {{-- Pied --}}
        <div style="display:flex;align-items:center;justify-content:flex-end;
                    padding:16px 24px;border-top:1px solid var(--pd-border);gap:8px;">
            <button wire:click="closeAdd"
                    style="padding:8px 16px;border:1px solid var(--pd-border);border-radius:7px;
                           font-size:12px;font-weight:600;color:var(--pd-text);
                           background:var(--pd-bg);cursor:pointer;">
                Annuler
            </button>
            <button wire:click="saveAdd"
                    style="padding:8px 16px;background:var(--pd-navy);color:#fff;border:none;
                           border-radius:7px;font-size:12px;font-weight:600;cursor:pointer;">
                Enregistrer
            </button>
        </div>

    </div>
</div>
@endif
</div>
