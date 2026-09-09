<?php

namespace App\GraphQL\Mutations;

use App\Models\Post;
use App\Models\SavedPost;
use Illuminate\Support\Facades\Auth;

/**
 * GraphQL mutation resolver for bookmarking and unbookmarking threads.
 * Manages user-post pairings in MySQL `saved_posts`, confirming existence
 * before persistence and returning boolean state flags.
 */
class SavedPostMutation
{
    /**
     * Bookmark a post for the authenticated user.
     *
     * @param  null  $root
     * @param  array{postId: string}  $args
     */
    public function save($root, array $args): bool
    {
        $userId = (int) Auth::id();
        $postId = (string) $args['postId'];

        $post = Post::find($postId);
        if (! $post) {
            throw new \RuntimeException('Post not found', 404);
        }

        SavedPost::firstOrCreate([
            'user_id' => $userId,
            'post_id' => $postId,
        ]);

        return true;
    }

    /**
     * Remove a post bookmark for the authenticated user.
     *
     * @param  null  $root
     * @param  array{postId: string}  $args
     */
    public function unsave($root, array $args): bool
    {
        $userId = (int) Auth::id();
        $postId = (string) $args['postId'];

        SavedPost::where('user_id', $userId)
            ->where('post_id', $postId)
            ->delete();

        return true;
    }
}
