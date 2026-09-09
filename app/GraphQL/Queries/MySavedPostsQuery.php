<?php

namespace App\GraphQL\Queries;

use App\Models\Post;
use App\Models\SavedPost;
use App\Transformers\PostTransformer;
use Illuminate\Support\Facades\Auth;

/**
 * GraphQL query resolver for the authenticated user's bookmarked threads.
 * Bridges MySQL `saved_posts` records with MongoDB `posts` documents,
 * preserving chronological bookmark order and batch hydrating metadata via DataLoader.
 */
class MySavedPostsQuery
{
    public function __construct(
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

        $savedPaginator = SavedPost::where('user_id', $userId)
            ->latest()
            ->paginate($perPage, ['*'], 'page', $page);

        $postIds = $savedPaginator->pluck('post_id')->values()->all();

        if (empty($postIds)) {
            return [
                'data' => [],
                'pagination' => [
                    'total' => $savedPaginator->total(),
                    'per_page' => $savedPaginator->perPage(),
                    'current_page' => $savedPaginator->currentPage(),
                    'last_page' => $savedPaginator->lastPage(),
                    'has_more' => $savedPaginator->hasMorePages(),
                ],
            ];
        }

        $posts = Post::whereIn('_id', $postIds)->get();

        // Preserve chronological saved order
        $sortedPosts = collect($postIds)->map(function ($id) use ($posts) {
            return $posts->firstWhere('_id', $id) ?? $posts->firstWhere('id', $id);
        })->filter()->values();

        $transformed = $this->postTransformer->transformPosts($sortedPosts);

        $cleanPosts = $transformed->filter(function ($p) {
            return ! empty($p['content']) && trim((string) $p['content']) !== '';
        })->values()->all();

        return [
            'data' => $cleanPosts,
            'pagination' => [
                'total' => $savedPaginator->total(),
                'per_page' => $savedPaginator->perPage(),
                'current_page' => $savedPaginator->currentPage(),
                'last_page' => $savedPaginator->lastPage(),
                'has_more' => $savedPaginator->hasMorePages(),
            ],
        ];
    }
}
