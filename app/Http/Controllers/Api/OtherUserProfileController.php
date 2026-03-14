<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\User;
use App\Transformers\PostTransformer;
use App\Utils\Http;
use Illuminate\Http\Request;

class OtherUserProfileController extends Controller
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

        if (!$user) return $this->error("User not found", 404);

        // Get user and user's posts
        // /api/v1/user/{username}?posts=include
        if ($request->query('posts') === "include") {
            // Get pagination parameters
            $perPage = min($request->get('per_page', 15), 50); // Max 50 per page
            $page = max($request->get('page', 1), 1);

            $posts = Post::where("user_id", $user->id)->latest()->paginate($perPage, ['*'], 'page', $page);

            // Response user and user's posts
            return $this->success([
                "user" => [
                    "id" => $user->id,
                    "name" => $user->name,
                    "username" => $user->username,
                    "avatar" => $user->avatar,
                    "bio" => $user->bio,
                ],
                "posts" => $this->postTransformer->transformPosts($posts),
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

        // Get user's post by id from query param
        // /api/v1/users/{username}?post={post_id}
        if ($request->query('post')) {
            $post = Post::where('user_id', $user->id)->whereId($request->query('post'))->first();

            if (!$post) return $this->error("Post not found", 404);

            // Response user's post
            $transformedPost = $this->postTransformer->transformPost($post);
            return $this->success([
                "post" => $transformedPost
            ]);
        }

        // Response user infomation
        return $this->success([
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
