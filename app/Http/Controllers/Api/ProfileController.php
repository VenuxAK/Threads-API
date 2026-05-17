<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\User;
use App\Services\UserService;
use App\Transformers\PostTransformer;
use App\Utils\Http;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    use Http;

    public function __construct(
        private UserService $userService,
        private PostTransformer $postTransformer,
    ) {}

    public function myProfile()
    {
        return $this->success($this->userService->getAuthUser());
    }

    public function myReposts(Request $request)
    {
        $perPage = min($request->get('per_page', 15), 50);
        $page = max($request->get('page', 1), 1);

        $result = $this->userService->getAuthUserReposts($perPage, $page);

        return $this->success([
            'posts' => $this->postTransformer->transformPosts($result['posts']),
            'pagination' => $result['pagination'],
        ]);
    }

    public function show(string $username)
    {
        $user = $this->userService->getUser($username);
        if (!$user) {
            return $this->error('User not found', 404);
        }

        return $this->success([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'avatar' => $user->avatar,
                'bio' => $user->bio,
            ],
        ]);
    }

    public function userPosts(Request $request, string $username)
    {
        $user = $this->userService->getUser($username);
        if (!$user) {
            return $this->error('User not found', 404);
        }

        $perPage = min($request->get('per_page', 15), 50);
        $page = max($request->get('page', 1), 1);

        $posts = Post::where('user_id', $user->id)
            ->latest()
            ->paginate($perPage, ['*'], 'page', $page);

        return $this->success([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'avatar' => $user->avatar,
                'bio' => $user->bio,
            ],
            'posts' => $this->postTransformer->transformPosts($posts),
            'pagination' => [
                'total' => $posts->total(),
                'per_page' => $posts->perPage(),
                'current_page' => $posts->currentPage(),
                'last_page' => $posts->lastPage(),
                'from' => $posts->firstItem(),
                'to' => $posts->lastItem(),
            ],
        ]);
    }

    public function userPost(Request $request, string $username)
    {
        $user = $this->userService->getUser($username);
        if (!$user) {
            return $this->error('User not found', 404);
        }

        $postId = $request->query('post');
        if (!$postId) {
            return $this->error('Post ID is required', 400);
        }

        $post = Post::where('user_id', $user->id)->where('id', $postId)->first();
        if (!$post) {
            return $this->error('Post not found', 404);
        }

        return $this->success([
            'post' => $this->postTransformer->transformPost($post),
        ]);
    }

    public function userReposts(Request $request, string $username)
    {
        $user = $this->userService->getUser($username);
        if (!$user) {
            return $this->error('User not found', 404);
        }

        $perPage = min($request->get('per_page', 15), 50);
        $page = max($request->get('page', 1), 1);

        $result = $this->userService->getUserReposts($user->id, $perPage, $page);

        return $this->success([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'avatar' => $user->avatar,
                'bio' => $user->bio,
            ],
            'posts' => $this->postTransformer->transformPosts($result['posts']),
            'pagination' => $result['pagination'],
        ]);
    }
}
