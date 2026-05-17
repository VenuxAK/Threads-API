<?php

namespace App\Actions;

use App\Models\Comment;
use App\Models\Post;
use App\Models\PostMetaData;

class CreateCommentAction
{
    public function execute(string $content, string $postId, int $userId, ?string $parentId = null): Comment
    {
        $post = Post::find($postId);
        if (!$post) {
            throw new \RuntimeException('Post not found', 404);
        }

        if ($parentId) {
            $parentComment = Comment::find($parentId);
            if (!$parentComment) {
                throw new \RuntimeException('Parent comment not found', 404);
            }
            if ((string) $parentComment->post_id !== $postId) {
                throw new \RuntimeException('Parent comment does not belong to this post', 400);
            }
        }

        $comment = Comment::create([
            'content' => $content,
            'post_id' => $postId,
            'user_id' => $userId,
            'parent_id' => $parentId,
        ]);

        try {
            PostMetaData::where('post_id', $postId)
                ->increment('comments_count');
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to increment comments_count', [
                'post_id' => $postId,
                'error' => $e->getMessage(),
            ]);
        }

        return $comment;
    }
}
