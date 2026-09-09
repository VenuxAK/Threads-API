<?php

namespace App\GraphQL\Mutations;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

class ProfileMutation
{
    /**
     * Handles user profile update mutations.
     * Modifies the authenticated user's MySQL record (name, bio, avatar)
     * and returns the refreshed User model for GraphQL serialization.
     */
    public function update($root, array $args): User
    {
        /** @var User $user */
        $user = Auth::user();

        $updateData = [];
        if (isset($args['name'])) {
            $updateData['name'] = trim($args['name']);
        }
        if (isset($args['bio'])) {
            $updateData['bio'] = trim($args['bio']);
        }
        if (isset($args['avatar'])) {
            $updateData['avatar'] = trim($args['avatar']);
        }

        if (! empty($updateData)) {
            $user->update($updateData);
        }

        return $user->fresh();
    }
}
