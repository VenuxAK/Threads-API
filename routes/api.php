<?php

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
        Route::post('/share', [PostInteractionController::class, 'share']);
        Route::get('/interactions', [PostInteractionController::class, 'interactions']);
    });

    /**
     * @desc Search
     *
     * @public
     */
    Route::post('/search', [SearchController::class, 'search']); // ->where('keyword', '[A-Za-z0-9\_\@]+')
});

Route::get('/ping-mongodb', function (Request $request) {
    $uri = env('MONGODB_URI');
    // Set the version of the Stable API on the client
    $apiVersion = new ServerApi(ServerApi::V1);
    // Create a new client and connect to the server
    $client = new MongoDB\Client($uri, [], ['serverApi' => $apiVersion]);
    try {
        // Send a ping to confirm a successful connection
        $client->selectDatabase('admin')->command(['ping' => 1]);

        // echo "Pinged your deployment. You successfully connected to MongoDB!\n";
        return response()->json([
            'msg' => "Pinged your deployment. You successfully connected to MongoDB!\n",
        ]);
    } catch (Exception $e) {
        return response()->json([
            'msg' => $e->getMessage(),
        ]);
        // printf($e->getMessage());
    }
});
