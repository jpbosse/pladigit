<?php

namespace App\Livewire\Tenant\Datagrid;

use App\Enums\DatagridAuditAction;
use App\Enums\DatagridColumnType;
use App\Models\Tenant\DatagridAuditLog;
use App\Models\Tenant\DatagridTable;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Composant Livewire — Modal d'ajout d'une nouvelle ligne DataGrid.
 *
 * Responsabilités :
 *   - Initialiser le formulaire avec les valeurs par défaut par type
 *   - Valider et insérer la nouvelle ligne
 *   - Tracer la création dans datagrid_audit_log
 *   - Émettre row-added vers ShowGrid
 *
 * Communication avec ShowGrid :
 *   - Écoute : $on('open-add-modal')
 *   - Émet   : row-added → ShowGrid::resetRows()
 *
 * @property DatagridTable $table
 */
class AddRowModal extends Component
{
    public DatagridTable $table;

    /** Modal ouverte/fermée */
    public bool $open = false;

    /** @var array<string, mixed> Valeurs du formulaire d'ajout */
    public array $addForm = [];

    /** @var array{can_write: bool} Droits injectés par ShowGrid */
    public array $userPerms = [
        'can_write' => false,
    ];

    /** @var array<string, array<int, mixed>> Valeurs distinctes pour les selects (injectées par ShowGrid) */
    public array $distinctValues = [];

    // ── Listeners ────────────────────────────────────────────────────────────

    /** @return array<string, string> */
    protected function getListeners(): array
    {
        return [
            'open-add-modal' => 'openAdd',
        ];
    }

    // ── Ouverture / fermeture ─────────────────────────────────────────────────

    public function openAdd(): void
    {
        if (! $this->userPerms['can_write']) {
            abort(403);
        }

        // Initialiser le formulaire avec les valeurs par défaut par type
        $this->addForm = [];
        foreach ($this->table->columns as $col) {
            if ($col->name === 'id') {
                continue;
            }
            $this->addForm[$col->name] = match ($col->type) {
                DatagridColumnType::BOOLEAN => '0',
                default => '',
            };
        }

        $this->resetValidation();
        $this->open = true;
    }

    public function closeAdd(): void
    {
        $this->open = false;
        $this->addForm = [];
        $this->resetValidation();
    }

    // ── Sauvegarde ────────────────────────────────────────────────────────────

    public function saveAdd(): void
    {
        if (! $this->userPerms['can_write']) {
            abort(403);
        }

        $rules = $this->buildValidationRules();
        $this->validate($rules);

        $colTypeMap = $this->table->columns->keyBy('name');
        $insertData = collect($this->addForm)
            ->map(function ($v, $colName) use ($colTypeMap) {
                if ($v === '' || $v === null) {
                    return null;
                }
                $col = $colTypeMap->get($colName);
                if (! $col) {
                    return $v;
                }

                return match ($col->type) {
                    DatagridColumnType::SIRET => preg_replace('/\D/', '', (string) $v),
                    DatagridColumnType::PHONE => $this->denormalizePhone((string) $v),
                    DatagridColumnType::POSTAL_CODE => str_pad((string) preg_replace('/\D/', '', (string) $v), 5, '0', STR_PAD_LEFT),
                    default => $v,
                };
            })
            ->toArray();

        $insertData['created_at'] = now();
        $insertData['updated_at'] = now();

        $newId = DB::connection('tenant')
            ->table($this->table->mysql_table)
            ->insertGetId($insertData);

        DatagridAuditLog::create([
            'datagrid_table_id' => $this->table->id,
            'user_id' => auth()->id(),
            'action' => DatagridAuditAction::WRITE->value,
            'row_id' => $newId,
            'column_name' => null,
            'old_value' => null,
            'new_value' => json_encode($insertData, JSON_UNESCAPED_UNICODE),
            'ip_address' => request()->ip(),
        ]);

        $this->closeAdd();
        $this->dispatch('row-added');
    }

    // ── Rendu ─────────────────────────────────────────────────────────────────

    public function render(): View
    {
        $columns = $this->table->columns()->where('name', '!=', 'id')->get();

        return view('livewire.tenant.datagrid.add-row-modal', [
            'columns' => $columns,
        ]);
    }

    // ── Méthodes privées ─────────────────────────────────────────────────────

    /**
     * @return array<string, string>
     */
    private function buildValidationRules(): array
    {
        $rules = [];

        foreach ($this->table->columns as $col) {
            if ($col->name === 'id') {
                continue;
            }

            $rule = $col->required ? 'required' : 'nullable';
            $rule .= match ($col->type) {
                DatagridColumnType::NUMBER => '|numeric',
                DatagridColumnType::DATE => '|date',
                DatagridColumnType::EMAIL => '|email|max:'.($col->length ?? 255),
                DatagridColumnType::BOOLEAN => '|boolean',
                DatagridColumnType::SIRET => '|max:19',
                DatagridColumnType::POSTAL_CODE => '|max:10',
                DatagridColumnType::PHONE => '|max:'.($col->length ?? 30),
                default => '|max:'.($col->length ?? 255),
            };

            $rules["addForm.{$col->name}"] = $rule;
        }

        return $rules;
    }

    private function denormalizePhone(string $val): string
    {
        $prefix = str_starts_with($val, '+') ? '+' : '';
        $digits = (string) preg_replace('/\D/', '', $val);

        return $prefix.$digits;
    }
}
