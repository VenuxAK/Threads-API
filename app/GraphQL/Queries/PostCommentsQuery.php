<?php

namespace App\GraphQL\Queries;

use App\Services\CommentService;

class PostCommentsQuery
{
    /**
     * Resolves top-level comments for a given post.
     * Uses CommentService to pull root comments from MongoDB (where parent_id is null)
     * and joins author user records from MySQL in a batch lookup.
     */
    public function __construct(
        private CommentService $commentService
    ) {}

    /**
     * Handle the incoming GraphQL query invocation.
     *
     * @param  null  $root
     * @param  array{postId: string}  $args
     * @return array<int, array<string, mixed>>
     */
    public function __invoke($root, array $args): array
    {
        return $this->commentService->getComments($args['postId']);
    }
}
