<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PostRequest;
use App\Models\Post;
use App\Transformers\PostTransformer;
use App\Utils\HashtagTrait;
use App\Utils\Http;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class MyProfileController extends Controller
{
    // Use custom http trait helper
    use HashtagTrait;
    use Http; // Use hashtag trait

    private $postTransformer;

    public function __construct(PostTransformer $postTransformer)
    {
        $this->postTransformer = $postTransformer;
    }

    /**
     * @desc    Get auth user
     *
     * @route   /api/v1/user
     *
     * @method  GET
     */
    public function me(Request $request)
    {
        return $this->success([
            'id' => Auth::user()->id,
            'name' => Auth::user()->name,
            'username' => Auth::user()->username,
            'email' => Auth::user()->email,
            'avatar' => Auth::user()->avatar,
            'bio' => Auth::user()->bio,
            'email_verified' => Auth::user()->email_verified_at ? true : false,
        ]);
    }

    /**
     * @desc    Show all posts of auth user
     *
     * @route   /api/v1/user/posts
     *
     * @method  GET
     */
    public function index(Request $request)
    {
        // Get pagination parameters
        $perPage = min($request->get('per_page', 15), 50); // Max 50 per page
        $page = max($request->get('page', 1), 1);

        $posts = Post::where('user_id', Auth::id())->latest()->paginate($perPage, ['*'], 'page', $page);

        return $this->success([
            'posts' => $this->postTransformer->transformPosts($posts),
            'pagination' => [
                'total' => $posts->total(),
                'per_page' => $posts->perPage(),
                'current_page' => $posts->currentPage(),
                'last_page' => $posts->lastPage(),
                'from' => $posts->firstItem(),
                'to' => $posts->lastItem(),
            ],
        ]);
    }

    /**
     * @desc    Show single post of auth user by id
     *
     * @route   /api/v1/user/posts/{post_id}
     *
     * @method  GET
     */
    public function show(Request $request, string $id)
    {
        $post = Post::where('user_id', Auth::id())->where('id', $id)->first();

        if (! $post) {
            return $this->error('Post not found', 404);
        }

        $transformedPost = $this->postTransformer->transformPost($post);

        return $this->success([
            'post' => $transformedPost,
        ]);
    }

    /**
     * @desc    Create new post
     *
     * @route   /api/v1/user/posts/{post_id}
     *
     * @method  POST
     */
    public function store(PostRequest $request)
    {
        $content = $request->content;
        $contentTags = $this->filterHashTags($content);

        try {
            Post::create([
                'content' => $content,
                'tags' => $contentTags,
            ]);

            return $this->responseStatus(204);
        } catch (\Exception $e) {
            Log::error('Failed to create post in MyProfileController', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
                'content_length' => strlen($content),
            ]);

            return $this->error('Failed to create post. Please try again.', 500);
        }
    }

    /**
     * @desc    Update post
     *
     * @route   /api/v1/user/posts/{post_id}
     *
     * @method  PUT | PATCH
     */
    public function update(PostRequest $request, string $id)
    {
        $post = Post::where('user_id', Auth::id())->whereId($id)->first();

        if (! $post) {
            return $this->error('Post not found', 404);
        }

        $content = $request->content ?? $post->content;
        $contentTags = $this->filterHashTags($content);

        $post->update([
            'content' => $content,
            'tags' => $contentTags,
        ]);

        return $this->responseStatus(204);
    }

    /**
     * @desc    Delete post of auth user
     *
     * @route   /api/v1/user/posts/{post_id}
     *
     * @method  DELETE
     */
    public function destroy(Request $request, string $id)
    {
        $post = Post::where('user_id', Auth::id())->whereId($id)->first();

        if (! $post) {
            return $this->error('Post not found', 404);
        }

        try {
            $post->delete();

            return $this->responseStatus(204);
        } catch (\Exception $e) {
            Log::error('Failed to delete post in MyProfileController', [
                'error' => $e->getMessage(),
                'post_id' => $id,
                'user_id' => Auth::id(),
            ]);

            return $this->error('Failed to delete post. Please try again.', 500);
        }
    }
}
