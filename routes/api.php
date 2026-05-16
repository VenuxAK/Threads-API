<?php

use App\Http\Controllers\Api\CommentController;
use App\Http\Controllers\Api\MyProfileController;
use App\Http\Controllers\Api\OtherUserProfileController;
use App\Http\Controllers\Api\PostController;
use App\Http\Controllers\Api\PostInteractionController;
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
        // Auth user routes
        Route::get('/profile', [MyProfileController::class, 'me']); // Get auth user info
        Route::get('/reposts', [MyProfileController::class, 'repostsIndex']);
        Route::apiResource('/posts', MyProfileController::class);
    });

    // Other user routes
    Route::prefix('users')->group(function () {
        /**
         * @desc Get user, user's posts or user's post by id
         *
         * @usage
         *  -   To get only user infomation, then fetch /api/v1/users/{username}
         *  -   To get user with posts, then fetch  /api/v1/users/{username}?posts=include
         *  -   To get user's single post, then fetch /api/v1/users/{username}?post={post_id}
         */
        Route::get('/{username}', [OtherUserProfileController::class, 'show']);
        // Route::get("/{username}/posts", [OtherUserProfileController::class, ""]);
    });

    /**
     * @desc  Get all posts
     */
    // Route::apiResource('posts', PostController::class);
    Route::get('/posts', [PostController::class, 'index']);
    Route::get('/posts/{post}', [PostController::class, 'show']);

    /**
     * @desc Post interactions (likes, shares)
     */
    Route::prefix('posts/{post}')->group(function () {
        Route::post('/like', [PostInteractionController::class, 'like']);
        Route::delete('/like', [PostInteractionController::class, 'unlike']);
        Route::get('/like/check', [PostInteractionController::class, 'checkLike']);
        Route::post('/share', [PostInteractionController::class, 'share']);
        Route::post('/repost', [PostInteractionController::class, 'repost']);
        Route::get('/repost/check', [PostInteractionController::class, 'checkRepost']);
        Route::get('/interactions', [PostInteractionController::class, 'interactions']);

        /**
         * @desc Comments
         */
        Route::get('/comments', [CommentController::class, 'index']);
        Route::post('/comments', [CommentController::class, 'store']);
    });

    /**
     * @desc Comment operations
     */
    Route::prefix('comments')->group(function () {
        Route::get('/{id}/thread', [CommentController::class, 'thread']);
        Route::get('/{id}/replies', [CommentController::class, 'replies']);
        Route::get('/{id}', [CommentController::class, 'show']);
        Route::delete('/{id}', [CommentController::class, 'destroy']);
    });

    /**
     * @desc Search
     *
     * @public
     */
    Route::post('/search', [SearchController::class, 'search']); // ->where('keyword', '[A-Za-z0-9\_\@]+')
});

Route::get('/ping-mongodb', function (Request $request) {
    try {
        $uri = env('MONGODB_URI');
        if (!$uri) {
            return response()->json([
                'msg' => 'MongoDB URI not configured',
            ], 500);
        }

        // Set the version of the Stable API on the client
        $apiVersion = new ServerApi(ServerApi::V1);
        // Create a new client and connect to the server
        $client = new MongoDB\Client($uri, [], ['serverApi' => $apiVersion]);

        // Send a ping to confirm a successful connection
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

// Route::get('/test-comments', function () {
//     try {
//         $results = [];
//
//         // Test 1: Simple MongoDB query
//         $results['test1'] = 'Testing Comment model...';
//         try {
//             $commentCount = \App\Models\Comment::count();
//             $results['test1_result'] = "Success - Found $commentCount comments";
//         } catch (\Exception $e) {
//             $results['test1_error'] = $e->getMessage();
//             $results['test1_trace'] = $e->getTraceAsString();
//         }
//
//         // Test 2: Find a specific post
//         $results['test2'] = 'Testing Post model...';
//         try {
//             // Try to find any post
//             $post = \App\Models\Post::first();
//             if ($post) {
//                 $results['test2_result'] = "Success - Found post ID: " . $post->id;
//             } else {
//                 $results['test2_result'] = "Success - No posts found";
//             }
//         } catch (\Exception $e) {
//             $results['test2_error'] = $e->getMessage();
//         }
//
//         // Test 3: User model
//         $results['test3'] = 'Testing User model...';
//         try {
//             $userCount = \App\Models\User::count();
//             $results['test3_result'] = "Success - Found $userCount users";
//         } catch (\Exception $e) {
//             $results['test3_error'] = $e->getMessage();
//         }
//
//         return response()->json($results);
//     } catch (\Exception $e) {
//         return response()->json([
//             'error' => $e->getMessage(),
//             'trace' => $e->getTraceAsString()
//         ]);
//     }
// });
