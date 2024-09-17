<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return ['Laravel' => app()->version()];
});

Route::prefix("auth")->group(function () {
    require __DIR__ . '/auth.php';
});
