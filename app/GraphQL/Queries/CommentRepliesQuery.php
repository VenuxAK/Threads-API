<?php

namespace App\GraphQL\Queries;

use App\Services\CommentService;

class CommentRepliesQuery
{
    /**
     * Resolves immediate direct child replies to a comment.
     * Uses CommentService to query MongoDB comments where parent_id matches the target,
     * batch-hydrating reply authors from MySQL.
     */
    public function __construct(
        private CommentService $commentService
    ) {}

    /**
     * Handle the incoming GraphQL query invocation.
     *
     * @param  null  $root
     * @param  array{id: string}  $args
     * @return array<int, array<string, mixed>>
     */
    public function __invoke($root, array $args): array
    {
        return $this->commentService->getReplies($args['id']);
    }
}
