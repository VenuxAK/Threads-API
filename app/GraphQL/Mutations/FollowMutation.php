<?php

namespace App\GraphQL\Mutations;

use App\Actions\FollowUserAction;
use App\Actions\UnfollowUserAction;
use App\DTOs\FollowResult;
use Illuminate\Support\Facades\Auth;

/**
 * GraphQL mutation resolver for user follow and unfollow operations.
 * Handles authenticated user interactions, invoking dedicated business actions
 * and returning the updated follow status and counters.
 */
class FollowMutation
{
    public function __construct(
        private FollowUserAction $followUserAction,
        private UnfollowUserAction $unfollowUserAction,
    ) {}

    /**
     * Follow a target user by ID.
     *
     * @param  null  $root
     * @param  array{userId: string|int}  $args
     */
    public function follow($root, array $args): FollowResult
    {
        $followerId = (int) Auth::id();
        $targetUserId = (int) $args['userId'];

        return $this->followUserAction->execute($followerId, $targetUserId);
    }

    /**
     * Unfollow a target user by ID.
     *
     * @param  null  $root
     * @param  array{userId: string|int}  $args
     */
    public function unfollow($root, array $args): FollowResult
    {
        $followerId = (int) Auth::id();
        $targetUserId = (int) $args['userId'];

        return $this->unfollowUserAction->execute($followerId, $targetUserId);
    }
}
