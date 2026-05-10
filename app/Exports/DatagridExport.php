<?php

namespace App\Exports;

use App\Models\Tenant\DatagridTable;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class DatagridExport implements FromCollection, WithHeadings, WithStyles
{
    public function __construct(
        private readonly DatagridTable $table,
        private readonly array $visibleColumnIds,
        private readonly array $filters = [],
    ) {}

    public function headings(): array
    {
        return $this->table->columns()
            ->whereIn('id', $this->visibleColumnIds)
            ->pluck('label')
            ->toArray();
    }

    public function collection(): Collection
    {
        $columns = $this->table->columns()
            ->whereIn('id', $this->visibleColumnIds)
            ->pluck('name')
            ->toArray();

        $query = \DB::connection('tenant')->table($this->table->mysql_table);

        foreach ($this->filters as $col => $val) {
            if ($val !== '' && $val !== null) {
                $query->where($col, 'like', '%'.$val.'%');
            }
        }

        return $query->get()->map(fn ($row) => collect((array) $row)->only($columns)->values()->toArray()
        );
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
