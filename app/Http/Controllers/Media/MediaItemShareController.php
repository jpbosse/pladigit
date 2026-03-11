<?php

namespace App\Http\Controllers\Media;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Department;
use App\Models\Tenant\MediaItem;
use App\Models\Tenant\Share;
use App\Models\Tenant\User;
use App\Services\ShareService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MediaItemShareController extends Controller
{
    public function __construct(private ShareService $shareService) {}

    public function edit(MediaItem $item): View
    {
        $this->authorize('manage', $item);

        $shares = $this->shareService->sharesFor($item);

        $userShares = $shares->where('shared_with_type', 'user');
        $deptShares = $shares->where('shared_with_type', 'department');

        $departments = Department::orderBy('name')->get();
        $users = User::where('status', 'active')
            ->whereNotIn('role', [UserRole::PRESIDENT->value, UserRole::DGS->value])
            ->orderBy('name')->get();

        return view('media.items.shares', compact(
            'item',
            'userShares',
            'deptShares',
            'departments',
            'users',
        ));
    }

    public function store(Request $request, MediaItem $item): RedirectResponse
    {
        $this->authorize('manage', $item);

        $request->validate([
            'shared_with_type' => 'required|in:user,department',
            'shared_with_id' => 'required|integer',
        ]);

        $this->shareService->upsert(
            $item,
            $request->input('shared_with_type'),
            $request->integer('shared_with_id'),
            null,
            [
                'can_view' => $request->boolean('can_view'),
                'can_download' => $request->boolean('can_download'),
                'can_edit' => false,
                'can_manage' => false,
            ],
            auth()->id()
        );

        return redirect()->route('media.items.shares.edit', $item)
            ->with('success', 'Partage ajouté.');
    }

    public function update(Request $request, MediaItem $item, Share $share): RedirectResponse
    {
        $this->authorize('manage', $item);

        $share->update([
            'can_view' => $request->boolean('can_view'),
            'can_download' => $request->boolean('can_download'),
            'can_edit' => false,
            'can_manage' => false,
        ]);

        return redirect()->route('media.items.shares.edit', $item)
            ->with('success', 'Droits mis à jour.');
    }

    public function destroy(MediaItem $item, Share $share): RedirectResponse
    {
        $this->authorize('manage', $item);
        $share->delete();

        return redirect()->route('media.items.shares.edit', $item)
            ->with('success', 'Partage supprimé.');
    }
}
