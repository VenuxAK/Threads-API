<?php

use App\Http\Controllers\Api\CommentController;
use App\Http\Controllers\Api\PostController;
use App\Http\Controllers\Api\PostInteractionController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\SearchController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use MongoDB\Driver\ServerApi;

// Public test endpoint for WAF testing
Route::prefix('v1')->group(function () {
    Route::match(['GET', 'POST'], '/waf-test', function (\Illuminate\Http\Request $request) {
        return response()->json([
            'message' => 'WAF Test Endpoint',
            'timestamp' => now()->toISOString(),
            'waf_enabled' => config('waf.enabled', false),
            'waf_mode' => config('waf.mode', 'monitor'),
            'request_data' => $request->all(),
            'has_files' => $request->hasFile('file'),
        ]);
    });
});

Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    Route::prefix('me')->group(function () {
        Route::get('/profile', [ProfileController::class, 'myProfile']);
        Route::get('/reposts', [ProfileController::class, 'myReposts']);
        Route::get('/posts', [PostController::class, 'myPosts']);
        Route::post('/posts', [PostController::class, 'store']);
        Route::get('/posts/{id}', [PostController::class, 'myPost']);
        Route::put('/posts/{id}', [PostController::class, 'update']);
        Route::patch('/posts/{id}', [PostController::class, 'update']);
        Route::delete('/posts/{id}', [PostController::class, 'destroy']);
    });

    Route::prefix('users')->group(function () {
        Route::get('/{username}/posts', [ProfileController::class, 'userPosts']);
        Route::get('/{username}/reposts', [ProfileController::class, 'userReposts']);
        Route::get('/{username}', [ProfileController::class, 'show']);
    });

    Route::get('/posts', [PostController::class, 'index']);
    Route::get('/posts/{post}', [PostController::class, 'show']);

    Route::prefix('posts/{post}')->group(function () {
        Route::post('/like', [PostInteractionController::class, 'like']);
        Route::delete('/like', [PostInteractionController::class, 'unlike']);
        Route::get('/like/check', [PostInteractionController::class, 'checkLike']);
        Route::post('/share', [PostInteractionController::class, 'share']);
        Route::post('/repost', [PostInteractionController::class, 'repost']);
        Route::get('/repost/check', [PostInteractionController::class, 'checkRepost']);
        Route::get('/interactions', [PostInteractionController::class, 'interactions']);

        Route::get('/comments', [CommentController::class, 'index']);
        Route::post('/comments', [CommentController::class, 'store']);
    });

    Route::prefix('comments')->group(function () {
        Route::get('/{id}/thread', [CommentController::class, 'thread']);
        Route::get('/{id}/replies', [CommentController::class, 'replies']);
        Route::get('/{id}', [CommentController::class, 'show']);
        Route::delete('/{id}', [CommentController::class, 'destroy']);
    });

    Route::post('/search', [SearchController::class, 'search']);
});

Route::get('/ping-mongodb', function (Request $request) {
    try {
        $uri = env('MONGODB_URI');
        if (!$uri) {
            return response()->json([
                'msg' => 'MongoDB URI not configured',
            ], 500);
        }

        $apiVersion = new ServerApi(ServerApi::V1);
        $client = new MongoDB\Client($uri, [], ['serverApi' => $apiVersion]);
        $client->selectDatabase('admin')->command(['ping' => 1]);

        return response()->json([
            'msg' => "Pinged your deployment. You successfully connected to MongoDB!\n",
        ]);
    } catch (Exception $e) {
        \Illuminate\Support\Facades\Log::error('MongoDB ping failed', ['error' => $e->getMessage()]);
        return response()->json([
            'msg' => 'MongoDB connection failed: ' . $e->getMessage(),
        ], 500);
    }
});
