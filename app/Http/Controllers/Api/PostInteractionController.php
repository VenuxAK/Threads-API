<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\PostLike;
use App\Models\PostMetaData;
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

                // Update likes_count
                $postMeta = PostMetaData::where('post_id', $id)->first();
                if ($postMeta && $postMeta->likes_count > 0) {
                    $postMeta->decrement('likes_count');
                }

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
                ['user_id' => $post->user_id, 'likes_count' => 0, 'comments_count' => 0, 'shares_count' => 0]
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

            // Update likes_count
            $postMeta = PostMetaData::where('post_id', $id)->first();
            if ($postMeta && $postMeta->likes_count > 0) {
                $postMeta->decrement('likes_count');
            }

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
                ['user_id' => $post->user_id, 'likes_count' => 0, 'comments_count' => 0, 'shares_count' => 0]
            );
            
            $postMeta->increment('shares_count');

            return $this->success([
                'shares_count' => $postMeta->shares_count
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
                'shares_count' => 0
            ]);
        }

        return $this->success([
            'likes_count' => $metadata->likes_count,
            'comments_count' => $metadata->comments_count,
            'shares_count' => $metadata->shares_count,
        ]);
    }
}