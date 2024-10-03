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

        $query = $request->keyword;

        if (!$query) return NULL;

        $results = [];

        $results["users"] = User::where("username", "LIKE",  "$query%")->orWhere("name",  "LIKE",  "%$query%")->get();
        if ($request->query('posts') === "include") {
            Log::debug($query);
            $results["posts"] = $this->postTransformer->transformPosts(Post::where("tags", "regex", "/$query/i")->latest()->get());
        }

        return $this->response($results);
    }
}
