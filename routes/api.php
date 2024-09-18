<?php

use App\Http\Controllers\Api\PostController;
use App\Http\Controllers\Auth\UserProfileController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', [UserProfileController::class, "show"])->middleware(['auth:sanctum']);

Route::prefix("v1")->group(function() {
    Route::resource('posts', PostController::class);
});
