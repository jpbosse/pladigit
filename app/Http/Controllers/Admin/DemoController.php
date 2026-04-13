<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\TenantManager;
use Illuminate\Http\Request;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Gestion des données de démonstration.
 * Accessible uniquement pour l'organisation dont le slug est "demo".
 */
class DemoController extends Controller
{
    public function __construct(private TenantManager $tenantManager) {}

    // ─────────────────────────────────────────────────────────────
    //  Vérification accès démo
    // ─────────────────────────────────────────────────────────────

    private function checkDemo(): bool
    {
        return $this->tenantManager->current()?->slug === 'demo';
    }

    // ─────────────────────────────────────────────────────────────
    //  Index
    // ─────────────────────────────────────────────────────────────

    public function index()
    {
        if (! $this->checkDemo()) {
            abort(403, 'Cette page n\'est disponible que sur l\'organisation de démonstration.');
        }

        $photos = $this->listFiles(storage_path('demo_photos'), ['jpg', 'jpeg', 'png', 'webp']);
        $gedTree = $this->buildGedTree(storage_path('demo_ged'));

        return view('admin.demo.index', compact('photos', 'gedTree'));
    }

    // ─────────────────────────────────────────────────────────────
    //  Upload photos
    // ─────────────────────────────────────────────────────────────

    public function uploadPhotos(Request $request)
    {
        if (! $this->checkDemo()) {
            abort(403);
        }

        $request->validate([
            'photos'   => ['required', 'array', 'max:20'],
            'photos.*' => ['file', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ]);

        $dir = storage_path('demo_photos');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $count = 0;
        foreach ($request->file('photos') as $file) {
            $file->move($dir, $file->getClientOriginalName());
            $count++;
        }

        return back()->with('success', "{$count} photo(s) ajoutée(s) dans les sources de démo.");
    }

    // ─────────────────────────────────────────────────────────────
    //  Upload documents GED
    // ─────────────────────────────────────────────────────────────

    public function uploadGed(Request $request)
    {
        if (! $this->checkDemo()) {
            abort(403);
        }

        $request->validate([
            'docs'      => ['required', 'array', 'max:20'],
            'docs.*'    => ['file', 'mimes:pdf,doc,docx,odt,xls,xlsx,ods,txt', 'max:20480'],
            'subfolder' => ['nullable', 'string', 'max:100', 'regex:/^[^\/\\\\.]+$/'],
        ]);

        $base = storage_path('demo_ged');
        $dir  = $request->filled('subfolder')
            ? $base . '/' . $request->subfolder
            : $base;

        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $count = 0;
        foreach ($request->file('docs') as $file) {
            $file->move($dir, $file->getClientOriginalName());
            $count++;
        }

        return back()->with('success', "{$count} document(s) ajouté(s) dans les sources GED.");
    }

    // ─────────────────────────────────────────────────────────────
    //  Supprimer un fichier source
    // ─────────────────────────────────────────────────────────────

    public function deleteFile(Request $request)
    {
        if (! $this->checkDemo()) {
            abort(403);
        }

        $request->validate([
            'type' => ['required', 'in:photo,ged'],
            'path' => ['required', 'string'],
        ]);

        $base = $request->type === 'photo'
            ? storage_path('demo_photos')
            : storage_path('demo_ged');

        // Sécurité : s'assurer que le chemin reste dans le dossier autorisé
        $full = realpath($base . '/' . $request->path);
        if (! $full || ! str_starts_with($full, realpath($base))) {
            abort(403, 'Chemin non autorisé.');
        }

        if (is_file($full)) {
            unlink($full);
        }

        return back()->with('success', 'Fichier supprimé.');
    }

    // ─────────────────────────────────────────────────────────────
    //  Lancer la remise à zéro
    // ─────────────────────────────────────────────────────────────

    public function reset()
    {
        if (! $this->checkDemo()) {
            abort(403);
        }

        set_time_limit(300);

        // Exécuter la commande dans un processus PHP séparé (comme le terminal)
        // pour éviter les problèmes de contexte liés à Artisan::call() depuis le web.
        $phpBin  = (new PhpExecutableFinder())->find() ?: 'php';
        $process = new Process([$phpBin, base_path('artisan'), 'demo:reset', '--slug=demo']);
        $process->setTimeout(270);
        $process->run();

        if (! $process->isSuccessful()) {
            $detail = trim($process->getErrorOutput() . "\n" . $process->getOutput());
            return back()->withErrors(['reset' => 'Erreur lors du reset : ' . $detail]);
        }

        return back()->with('success', 'Remise à zéro effectuée.');
    }

    // ─────────────────────────────────────────────────────────────
    //  Helpers
    // ─────────────────────────────────────────────────────────────

    private function listFiles(string $dir, array $exts): array
    {
        if (! is_dir($dir)) {
            return [];
        }

        $files = [];
        foreach (glob($dir . '/*') as $path) {
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            if (is_file($path) && in_array($ext, $exts)) {
                $files[] = [
                    'name' => basename($path),
                    'size' => $this->humanSize(filesize($path)),
                    'path' => basename($path),
                ];
            }
        }

        return $files;
    }

    private function buildGedTree(string $dir, string $relative = ''): array
    {
        if (! is_dir($dir)) {
            return [];
        }

        $tree = [];
        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..' || $item === 'LISEZ-MOI.txt') {
                continue;
            }

            $full = $dir . '/' . $item;
            $rel  = $relative ? $relative . '/' . $item : $item;

            if (is_dir($full)) {
                $tree[] = [
                    'type'     => 'folder',
                    'name'     => $item,
                    'path'     => $rel,
                    'children' => $this->buildGedTree($full, $rel),
                ];
            } elseif (is_file($full)) {
                $tree[] = [
                    'type' => 'file',
                    'name' => $item,
                    'size' => $this->humanSize(filesize($full)),
                    'path' => $rel,
                ];
            }
        }

        return $tree;
    }

    private function humanSize(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' o';
        }
        if ($bytes < 1048576) {
            return round($bytes / 1024, 1) . ' Ko';
        }

        return round($bytes / 1048576, 1) . ' Mo';
    }
}
