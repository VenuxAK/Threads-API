<?php

namespace App\Services;

use App\Models\Post;
use App\Models\PostMetaData;
use App\Utils\HashtagTrait;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class PostService
{
    use HashtagTrait;

    public function getFeed(int $perPage, int $page)
    {
        return Post::latest()->paginate($perPage, ['*'], 'page', $page);
    }

    public function getPost(string $id): ?Post
    {
        return Post::find($id);
    }

    public function getAuthUserPosts(int $userId, int $perPage, int $page)
    {
        return Post::where('user_id', $userId)
            ->latest()
            ->paginate($perPage, ['*'], 'page', $page);
    }

    public function createPost(string $content, ?int $userId = null): Post
    {
        $tags = $this->filterHashTags($content);

        $post = Post::create([
            'content' => $content,
            'tags' => $tags,
            'user_id' => $userId,
        ]);

        try {
            PostMetaData::create([
                'post_id' => $post->id,
                'user_id' => $userId,
            ]);
        } catch (\Exception $e) {
            $post->delete();
            Log::error('Failed to create PostMetaData, rolled back post', [
                'error' => $e->getMessage(),
                'post_id' => $post->id,
                'user_id' => $userId,
            ]);
            throw $e;
        }

        return $post;
    }

    public function updatePost(string $id, string $content, int $userId): ?Post
    {
        $tags = $this->filterHashTags($content);

        $post = Post::where('user_id', $userId)->where('id', $id)->first();
        if (! $post) {
            return null;
        }

        $post->update([
            'content' => $content,
            'tags' => $tags,
        ]);

        return $post;
    }

    public function deletePost(string $id, int $userId): bool
    {
        $post = Post::where('user_id', $userId)->where('id', $id)->first();
        if (! $post) {
            return false;
        }

        try {
            $post->delete();
        } catch (\Exception $e) {
            Log::error('Failed to delete post', [
                'error' => $e->getMessage(),
                'post_id' => $id,
                'user_id' => $userId,
            ]);
            throw $e;
        }

        try {
            PostMetaData::where('post_id', $id)->delete();
        } catch (\Exception $e) {
            Log::warning('Failed to clean up PostMetaData for deleted post', [
                'post_id' => $id,
                'error' => $e->getMessage(),
            ]);
        }

        return true;
    }

    public function getRepostedPosts($repostPaginator): Collection
    {
        $idsInOrder = $repostPaginator->getCollection()
            ->pluck('post_id')
            ->map(fn ($id) => (string) $id);

        if ($idsInOrder->isEmpty()) {
            return collect([]);
        }

        $uniqueIds = $idsInOrder->unique()->values();
        $postsById = Post::whereIn('id', $uniqueIds)
            ->get()
            ->keyBy(fn ($p) => (string) $p->id);

        return $idsInOrder
            ->map(fn (string $pid) => $postsById->get($pid))
            ->filter()
            ->values();
    }
}
