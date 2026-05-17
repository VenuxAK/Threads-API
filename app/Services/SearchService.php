<?php

namespace App\Services;

use App\Models\Post;
use App\Models\User;
use App\Transformers\PostTransformer;

class SearchService
{
    public function __construct(
        private PostTransformer $postTransformer,
    ) {}

    public function search(string $query, bool $includePosts = false, int $page = 1, int $perPage = 20): array
    {
        $cleanQuery = preg_replace('/[^a-zA-Z0-9\s#]/', '', $query);

        $results = [];

        $users = User::where(function ($q) use ($cleanQuery) {
            $q->where('username', 'LIKE', "%{$cleanQuery}%")
                ->orWhere('name', 'LIKE', "%{$cleanQuery}%");
        })
            ->limit(10)
            ->get(['id', 'name', 'username', 'avatar', 'bio']);

        $results['users'] = $users->map(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'avatar' => $user->avatar,
                'bio' => $user->bio,
            ];
        });

        if ($includePosts) {
            $results['posts'] = $this->searchPosts($cleanQuery, $page, $perPage);
            $results['search_metadata'] = [
                'query' => $cleanQuery,
                'total_users' => $users->count(),
                'page' => $page,
                'per_page' => $perPage,
            ];
        } else {
            $results['search_metadata'] = [
                'query' => $cleanQuery,
                'total_users' => $users->count(),
                'message' => 'Add ?posts=include to search in posts as well',
            ];
        }

        return $results;
    }

    private function searchPosts(string $cleanQuery, int $page, int $perPage): array
    {
        $tagQuery = str_starts_with($cleanQuery, '#')
            ? substr($cleanQuery, 1)
            : $cleanQuery;

        $postQuery = Post::query();
        $postQuery->where(function ($q) use ($cleanQuery) {
            $q->where('content', 'regex', "/{$cleanQuery}/i");
        });

        if (strlen($tagQuery) > 0 && strlen($tagQuery) <= 30) {
            $postQuery->orWhere(function ($q) use ($tagQuery) {
                $q->where('tags', 'regex', "/^{$tagQuery}$/i")
                    ->orWhere('tags', 'regex', "/{$tagQuery}/i");
            });
        }

        $posts = $postQuery->latest()
            ->skip(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        return $this->postTransformer->transformPosts($posts)->all();
    }
}
