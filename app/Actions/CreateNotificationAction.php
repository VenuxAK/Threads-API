<?php

namespace App\Actions;

use App\Models\Notification;
use Illuminate\Support\Facades\Log;

/**
 * Action responsible for creating in-app user notifications.
 * Enforces business constraints such as preventing self-notifications (e.g. liking one's own post)
 * and safely recording interaction context across MySQL and MongoDB references.
 */
class CreateNotificationAction
{
    /**
     * Dispatch an in-app notification to a user.
     *
     * @param  int  $userId  Target recipient user ID.
     * @param  int  $senderId  Actor who triggered the interaction.
     * @param  string  $type  Action type (like, comment, repost, follow, mention).
     * @param  string|null  $entityId  Associated post or comment ID.
     */
    public function execute(int $userId, int $senderId, string $type, ?string $entityId = null): ?Notification
    {
        // Suppress notifications for actions taken on one's own content
        if ($userId === $senderId) {
            return null;
        }

        try {
            return Notification::create([
                'user_id' => $userId,
                'sender_id' => $senderId,
                'type' => $type,
                'entity_id' => $entityId,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to persist in-app notification', [
                'user_id' => $userId,
                'sender_id' => $senderId,
                'type' => $type,
                'entity_id' => $entityId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
