<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Like;
use App\Models\Post;
use App\Models\PostMetaData;
use App\Utils\Http;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LikeController extends Controller
{
    use Http;

    public function store(Request $request, $username, $post_id)
    {
        // Find post
        $post = Post::find($post_id);
        if (!$post) {
            return $this->failed([
                "message" => "Post not found"
            ], 404);
        }

        Like::create([
            "user_id" => Auth::id(),
            "post_id" => $post->id
        ]);

        $metadata = PostMetaData::where('post_id', $post->id)->first();
        $metadata->likes_count += 1;
        $metadata->save();

        return $this->responseStatus(201);
    }
}
