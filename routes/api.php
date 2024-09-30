<?php

use App\Http\Controllers\Api\UserProfileController;
use App\Http\Controllers\Api\PostController;
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
    });

    /**
     * @desc  Get all posts
     * @private Only admin can access
     */
    Route::apiResource('posts', PostController::class);
});
