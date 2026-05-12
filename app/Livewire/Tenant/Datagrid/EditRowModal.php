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
 * Composant Livewire — Modal d'édition / suppression d'une ligne DataGrid.
 *
 * Responsabilités :
 *   - Afficher la modal d'édition avec onglets (Données / Historique)
 *   - Valider et persister les modifications
 *   - Tracer chaque modification dans datagrid_audit_log
 *   - Émettre des événements vers ShowGrid après chaque action
 *
 * Onglets prévus (roadmap) :
 *   - 'main'    : colonnes tab='main' (ou toutes les colonnes par défaut)      ← implémenté
 *   - 'extra'   : colonnes tab='extra' (si au moins une)                       ← implémenté
 *   - 'history' : journal audit datagrid_audit_logs                            ← implémenté
 *   - 'docs'    : pièces jointes GED (Bloc 6.2 — dépend module GED)           ← stub prêt
 *
 * Communication avec ShowGrid :
 *   - Écoute : $on('open-edit-modal', rowId)
 *   - Émet   : row-updated, row-deleted → ShowGrid::$listeners les gère
 *
 * @property DatagridTable $table
 */
class EditRowModal extends Component
{
    public DatagridTable $table;

    /** ID de la ligne en cours d'édition (null = modal fermée) */
    public ?int $rowId = null;

    /** @var array<string, mixed> Valeurs du formulaire d'édition */
    public array $editForm = [];

    /** Onglet actif : 'main' | 'extra' | 'history' | 'docs' */
    public string $activeTab = 'main';

    /** @var array{can_write: bool, can_delete: bool, can_export: bool} Droits injectés par ShowGrid au montage */
    public array $userPerms = [
        'can_write' => false,
        'can_delete' => false,
        'can_export' => false,
    ];

    /** @var array<string, array<int, mixed>> Valeurs distinctes pour les selects (injectées par ShowGrid) */
    public array $distinctValues = [];

    /** @var array<int, string> Lignes de l'onglet Historique (chargées à la demande) */
    public array $historyEntries = [];

    // ── Listeners ────────────────────────────────────────────────────────────

    /** @return array<string, string> */
    protected function getListeners(): array
    {
        return [
            'open-edit-modal' => 'openEdit',
        ];
    }

    // ── Ouverture / fermeture ─────────────────────────────────────────────────

    public function openEdit(int $rowId): void
    {
        $row = DB::connection('tenant')
            ->table($this->table->mysql_table)
            ->where('id', $rowId)
            ->first();

        if (! $row) {
            return;
        }

        $this->rowId = $rowId;
        $this->activeTab = 'main';
        $this->historyEntries = [];
        $this->resetValidation();

        $rawRow = (array) $row;
        $formatted = $rawRow;

        foreach ($this->table->columns as $col) {
            $val = $rawRow[$col->name] ?? null;
            if ($val === null) {
                continue;
            }

            $formatted[$col->name] = match ($col->type) {
                DatagridColumnType::PHONE => $this->formatPhone((string) $val),
                DatagridColumnType::SIRET => $this->formatSiret((string) $val),
                DatagridColumnType::POSTAL_CODE => str_pad((string) preg_replace('/\D/', '', (string) $val), 5, '0', STR_PAD_LEFT),
                DatagridColumnType::BOOLEAN => in_array($val, ['1', 1, true, 'true', 'oui'], false) ? '1' : '0',
                DatagridColumnType::NUMBER => rtrim(rtrim((string) $val, '0'), '.') ?: '0',
                default => $val,
            };
        }

        $this->editForm = $formatted;
    }

    public function closeEdit(): void
    {
        $this->rowId = null;
        $this->editForm = [];
        $this->historyEntries = [];
        $this->resetValidation();
    }

    // ── Onglets ───────────────────────────────────────────────────────────────

    public function switchTab(string $tab): void
    {
        $this->activeTab = $tab;

        if ($tab === 'history') {
            $this->loadHistory();
        }
    }

    private function loadHistory(): void
    {
        if ($this->rowId === null) {
            return;
        }

        $this->historyEntries = DatagridAuditLog::with('user')
            ->forRow($this->rowId)
            ->forTable($this->table->id)
            ->orderByDesc('created_at')
            ->limit(100)
            ->get()
            ->map(fn ($entry) => [
                'id' => $entry->id,
                'date' => $entry->created_at?->format('d/m/Y H:i'),
                'user' => $entry->user->name ?? '—',
                'action' => $entry->action->value,
                'column_name' => $entry->column_name,
                'old_value' => $entry->old_value,
                'new_value' => $entry->new_value,
            ])
            ->toArray();
    }

    // ── Sauvegarde ────────────────────────────────────────────────────────────

