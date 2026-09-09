<?php

namespace App\GraphQL\Queries;

use App\Models\Follow;
use App\Models\Post;
use App\Transformers\PostTransformer;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;

/**
 * GraphQL query resolver for the authenticated user's Following feed.
 * Retrieves followed user IDs from MySQL, then queries MongoDB posts published
 * by those authors, hydrating interaction counters and author identities via batch loading.
 */
class FollowingFeedQuery
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
        $currentUserId = (int) Auth::id();
        $page = max($args['page'] ?? 1, 1);
        $perPage = min(max($args['perPage'] ?? 15, 1), 50);

        // Fetch user IDs followed by the authenticated user from MySQL
        $followingIds = Follow::where('follower_id', $currentUserId)
            ->pluck('following_id')
            ->values()
            ->all();

        if (empty($followingIds)) {
            return [
                'data' => [],
                'pagination' => [
                    'total' => 0,
                    'per_page' => $perPage,
                    'current_page' => $page,
                    'last_page' => 1,
                    'has_more' => false,
                ],
            ];
        }

        // Query MongoDB posts published by followed users
        $posts = Post::whereIn('user_id', $followingIds)
            ->latest()
            ->paginate($perPage, ['*'], 'page', $page);

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
