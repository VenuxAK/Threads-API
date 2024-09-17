<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class UserProfileController extends Controller
{
    public function show(Request $request)
    {
        return response()->json([
            "user" => [
                "id" => $request->user()->id,
                "name" => $request->user()->name,
                "username" => $request->user()->username,
                "email" => $request->user()->email,
                "avatar" => $request->user()->avatar,
                "bio" => $request->user()->bio,
                "email_verified_at" => $request->user()->email_verified_at,
            ]
        ]);
    }
}
