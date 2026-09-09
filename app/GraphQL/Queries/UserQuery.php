<?php

namespace App\GraphQL\Queries;

use App\Models\User;
use App\Services\UserService;

class UserQuery
{
    /**
     * Resolves a public user profile by username.
     * Delegates to UserService to find the MySQL user record and returns
     * the User Eloquent model for native GraphQL schema hydration.
     */
    public function __construct(
        private UserService $userService
    ) {}

    /**
     * Handle the incoming GraphQL query invocation.
     *
     * @param  null  $root
     * @param  array{username: string}  $args
     */
    public function __invoke($root, array $args): ?User
    {
        return $this->userService->getUser($args['username']);
    }
}
