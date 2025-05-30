<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\Products\ProductController;
use App\Http\Controllers\Products\ProductImageController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::group(['middleware' => 'auth:api'], function () {
    Route::get('/user', [UserController::class, 'show']);
    Route::patch('/user', [UserController::class, 'update']);
    Route::post('/user/change-password', [UserController::class, 'changePassword']);
    Route::post('/user/profile-image', [UserController::class, 'uploadProfileImage']);

    /* Products List, Store, Update, Delete */
    Route::get('/products', [ProductController::class, 'index']);
    Route::post('/products', [ProductController::class, 'store']);
    Route::get('/products/{product}', [ProductController::class, 'show']);
    Route::patch('/products/{product}', [ProductController::class, 'update']);
    Route::delete('/products/{product}', [ProductController::class, 'destroy']);

    /* Product Images, Features, Discounts */
    Route::prefix('products/{product}')->group(function () {
        Route::post('images', [ProductImageController::class, 'store']);
        Route::delete('images/{image}', [ProductImageController::class, 'destroy']);
    });

    Route::post('/logout', [AuthController::class, 'logout']);
});
