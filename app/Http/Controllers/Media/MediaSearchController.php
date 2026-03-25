<?php

namespace App\Http\Controllers\Media;

use App\Http\Controllers\Controller;
use App\Models\Tenant\MediaAlbum;
use App\Models\Tenant\MediaItem;
use App\Models\Tenant\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MediaSearchController extends Controller
{
    public function index(Request $request): View
    {
        /** @var User $user */
        $user = auth()->user();

        $q = trim($request->input('q', ''));
        $type = $request->input('type', '');
        $from = $request->input('from', '');
        $to = $request->input('to', '');
        $albumId = $request->input('album_id', '');

        // Arbre sidebar
        $albumTree = MediaAlbum::visibleFor($user)
            ->whereNull('parent_id')
            ->withCount(['items', 'children'])
            ->orderBy('name')
            ->get();
        $ancestorIds = [];

        // Sans critère : page vide
        if (! $q && ! $type && ! $from && ! $to && ! $albumId) {
            $results = null;

            return view('media.search', compact('albumTree', 'ancestorIds', 'q', 'type', 'from', 'to', 'albumId', 'results'));
        }

        $results = MediaItem::visibleFor($user)
            ->with(['album', 'album.parent'])
            ->when($q, function ($qry) use ($q) {
                $qry->where(fn ($sub) => $sub
                    ->where('file_name', 'LIKE', "%{$q}%")
                    ->orWhere('caption', 'LIKE', "%{$q}%")
                );
            })
            ->when($type === 'image', fn ($qry) => $qry->where('mime_type', 'LIKE', 'image/%'))
            ->when($type === 'video', fn ($qry) => $qry->where('mime_type', 'LIKE', 'video/%'))
            ->when($type === 'document', fn ($qry) => $qry->where('mime_type', 'NOT LIKE', 'image/%')
                ->where('mime_type', 'NOT LIKE', 'video/%'))
            ->when($from, fn ($qry) => $qry->where(fn ($sub) => $sub
                ->where('exif_taken_at', '>=', $from)
                ->orWhere(fn ($s2) => $s2->whereNull('exif_taken_at')->where('created_at', '>=', $from))
            ))
            ->when($to, fn ($qry) => $qry->where(fn ($sub) => $sub
                ->where('exif_taken_at', '<=', $to.' 23:59:59')
                ->orWhere(fn ($s2) => $s2->whereNull('exif_taken_at')->where('created_at', '<=', $to.' 23:59:59'))
            ))
            ->when($albumId, fn ($qry) => $qry->where('album_id', $albumId))
            ->orderByDesc('exif_taken_at')
            ->orderByDesc('created_at')
            ->paginate(48)
            ->withQueryString();

        return view('media.search', compact('albumTree', 'ancestorIds', 'q', 'type', 'from', 'to', 'albumId', 'results'));
    }
}
