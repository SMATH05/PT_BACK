<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\InteractsWithActorScope;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    use InteractsWithActorScope;

    /**
     * Get all notifications for the current actor.
     */
    public function index(Request $request)
    {
        $actor = $this->currentChefDeProjet($request) 
               ?? $this->currentManager($request) 
               ?? $this->currentDeveloper($request);

        if (!$actor) {
            return response()->json([]);
        }

        return response()->json($actor->notifications()->latest()->get());
    }

    /**
     * Mark a notification as read.
     */
    public function markAsRead(Request $request, $id)
    {
        $actor = $this->currentChefDeProjet($request) 
               ?? $this->currentManager($request) 
               ?? $this->currentDeveloper($request);

        if (!$actor) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $notification = $actor->notifications()->find($id);

        if ($notification) {
            $notification->markAsRead();
            return response()->json(['message' => 'Notification marked as read']);
        }

        return response()->json(['message' => 'Notification not found'], 404);
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllAsRead(Request $request)
    {
        $actor = $this->currentChefDeProjet($request) 
               ?? $this->currentManager($request) 
               ?? $this->currentDeveloper($request);

        if (!$actor) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $actor->unreadNotifications->markAsRead();

        return response()->json(['message' => 'All notifications marked as read']);
    }
}
