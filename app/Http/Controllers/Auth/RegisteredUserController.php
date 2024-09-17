<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;

class RegisteredUserController extends Controller
{
    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): Response
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:' . User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        // generate base username
        $baseUsername = $this->generateUsernameFromName($request->name);

        // Ensure the username is unique
        $username = $this->ensureUniqueUsername($baseUsername);

        $user = User::create([
            'name' => $request->name,
            'username' => $username,
            'email' => $request->email,
            'password' => Hash::make($request->string('password')),
        ]);

        event(new Registered($user));

        Auth::login($user);

        return response()->noContent();
    }

    private function generateUsernameFromName($name): string
    {
        // convert to lowercase, replace spaces with underscores and remove non-alphanumeric
        return strtolower(str_replace(' ', '_', $name));
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
            if (preg_match('/^' . preg_quote($baseUsername, '/') . '_(\d+)$/', $existingUsername, $matches)) {
                $maxCounter = max($maxCounter, (int) $matches[1]);
            }
        }

        // Increment the highest suffix to get the new unique username
        $username = $baseUsername . '_' . ($maxCounter + 1);
        return $username;
    }
}
