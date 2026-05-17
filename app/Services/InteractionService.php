<?php

namespace App\Services;

use App\DTOs\InteractionResult;
use App\Models\Post;
use App\Models\PostLike;
use App\Models\PostMetaData;
use App\Models\PostRepost;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class InteractionService
{
    public function likePost(string $id, int $userId): InteractionResult
    {
        $post = Post::find($id);
        if (!$post) {
            throw new \RuntimeException('Post not found', 404);
        }

        $existingLike = PostLike::where('post_id', $id)
            ->where('user_id', $userId)
            ->first();

        if ($existingLike) {
            $existingLike->delete();

            PostMetaData::where('post_id', $id)
                ->where('likes_count', '>', 0)
                ->decrement('likes_count');

            $postMeta = PostMetaData::where('post_id', $id)->first();

            return new InteractionResult(
                count: $postMeta ? $postMeta->likes_count : 0,
                active: false,
            );
        }

        PostLike::create([
            'post_id' => $id,
            'user_id' => $userId,
        ]);

        $postMeta = PostMetaData::firstOrCreate(
            ['post_id' => $id],
            ['user_id' => $post->user_id, 'likes_count' => 0, 'comments_count' => 0, 'shares_count' => 0, 'reposts_count' => 0]
        );

        $postMeta->increment('likes_count');

        return new InteractionResult(
            count: $postMeta->likes_count,
            active: true,
        );
    }

    public function unlikePost(string $id, int $userId): InteractionResult
    {
        $post = Post::find($id);
        if (!$post) {
            throw new \RuntimeException('Post not found', 404);
        }

        $existingLike = PostLike::where('post_id', $id)
            ->where('user_id', $userId)
            ->first();

        if (!$existingLike) {
            throw new \RuntimeException('You have not liked this post', 400);
        }

        $existingLike->delete();

        PostMetaData::where('post_id', $id)
            ->where('likes_count', '>', 0)
            ->decrement('likes_count');

        $postMeta = PostMetaData::where('post_id', $id)->first();

        return new InteractionResult(
            count: $postMeta ? $postMeta->likes_count : 0,
            active: false,
        );
    }

    public function checkLike(string $id, ?int $userId): bool
    {
        if (!$userId) {
            return false;
        }

        return PostLike::where('post_id', $id)
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
        $post = Post::find($id);
        if (!$post) {
            throw new \RuntimeException('Post not found', 404);
        }

        $existing = PostRepost::where('post_id', $id)
            ->where('user_id', $userId)
            ->first();

        $postMeta = PostMetaData::firstOrCreate(
            ['post_id' => $id],
            ['user_id' => $post->user_id, 'likes_count' => 0, 'comments_count' => 0, 'shares_count' => 0, 'reposts_count' => 0]
        );

        if ($existing) {
            $existing->delete();

            PostMetaData::where('post_id', $id)
                ->where('reposts_count', '>', 0)
                ->decrement('reposts_count');

            $count = PostMetaData::where('post_id', $id)->value('reposts_count') ?? 0;

            return new InteractionResult(count: $count, active: false);
        }

        PostRepost::create([
            'post_id' => $id,
            'user_id' => $userId,
        ]);

        $postMeta->increment('reposts_count');

        return new InteractionResult(
            count: $postMeta->fresh()->reposts_count,
            active: true,
        );
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
