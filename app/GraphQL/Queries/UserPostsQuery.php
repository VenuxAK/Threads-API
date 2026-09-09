<?php

namespace App\GraphQL\Queries;

use App\Models\Post;
use App\Services\UserService;
use App\Transformers\PostTransformer;
use Illuminate\Pagination\LengthAwarePaginator;

class UserPostsQuery
{
    /**
     * Resolves posts published by a specific user profile.
     * Looks up the user's MySQL ID by username, queries MongoDB for corresponding
     * posts, and batch-hydrates interactions using PostTransformer.
     */
    public function __construct(
        private UserService $userService,
        private PostTransformer $postTransformer
    ) {}

    /**
     * Handle the incoming GraphQL query invocation.
     *
     * @param  null  $root
     * @param  array{username: string, page?: int, perPage?: int}  $args
     * @return array<string, mixed>
     */
    public function __invoke($root, array $args): array
    {
        $user = $this->userService->getUser($args['username']);
        if (! $user) {
            return [
                'data' => [],
                'pagination' => [
                    'total' => 0,
                    'per_page' => 15,
                    'current_page' => 1,
                    'last_page' => 1,
                    'has_more' => false,
                ],
            ];
        }

        $page = max($args['page'] ?? 1, 1);
        $perPage = min(max($args['perPage'] ?? 15, 1), 50);

        $posts = Post::where('user_id', $user->id)
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
