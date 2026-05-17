<?php

namespace App\Actions;

use App\DTOs\InteractionResult;
use App\Models\Post;
use App\Models\PostMetaData;
use App\Models\PostRepost;

class RepostPostAction
{
    public function execute(string $postId, int $userId): InteractionResult
    {
        $post = Post::find($postId);
        if (!$post) {
            throw new \RuntimeException('Post not found', 404);
        }

        $existing = PostRepost::where('post_id', $postId)
            ->where('user_id', $userId)
            ->first();

        $postMeta = PostMetaData::firstOrCreate(
            ['post_id' => $postId],
            ['user_id' => $post->user_id, 'likes_count' => 0, 'comments_count' => 0, 'shares_count' => 0, 'reposts_count' => 0]
        );

        if ($existing) {
            $existing->delete();
            PostMetaData::where('post_id', $postId)
                ->where('reposts_count', '>', 0)
                ->decrement('reposts_count');

            $count = PostMetaData::where('post_id', $postId)->value('reposts_count') ?? 0;
            return new InteractionResult($count, false);
        }

        PostRepost::create(['post_id' => $postId, 'user_id' => $userId]);
        $postMeta->increment('reposts_count');

        return new InteractionResult($postMeta->fresh()->reposts_count, true);
    }
}
