<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\PostMetaData;
use App\Utils\Http;
use Illuminate\Http\Request;
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

        try {
            // Increment likes count
            PostMetaData::where('post_id', $id)
                ->increment('likes_count');
            
            return $this->responseStatus(204);
            
        } catch (\Exception $e) {
            Log::error('Failed to like post', [
                'error' => $e->getMessage(),
                'post_id' => $id,
                'user_id' => Auth::id()
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

        try {
            // Decrement likes count (but not below 0)
            PostMetaData::where('post_id', $id)
                ->where('likes_count', '>', 0)
                ->decrement('likes_count');
            
            return $this->responseStatus(204);
            
        } catch (\Exception $e) {
            Log::error('Failed to unlike post', [
                'error' => $e->getMessage(),
                'post_id' => $id,
                'user_id' => Auth::id()
            ]);
            
            return $this->error('Failed to unlike post. Please try again.', 500);
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
            // Increment shares count
            PostMetaData::where('post_id', $id)
                ->increment('shares_count');
            
            return $this->responseStatus(204);
            
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
            // Create metadata if it doesn't exist (should not happen)
            $metadata = PostMetaData::create([
                'post_id' => $id,
                'user_id' => $post->user_id
            ]);
        }

        return $this->success([
            'likes_count' => $metadata->likes_count,
            'comments_count' => $metadata->comments_count,
            'shares_count' => $metadata->shares_count,
        ]);
    }
}