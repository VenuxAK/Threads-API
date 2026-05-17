<?php

namespace App\Services;

use App\Actions\LikePostAction;
use App\Actions\RepostPostAction;
use App\DTOs\InteractionResult;
use App\Models\Post;
use App\Models\PostMetaData;
use App\Models\PostRepost;
use Illuminate\Support\Facades\Log;

class InteractionService
{
    public function __construct(
        private LikePostAction $likePostAction,
        private RepostPostAction $repostPostAction,
    ) {}

    public function likePost(string $id, int $userId): InteractionResult
    {
        return $this->likePostAction->execute($id, $userId);
    }

    public function unlikePost(string $id, int $userId): InteractionResult
    {
        return $this->likePostAction->unlike($id, $userId);
    }

    public function checkLike(string $id, ?int $userId): bool
    {
        if (!$userId) {
            return false;
        }

        return \App\Models\PostLike::where('post_id', $id)
            ->where('user_id', $userId)
            ->exists();
    }

    public function sharePost(string $id): int
    {
        $post = Post::find($id);
        if (!$post) {
            throw new \RuntimeException('Post not found', 404);
        }

        $postMeta = PostMetaData::firstOrCreate(
            ['post_id' => $id],
            ['user_id' => $post->user_id, 'likes_count' => 0, 'comments_count' => 0, 'shares_count' => 0, 'reposts_count' => 0]
        );

        $postMeta->increment('shares_count');

        return $postMeta->fresh()->shares_count;
    }

    public function toggleRepost(string $id, int $userId): InteractionResult
    {
        return $this->repostPostAction->execute($id, $userId);
    }

    public function checkRepost(string $id, ?int $userId): bool
    {
        if (!$userId) {
            return false;
        }

        return PostRepost::where('post_id', $id)
            ->where('user_id', $userId)
            ->exists();
    }

    public function getInteractions(string $id): array
    {
        $post = Post::find($id);
        if (!$post) {
            throw new \RuntimeException('Post not found', 404);
        }

        $metadata = PostMetaData::where('post_id', $id)->first();

        if (!$metadata) {
            $metadata = PostMetaData::create([
                'post_id' => $id,
                'user_id' => $post->user_id,
                'likes_count' => 0,
                'comments_count' => 0,
                'shares_count' => 0,
                'reposts_count' => 0,
            ]);
        }

        return [
            'likes_count' => $metadata->likes_count,
            'comments_count' => $metadata->comments_count,
            'shares_count' => $metadata->shares_count,
            'reposts_count' => $metadata->reposts_count,
        ];
    }
}
