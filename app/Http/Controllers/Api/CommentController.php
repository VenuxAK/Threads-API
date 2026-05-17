<?php

namespace App\Http\Controllers\Api;

use App\DTOs\CommentData;
use App\Http\Controllers\Controller;
use App\Models\PostMetaData;
use App\Services\CommentService;
use App\Utils\Http;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CommentController extends Controller
{
    use Http;

    public function __construct(
        private CommentService $commentService,
    ) {}

    public function index(string $id)
    {
        try {
            $comments = $this->commentService->getComments($id);

            return $this->success([
                'comments' => $comments,
            ]);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 404);
        } catch (\Exception $e) {
            Log::error('Failed to fetch comments', [
                'error' => $e->getMessage(),
                'post_id' => $id,
            ]);

            if (str_contains($e->getMessage(), 'prepare()') || str_contains($e->getMessage(), 'PDO') || str_contains($e->getMessage(), 'connection')) {
                return $this->error('Database connection issue. Please check your database configuration.', 500);
            }

            return $this->error('Failed to fetch comments: ' . $e->getMessage(), 500);
        }
    }

    public function store(Request $request, string $id)
    {
        $request->validate([
            'content' => 'required|string|max:500',
            'parent_id' => 'nullable|string',
        ]);

        try {
            $data = new CommentData(
                content: $request->content,
                postId: $id,
                userId: Auth::id(),
                parentId: $request->parent_id,
            );

            $comment = $this->commentService->createComment($data);

            return $this->success([
                'comment' => $comment,
            ], 'Comment created successfully', 201);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 404);
        } catch (\Exception $e) {
            Log::error('Failed to create comment', [
                'error' => $e->getMessage(),
                'post_id' => $id,
                'user_id' => Auth::id(),
            ]);

            return $this->error('Failed to create comment. Please try again.', 500);
        }
    }

    public function show(string $id)
    {
        try {
            $comment = $this->commentService->getComment($id);
            if (!$comment) {
                return $this->error('Comment not found', 404);
            }

            return $this->success([
                'comment' => $comment,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch comment', [
                'error' => $e->getMessage(),
                'comment_id' => $id,
            ]);

            if (str_contains($e->getMessage(), 'prepare()') || str_contains($e->getMessage(), 'PDO') || str_contains($e->getMessage(), 'connection')) {
                return $this->error('Database connection issue. Please check your database configuration.', 500);
            }

            return $this->error('Failed to fetch comment: ' . $e->getMessage(), 500);
        }
    }

    public function destroy(Request $request, string $id)
    {
        try {
            $comment = \App\Models\Comment::find($id);
            if (!$comment) {
                return $this->error('Comment not found', 404);
            }

            if ($request->user()->cannot('delete', $comment)) {
                return $this->error('You are not authorized to delete this comment', 403);
            }

            $this->commentService->deleteComment($id);

            return $this->success([], 'Comment deleted successfully');
        } catch (\Exception $e) {
            Log::error('Failed to delete comment', [
                'error' => $e->getMessage(),
                'comment_id' => $id,
                'user_id' => Auth::id(),
            ]);

            return $this->error('Failed to delete comment. Please try again.', 500);
        }
    }

    public function replies(string $id)
    {
        try {
            $replies = $this->commentService->getReplies($id);

            return $this->success([
                'replies' => $replies,
            ]);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 404);
        } catch (\Exception $e) {
            Log::error('Failed to fetch replies', [
                'error' => $e->getMessage(),
                'comment_id' => $id,
            ]);

            if (str_contains($e->getMessage(), 'prepare()') || str_contains($e->getMessage(), 'PDO') || str_contains($e->getMessage(), 'connection')) {
                return $this->error('Database connection issue. Please check your database configuration.', 500);
            }

            return $this->error('Failed to fetch replies: ' . $e->getMessage(), 500);
        }
    }

    public function thread(string $id)
    {
        try {
            $thread = $this->commentService->getThread($id);

            return $this->success([
                'thread' => $thread,
            ]);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 404);
        } catch (\Exception $e) {
            Log::error('Failed to fetch comment thread', [
                'error' => $e->getMessage(),
                'comment_id' => $id,
            ]);

            if (str_contains($e->getMessage(), 'prepare()') || str_contains($e->getMessage(), 'PDO') || str_contains($e->getMessage(), 'connection')) {
                return $this->error('Database connection issue. Please check your database configuration.', 500);
            }

            return $this->error('Failed to fetch comment thread: ' . $e->getMessage(), 500);
        }
    }
}
