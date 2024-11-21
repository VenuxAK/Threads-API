<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\PostMetaData;
use App\Models\User;
use App\Transformers\PostTransformer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PostController extends Controller // implements Illuminate\Routing\Controllers\HasMiddleware
{
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
        $dummy_posts = Post::latest()->get();

        $posts = $this->postTransformer->transformPosts($dummy_posts);

        return response()->json([
            "posts" => $posts,
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

        // Store in MongoDB
        $post = Post::create([
            "content" => $request->content ?? fake()->paragraph(),
            "user_id" => Auth::id()
        ]);

        // Create post metadata in MySQL
        $postMetaData = PostMetaData::create([
            "post_id" => $post->id,
            "user_id" => Auth::id()
        ]);

        return response()->noContent();
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $post = Post::find($id);
        if (!$post) {
            return response()->json([
                "status" => "Not Found"
            ], 404);
        }


        return response()->json([
            "post" => $this->postTransformer->transformPost($post)
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $post = Post::find($id);
        if (!$post) {
            return response()->json([
                "status" => "Not Found"
            ], 404);
        }

        // Authorize the user
        // Gate::authorize('update', $post);
        if ($request->user()->cannot('update', $post)) {
            return response()->json([
                "message" => "You are not authorized to make this request"
            ], 403);
        }

        // Validate content
        $request->validate([
            "content" => "string"
        ]);

        // Update
        $post->update([
            "content" => $request->content ?? $post->content
        ]);

        return response()->noContent();
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, string $id)
    {
        $post = Post::find($id);
        if (!$post) {
            return response()->json([
                "status" => "Not Found"
            ], 404);
        }

        // Authorize the user
        // Gate::authorize('update', $post);
        if ($request->user()->cannot('delete', $post)) {
            return response()->json([
                "message" => "You are not authorized to make this request"
            ], 403);
        }

        $post->delete();

        return response()->noContent();
    }
}
