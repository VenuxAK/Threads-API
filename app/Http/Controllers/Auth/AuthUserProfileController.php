<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\PostMetaData;
use App\Transformers\PostTransformer;
use App\Utils\Http;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthUserProfileController extends Controller
{
    use Http; // Use custom http trait helper

    private $postTransformer;
    public function __construct(PostTransformer $postTransformer)
    {
        $this->postTransformer = $postTransformer;
    }

    /**
     * @desc    Get auth user
     * @route   /api/v1/user
     * @method  GET
     */
    public function showUser(Request $request)
    {
        return $this->response([
            "id" => Auth::user()->id,
            "name" => Auth::user()->name,
            "username" => Auth::user()->username,
            "email" => Auth::user()->email,
            "avatar" => Auth::user()->avatar,
            "bio" => Auth::user()->bio,
            "email_verified" => Auth::user()->email_verified_at ? true : false,
        ]);
    }

    /**
     * @desc    Show all posts of auth user
     * @route   /api/v1/user/posts
     * @method  GET
     */
    public function showPosts()
    {
        $posts = Post::where('user_id', Auth::id())->latest()->get();

        return $this->response([
            "posts" => $this->postTransformer->transformPosts($posts)
        ]);
    }

    /**
     * @desc    Show single post of auth user by id
     * @route   /api/v1/user/posts/{post_id}
     * @method  GET
     */
    public function showPost(Request $request, String $id)
    {
        $post = Post::where('user_id', Auth::id())->where('id', $id)->first();

        if (!$post) return $this->responseStatus(404);

        return $this->response([
            "post" => $this->postTransformer->transformPosts(collect([$post]))->first(),
        ]);
    }

    /**
     * @desc    Create new post
     * @route   /api/v1/user/posts/{post_id}
     * @method  POST
     */
    public function  storePost(Request $request)
    {
        $request->validate([
            "content" => ["required"]
        ]);

        $content = $request->content;
        $contentTags = $this->filterHashTags($content);

        Post::create([
            "content" => $content,
            "tags" => $contentTags
        ]);

        return $this->responseStatus(204);
    }

    /**
     * @desc    Update post
     * @route   /api/v1/user/posts/{post_id}
     * @method  PUT | PATCH
     */
    public function updatePost(Request $request, String $id)
    {
        $post = Post::where("user_id", Auth::id())->whereId($id)->first();

        if (!$post) return $this->responseStatus(404);

        $request->validate([
            "content" => ["required"]
        ]);

        $post = $post->update([
            "content" => $request->content ?? $post->content
        ]);

        return $this->responseStatus(204);
    }

    /**
     * @desc    Delete post of auth user
     * @route   /api/v1/user/posts/{post_id}
     * @method  DELETE
     */
    public function deletePost(Request $request, String $id)
    {
        $post = Post::where("user_id", Auth::id())->whereId($id)->first();

        if (!$post) return $this->responseStatus(404);

        $post->delete();

        return $this->responseStatus(204);
    }

    // Filter hash tags
    private function filterHashTags($content)
    {
        preg_match_all('/#\w+\b/', $content, $matches);
        return array_values(array_unique($matches[0]));
    }
}
