<?php

namespace App\Http\Controllers\Media;

use App\Http\Controllers\Controller;
use App\Models\Tenant\MediaItem;
use App\Models\Tenant\MediaShareLink;
use App\Services\Nas\NasManager;
use Illuminate\Http\Request;

/**
 * Accès public aux albums partagés via lien temporaire.
 * Aucune authentification requise — le token sert de preuve d'accès.
 */
class SharedAlbumController extends Controller
{
    public function __construct(private readonly NasManager $nasManager) {}

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function resolveLink(string $token): MediaShareLink
    {
        $link = MediaShareLink::where('token', $token)->with('album')->first();

        if (! $link || ! $link->isValid() || ! $link->album) {
            abort(404, 'Ce lien de partage est introuvable ou a expiré.');
        }

        return $link;
    }

    private function isAuthed(MediaShareLink $link): bool
    {
        if ($link->password_hash === null) {
            return true;
        }

        return session('share_authed_'.$link->token) === true;
    }

    // ── Actions ──────────────────────────────────────────────────────────────

    /**
     * Affiche la galerie publique ou le formulaire mot de passe.
     */
    public function show(string $token)
    {
        $link = $this->resolveLink($token);

        if (! $this->isAuthed($link)) {
            return view('media.shared.password', compact('link', 'token'));
        }

        $items = MediaItem::where('album_id', $link->album_id)
            ->whereNull('deleted_at')
            ->orderBy('file_name')
            ->get();

        return view('media.shared.show', compact('link', 'items', 'token'));
    }

    /**
     * Vérifie le mot de passe et redirige vers la galerie.
     */
    public function authenticate(Request $request, string $token)
    {
        $link = $this->resolveLink($token);

        $request->validate(['password' => ['required', 'string']]);

        if (! $link->checkPassword($request->input('password'))) {
            return back()->withErrors(['password' => 'Mot de passe incorrect.']);
        }

        session(['share_authed_'.$link->token => true]);

        return redirect()->route('media.shared.show', $token);
    }

    /**
     * Sert une image (miniature ou pleine résolution) depuis le NAS.
     */
    public function serveItem(string $token, int $itemId, string $type = 'thumb')
    {
        $link = $this->resolveLink($token);

        abort_unless($this->isAuthed($link), 403);

        $item = MediaItem::where('id', $itemId)
            ->where('album_id', $link->album_id)
            ->whereNull('deleted_at')
            ->firstOrFail();

        $nas = $this->nasManager->photoDriver();
        $isThumb = $type === 'thumb';
        $path = ($isThumb && $item->thumb_path) ? $item->thumb_path : $item->file_path;

        $maxAge = $isThumb ? 604800 : 86400;
        $etag = '"'.$item->updated_at->timestamp.($isThumb ? 't' : 'f').'"';
        $lastModified = $item->updated_at->format('D, d M Y H:i:s').' GMT';

        if (request()->header('If-None-Match') === $etag) {
            return response('', 304, [
                'ETag' => $etag,
                'Cache-Control' => "private, max-age={$maxAge}, must-revalidate",
            ]);
        }

        try {
            $contents = $nas->readFile($path);
            $mime = $isThumb ? 'image/jpeg' : ($item->mime_type ?? 'application/octet-stream');

            return response($contents, 200, [
                'Content-Type' => $mime,
                'Cache-Control' => "private, max-age={$maxAge}, must-revalidate",
                'ETag' => $etag,
                'Last-Modified' => $lastModified,
            ]);
        } catch (\Throwable) {
            abort(404);
        }
    }

    /**
     * Télécharge un fichier (uniquement si allow_download).
     */
    public function downloadItem(string $token, int $itemId)
    {
        $link = $this->resolveLink($token);

        abort_unless($this->isAuthed($link) && $link->allow_download, 403);

        $item = MediaItem::where('id', $itemId)
            ->where('album_id', $link->album_id)
            ->whereNull('deleted_at')
            ->firstOrFail();

        $nas = $this->nasManager->photoDriver();

        try {
            $contents = $nas->readFile($item->file_path);

            return response($contents, 200, [
                'Content-Type' => $item->mime_type ?? 'application/octet-stream',
                'Content-Disposition' => 'attachment; filename="'.$item->file_name.'"',
                'Content-Length' => strlen($contents),
            ]);
        } catch (\Throwable) {
            abort(404);
        }
    }

    /**
     * Exporte l'album en ZIP (uniquement si allow_download).
     */
    public function exportZip(string $token)
    {
        $link = $this->resolveLink($token);

        abort_unless($this->isAuthed($link) && $link->allow_download, 403);

        set_time_limit(300);

        $items = MediaItem::where('album_id', $link->album_id)
            ->whereNull('deleted_at')
            ->orderBy('file_name')
            ->get();

        if ($items->isEmpty()) {
            abort(404, 'Cet album est vide.');
        }

        $totalBytes = $items->sum('file_size_bytes');
        if ($totalBytes > 500 * 1024 * 1024) {
            abort(413, "L'album est trop volumineux pour l'export ZIP (> 500 Mo).");
        }

        $nas = $this->nasManager->photoDriver();
        $slug = \Illuminate\Support\Str::slug($link->album->name) ?: 'album';
        $zipName = $slug.'-'.now()->format('Ymd').'.zip';
        $tmpZip = sys_get_temp_dir().'/phzip_'.uniqid().'.zip';

        $zip = new \ZipArchive;
        if ($zip->open($tmpZip, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            abort(500, "Impossible de créer l'archive ZIP.");
        }

        $tmpFiles = [];
        foreach ($items as $item) {
            try {
                $contents = $nas->readFile($item->file_path);
                $tmpFile = sys_get_temp_dir().'/phzip_item_'.uniqid().'.tmp';
                file_put_contents($tmpFile, $contents);
                unset($contents);

                $name = $item->file_name;
                $counter = 1;
                while ($zip->locateName($name) !== false) {
                    $ext = pathinfo($item->file_name, PATHINFO_EXTENSION);
                    $base = pathinfo($item->file_name, PATHINFO_FILENAME);
                    $name = $base.'_'.$counter.($ext ? '.'.$ext : '');
                    $counter++;
                }
                $zip->addFile($tmpFile, $name);
                $tmpFiles[] = $tmpFile;
            } catch (\Throwable) {
            }
        }

        $zip->close();
        foreach ($tmpFiles as $f) {
            @unlink($f);
        }

        $size = filesize($tmpZip);

        return response()->streamDownload(function () use ($tmpZip) {
            $fh = fopen($tmpZip, 'rb');
            while (! feof($fh)) {
                echo fread($fh, 65536);
                flush();
            }
            fclose($fh);
            @unlink($tmpZip);
        }, $zipName, [
            'Content-Type' => 'application/zip',
            'Content-Length' => $size,
        ]);
    }
}
