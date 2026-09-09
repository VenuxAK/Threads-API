<?php

namespace App\Actions;

use App\DTOs\FollowResult;
use App\Models\Follow;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Action executing user unfollow operations in MySQL.
 * Removes the follow pairing, re-aggregates follower and following counters,
 * and returns the updated state to keep frontend stores synchronized.
 */
class UnfollowUserAction
{
    public function execute(int $followerId, int $targetUserId): FollowResult
    {
        if ($followerId === $targetUserId) {
            throw ValidationException::withMessages([
                'userId' => ['You cannot unfollow yourself.'],
            ]);
        }

        $targetUser = User::find($targetUserId);
        if (! $targetUser) {
            throw new \RuntimeException('Target user not found', 404);
        }

        Follow::where('follower_id', $followerId)
            ->where('following_id', $targetUserId)
            ->delete();

        $followersCount = $targetUser->followers()->count();
        $followingCount = $targetUser->following()->count();

        return new FollowResult(
            status: false,
            followers_count: $followersCount,
            following_count: $followingCount,
            user: $targetUser
        );
    }
}
