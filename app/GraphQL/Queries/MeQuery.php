<?php

namespace App\GraphQL\Queries;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

class MeQuery
{
    /**
     * Resolves the authenticated user for the `me` GraphQL query.
     * Returns the Eloquent User model directly so Lighthouse can serialize
     * model attributes, casts, and date formatting seamlessly.
     */
    public function __invoke($root, array $args): ?User
    {
        return Auth::user();
    }
}
