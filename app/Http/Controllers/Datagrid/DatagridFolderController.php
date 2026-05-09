<?php

namespace App\Http\Controllers\Datagrid;

use App\Http\Controllers\Controller;
use App\Models\Tenant\DatagridFolder;
use App\Models\Tenant\DatagridTable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class DatagridFolderController extends Controller
{
    public function store(): RedirectResponse
    {
        $data = request()->validate([
            'label' => ['required', 'string', 'max:255'],
        ]);

        DatagridFolder::create([
            'label' => $data['label'],
            'created_by' => auth()->id(),
        ]);

        return redirect()->route('datagrid.index')->with('success', 'Dossier créé.');
    }

    public function update(DatagridFolder $folder): RedirectResponse
    {
        $data = request()->validate([
            'label' => ['required', 'string', 'max:255'],
        ]);

        $folder->update(['label' => $data['label']]);

        return redirect()->route('datagrid.index')->with('success', 'Dossier renommé.');
    }

    public function destroy(DatagridFolder $folder): RedirectResponse
    {
        // Retirer les grilles du dossier avant suppression
        DatagridTable::where('folder_id', $folder->id)->update(['folder_id' => null]);

        $folder->delete();

        return redirect()->route('datagrid.index')->with('success', 'Dossier supprimé.');
    }

    public function moveTable(DatagridTable $table): RedirectResponse|JsonResponse
    {
        $data = request()->validate([
            'folder_id' => ['nullable', 'integer'],
        ]);

        $folderId = $data['folder_id'] ?: null;

        // Vérifier que le dossier existe si fourni
        if ($folderId && ! DatagridFolder::find($folderId)) {
            if (request()->expectsJson()) {
                return response()->json(['message' => 'Dossier introuvable'], 404);
            }

            return redirect()->back()->with('error', 'Dossier introuvable.');
        }

        $table->update(['folder_id' => $folderId]);

        if (request()->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->route('datagrid.index')->with('success', 'Grille déplacée.');
    }
}
