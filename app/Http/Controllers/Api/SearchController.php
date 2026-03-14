<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\User;
use App\Transformers\PostTransformer;
use App\Utils\Http;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SearchController extends Controller
{
    use Http;
    private $postTransformer;
    public function __construct(PostTransformer $postTransformer)
    {
        $this->postTransformer = $postTransformer;
    }

    public function search(Request $request)
    {
        $query = trim($request->keyword);

        if (empty($query)) {
            return $this->success([
                "users" => [],
                "posts" => [],
                "message" => "Please enter a search term"
            ]);
        }

        // Clean the query for safe searching
        $cleanQuery = preg_replace('/[^a-zA-Z0-9\s#]/', '', $query);

        if (strlen($cleanQuery) < 2) {
            return $this->error("Search term must be at least 2 characters", 422);
        }

        $results = [];

        // Search users (by username or name)
        $users = User::where(function ($q) use ($cleanQuery) {
            $q->where("username", "LIKE", "%{$cleanQuery}%")
                ->orWhere("name", "LIKE", "%{$cleanQuery}%");
        })
            ->limit(10) // Limit results for performance
            ->get(['id', 'name', 'username', 'avatar', 'bio']);

        $results["users"] = $users->map(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'avatar' => $user->avatar,
                'bio' => $user->bio,
            ];
        });

        // Search posts if requested
        if ($request->query('posts') === "include") {
            Log::debug('Searching posts for: ' . $cleanQuery);

            // Remove # from query for tag search
            $tagQuery = str_starts_with($cleanQuery, '#')
                ? substr($cleanQuery, 1)
                : $cleanQuery;

            // Build post query
            $postQuery = Post::query();

            // Search in post content (case-insensitive)
            $postQuery->where(function ($q) use ($cleanQuery) {
                $q->where("content", "regex", "/{$cleanQuery}/i");
            });

            // Also search in tags if query is short (likely a tag/hashtag)
            if (strlen($tagQuery) <= 30) {
                $postQuery->orWhere(function ($q) use ($tagQuery) {
                    // Exact tag match
                    $q->where("tags", "regex", "/^{$tagQuery}$/i")
                        // Tag contains the query
                        ->orWhere("tags", "regex", "/{$tagQuery}/i");
                });
            }

            // Execute query with limits
            $posts = $postQuery->latest()->limit(30)->get();

            // If no results with regex, try a simpler approach for content
            if ($posts->isEmpty() && strlen($cleanQuery) > 3) {
                // Try searching for words in content
                $words = explode(' ', $cleanQuery);
                $simpleQuery = Post::query();

                foreach ($words as $word) {
                    if (strlen($word) > 2) {
                        $simpleQuery->orWhere("content", "regex", "/{$word}/i");
                    }
                }

                $posts = $simpleQuery->latest()->limit(20)->get();
            }

            $results["posts"] = $this->postTransformer->transformPosts($posts);

            // Add search metadata
            $results["search_metadata"] = [
                'query' => $cleanQuery,
                'tag_query' => $tagQuery,
                'total_posts' => $posts->count(),
                'total_users' => $users->count(),
                'search_method' => $posts->isEmpty() ? 'simple_word_search' : 'regex_search',
            ];
        } else {
            $results["search_metadata"] = [
                'query' => $cleanQuery,
                'total_users' => $users->count(),
                'message' => 'Add ?posts=include to search in posts as well',
            ];
        }

        return $this->success($results);
    }
}
