<?php

namespace App\Transformers;

use App\Models\Comment;
use App\Models\PostMetaData;
use App\Models\User;

class PostTransformer
{
    public function transformPosts($posts)
    {
        // Get a list of unique user IDs from the posts
        $userIds = $posts->pluck('user_id')->unique();

        // Retrieve the users corresponding to the post authors
        $users = User::whereIn('id', $userIds)->get()->keyBy('id');

        // Transform the posts to include user information and return
        return $posts->map(function ($post) use ($users) {
            // Associate the user with the post
            $post->user = $users->get($post->user_id);
            $metadata = PostMetaData::where('post_id', $post->id)->first();

            // Return a simplified post object with author information
            return [
                'id' => $post->id,
                'content' => $post->content,
                'tags' => $post?->tags,
                'published_at' => $post->created_at->diffForHumans(),
                'edited_at' => $post->updated_at->diffForHumans(),
                'comments_count' => $metadata->comments_count,
                'likes_count' => $metadata->likes_count,
                'author' => [
                    'name' => $post->user->name,
                    'username' => $post->user->username,
                    'avatar' => $post->user->avatar,
                    'bio' => $post->user->bio,
                ]
            ];
            /** Data Structure
             *  posts [
             *  {
             *  id, content, published_at, edited_at,
             *  author: { name, username, avatar, bio}
             *  comments_count, likes_count
             *  }
             * ]
             */
        });
    }

    public function transformPost($post)
    {
        $user = User::whereId($post->user_id)->first();
        $comments = Comment::where('post_id', $post->id)->with('user')->latest()->get();
        $metadata = PostMetaData::where('post_id', $post->id)->first();
        return [
            "id" => $post->id,
            "content" => $post->content,
            'published_at' => $post->created_at->diffForHumans(),
            'edited_at' => $post->updated_at->diffForHumans(),
            "likes_count" => $metadata->likes_count,
            "comments_count" => $metadata->comments_count,
            "author" => [
                "name" => $user->name,
                "username" => $user->username,
                "avatar" => $user->avatar,
                "bio" => $user->bio,
            ],
            "comments" => $comments->map(function ($comment) {
                return [
                    "content" => $comment->content,
                    "created_at" => $comment->created_at->diffForHumans(),
                    "user" => [
                        "name" => $comment->user->name,
                        "username" => $comment->user->username,
                        "avatar" => $comment->user->avatar,
                        "bio" => $comment->user->bio,
                    ]
                ];
            })
        ];
        /** Data structure
         * post: {
         *  id,content,published_at, edited_at,
         *  author: {name,username,avatar},
         *  comments: [{content,created_at, name, username}]
         * }
         */
    }
}
