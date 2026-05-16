<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\PostLike;
use App\Models\PostMetaData;
use App\Models\PostRepost;
use App\Utils\Http;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class PostInteractionController extends Controller
{
    use Http;

    /**
     * Like a post
     *
     * @route POST /api/v1/posts/{id}/like
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function like(string $id)
    {
        $post = Post::find($id);
        if (!$post) {
            return $this->error("Post not found", 404);
        }

        $userId = Auth::id();
        if (!$userId) {
            return $this->error("Unauthorized", 401);
        }

        try {
            // Check if already liked
            $existingLike = PostLike::where('post_id', $id)
                ->where('user_id', $userId)
                ->first();
            
            // If already liked, unlike it (toggle behavior)
            if ($existingLike) {
                $existingLike->delete();

                PostMetaData::where('post_id', $id)
                    ->where('likes_count', '>', 0)
                    ->decrement('likes_count');
                $postMeta = PostMetaData::where('post_id', $id)->first();

                return $this->success([
                    'likes_count' => $postMeta ? $postMeta->likes_count : 0,
                    'liked' => false
                ]);
            }

            // Create like record
            PostLike::create([
                'post_id' => $id,
                'user_id' => $userId,
            ]);

            // Update likes_count in metadata
            $postMeta = PostMetaData::firstOrCreate(
                ['post_id' => $id],
                ['user_id' => $post->user_id, 'likes_count' => 0, 'comments_count' => 0, 'shares_count' => 0, 'reposts_count' => 0]
            );
            
            $postMeta->increment('likes_count');

            return $this->success([
                'likes_count' => $postMeta->likes_count,
                'liked' => true
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to like post', [
                'error' => $e->getMessage(),
                'post_id' => $id,
                'user_id' => $userId
            ]);

            return $this->error('Failed to like post. Please try again.', 500);
        }
    }

    /**
     * Unlike a post
     *
     * @route DELETE /api/v1/posts/{id}/like
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function unlike(string $id)
    {
        $post = Post::find($id);
        if (!$post) {
            return $this->error("Post not found", 404);
        }

        $userId = Auth::id();
        if (!$userId) {
            return $this->error("Unauthorized", 401);
        }

        try {
            // Check if liked
            $existingLike = PostLike::where('post_id', $id)
                ->where('user_id', $userId)
                ->first();
            
            if (!$existingLike) {
                return $this->error("You have not liked this post", 400);
            }

            // Delete like record
            $existingLike->delete();

            PostMetaData::where('post_id', $id)
                ->where('likes_count', '>', 0)
                ->decrement('likes_count');
            $postMeta = PostMetaData::where('post_id', $id)->first();

            return $this->success([
                'likes_count' => $postMeta ? $postMeta->likes_count : 0,
                'liked' => false
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to unlike post', [
                'error' => $e->getMessage(),
                'post_id' => $id,
                'user_id' => $userId
            ]);

            return $this->error('Failed to unlike post. Please try again.', 500);
        }
    }

    /**
     * Check if current user has liked a post
     *
     * @route GET /api/v1/posts/{id}/like/check
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkLike(string $id)
    {
        $post = Post::find($id);
        if (!$post) {
            return $this->error("Post not found", 404);
        }

        $userId = Auth::id();
        if (!$userId) {
            return $this->success([
                'liked' => false,
                'message' => 'User not authenticated'
            ]);
        }

        try {
            $liked = PostLike::where('post_id', $id)
                ->where('user_id', $userId)
                ->exists();

            return $this->success([
                'liked' => $liked
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to check like status', [
                'error' => $e->getMessage(),
                'post_id' => $id,
                'user_id' => $userId
            ]);

            return $this->error('Failed to check like status. Please try again.', 500);
        }
    }

    /**
     * Share a post
     * 
     * @route POST /api/v1/posts/{id}/share
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function share(string $id)
    {
        $post = Post::find($id);
        if (!$post) {
            return $this->error("Post not found", 404);
        }

        try {
            $postMeta = PostMetaData::firstOrCreate(
                ['post_id' => $id],
                ['user_id' => $post->user_id, 'likes_count' => 0, 'comments_count' => 0, 'shares_count' => 0, 'reposts_count' => 0]
            );
            
            $postMeta->increment('shares_count');

            return $this->success([
                'shares_count' => $postMeta->fresh()->shares_count,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to share post', [
                'error' => $e->getMessage(),
                'post_id' => $id,
                'user_id' => Auth::id()
            ]);

            return $this->error('Failed to share post. Please try again.', 500);
        }
    }

    /**
     * Toggle repost (auth user): create repost or remove existing (Threads-style).
     *
     * @route POST /api/v1/posts/{id}/repost
     */
    public function repost(string $id)
    {
        $post = Post::find($id);
        if (! $post) {
            return $this->error('Post not found', 404);
        }

        $userId = Auth::id();
        if (! $userId) {
            return $this->error('Unauthorized', 401);
        }

        try {
            $existing = PostRepost::where('post_id', (string) $id)
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

                return $this->success([
                    'reposts_count' => PostMetaData::where('post_id', $id)->value('reposts_count') ?? 0,
                    'reposted' => false,
                ]);
            }

            PostRepost::create([
                'post_id' => (string) $id,
                'user_id' => $userId,
            ]);

            $postMeta->increment('reposts_count');

            return $this->success([
                'reposts_count' => $postMeta->fresh()->reposts_count,
                'reposted' => true,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to toggle repost', [
                'error' => $e->getMessage(),
                'post_id' => $id,
                'user_id' => $userId,
            ]);

            return $this->error('Failed to repost. Please try again.', 500);
        }
    }

    /**
     * Whether the authenticated user has reposted this post.
     *
     * @route GET /api/v1/posts/{id}/repost/check
     */
    public function checkRepost(string $id)
    {
        $post = Post::find($id);
        if (! $post) {
            return $this->error('Post not found', 404);
        }

        $userId = Auth::id();
        if (! $userId) {
            return $this->success([
                'reposted' => false,
                'message' => 'User not authenticated',
            ]);
        }

        try {
            $reposted = PostRepost::where('post_id', (string) $id)
                ->where('user_id', $userId)
                ->exists();

            return $this->success([
                'reposted' => $reposted,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to check repost status', [
                'error' => $e->getMessage(),
                'post_id' => $id,
                'user_id' => $userId,
            ]);

            return $this->error('Failed to check repost status. Please try again.', 500);
        }
    }

    /**
     * Get post interaction counts
     * 
     * @route GET /api/v1/posts/{id}/interactions
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function interactions(string $id)
    {
        $post = Post::find($id);
        if (!$post) {
            return $this->error("Post not found", 404);
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

        return $this->success([
            'likes_count' => $metadata->likes_count,
            'comments_count' => $metadata->comments_count,
            'shares_count' => $metadata->shares_count,
            'reposts_count' => $metadata->reposts_count,
        ]);
    }
}