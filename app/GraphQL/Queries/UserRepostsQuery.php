<?php

namespace App\GraphQL\Queries;

use App\Services\UserService;
use App\Transformers\PostTransformer;
use Illuminate\Pagination\LengthAwarePaginator;

class UserRepostsQuery
{
    /**
     * Resolves posts reposted by a specific user profile.
     * Identifies the target user by username, fetches their MySQL repost IDs,
     * pulls the MongoDB post documents, and transforms them into a PostPaginator.
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

        $result = $this->userService->getUserReposts($user->id, $perPage, $page);
        $transformed = $this->postTransformer->transformPosts($result['posts']);

        $postList = $transformed instanceof LengthAwarePaginator
            ? $transformed->getCollection()->values()
            : $transformed->values();

        $cleanPosts = $postList->filter(function ($p) {
            return ! empty($p['content']) && trim((string) $p['content']) !== '';
        })->values()->all();

        $pagination = $result['pagination'];

        return [
            'data' => $cleanPosts,
            'pagination' => [
                'total' => $pagination['total'] ?? count($cleanPosts),
                'per_page' => $pagination['per_page'] ?? $perPage,
                'current_page' => $pagination['current_page'] ?? $page,
                'last_page' => $pagination['last_page'] ?? 1,
                'has_more' => ($pagination['current_page'] ?? 1) < ($pagination['last_page'] ?? 1),
            ],
        ];
    }
}
