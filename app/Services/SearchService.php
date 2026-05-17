<?php

namespace App\Services;

use App\Models\Post;
use App\Models\User;
use App\Transformers\PostTransformer;
use Illuminate\Support\Facades\Log;

class SearchService
{
    public function __construct(
        private PostTransformer $postTransformer,
    ) {}

    public function search(string $query, bool $includePosts = false): array
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
            $results['posts'] = $this->searchPosts($cleanQuery);
            $results['search_metadata'] = [
                'query' => $cleanQuery,
                'total_users' => $users->count(),
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

    private function searchPosts(string $cleanQuery): array
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

        $posts = $postQuery->latest()->limit(30)->get();

        if ($posts->isEmpty() && strlen($cleanQuery) > 3) {
            $words = explode(' ', $cleanQuery);
            $simpleQuery = Post::query();
            foreach ($words as $word) {
                if (strlen($word) > 2) {
                    $simpleQuery->orWhere('content', 'regex', "/{$word}/i");
                }
            }
            $posts = $simpleQuery->latest()->limit(20)->get();
        }

        return $this->postTransformer->transformPosts($posts)->all();
    }
}
