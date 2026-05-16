<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use App\Utils\Http;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class CommentController extends Controller
{
    use Http;

    /**
     * Display a listing of comments for a post
     *
     * @route GET /api/v1/posts/{id}/comments
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(string $id)
    {
        try {
            Log::info('Fetching comments for post', ['post_id' => $id]);

            $post = Post::find($id);
            Log::info('Post query result', ['post' => $post ? 'found' : 'not found']);

            if (!$post) {
                return $this->error("Post not found", 404);
            }

            $comments = Comment::where('post_id', $id)
                ->whereNull('parent_id')
                ->latest()
                ->get();

            Log::info('Comments query result', ['count' => $comments->count()]);

            $userIds = $comments->pluck('user_id')->unique()->values();
            $users = User::whereIn('id', $userIds)
                ->get(['id', 'name', 'username', 'avatar'])
                ->keyBy('id');

            $transformedComments = $comments->map(function ($comment) use ($users) {
                try {
                    $replyCount = 0;
                    try {
                        $replyCount = Comment::where('parent_id', $comment->id)->count();
                    } catch (\Exception $e) {
                        Log::warning('Failed to count replies for comment', [
                            'comment_id' => $comment->id,
                            'error' => $e->getMessage()
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
                        ]
                    ];
                } catch (\Exception $e) {
                    Log::error('Failed to transform comment', [
                        'comment_id' => $comment->id,
                        'error' => $e->getMessage()
                    ]);

                    return [
                        'id' => $comment->id,
                        'content' => $comment->content,
                        'post_id' => $comment->post_id,
                        'parent_id' => $comment->parent_id,
                        'created_at' => $comment->created_at->diffForHumans(),
                        'reply_count' => 0,
                        'author' => [
                            'id' => null,
                            'name' => 'Unknown User',
                            'username' => 'unknown',
                            'avatar' => null,
                        ]
                    ];
                }
            });

            return $this->success([
                'comments' => $transformedComments
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch comments', [
                'error' => $e->getMessage(),
                'post_id' => $id,
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            // Check if it's a database connection error
            if (str_contains($e->getMessage(), 'prepare()') || str_contains($e->getMessage(), 'PDO') || str_contains($e->getMessage(), 'connection')) {
                return $this->error('Database connection issue. Please check your database configuration.', 500);
            }

            return $this->error('Failed to fetch comments: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Store a newly created comment
     *
     * @route POST /api/v1/posts/{id}/comments
     * @param Request $request
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request, string $id)
    {
        $post = Post::find($id);
        if (!$post) {
            return $this->error("Post not found", 404);
        }

        $request->validate([
            'content' => 'required|string|max:500',
            'parent_id' => 'nullable|string',
        ]);

        // Validate parent_id if provided
        if ($request->parent_id) {
            $parentComment = Comment::find($request->parent_id);
            if (!$parentComment) {
                return $this->error('Parent comment not found', 404);
            }

            // Make sure parent comment belongs to the same post
            if ($parentComment->post_id != $id) {
                return $this->error('Parent comment does not belong to this post', 400);
            }
        }

        try {
            $comment = Comment::create([
                'content' => $request->content,
                'post_id' => $id,
                'user_id' => Auth::id(),
                'parent_id' => $request->parent_id,
            ]);

            $userId = Auth::id();

            $transformedComment = [
                'id' => $comment->id,
                'content' => $comment->content,
                'post_id' => $comment->post_id,
                'parent_id' => $comment->parent_id,
                'created_at' => $comment->created_at->diffForHumans(),
                'author' => [
                    'name' => 'User',
                    'username' => 'user_' . substr($userId, 0, 8),
                    'avatar' => null,
                    'id' => $userId
                ]
            ];

            return $this->success([
                'comment' => $transformedComment
            ], 'Comment created successfully', 201);
        } catch (\Exception $e) {
            Log::error('Failed to create comment', [
                'error' => $e->getMessage(),
                'post_id' => $id,
                'user_id' => Auth::id()
            ]);

            return $this->error('Failed to create comment. Please try again.', 500);
        }
    }

    /**
     * Display the specified comment
     *
     * @route GET /api/v1/comments/{id}
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(string $id)
    {
        try {
            $comment = Comment::find($id);
            if (!$comment) {
                return $this->error("Comment not found", 404);
            }

            // Simplified user data to avoid database connection issues
            $transformedComment = [
                'id' => $comment->id,
                'content' => $comment->content,
                'post_id' => $comment->post_id,
                'parent_id' => $comment->parent_id,
                'created_at' => $comment->created_at->diffForHumans(),
                'author' => [
                    'name' => 'User',
                    'username' => 'user_' . substr($comment->user_id, 0, 8),
                    'avatar' => null,
                    'id' => $comment->user_id
                ]
            ];

            return $this->success([
                'comment' => $transformedComment
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch comment', [
                'error' => $e->getMessage(),
                'comment_id' => $id,
                'trace' => $e->getTraceAsString()
            ]);

            // Check if it's a database connection error
            if (str_contains($e->getMessage(), 'prepare()') || str_contains($e->getMessage(), 'PDO') || str_contains($e->getMessage(), 'connection')) {
                return $this->error('Database connection issue. Please check your database configuration.', 500);
            }

            return $this->error('Failed to fetch comment: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Remove the specified comment
     *
     * @route DELETE /api/v1/comments/{id}
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(string $id)
    {
        $comment = Comment::find($id);
        if (!$comment) {
            return $this->error("Comment not found", 404);
        }

        // Authorize the user
        if ($comment->user_id !== Auth::id()) {
            return $this->error('You are not authorized to delete this comment', 403);
        }

        try {
            $comment->delete();

            return $this->success([], 'Comment deleted successfully');
        } catch (\Exception $e) {
            Log::error('Failed to delete comment', [
                'error' => $e->getMessage(),
                'comment_id' => $id,
                'user_id' => Auth::id()
            ]);

            return $this->error('Failed to delete comment. Please try again.', 500);
        }
    }

    /**
     * Get replies to a comment
     *
     * @route GET /api/v1/comments/{id}/replies
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function replies(string $id)
    {
        try {
            Log::info('Fetching replies for comment', ['comment_id' => $id]);

            $comment = Comment::find($id);
            if (!$comment) {
                return $this->error("Comment not found", 404);
            }

            $replies = Comment::where('parent_id', $id)
                ->latest()
                ->get();

            Log::info('Replies query result', ['count' => $replies->count()]);

            $replyUserIds = $replies->pluck('user_id')->unique()->values();
            $replyUsers = User::whereIn('id', $replyUserIds)
                ->get(['id', 'name', 'username', 'avatar'])
                ->keyBy('id');

            $transformedReplies = $replies->map(function ($reply) use ($replyUsers) {
                $replyCount = 0;
                try {
                    $replyCount = Comment::where('parent_id', $reply->id)->count();
                } catch (\Exception $e) {
                    Log::warning('Failed to count nested replies for comment', [
                        'comment_id' => $reply->id,
                        'error' => $e->getMessage()
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
                    ]
                ];
            });

            return $this->success([
                'replies' => $transformedReplies
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch replies', [
                'error' => $e->getMessage(),
                'comment_id' => $id,
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            // Check if it's a database connection error
            if (str_contains($e->getMessage(), 'prepare()') || str_contains($e->getMessage(), 'PDO') || str_contains($e->getMessage(), 'connection')) {
                return $this->error('Database connection issue. Please check your database configuration.', 500);
            }

            return $this->error('Failed to fetch replies: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Flat chronological thread: all nested replies under a top-level comment
     * (Instagram / Threads style — one scrollable list, no per-branch expand).
     *
     * @route GET /api/v1/comments/{id}/thread
     */
    public function thread(string $id)
    {
        try {
            $root = Comment::find($id);
            if (!$root) {
                return $this->error('Comment not found', 404);
            }

            $descendantIds = [];
            $frontier = [(string) $root->id];

            while (! empty($frontier)) {
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
                return $this->success([
                    'thread' => [],
                ]);
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

            $thread = $rows->map(function ($reply) use ($parents, $rootId, $threadUsers) {
                $replyCount = 0;
                try {
                    $replyCount = Comment::where('parent_id', $reply->id)->count();
                } catch (\Exception $e) {
                    Log::warning('Failed to count nested replies for comment', [
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
            });

            return $this->success([
                'thread' => $thread,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch comment thread', [
                'error' => $e->getMessage(),
                'comment_id' => $id,
                'trace' => $e->getTraceAsString(),
            ]);

            if (str_contains($e->getMessage(), 'prepare()') || str_contains($e->getMessage(), 'PDO') || str_contains($e->getMessage(), 'connection')) {
                return $this->error('Database connection issue. Please check your database configuration.', 500);
            }

            return $this->error('Failed to fetch comment thread: ' . $e->getMessage(), 500);
        }
    }
}
