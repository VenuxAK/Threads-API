<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PostRequest;
use App\Models\Post;
use App\Models\PostRepost;
use App\Transformers\PostTransformer;
use App\Utils\HashtagTrait;
use App\Utils\Http;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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
            $post = Post::create([
                'content' => $content,
                'tags' => $contentTags,
            ]);

            return $this->success([
                'post' => $this->postTransformer->transformPost($post),
            ], 'Post created successfully', 201);
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

    /**
     * Posts the authenticated user has reposted (newest repost first).
     *
     * @route GET /api/v1/me/reposts
     */
    public function repostsIndex(Request $request)
    {
        $perPage = min($request->get('per_page', 15), 50);
        $page = max($request->get('page', 1), 1);

        $repostPaginator = PostRepost::where('user_id', Auth::id())
            ->orderByDesc('created_at')
            ->paginate($perPage, ['*'], 'page', $page);

        $postsInOrder = $this->postsFromRepostPaginator($repostPaginator);

        return $this->success([
            'posts' => $this->postTransformer->transformPosts($postsInOrder),
            'pagination' => [
                'total' => $repostPaginator->total(),
                'per_page' => $repostPaginator->perPage(),
                'current_page' => $repostPaginator->currentPage(),
                'last_page' => $repostPaginator->lastPage(),
                'from' => $repostPaginator->firstItem(),
                'to' => $repostPaginator->lastItem(),
            ],
        ]);
    }

    /**
     * @param  \Illuminate\Contracts\Pagination\LengthAwarePaginator  $repostPaginator
     */
    private function postsFromRepostPaginator($repostPaginator): Collection
    {
        $idsInOrder = $repostPaginator->getCollection()->pluck('post_id')->map(fn ($id) => (string) $id);
        if ($idsInOrder->isEmpty()) {
            return collect([]);
        }

        $uniqueIds = $idsInOrder->unique()->values();
        $postsById = Post::whereIn('id', $uniqueIds)->get()->keyBy(fn ($p) => (string) $p->id);

        return $idsInOrder
            ->map(fn (string $pid) => $postsById->get($pid))
            ->filter()
            ->values();
    }
}
