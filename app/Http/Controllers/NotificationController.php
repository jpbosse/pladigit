<?php

namespace App\Http\Controllers;

use App\Models\Tenant\Notification;
use Illuminate\Http\Request;

/**
 * Gestion des notifications in-app.
 *
 * Routes :
 *   GET    /notifications          → index (JSON)
 *   PATCH  /notifications/{id}    → markRead
 *   POST   /notifications/read-all → markAllRead
 *   DELETE /notifications/{id}    → destroy
 */
class NotificationController extends Controller
{
    /**
     * Liste des notifications de l'utilisateur courant.
     * Retourne les 30 plus récentes.
     */
    public function index(Request $request)
    {
        /** @var \App\Models\Tenant\User $user */
        $user = auth()->user();

        $notifications = Notification::on('tenant')
            ->forUser($user->id)
            ->orderByDesc('created_at')
            ->limit(30)
            ->get()
            ->map(function ($n) {
                $n->created_at_diff = $n->created_at->locale('fr')->diffForHumans();

                return $n;
            });

        $unreadCount = Notification::on('tenant')
            ->forUser($user->id)
            ->unread()
            ->count();

        if ($request->wantsJson()) {
            return response()->json([
                'notifications' => $notifications,
                'unread_count' => $unreadCount,
            ]);
        }

        return view('notifications.index', compact('notifications', 'unreadCount'));
    }

    /**
     * Marquer une notification comme lue.
     */
    public function markRead(Notification $notification)
    {
        /** @var \App\Models\Tenant\User $user */
        $user = auth()->user();

        abort_if($notification->user_id !== $user->id, 403);

        $notification->markAsRead();

        return response()->json(['success' => true]);
    }

    /**
     * Marquer toutes les notifications comme lues.
     */
    public function markAllRead()
    {
        /** @var \App\Models\Tenant\User $user */
        $user = auth()->user();

        Notification::on('tenant')
            ->forUser($user->id)
            ->unread()
            ->update(['read' => true, 'read_at' => now()]);

        return response()->json(['success' => true]);
    }

    /**
     * Supprimer une notification.
     */
    public function destroy(Notification $notification)
    {
        /** @var \App\Models\Tenant\User $user */
        $user = auth()->user();

        abort_if($notification->user_id !== $user->id, 403);

        $notification->delete();

        return response()->json(['success' => true]);
    }
}
