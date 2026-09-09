<?php

namespace App\GraphQL\Mutations;

use App\Services\PostService;
use App\Transformers\PostTransformer;
use Illuminate\Support\Facades\Auth;

class PostMutation
{
    /**
     * Handles post authoring mutations (creation, updates, deletion).
     * Dispatches writes through PostService, ensuring MongoDB documents and MySQL
     * metadata records stay synchronized with compensation rollbacks on failure.
     */
    public function __construct(
        private PostService $postService,
        private PostTransformer $postTransformer
    ) {}

    /**
     * Create and publish a new post for the authenticated user.
     *
     * @param  null  $root
     * @param  array{content: string}  $args
     * @return array<string, mixed>
     */
    public function create($root, array $args): array
    {
        $userId = (int) Auth::id();
        $post = $this->postService->createPost($args['content'], $userId);

        $transformed = $this->postTransformer->transformPosts(collect([$post]));

        return $transformed->first();
    }

    /**
     * Update an existing post owned by the authenticated user.
     *
     * @param  null  $root
     * @param  array{id: string, content: string}  $args
     * @return array<string, mixed>
     */
    public function update($root, array $args): array
    {
        $userId = (int) Auth::id();
        $post = $this->postService->updatePost($args['id'], $args['content'], $userId);

        if (! $post) {
            throw new \RuntimeException('Post not found or unauthorized', 404);
        }

        $transformed = $this->postTransformer->transformPosts(collect([$post]));

        return $transformed->first();
    }

    /**
     * Delete a post owned by the authenticated user and remove MySQL metadata.
     *
     * @param  null  $root
     * @param  array{id: string}  $args
     */
    public function delete($root, array $args): bool
    {
        $userId = (int) Auth::id();

        return $this->postService->deletePost($args['id'], $userId);
    }
}
