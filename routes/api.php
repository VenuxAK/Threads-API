<?php

use App\Http\Controllers\Api\CommentController;
use App\Http\Controllers\Api\LikeController;
use App\Http\Controllers\Api\UserProfileController;
use App\Http\Controllers\Api\PostController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Auth\AuthUserProfileController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix("v1")->group(function () {
    Route::prefix("user")->middleware('auth:sanctum')->group(function () {
        // Auth user routes
        Route::get('/', [AuthUserProfileController::class, "showUser"]);
        Route::get("/posts", [AuthUserProfileController::class, "showPosts"]);
        Route::get("/posts/{id}", [AuthUserProfileController::class, "showPost"]);
        Route::post("/posts", [AuthUserProfileController::class, "storePost"]);
        Route::put("/posts/{id}", [AuthUserProfileController::class, "updatePost"]);
        Route::patch("/posts/{id}", [AuthUserProfileController::class, "updatePost"]);
        Route::delete("/posts/{id}", [AuthUserProfileController::class, "deletePost"]);
    });

    // Other user routes
    Route::prefix("users")->middleware(['auth:sanctum'])->group(function () {
        /**
         * @desc Get user, user's posts or user's post by id
         * @usage
         *  -   To get only user infomation, then fetch /api/v1/users/{username}
         *  -   To get user with posts, then fetch  /api/v1/users/{username}?posts=include
         *  -   To get user's single post, then fetch /api/v1/users/{username}?post={post_id}
         */
        Route::get("/{username}", [UserProfileController::class, "show"]);

        // CRUD post API
        Route::get("/{username}/posts", [UserProfileController::class, "showAllPosts"]);
        Route::get("/{username}/posts/{id}", [UserProfileController::class, "showPost"]);
        Route::post("/{username}/posts", [UserProfileController::class, "storePost"]);
        Route::put("/{username}/posts/{id}", [UserProfileController::class, "updatePost"]);
        Route::patch("/{username}/posts/{id}", [UserProfileController::class, "updatePost"]);
        Route::delete("/{username}/posts/{id}", [UserProfileController::class, "destroyPost"]);


        // Comments
        Route::get("/{username}/posts/{id}/comments", [CommentController::class, "show"]);
        Route::post("/{username}/posts/{id}/comments", [CommentController::class, "store"]);

        // Likes
        // Route::get('/{username}/posts/{id}/likes', [LikeController::class, "show"]);
        Route::post('/{username}/posts/{id}/likes', [LikeController::class, "store"]);

        // // Comments
        // Route::get('/posts/{id}/comments', [CommentController::class, "show"]);
        // Route::post('/posts/{id}/comments', [CommentController::class, "store"]);

        // // Likes
        // Route::post('/posts/{id}/likes', [LikeController::class, "store"]);
    });

    /**
     * @desc  Get all posts
     * @private Only admin can access
     */
    Route::apiResource('posts', PostController::class);

    // // Comments
    // Route::get('/posts/{id}/comments', [CommentController::class, "show"]);
    // Route::post('/posts/{id}/comments', [CommentController::class, "store"]);

    // // Likes
    // Route::post('/posts/{id}/likes', [LikeController::class, "store"]);

    /**
     * @desc Search
     * @public
     */
    Route::post("/search", [SearchController::class, "search"]); // ->where('keyword', '[A-Za-z0-9\_\@]+')
});
