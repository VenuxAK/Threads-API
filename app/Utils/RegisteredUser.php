<?php

namespace App\Utils;

use App\Models\User;

trait RegisteredUser
{

    private function generateUniqueUsername($name): string
    {
        // generate base username
        $baseUsername = strtolower(str_replace(' ', '_', $name));
        return $this->ensureUniqueUsername($baseUsername);
    }

    private function ensureUniqueUsername($baseUsername)
    {
        $username = $baseUsername;

        // Find all usernames that start with the base username
        $existingUsernames = User::where('username', 'like', "$baseUsername%")
            ->pluck('username')
            ->toArray();

        // If no usernames match the base, return the base username
        if (!in_array($username, $existingUsernames)) {
            return $username;
        }

        // Initialize counter
        $maxCounter = 0;

        // Loop through existing usernames to find the highest suffix
        foreach ($existingUsernames as $existingUsername) {
            // Match usernames like baseUsername_1, baseUsername_2, etc.
            if (preg_match('/^' . preg_quote($baseUsername, '/') . '_(\d+)$/', $existingUsername, $matches)) {
                // Update maxCounter if this suffix is higher
                $maxCounter = max($maxCounter, (int) $matches[1]);
            }
        }

        // Increment the highest suffix to get the new unique username
        $username = $baseUsername . '_' . ($maxCounter + 1);
        return $username;
    }
}
