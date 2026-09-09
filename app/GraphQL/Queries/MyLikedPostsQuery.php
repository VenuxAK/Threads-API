<?php

namespace App\GraphQL\Queries;

use App\Models\Post;
use App\Models\PostLike;
use App\Transformers\PostTransformer;
use Illuminate\Support\Facades\Auth;

class MyLikedPostsQuery
{
    /**
     * Resolves all posts that the authenticated user has liked.
     * Bridges the MySQL `post_likes` table with MongoDB `posts`, solving
     * the missing liked-posts API endpoint and powering the frontend liked view.
     */
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

        $likesPaginator = PostLike::where('user_id', $userId)
            ->latest()
            ->paginate($perPage, ['*'], 'page', $page);

        $postIds = $likesPaginator->pluck('post_id')->values()->all();

        if (empty($postIds)) {
            return [
                'data' => [],
                'pagination' => [
                    'total' => $likesPaginator->total(),
                    'per_page' => $likesPaginator->perPage(),
                    'current_page' => $likesPaginator->currentPage(),
                    'last_page' => $likesPaginator->lastPage(),
                    'has_more' => $likesPaginator->hasMorePages(),
                ],
            ];
        }

        $posts = Post::whereIn('_id', $postIds)->get();

        // Preserve chronological liked order
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
                'total' => $likesPaginator->total(),
                'per_page' => $likesPaginator->perPage(),
                'current_page' => $likesPaginator->currentPage(),
                'last_page' => $likesPaginator->lastPage(),
                'has_more' => $likesPaginator->hasMorePages(),
            ],
        ];
    }
}
