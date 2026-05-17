<?php

namespace App\Services;

use App\Models\Post;
use App\Models\PostRepost;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class UserService
{
    public function __construct(
        private PostService $postService,
    ) {}

    public function getAuthUser(): array
    {
        $user = Auth::user();

        return [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'avatar' => $user->avatar,
            'bio' => $user->bio,
            'email_verified' => $user->email_verified_at ? true : false,
        ];
    }

    public function getUser(string $username): ?User
    {
        return User::where('username', $username)->first();
    }

    public function getAuthUserReposts(int $perPage, int $page)
    {
        $repostPaginator = PostRepost::where('user_id', Auth::id())
            ->orderByDesc('created_at')
            ->paginate($perPage, ['*'], 'page', $page);

        $postsInOrder = $this->postService->getRepostedPosts($repostPaginator);

        return [
            'posts' => $postsInOrder,
            'pagination' => [
                'total' => $repostPaginator->total(),
                'per_page' => $repostPaginator->perPage(),
                'current_page' => $repostPaginator->currentPage(),
                'last_page' => $repostPaginator->lastPage(),
                'from' => $repostPaginator->firstItem(),
                'to' => $repostPaginator->lastItem(),
            ],
        ];
    }

    public function getUserReposts(int $userId, int $perPage, int $page): array
    {
        $repostPaginator = PostRepost::where('user_id', $userId)
            ->orderByDesc('created_at')
            ->paginate($perPage, ['*'], 'page', $page);

        $postsInOrder = $this->postService->getRepostedPosts($repostPaginator);

        return [
            'posts' => $postsInOrder,
            'pagination' => [
                'total' => $repostPaginator->total(),
                'per_page' => $repostPaginator->perPage(),
                'current_page' => $repostPaginator->currentPage(),
                'last_page' => $repostPaginator->lastPage(),
                'from' => $repostPaginator->firstItem(),
                'to' => $repostPaginator->lastItem(),
            ],
        ];
    }

    public function getUserWithPosts(string $username, int $perPage, int $page): array
    {
        $user = User::where('username', $username)->first();
        if (!$user) {
            throw new \RuntimeException('User not found', 404);
        }

        $posts = Post::where('user_id', $user->id)
            ->latest()
            ->paginate($perPage, ['*'], 'page', $page);

        return [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'avatar' => $user->avatar,
                'bio' => $user->bio,
            ],
            'posts' => $posts,
            'pagination' => [
                'total' => $posts->total(),
                'per_page' => $posts->perPage(),
                'current_page' => $posts->currentPage(),
                'last_page' => $posts->lastPage(),
                'from' => $posts->firstItem(),
                'to' => $posts->lastItem(),
            ],
        ];
    }
}
