<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Post;
use App\Models\PostMetaData;
use App\Utils\Http;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CommentController extends Controller
{
    use Http;

    public function show(Request $request, $username, $post_id)
    {
        // Find post
        $post = Post::find($post_id);
        if (!$post) {
            return $this->failed([
                "message" => "Post not found"
            ], 404);
        }

        // Get comments
        // $post_metadata = PostMetaData::where('post_id', $post_id)->first();
        $comments = Comment::where('post_id', $post_id)->with('user')->latest()->get();

        return $this->response([
            // "metadata" => [
            //     "likes_count" => $post_metadata->likes_count,
            //     "comments_count" => $post_metadata->comments_count,
            // ],
            "comments" => $comments->map(function ($comment) {
                return [
                    "content" => $comment->content,
                    "created_at" => $comment->created_at->diffForHumans(),
                    "user" => [
                        "name" => $comment->user->name,
                        "username" => $comment->user->username,
                        "avatar" => $comment->user->avatar,
                        "bio" => $comment->user->bio,
                    ]
                ];
            })
        ]);
    }

    //
    public function store(Request $request, $username, $id)
    {
        // Find post
        $post = Post::find($id);
        if (!$post) {
            return $this->failed([
                "message" => "Post not found"
            ], 404);
        }

        // Validate request
        $request->validate([
            "content" => ["required", "string", "max:255"]
        ]);

        // Create comment
        Comment::create([
            "user_id" => Auth::user()->id,
            "post_id" => $id,
            "content" => $request->content
        ]);

        $post_metadata = PostMetaData::where('post_id', $id)->first();
        $post_metadata->update([
            "comments_count" => $post_metadata->comments_count + 1,
        ]);


        return $this->responseStatus(201);
        // return $this->response([
        //     "data" => $post_metadata
        // ]);
    }
}
