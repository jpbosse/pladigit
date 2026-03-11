<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Normalise les labels des départements : ucfirst() sur tous les labels existants.
 * Ex : "direction" → "Direction", "pôle" → "Pôle", "SERVICE" → "Service"
 */
return new class extends Migration
{
    public function up(): void
    {
        $departments = DB::connection('tenant')
            ->table('departments')
            ->whereNotNull('label')
            ->where('label', '!=', '')
            ->get(['id', 'label']);

        foreach ($departments as $dept) {
            $normalized = ucfirst(mb_strtolower(trim($dept->label)));
            if ($normalized !== $dept->label) {
                DB::connection('tenant')
                    ->table('departments')
                    ->where('id', $dept->id)
                    ->update(['label' => $normalized]);
            }
        }
    }

    public function down(): void
    {
        // Irréversible — normalisation cosmétique
    }
};
