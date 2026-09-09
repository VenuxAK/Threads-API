<?php

namespace App\GraphQL\Queries;

use App\Services\PostService;
use App\Transformers\PostTransformer;
use Illuminate\Pagination\LengthAwarePaginator;

class FeedQuery
{
    /**
     * Resolves the paginated global feed for the `feed` GraphQL query.
     * Integrates PostService to query MongoDB posts and PostTransformer to
     * batch-hydrate MySQL authors, interaction counters, and like/repost flags
     * without introducing N+1 cross-database query bottlenecks.
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
        $page = max($args['page'] ?? 1, 1);
        $perPage = min(max($args['perPage'] ?? 15, 1), 50);

        $posts = $this->postService->getFeed($perPage, $page);
        $transformed = $this->postTransformer->transformPosts($posts);

        $postList = $transformed instanceof LengthAwarePaginator
            ? $transformed->getCollection()->values()
            : $transformed->values();

        // Sanitize out any malformed or orphan documents
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
