<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SearchService;
use App\Utils\Http;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    use Http;

    public function __construct(
        private SearchService $searchService,
    ) {}

    public function search(Request $request)
    {
        $query = trim($request->keyword);

        if (empty($query)) {
            return $this->success([
                'users' => [],
                'posts' => [],
                'message' => 'Please enter a search term',
            ]);
        }

        $cleanQuery = preg_replace('/[^a-zA-Z0-9\s#]/', '', $query);

        if (strlen($cleanQuery) < 2) {
            return $this->error('Search term must be at least 2 characters', 422);
        }

        $perPage = min($request->get('per_page', 20), 50);
        $page = max($request->get('page', 1), 1);

        $results = $this->searchService->search(
            $cleanQuery,
            $request->query('posts') === 'include',
            $page,
            $perPage,
        );

        return $this->success($results);
    }
}
