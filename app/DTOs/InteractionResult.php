<?php

namespace App\DTOs;

class InteractionResult
{
    public function __construct(
        public readonly int $count,
        public readonly bool $active,
    ) {}
}
