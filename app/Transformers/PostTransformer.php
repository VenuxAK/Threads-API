<?php

namespace App\Transformers;

use App\Models\Follow;
use App\Models\Post;
use App\Models\PostLike;
use App\Models\PostMetaData;
use App\Models\PostRepost;
use App\Models\SavedPost;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class PostTransformer
{
    /**
     * Transform a collection of posts
     *
     * @param  mixed  $posts  Can be Collection or LengthAwarePaginator
     * @return Collection
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

        // Get current user ID
        $currentUserId = Auth::id();

        // Retrieve the users corresponding to the post authors
        $users = Cache::remember(
            'users:'.md5(implode(',', $userIds->toArray())),
            300, // 5 minutes cache
            function () use ($userIds) {
                return User::whereIn('id', $userIds)
                    ->get(['id', 'name', 'username', 'avatar', 'bio'])
                    ->keyBy('id');
            }
        );

        // Batch DataLoader: Compute follower and following counts for author users
        $authorUserIds = $userIds->filter()->values()->all();
        $followerCounts = collect([]);
        $followingCounts = collect([]);
        $authFollowingIds = [];

        if (! empty($authorUserIds)) {
            $followerCounts = Follow::whereIn('following_id', $authorUserIds)
                ->groupBy('following_id')
                ->selectRaw('following_id, count(*) as count')
                ->pluck('count', 'following_id');

            $followingCounts = Follow::whereIn('follower_id', $authorUserIds)
                ->groupBy('follower_id')
                ->selectRaw('follower_id, count(*) as count')
                ->pluck('count', 'follower_id');

            if ($currentUserId) {
                $authFollowingIds = Follow::where('follower_id', $currentUserId)
                    ->whereIn('following_id', $authorUserIds)
                    ->pluck('following_id')
                    ->all();
            }
        }

        // Retrieve post metadata in batch
        $metadata = PostMetaData::whereIn('post_id', $postIds)
            ->get(['post_id', 'likes_count', 'comments_count', 'shares_count', 'reposts_count'])
            ->keyBy('post_id');

        $likedPostIds = [];
        if ($currentUserId) {
            $likedPostIds = PostLike::whereIn('post_id', $postIds->map(fn ($id) => (string) $id))
                ->where('user_id', $currentUserId)
                ->pluck('post_id')
                ->map(fn ($id) => (string) $id)
                ->toArray();
        }

        $repostedPostIds = [];
        if ($currentUserId) {
            $repostedPostIds = PostRepost::whereIn('post_id', $postIds->map(fn ($id) => (string) $id))
                ->where('user_id', $currentUserId)
                ->pluck('post_id')
                ->map(fn ($id) => (string) $id)
                ->toArray();
        }

        $savedPostIds = [];
        if ($currentUserId) {
            $savedPostIds = SavedPost::whereIn('post_id', $postIds->map(fn ($id) => (string) $id))
                ->where('user_id', $currentUserId)
                ->pluck('post_id')
                ->map(fn ($id) => (string) $id)
                ->toArray();
        }

        // Transform the posts to include user information
        $transformedPosts = $postCollection->map(function ($post) use ($users, $metadata, $likedPostIds, $repostedPostIds, $savedPostIds, $followerCounts, $followingCounts, $authFollowingIds) {
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
                'likes' => $postMetadata ? $postMetadata->likes_count : 0,
                'comments' => $postMetadata ? $postMetadata->comments_count : 0,
                'reposts' => $postMetadata ? $postMetadata->reposts_count : 0,
                'is_liked' => in_array((string) $post->id, $likedPostIds, true),
                'is_reposted' => in_array((string) $post->id, $repostedPostIds, true),
                'is_saved' => in_array((string) $post->id, $savedPostIds, true),
                'interactions' => [
                    'likes' => $postMetadata ? $postMetadata->likes_count : 0,
                    'comments' => $postMetadata ? $postMetadata->comments_count : 0,
                    'shares' => $postMetadata ? $postMetadata->shares_count : 0,
                    'reposts' => $postMetadata ? $postMetadata->reposts_count : 0,
                ],
                'author' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'username' => $user->username,
                    'avatar' => $user->avatar,
                    'bio' => $user->bio,
                    'followers_count' => (int) ($followerCounts->get($user->id) ?? 0),
                    'following_count' => (int) ($followingCounts->get($user->id) ?? 0),
                    'is_following' => in_array($user->id, $authFollowingIds, true),
                ] : [
                    'id' => null,
                    'name' => 'Deleted User',
                    'username' => 'deleted',
                    'avatar' => null,
                    'bio' => null,
                    'followers_count' => 0,
                    'following_count' => 0,
                    'is_following' => false,
                ],
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
     * @param  Post  $post
     * @return array
     */
    public function transformPost($post)
    {
        if (! $post) {
            return null;
        }

        $transformed = $this->transformPosts(collect([$post]));

        return $transformed->first();
    }
}
