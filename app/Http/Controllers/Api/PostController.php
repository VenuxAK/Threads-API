<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PostRequest;
use App\Models\Post;
use App\Services\PostService;
use App\Transformers\PostTransformer;
use App\Utils\Http;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class PostController extends Controller
{
    use Http;

    public function __construct(
        private PostService $postService,
        private PostTransformer $postTransformer,
    ) {}

    public function index(Request $request)
    {
        $perPage = min($request->get('per_page', 15), 50);
        $page = max($request->get('page', 1), 1);

        $posts = $this->postService->getFeed($perPage, $page);
        $transformed = $this->postTransformer->transformPosts($posts);
        $postsArray = $transformed instanceof LengthAwarePaginator
            ? $transformed->getCollection()->values()
            : $transformed;
        $postsArray = $postsArray->filter(fn ($p) => ! empty($p['content']) && trim((string) $p['content']) !== '' && ($p['author']['username'] ?? '') !== 'deleted')->values();

        return $this->success([
            'posts' => $postsArray,
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

    public function myPosts(Request $request)
    {
        $perPage = min($request->get('per_page', 15), 50);
        $page = max($request->get('page', 1), 1);

        $posts = $this->postService->getAuthUserPosts(Auth::id(), $perPage, $page);
        $transformed = $this->postTransformer->transformPosts($posts);
        $postsArray = $transformed instanceof LengthAwarePaginator
            ? $transformed->getCollection()->values()
            : $transformed;
        $postsArray = $postsArray->filter(fn ($p) => ! empty($p['content']) && trim((string) $p['content']) !== '' && ($p['author']['username'] ?? '') !== 'deleted')->values();

        return $this->success([
            'posts' => $postsArray,
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

    public function myPost(string $id)
    {
        $post = Post::where('user_id', Auth::id())->where('id', $id)->first();
        if (! $post) {
            return $this->error('Post not found', 404);
        }

        return $this->success([
            'post' => $this->postTransformer->transformPost($post),
        ]);
    }

    public function store(PostRequest $request)
    {
        try {
            $post = $this->postService->createPost($request->content, Auth::id());

            return $this->success([
                'post' => $this->postTransformer->transformPost($post),
            ], 'Post created successfully', 201);
        } catch (\Exception $e) {
            Log::error('Failed to create post', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);

            return $this->error('Failed to create post. Please try again.', 500);
        }
    }

    public function show(string $id)
    {
        $post = $this->postService->getPost($id);
        if (! $post) {
            return $this->error('Post not found', 404);
        }

        return $this->success([
            'post' => $this->postTransformer->transformPost($post),
        ]);
    }

    public function update(Request $request, string $id)
    {
        $post = Post::find($id);
        if (! $post) {
            return $this->error('Post not found', 404);
        }

        if ($request->user()->cannot('update', $post)) {
            return $this->error('You are not authorized to make this request', 403);
        }

        $request->validate(['content' => 'string|max:5000']);
        $content = $request->content ?? $post->content;

        $updated = $this->postService->updatePost($id, $content, Auth::id());
        if (! $updated) {
            return $this->error('Post not found', 404);
        }

        return $this->responseStatus(204);
    }

    public function destroy(Request $request, string $id)
    {
        $post = Post::find($id);
        if (! $post) {
            return $this->error('Post not found', 404);
        }

        if ($request->user()->cannot('delete', $post)) {
            return $this->error('You are not authorized to make this request', 403);
        }

        try {
            $this->postService->deletePost($id, Auth::id());

            return $this->responseStatus(204);
        } catch (\Exception $e) {
            Log::error('Failed to delete post', [
                'error' => $e->getMessage(),
                'post_id' => $id,
                'user_id' => Auth::id(),
            ]);

            return $this->error('Failed to delete post. Please try again.', 500);
        }
    }
}
