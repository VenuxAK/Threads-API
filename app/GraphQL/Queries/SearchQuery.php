<?php

namespace App\GraphQL\Queries;

use App\Services\SearchService;

class SearchQuery
{
    /**
     * Resolves multi-model search queries across users and posts.
     * Searches MySQL users by name/username and scans MongoDB posts and hashtags
     * using regex pattern matching, returning formatted compound results.
     */
    public function __construct(
        private SearchService $searchService
    ) {}

    /**
     * Handle the incoming GraphQL query invocation.
     *
     * @param  null  $root
     * @param  array{keyword: string, includePosts?: bool, page?: int, perPage?: int}  $args
     * @return array<string, mixed>
     */
    public function __invoke($root, array $args): array
    {
        $keyword = trim($args['keyword']);
        $includePosts = (bool) ($args['includePosts'] ?? false);
        $page = max($args['page'] ?? 1, 1);
        $perPage = min(max($args['perPage'] ?? 20, 1), 50);

        $results = $this->searchService->search($keyword, $includePosts, $page, $perPage);

        return [
            'users' => $results['users'] ?? [],
            'posts' => $results['posts'] ?? null,
            'query' => $keyword,
        ];
    }
}