    public function saveEdit(): void
    {
        if (! $this->userPerms['can_write']) {
            abort(403);
        }

        if ($this->rowId === null) {
            return;
        }

        $rules = $this->buildValidationRules('editForm');
        $this->validate($rules);

        $oldRow = (array) DB::connection('tenant')
            ->table($this->table->mysql_table)
            ->where('id', $this->rowId)
            ->first();

        $colTypeMap = $this->table->columns->keyBy('name');
        $updateData = collect($this->editForm)
            ->except(['id'])
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

        DB::connection('tenant')
            ->table($this->table->mysql_table)
            ->where('id', $this->rowId)
            ->update($updateData);

        // Audit — une entrée par colonne modifiée
        foreach ($updateData as $colName => $newVal) {
            $oldVal = $oldRow[$colName] ?? null;
            $oldStr = $oldVal !== null ? (string) $oldVal : null;
            $newStr = $newVal !== null ? (string) $newVal : null;

            if ($oldStr === $newStr) {
                continue;
            }

            DatagridAuditLog::create([
                'datagrid_table_id' => $this->table->id,
                'user_id' => auth()->id(),
                'action' => DatagridAuditAction::WRITE->value,
                'row_id' => $this->rowId,
                'column_name' => $colName,
                'old_value' => $oldStr,
                'new_value' => $newStr,
                'ip_address' => request()->ip(),
            ]);
        }

        $this->closeEdit();
        $this->dispatch('row-updated');
    }

    // ── Suppression ───────────────────────────────────────────────────────────

    public function deleteRow(): void
    {
        if (! $this->userPerms['can_delete']) {
            abort(403);
        }

        if ($this->rowId === null) {
            return;
        }

        $oldRow = (array) DB::connection('tenant')
            ->table($this->table->mysql_table)
            ->where('id', $this->rowId)
            ->first();

        DB::connection('tenant')
            ->table($this->table->mysql_table)
            ->where('id', $this->rowId)
            ->delete();

        DatagridAuditLog::create([
            'datagrid_table_id' => $this->table->id,
            'user_id' => auth()->id(),
            'action' => DatagridAuditAction::DELETE->value,
            'row_id' => $this->rowId,
            'column_name' => null,
            'old_value' => json_encode($oldRow, JSON_UNESCAPED_UNICODE),
            'new_value' => null,
            'ip_address' => request()->ip(),
        ]);

        $this->closeEdit();
        $this->dispatch('row-deleted');
    }

    // ── Rendu ─────────────────────────────────────────────────────────────────

    public function render(): View
    {
        $columns = $this->table->columns()->get();

        // Répartition des colonnes par onglet
        // Par défaut toutes les colonnes sont dans 'main'.
        // Le Super Admin pourra définir tab='extra' sur certaines colonnes (Bloc 2.15).
        $mainColumns = $columns->where('name', '!=', 'id')
            ->filter(fn ($col) => ($col->tab ?? 'main') === 'main');
        $extraColumns = $columns->where('name', '!=', 'id')
            ->filter(fn ($col) => ($col->tab ?? 'main') === 'extra');

        $hasExtra = $extraColumns->isNotEmpty();

        return view('livewire.tenant.datagrid.edit-row-modal', [
            'columns' => $columns,
            'mainColumns' => $mainColumns,
            'extraColumns' => $extraColumns,
            'hasExtra' => $hasExtra,
        ]);
    }

    // ── Méthodes privées ─────────────────────────────────────────────────────

    /**
     * Construit les règles de validation dynamiques.
     *
     * @return array<string, string>
     */
    private function buildValidationRules(string $prefix): array
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

            $rules["{$prefix}.{$col->name}"] = $rule;
        }

        return $rules;
    }

    private function formatPhone(string $val): string
    {
        $prefix = str_starts_with($val, '+') ? '+' : '';
        $digits = (string) preg_replace('/\D/', '', $val);

        if (! $prefix && strlen($digits) === 10) {
            return implode(' ', str_split($digits, 2));
        }

        return $prefix.$digits;
    }

    private function denormalizePhone(string $val): string
    {
        $prefix = str_starts_with($val, '+') ? '+' : '';
        $digits = (string) preg_replace('/\D/', '', $val);

        return $prefix.$digits;
    }

    private function formatSiret(string $val): string
    {
        $digits = (string) preg_replace('/\D/', '', $val);
        $padded = str_pad($digits, 14, '0', STR_PAD_LEFT);

        if (strlen($padded) === 14) {
            return substr($padded, 0, 3).' '.substr($padded, 3, 3).' '.substr($padded, 6, 3).' '.substr($padded, 9, 5);
        }

        return $val;
    }
}
