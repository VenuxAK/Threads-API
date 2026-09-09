<?php

namespace App\DTOs;

use App\Models\User;

/**
 * Data Transfer Object representing the outcome of a follow or unfollow operation.
 * Conveys boolean following status alongside updated follower and following counts
 * to immediately satisfy GraphQL client-side cache updates.
 */
class FollowResult
{
    public function __construct(
        public readonly bool $status,
        public readonly int $followers_count,
        public readonly int $following_count,
        public readonly ?User $user = null,
    ) {}
}
