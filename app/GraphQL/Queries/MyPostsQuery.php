<?php

namespace App\GraphQL\Queries;

use App\Services\PostService;
use App\Transformers\PostTransformer;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;

class MyPostsQuery
{
    /**
     * Resolves the authenticated user's authored posts.
     * Queries MongoDB documents matching the current session's user_id,
     * applying pagination and batch-hydrating MySQL interaction counters.
     */
    public function __construct(
        private PostService $postService,
        private PostTransformer $postTransformer
    ) {}

    /**
     * Handle the incoming GraphQL query invocation.
     *
     * @param  null  $root
     * @param  array{page?: int, perPage?: int}  $args
     * @return array<string, mixed>
     */
    public function __invoke($root, array $args): array
    {
        $userId = (int) Auth::id();
        $page = max($args['page'] ?? 1, 1);
        $perPage = min(max($args['perPage'] ?? 15, 1), 50);

        $posts = $this->postService->getAuthUserPosts($userId, $perPage, $page);
        $transformed = $this->postTransformer->transformPosts($posts);

        $postList = $transformed instanceof LengthAwarePaginator
            ? $transformed->getCollection()->values()
            : $transformed->values();

        $cleanPosts = $postList->filter(function ($p) {
            return ! empty($p['content']) && trim((string) $p['content']) !== '';
        })->values()->all();

        return [
            'data' => $cleanPosts,
            'pagination' => [
                'total' => $posts->total(),
                'per_page' => $posts->perPage(),
                'current_page' => $posts->currentPage(),
                'last_page' => $posts->lastPage(),
                'has_more' => $posts->hasMorePages(),
            ],
        ];
    }
}
