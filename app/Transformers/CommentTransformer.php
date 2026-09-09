<?php

namespace App\Transformers;

use App\Models\Comment;
use App\Models\User;
use Illuminate\Support\Collection;

class CommentTransformer
{
    public function transform(Comment $comment, ?User $user, int $replyCount = 0, ?array $replyingTo = null): array
    {
        return [
            'id' => $comment->id,
            'content' => $comment->content,
            'post_id' => $comment->post_id,
            'parent_id' => $comment->parent_id,
            'created_at' => $comment->created_at->diffForHumans(),
            'reply_count' => $replyCount,
            'replying_to' => $replyingTo,
            'author' => $user ? [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'avatar' => $user->avatar,
            ] : [
                'id' => $comment->user_id,
                'name' => 'User',
                'username' => 'user_'.substr((string) $comment->user_id, 0, 8),
                'avatar' => null,
            ],
        ];
    }

    public function transformCollection(Collection $comments, Collection $users): array
    {
        return $comments->map(function ($comment) use ($users) {
            $user = $users->get($comment->user_id);

            return $this->transform($comment, $user);
        })->all();
    }

    public function transformThread(
        Collection $rows,
        Collection $threadUsers,
        Collection $parents,
        string $rootId,
    ): array {
        return $rows->map(function ($reply) use ($threadUsers, $parents, $rootId) {
            $parentKey = $reply->parent_id !== null ? (string) $reply->parent_id : null;
            $replyingTo = null;

            if ($parentKey !== null && $parentKey !== $rootId) {
                $parent = $parents->get($parentKey);
                if ($parent) {
                    $replyingTo = [
                        'username' => 'user_'.substr((string) $parent->user_id, 0, 8),
                    ];
                }
            }

            $user = $threadUsers->get($reply->user_id);

            return $this->transform($reply, $user, 0, $replyingTo);
        })->all();
    }
}
