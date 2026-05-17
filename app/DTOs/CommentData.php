<?php

namespace App\DTOs;

class CommentData
{
    public function __construct(
        public readonly string $content,
        public readonly string $postId,
        public readonly int $userId,
        public readonly ?string $parentId = null,
    ) {}
}
