<?php

namespace App\GraphQL\Queries;

use App\Services\PostService;
use App\Transformers\PostTransformer;

class PostQuery
{
    /**
     * Resolves an individual post by its unique identifier.
     * Queries the MongoDB post document and hydrates MySQL author metadata
     * and interaction counters via PostTransformer.
     */
    public function __construct(
        private PostService $postService,
        private PostTransformer $postTransformer
    ) {}

    /**
     * Handle the incoming GraphQL query invocation.
     *
     * @param  null  $root
     * @param  array{id: string}  $args
     * @return array<string, mixed>|null
     */
    public function __invoke($root, array $args): ?array
    {
        $post = $this->postService->getPost($args['id']);
        if (! $post) {
            return null;
        }

        $transformed = $this->postTransformer->transformPosts(collect([$post]));

        return $transformed->first();
    }
}
