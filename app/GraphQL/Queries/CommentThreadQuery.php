<?php

namespace App\GraphQL\Queries;

use App\Services\CommentService;

class CommentThreadQuery
{
    /**
     * Resolves the entire chronological thread under a comment.
     * Delegates to CommentService::getThread which runs a MongoDB `$graphLookup`
     * aggregation pipeline up to 50 levels deep, hydrating parent author references.
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
        return $this->commentService->getThread($args['id']);
    }
}
