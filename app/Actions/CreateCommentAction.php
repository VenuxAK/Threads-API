<?php

namespace App\Actions;

use App\Models\Comment;
use App\Models\Post;
use App\Models\PostMetaData;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class CreateCommentAction
{
    public function execute(string $content, string $postId, int $userId, ?string $parentId = null): Comment
    {
        $post = Post::find($postId);
        if (! $post) {
            throw new \RuntimeException('Post not found', 404);
        }

        if ($parentId) {
            $parentComment = Comment::find($parentId);
            if (! $parentComment) {
                throw new \RuntimeException('Parent comment not found', 404);
            }
            if ((string) $parentComment->post_id !== $postId) {
                throw new \RuntimeException('Parent comment does not belong to this post', 400);
            }
        }

        // Protect against rapid duplicate submissions (e.g. double-click or simultaneous browser events)
        $recentDuplicate = Comment::where('post_id', $postId)
            ->where('user_id', $userId)
            ->where('content', $content)
            ->where('parent_id', $parentId)
            ->where('created_at', '>=', now()->subSeconds(3))
            ->first();

        if ($recentDuplicate) {
            return $recentDuplicate;
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
            Log::warning('Failed to increment comments_count', [
                'post_id' => $postId,
                'error' => $e->getMessage(),
            ]);
        }

        // Dispatch notifications for comments, replies, and @mentions
        try {
            $notificationAction = app(CreateNotificationAction::class);

            // Notify parent comment author if replying in a thread
            if (isset($parentComment) && $parentComment->user_id) {
                $notificationAction->execute(
                    userId: (int) $parentComment->user_id,
                    senderId: $userId,
                    type: 'comment',
                    entityId: $postId
                );
            }

            // Notify post author if not already notified as parent author
            $parentAuthorId = isset($parentComment) ? (int) $parentComment->user_id : null;
            if ($post->user_id && (int) $post->user_id !== $parentAuthorId) {
                $notificationAction->execute(
                    userId: (int) $post->user_id,
                    senderId: $userId,
                    type: 'comment',
                    entityId: $postId
                );
            }

            // Detect @username mentions and dispatch mention alerts
            preg_match_all('/@([a-zA-Z0-9_]+)/', $content, $matches);
            if (! empty($matches[1])) {
                $mentionedUsernames = array_unique($matches[1]);
                $mentionedUsers = User::whereIn('username', $mentionedUsernames)->get(['id']);
                foreach ($mentionedUsers as $mUser) {
                    $notificationAction->execute(
                        userId: (int) $mUser->id,
                        senderId: $userId,
                        type: 'mention',
                        entityId: $postId
                    );
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to dispatch comment notifications', [
                'post_id' => $postId,
                'error' => $e->getMessage(),
            ]);
        }

        return $comment;
    }
}
