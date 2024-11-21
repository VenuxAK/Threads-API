<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\User;
use App\Transformers\PostTransformer;
use App\Utils\Http;
use Illuminate\Http\Request;

class UserProfileController extends Controller
{
    use Http;   // Use custom http trait helper

    private $postTransformer;
    public function __construct(PostTransformer $postTransformer)
    {
        $this->postTransformer = $postTransformer;
    }

    /**
     * @desc  Get user profile
     * @routes
     *  -   /api/v1/users/{username}                    // Get user infomation only
     *      @response
     *
     *  -   /api/v1/users/{username}?posts=include      // Get user and user's posts
     *      @response
     *
     *  -   /api/v1/users/{username}?post={post_id}     // Get only user's post by id
     *      @response
     *
     */
    public function show(Request $request, $username)
    {
        $user = User::where("username", $username)->first();

        if (!$user) return $this->responseStatus(404);

        // Get user and user's posts
        // /api/v1/user/{username}?posts=include
        if ($request->query('posts') === "include") {
            $posts = Post::where("user_id", $user->id)->latest()->get();

            // Response user and user's posts
            return $this->response([
                "user" => [
                    "id" => $user->id,
                    "name" => $user->name,
                    "username" => $user->username,
                    "avatar" => $user->avatar,
                    "bio" => $user->bio,
                ],
                "posts" => $this->postTransformer->transformPosts($posts)
            ]);
        }

        // Get user's post by id from query param
        // /api/v1/users/{username}?post={post_id}
        if ($request->query('post')) {

            $post = Post::where('user_id', $user->id)->whereId($request->query('post'))->first();

            if (!$post) return $this->responseStatus(404);

            // Response user's post
            return $this->response([
                // "post" => $this->postTransformer->transformPosts(collect([$post]))->first()
                "post" => $this->postTransformer->transformPost($post)
            ]);

            /**
             * post: {
             *  id,content,published_at, edited_at,
             *  author: {name,username,avatar},
             *  comments: [{content,created_at, name, username}]
             * }
             */
        }

        // Response user infomation
        return $this->response([
            "user" => [
                "id" => $user->id,
                "name" => $user->name,
                "username" => $user->username,
                "avatar" => $user->avatar,
                "bio" => $user->bio,
            ]
        ]);
    }
}
