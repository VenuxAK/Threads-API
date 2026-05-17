<?php

namespace App\Services;

use App\Actions\CreateCommentAction;
use App\DTOs\CommentData;
use App\Models\Comment;
use App\Models\PostMetaData;
use App\Models\User;
use App\Transformers\CommentTransformer;
use Illuminate\Support\Facades\Log;

class CommentService
{
    public function __construct(
        private CreateCommentAction $createCommentAction,
        private CommentTransformer $commentTransformer,
    ) {}

    public function getComments(string $postId): array
    {
        $post = \App\Models\Post::find($postId);
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

        return $this->commentTransformer->transformCollection($comments, $users);
    }

    public function createComment(CommentData $data): array
    {
        $comment = $this->createCommentAction->execute(
            $data->content,
            $data->postId,
            $data->userId,
            $data->parentId,
        );

        $user = User::find($data->userId);

        return $this->commentTransformer->transform($comment, $user);
    }

    public function getComment(string $id): ?array
    {
        $comment = Comment::find($id);
        if (!$comment) {
            return null;
        }

        $user = User::find($comment->user_id);

        return $this->commentTransformer->transform($comment, $user);
    }

    public function deleteComment(string $id): bool
    {
        $comment = Comment::find($id);
        if (!$comment) {
            return false;
        }

        $postId = $comment->post_id;
        $comment->delete();

        try {
            PostMetaData::where('post_id', $postId)
                ->where('comments_count', '>', 0)
                ->decrement('comments_count');
        } catch (\Exception $e) {
            Log::warning('Failed to decrement comments_count', [
                'post_id' => $postId,
                'comment_id' => $id,
                'error' => $e->getMessage(),
            ]);
        }

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

        return $this->commentTransformer->transformCollection($replies, $replyUsers);
    }

    public function getThread(string $id): array
    {
        $root = Comment::find($id);
        if (!$root) {
            throw new \RuntimeException('Comment not found', 404);
        }

        $results = Comment::raw(function ($collection) use ($id) {
            return $collection->aggregate([
                ['$match' => ['_id' => new \MongoDB\BSON\ObjectId($id)]],
                ['$graphLookup' => [
                    'from' => 'comments',
                    'connectFromField' => '_id',
                    'connectToField' => 'parent_id',
                    'startWith' => '$_id',
                    'as' => 'descendants',
                    'maxDepth' => 50,
                ]],
                ['$unwind' => '$descendants'],
                ['$replaceRoot' => ['newRoot' => '$descendants']],
                ['$sort' => ['created_at' => 1]],
            ]);
        });

        $results = iterator_to_array($results);

        if (empty($results)) {
            return [];
        }

        $threadUserIds = collect($results)->pluck('user_id')->unique()->values();
        $threadUsers = User::whereIn('id', $threadUserIds)
            ->get(['id', 'name', 'username', 'avatar'])
            ->keyBy('id');

        $parentMap = collect($results)
            ->filter(fn ($r) => isset($r['parent_id']))
            ->keyBy(fn ($r) => (string) $r['_id']);

        $rootId = (string) $root->id;

        return collect($results)->map(function ($doc) use ($threadUsers, $parentMap, $rootId) {
            $docId = (string) $doc['_id'];
            $parentId = isset($doc['parent_id']) ? (string) $doc['parent_id'] : null;

            $replyingTo = null;
            if ($parentId !== null && $parentId !== $rootId) {
                $parent = $parentMap->get($parentId);
                if ($parent) {
                    $replyingTo = [
                        'username' => 'user_' . substr((string) $parent['user_id'], 0, 8),
                    ];
                }
            }

            $userId = (string) $doc['user_id'];
            $user = $threadUsers->get($userId);

            return [
                'id' => $docId,
                'content' => $doc['content'],
                'post_id' => (string) $doc['post_id'],
                'parent_id' => $parentId,
                'created_at' => \Illuminate\Support\Carbon::parse($doc['created_at'])->diffForHumans(),
                'reply_count' => 0,
                'replying_to' => $replyingTo,
                'author' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'username' => $user->username,
                    'avatar' => $user->avatar,
                ] : [
                    'id' => $userId,
                    'name' => 'User',
                    'username' => 'user_' . substr($userId, 0, 8),
                    'avatar' => null,
                ],
            ];
        })->all();
    }
}
