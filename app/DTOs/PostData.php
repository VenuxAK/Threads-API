<?php

namespace App\DTOs;

class PostData
{
    public function __construct(
        public readonly string $content,
        public readonly array $tags = [],
        public readonly ?int $userId = null,
    ) {}

    public static function fromRequest(array $data, ?int $userId = null): self
    {
        return new self(
            content: $data['content'],
            tags: $data['tags'] ?? [],
            userId: $userId,
        );
    }
}
