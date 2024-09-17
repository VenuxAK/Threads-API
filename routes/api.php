<?php

use App\Http\Controllers\Auth\UserProfileController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', [UserProfileController::class, "show"])->middleware(['auth:sanctum']);
