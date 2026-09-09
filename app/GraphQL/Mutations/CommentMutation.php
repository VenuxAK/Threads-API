<?php

namespace App\GraphQL\Mutations;

use App\DTOs\CommentData;
use App\Models\Comment;
use App\Services\CommentService;
use Illuminate\Support\Facades\Auth;

class CommentMutation
{
    /**
     * Handles comment lifecycle mutations (creation and deletion).
     * Interacts with CommentService to persist MongoDB comments and manages
     * parent_id threading relationships while updating post comments counters.
     */
    public function __construct(
        private CommentService $commentService
    ) {}

    /**
     * Create a new top-level comment or nested reply.
     *
     * @param  null  $root
     * @param  array{postId: string, content: string, parentId?: string|null}  $args
     * @return array<string, mixed>
     */
    public function create($root, array $args): array
    {
        $userId = (int) Auth::id();
        $data = new CommentData(
            $args['content'],
            $args['postId'],
            $userId,
            $args['parentId'] ?? null
        );

        return $this->commentService->createComment($data);
    }

    /**
     * Delete an existing comment owned by the authenticated user.
     *
     * @param  null  $root
     * @param  array{id: string}  $args
     */
    public function delete($root, array $args): bool
    {
        $userId = (int) Auth::id();
        $comment = Comment::find($args['id']);

        if (! $comment || (int) $comment->user_id !== $userId) {
            throw new \RuntimeException('Comment not found or unauthorized', 403);
        }

        return $this->commentService->deleteComment($args['id']);
    }
}
