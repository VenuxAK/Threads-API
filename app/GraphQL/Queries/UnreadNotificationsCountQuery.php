<?php

namespace App\GraphQL\Queries;

use App\Models\Notification;
use Illuminate\Support\Facades\Auth;

/**
 * GraphQL query resolver retrieving the unread notification count for the authenticated user.
 * Utilizes the indexed `['user_id', 'read_at']` composite MySQL index for fast counter retrieval.
 */
class UnreadNotificationsCountQuery
{
    /**
     * Resolve total unread notification count for the active user.
     *
     * @param  null  $root
     * @param  array<string, mixed>  $args
     */
    public function __invoke($root, array $args): int
    {
        $userId = (int) Auth::id();

        return Notification::where('user_id', $userId)
            ->whereNull('read_at')
            ->count();
    }
}
