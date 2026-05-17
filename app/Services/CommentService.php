<?php

namespace App\Services;

use App\DTOs\CommentData;
use App\Models\Comment;
use App\Models\Post;
use App\Models\PostMetaData;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class CommentService
{
    public function getComments(string $postId): array
    {
        $post = Post::find($postId);
        if (!$post) {
            throw new \RuntimeException('Post not found', 404);
        }

        $comments = Comment::where('post_id', $postId)
            ->whereNull('parent_id')
            ->latest()
            ->get();

        $userIds = $comments->pluck('user_id')->unique()->values();
        $users = User::whereIn('id', $userIds)
            ->get(['id', 'name', 'username', 'avatar'])
            ->keyBy('id');

        return $comments->map(function ($comment) use ($users) {
            $replyCount = 0;
            try {
                $replyCount = Comment::where('parent_id', $comment->id)->count();
            } catch (\Exception $e) {
                Log::warning('Failed to count replies', [
                    'comment_id' => $comment->id,
                    'error' => $e->getMessage(),
                ]);
            }

            $user = $users->get($comment->user_id);

            return [
                'id' => $comment->id,
                'content' => $comment->content,
                'post_id' => $comment->post_id,
                'parent_id' => $comment->parent_id,
                'created_at' => $comment->created_at->diffForHumans(),
                'reply_count' => $replyCount,
                'author' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'username' => $user->username,
                    'avatar' => $user->avatar,
                ] : [
                    'id' => $comment->user_id,
                    'name' => 'User',
                    'username' => 'user_' . substr($comment->user_id, 0, 8),
                    'avatar' => null,
                ],
            ];
        })->all();
    }

    public function createComment(CommentData $data): array
    {
        $post = Post::find($data->postId);
        if (!$post) {
            throw new \RuntimeException('Post not found', 404);
        }

        if ($data->parentId) {
            $parentComment = Comment::find($data->parentId);
            if (!$parentComment) {
                throw new \RuntimeException('Parent comment not found', 404);
            }
            if ((string) $parentComment->post_id !== $data->postId) {
                throw new \RuntimeException('Parent comment does not belong to this post', 400);
            }
        }

        $comment = Comment::create([
            'content' => $data->content,
            'post_id' => $data->postId,
            'user_id' => $data->userId,
            'parent_id' => $data->parentId,
        ]);

        return [
            'id' => $comment->id,
            'content' => $comment->content,
            'post_id' => $comment->post_id,
            'parent_id' => $comment->parent_id,
            'created_at' => $comment->created_at->diffForHumans(),
            'author' => [
                'id' => $data->userId,
                'name' => 'User',
                'username' => 'user_' . substr((string) $data->userId, 0, 8),
                'avatar' => null,
            ],
        ];
    }

    public function getComment(string $id): ?array
    {
        $comment = Comment::find($id);
        if (!$comment) {
            return null;
        }

        $user = User::find($comment->user_id);

        return [
            'id' => $comment->id,
            'content' => $comment->content,
            'post_id' => $comment->post_id,
            'parent_id' => $comment->parent_id,
            'created_at' => $comment->created_at->diffForHumans(),
            'author' => $user ? [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'avatar' => $user->avatar,
            ] : [
                'id' => $comment->user_id,
                'name' => 'User',
                'username' => 'user_' . substr($comment->user_id, 0, 8),
                'avatar' => null,
            ],
        ];
    }

    public function deleteComment(string $id, int $userId): bool
    {
        $comment = Comment::find($id);
        if (!$comment) {
            return false;
        }

        if ((string) $comment->user_id !== (string) $userId) {
            throw new \RuntimeException('You are not authorized to delete this comment', 403);
        }

        $comment->delete();

        return true;
    }

    public function getReplies(string $id): array
    {
        $comment = Comment::find($id);
        if (!$comment) {
            throw new \RuntimeException('Comment not found', 404);
        }

        $replies = Comment::where('parent_id', $id)
            ->latest()
            ->get();

        $replyUserIds = $replies->pluck('user_id')->unique()->values();
        $replyUsers = User::whereIn('id', $replyUserIds)
            ->get(['id', 'name', 'username', 'avatar'])
            ->keyBy('id');

        return $replies->map(function ($reply) use ($replyUsers) {
            $replyCount = 0;
            try {
                $replyCount = Comment::where('parent_id', $reply->id)->count();
            } catch (\Exception $e) {
                Log::warning('Failed to count nested replies', [
                    'comment_id' => $reply->id,
                    'error' => $e->getMessage(),
                ]);
            }

            $user = $replyUsers->get($reply->user_id);

            return [
                'id' => $reply->id,
                'content' => $reply->content,
                'post_id' => $reply->post_id,
                'parent_id' => $reply->parent_id,
                'created_at' => $reply->created_at->diffForHumans(),
                'reply_count' => $replyCount,
                'author' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'username' => $user->username,
                    'avatar' => $user->avatar,
                ] : [
                    'id' => $reply->user_id,
                    'name' => 'User',
                    'username' => 'user_' . substr($reply->user_id, 0, 8),
                    'avatar' => null,
                ],
            ];
        })->all();
    }

    public function getThread(string $id): array
    {
        $root = Comment::find($id);
        if (!$root) {
            throw new \RuntimeException('Comment not found', 404);
        }

        $descendantIds = [];
        $frontier = [(string) $root->id];

        while (!empty($frontier)) {
            $children = Comment::where('post_id', $root->post_id)
                ->whereIn('parent_id', $frontier)
                ->pluck('id')
                ->all();

            if (empty($children)) {
                break;
            }

            foreach ($children as $cid) {
                $descendantIds[] = $cid;
            }
            $frontier = array_map('strval', $children);
        }

        if (empty($descendantIds)) {
            return [];
        }

        $rows = Comment::whereIn('id', $descendantIds)
            ->orderBy('created_at', 'asc')
            ->get();

        $parentIds = $rows->pluck('parent_id')->unique()->filter()->values()->all();
        $parents = Comment::whereIn('id', $parentIds)->get()->keyBy(function ($m) {
            return (string) $m->id;
        });

        $threadUserIds = $rows->pluck('user_id')->unique()->values();
        $threadUsers = User::whereIn('id', $threadUserIds)
            ->get(['id', 'name', 'username', 'avatar'])
            ->keyBy('id');

        $rootId = (string) $root->id;

        return $rows->map(function ($reply) use ($parents, $rootId, $threadUsers) {
            $replyCount = 0;
            try {
                $replyCount = Comment::where('parent_id', $reply->id)->count();
            } catch (\Exception $e) {
                Log::warning('Failed to count nested replies', [
                    'comment_id' => $reply->id,
                    'error' => $e->getMessage(),
                ]);
            }

            $parentKey = $reply->parent_id !== null ? (string) $reply->parent_id : null;
            $replyingTo = null;
            if ($parentKey !== null && $parentKey !== $rootId) {
                $parent = $parents->get($parentKey);
                if ($parent) {
                    $replyingTo = [
                        'username' => 'user_' . substr($parent->user_id, 0, 8),
                    ];
                }
            }

            $user = $threadUsers->get($reply->user_id);

            return [
                'id' => $reply->id,
                'content' => $reply->content,
                'post_id' => $reply->post_id,
                'parent_id' => $reply->parent_id,
                'created_at' => $reply->created_at->diffForHumans(),
                'reply_count' => $replyCount,
                'replying_to' => $replyingTo,
                'author' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'username' => $user->username,
                    'avatar' => $user->avatar,
                ] : [
                    'id' => $reply->user_id,
                    'name' => 'User',
                    'username' => 'user_' . substr($reply->user_id, 0, 8),
                    'avatar' => null,
                ],
            ];
        })->all();
    }
}
