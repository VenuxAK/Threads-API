<?php

namespace App\GraphQL\Queries;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * GraphQL query resolver retrieving the list of users following a given user.
 * Reads from the MySQL `follows` table and eager loads public user attributes.
 */
class FollowersQuery
{
    /**
     * Resolve followers for a specific user ID.
     *
     * @param  null  $root
     * @param  array{userId: string|int}  $args
     * @return Collection<int, User>
     */
    public function __invoke($root, array $args): Collection
    {
        $userId = (int) $args['userId'];
        $user = User::find($userId);

        if (! $user) {
            return new Collection;
        }

        return $user->followers()
            ->select(['users.id', 'users.name', 'users.username', 'users.avatar', 'users.bio', 'users.created_at'])
            ->get();
    }
}
