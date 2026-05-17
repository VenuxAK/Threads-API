<?php

namespace App\Actions;

use App\DTOs\InteractionResult;
use App\Models\Post;
use App\Models\PostLike;
use App\Models\PostMetaData;
use Illuminate\Support\Facades\Log;

class LikePostAction
{
    public function execute(string $postId, int $userId): InteractionResult
    {
        $post = Post::find($postId);
        if (!$post) {
            throw new \RuntimeException('Post not found', 404);
        }

        $existingLike = PostLike::where('post_id', $postId)
            ->where('user_id', $userId)
            ->first();

        if ($existingLike) {
            $existingLike->delete();
            PostMetaData::where('post_id', $postId)
                ->where('likes_count', '>', 0)
                ->decrement('likes_count');

            $count = PostMetaData::where('post_id', $postId)->value('likes_count') ?? 0;
            return new InteractionResult($count, false);
        }

        PostLike::create(['post_id' => $postId, 'user_id' => $userId]);

        $postMeta = PostMetaData::firstOrCreate(
            ['post_id' => $postId],
            ['user_id' => $post->user_id, 'likes_count' => 0, 'comments_count' => 0, 'shares_count' => 0, 'reposts_count' => 0]
        );
        $postMeta->increment('likes_count');

        return new InteractionResult($postMeta->likes_count, true);
    }

    public function unlike(string $postId, int $userId): InteractionResult
    {
        $post = Post::find($postId);
        if (!$post) {
            throw new \RuntimeException('Post not found', 404);
        }

        $existingLike = PostLike::where('post_id', $postId)
            ->where('user_id', $userId)
            ->first();

        if (!$existingLike) {
            throw new \RuntimeException('You have not liked this post', 400);
        }

        $existingLike->delete();
        PostMetaData::where('post_id', $postId)
            ->where('likes_count', '>', 0)
            ->decrement('likes_count');

        $count = PostMetaData::where('post_id', $postId)->value('likes_count') ?? 0;
        return new InteractionResult($count, false);
    }
}
