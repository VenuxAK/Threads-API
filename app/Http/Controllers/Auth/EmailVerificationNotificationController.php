<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmailVerificationNotificationController extends Controller
{
    /**
     * Send a new email verification notification.
     */
    public function store(Request $request): JsonResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return response()->json([
                "status" => "The email address was already verified."
            ]);
        }

        $request->user()->sendEmailVerificationNotification();

        return response()->json(['status' => 'Verification link has been sent.']);
    }
}
