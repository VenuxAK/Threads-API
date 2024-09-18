<?php

namespace App\Transformers;

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

            // Return a simplified post object with author information
            return [
                'id' => $post->id,
                'content' => $post->content,
                'published_at' => $post->created_at->diffForHumans(),
                'edited_at' => $post->updated_at->diffForHumans(),
                'author' => [
                    'name' => $post->user->name,
                    'username' => $post->user->username,
                    'avatar' => $post->user->avatar,
                    'bio' => $post->user->bio,
                ]
            ];
        });
    }
}
