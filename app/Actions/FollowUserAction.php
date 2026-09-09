<?php

namespace App\Actions;

use App\DTOs\FollowResult;
use App\Models\Follow;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Action executing the user follow lifecycle in MySQL.
 * Validates target account existence, prevents self-following, persists the relationship,
 * and returns updated counter metrics for seamless client optimistic reconciliation.
 */
class FollowUserAction
{
    public function execute(int $followerId, int $targetUserId): FollowResult
    {
        if ($followerId === $targetUserId) {
            throw ValidationException::withMessages([
                'userId' => ['You cannot follow yourself.'],
            ]);
        }

        $targetUser = User::find($targetUserId);
        if (! $targetUser) {
            throw new \RuntimeException('Target user not found', 404);
        }

        $existing = Follow::where('follower_id', $followerId)
            ->where('following_id', $targetUserId)
            ->first();

        if (! $existing) {
            Follow::create([
                'follower_id' => $followerId,
                'following_id' => $targetUserId,
            ]);

            // Dispatch notification if notification action exists
            try {
                if (class_exists(CreateNotificationAction::class)) {
                    app(CreateNotificationAction::class)->execute(
                        userId: $targetUserId,
                        senderId: $followerId,
                        type: 'follow'
                    );
                }
            } catch (\Throwable $e) {
                Log::warning('Failed to dispatch follow notification', [
                    'follower_id' => $followerId,
                    'target_user_id' => $targetUserId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $followersCount = $targetUser->followers()->count();
        $followingCount = $targetUser->following()->count();

        return new FollowResult(
            status: true,
            followers_count: $followersCount,
            following_count: $followingCount,
            user: $targetUser
        );
    }
}
