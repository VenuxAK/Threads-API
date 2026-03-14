<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PostRequest;
use App\Models\Post;
use App\Models\PostMetaData;
use App\Models\User;
use App\Transformers\PostTransformer;
use App\Utils\HashtagTrait;
use App\Utils\Http;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class PostController extends Controller
{
    use HashtagTrait;
    use Http;

    private $postTransformer;
    public function __construct(PostTransformer $postTransformer)
    {
        $this->postTransformer = $postTransformer;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // Get pagination parameters
        $perPage = min($request->get('per_page', 15), 50); // Max 50 per page
        $page = max($request->get('page', 1), 1);

        // Get posts with pagination
        $posts = Post::latest()->paginate($perPage, ['*'], 'page', $page);

        $transformedPosts = $this->postTransformer->transformPosts($posts);

        return $this->success([
            "posts" => $transformedPosts,
            "pagination" => [
                "total" => $posts->total(),
                "per_page" => $posts->perPage(),
                "current_page" => $posts->currentPage(),
                "last_page" => $posts->lastPage(),
                "from" => $posts->firstItem(),
                "to" => $posts->lastItem(),
            ]
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(PostRequest $request)
    {
        $content = $request->content;
        $contentTags = $this->filterHashTags($content);

        try {
            // Store in MongoDB
            $post = Post::create([
                "content" => $content,
                "tags" => $contentTags
            ]);

            // The PostMetaData will be created by the Post model's created event
            // If it fails, an exception will be thrown and caught here

            return $this->responseStatus(204);
        } catch (\Exception $e) {
            Log::error('Failed to create post', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
                'content_length' => strlen($content)
            ]);

            return $this->error('Failed to create post. Please try again.', 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $post = Post::find($id);
        if (!$post) {
            return $this->error("Post not found", 404);
        }

        $transformedPost = $this->postTransformer->transformPost($post);
        return $this->success([
            "post" => $transformedPost
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $post = Post::find($id);
        if (!$post) {
            return $this->error("Post not found", 404);
        }

        // Authorize the user
        if ($request->user()->cannot('update', $post)) {
            return $this->error("You are not authorized to make this request", 403);
        }

        // Validate content
        $request->validate([
            "content" => "string|max:5000"
        ]);

        // Update
        $post->update([
            "content" => $request->content ?? $post->content
        ]);

        return $this->responseStatus(204);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, string $id)
    {
        $post = Post::find($id);
        if (!$post) {
            return $this->error("Post not found", 404);
        }

        // Authorize the user
        if ($request->user()->cannot('delete', $post)) {
            return $this->error("You are not authorized to make this request", 403);
        }

        try {
            $post->delete();
            return $this->responseStatus(204);
        } catch (\Exception $e) {
            Log::error('Failed to delete post', [
                'error' => $e->getMessage(),
                'post_id' => $id,
                'user_id' => Auth::id()
            ]);

            return $this->error('Failed to delete post. Please try again.', 500);
        }
    }
}
