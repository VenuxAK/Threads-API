<?php

namespace App\Transformers;

use App\Models\PostMetaData;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Pagination\LengthAwarePaginator;

class PostTransformer
{
    /**
     * Transform a collection of posts
     *
     * @param mixed $posts Can be Collection or LengthAwarePaginator
     * @return \Illuminate\Support\Collection
     */
    public function transformPosts($posts)
    {
        // Handle paginator objects
        $postCollection = $posts instanceof LengthAwarePaginator ? $posts->getCollection() : $posts;

        if ($postCollection->isEmpty()) {
            return $posts instanceof LengthAwarePaginator ? $posts : collect([]);
        }

        // Get a list of unique user IDs from the posts
        $userIds = $postCollection->pluck('user_id')->unique()->values();

        // Get post IDs for metadata lookup
        $postIds = $postCollection->pluck('id')->values();

        // Retrieve the users corresponding to the post authors
        // Use caching to reduce database queries
        $users = Cache::remember(
            'users:' . md5(implode(',', $userIds->toArray())),
            300, // 5 minutes cache
            function () use ($userIds) {
                return User::whereIn('id', $userIds)
                    ->get(['id', 'name', 'username', 'avatar', 'bio'])
                    ->keyBy('id');
            }
        );

        // Retrieve post metadata in batch
        $metadata = PostMetaData::whereIn('post_id', $postIds)
            ->get(['post_id', 'likes_count', 'comments_count', 'shares_count'])
            ->keyBy('post_id');

        // Transform the posts to include user information
        $transformedPosts = $postCollection->map(function ($post) use ($users, $metadata) {
            // Get user from cached collection
            $user = $users->get($post->user_id);

            // Get metadata for this post
            $postMetadata = $metadata->get($post->id);

            // Return transformed post
            return [
                'id' => $post->id,
                'content' => $post->content,
                'tags' => $post->tags ?? [],
                'published_at' => $post->created_at->diffForHumans(),
                'edited_at' => $post->updated_at->diffForHumans(),
                'interactions' => [
                    'likes' => $postMetadata ? $postMetadata->likes_count : 0,
                    'comments' => $postMetadata ? $postMetadata->comments_count : 0,
                    'shares' => $postMetadata ? $postMetadata->shares_count : 0,
                ],
                'author' => $user ? [
                    'name' => $user->name,
                    'username' => $user->username,
                    'avatar' => $user->avatar,
                    'bio' => $user->bio,
                ] : [
                    'name' => 'Deleted User',
                    'username' => 'deleted',
                    'avatar' => null,
                    'bio' => null,
                ]
            ];
        });

        // If it was a paginator, set the transformed collection back
        if ($posts instanceof LengthAwarePaginator) {
            $posts->setCollection($transformedPosts);
            return $posts;
        }

        return $transformedPosts;
    }

    /**
     * Transform a single post
     *
     * @param \App\Models\Post $post
     * @return array
     */
    public function transformPost($post)
    {
        if (!$post) {
            return null;
        }

        $transformed = $this->transformPosts(collect([$post]));
        return $transformed->first();
    }
}
