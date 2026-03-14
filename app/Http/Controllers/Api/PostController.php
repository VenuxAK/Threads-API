<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\PostMetaData;
use App\Models\User;
use App\Transformers\PostTransformer;
use App\Utils\HashtagTrait;
use App\Utils\Http;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PostController extends Controller // implements Illuminate\Routing\Controllers\HasMiddleware
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
    public function index()
    {
        $posts = Post::latest()->get();

        $transformedPosts = $this->postTransformer->transformPosts($posts);

        return $this->response([
            "posts" => $transformedPosts,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */

    public function store(Request $request)
    {
        // Validate incoming request
        $request->validate([
            "content" => ["required"]
        ]);

        $content = $request->content;
        $contentTags = $this->filterHashTags($content);

        // Store in MongoDB
        $post = Post::create([
            "content" => $content,
            "tags" => $contentTags
        ]);

        return $this->responseStatus(204);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $post = Post::find($id);
        if (!$post) {
            return $this->responseStatus(404);
        }

        $post = $this->postTransformer->transformPosts(collect([$post]))->first();
        return $this->response([
            "post" => $post
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $post = Post::find($id);
        if (!$post) {
            return $this->responseStatus(404);
        }

        // Authorize the user
        // Gate::authorize('update', $post);
        if ($request->user()->cannot('update', $post)) {
            return $this->failed("You are not authorized to make this request", 403);
        }

        // Validate content
        $request->validate([
            "content" => "string"
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
            return $this->responseStatus(404);
        }

        // Authorize the user
        // Gate::authorize('update', $post);
        if ($request->user()->cannot('delete', $post)) {
            return $this->failed("You are not authorized to make this request", 403);
        }

        $post->delete();

        return $this->responseStatus(204);
    }
}
