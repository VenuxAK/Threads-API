<?php

namespace App\GraphQL\Mutations;

use App\Actions\LikePostAction;
use App\Actions\RepostPostAction;
use Illuminate\Support\Facades\Auth;

class InteractionMutation
{
    /**
     * Handles social interaction mutations on posts (likes, unlikes, reposts).
     * Invokes atomic Actions to update MySQL relational join tables and counter caches,
     * returning the updated counter and boolean status for optimistic client updates.
     */
    public function __construct(
        private LikePostAction $likePostAction,
        private RepostPostAction $repostPostAction
    ) {}

    /**
     * Like a post or toggle like status for the authenticated user.
     *
     * @param  null  $root
     * @param  array{postId: string}  $args
     * @return array{count: int, status: bool}
     */
    public function like($root, array $args): array
    {
        $userId = (int) Auth::id();
        $result = $this->likePostAction->execute($args['postId'], $userId);

        return [
            'count' => $result->count,
            'status' => $result->active,
        ];
    }

    /**
     * Explicitly remove a like from a post.
     *
     * @param  null  $root
     * @param  array{postId: string}  $args
     * @return array{count: int, status: bool}
     */
    public function unlike($root, array $args): array
    {
        $userId = (int) Auth::id();
        $result = $this->likePostAction->unlike($args['postId'], $userId);

        return [
            'count' => $result->count,
            'status' => $result->active,
        ];
    }

    /**
     * Toggle repost status for a post by the authenticated user.
     *
     * @param  null  $root
     * @param  array{postId: string}  $args
     * @return array{count: int, status: bool}
     */
    public function repost($root, array $args): array
    {
        $userId = (int) Auth::id();
        $result = $this->repostPostAction->execute($args['postId'], $userId);

        return [
            'count' => $result->count,
            'status' => $result->active,
        ];
    }
}
