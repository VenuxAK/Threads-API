<?php

namespace App\GraphQL\Mutations;

use App\Models\Notification;
use Illuminate\Support\Facades\Auth;

/**
 * GraphQL mutation resolver for marking in-app notifications as read.
 * Allows single notification updates or batch marking all unread alerts for the active session.
 */
class NotificationMutation
{
    /**
     * Mark a specific notification as read.
     *
     * @param  null  $root
     * @param  array{id: string|int}  $args
     */
    public function markAsRead($root, array $args): bool
    {
        $userId = (int) Auth::id();
        $notifId = (int) $args['id'];

        $updated = Notification::where('id', $notifId)
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return $updated > 0 || Notification::where('id', $notifId)->where('user_id', $userId)->exists();
    }

    /**
     * Mark all pending notifications as read for the authenticated user.
     *
     * @param  null  $root
     * @param  array<string, mixed>  $args
     */
    public function markAllAsRead($root, array $args): bool
    {
        $userId = (int) Auth::id();

        Notification::where('user_id', $userId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return true;
    }
}
