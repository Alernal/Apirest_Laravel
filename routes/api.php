<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\Products\ProductController;
use App\Http\Controllers\Products\ProductImageController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WishlistController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{product}', [ProductController::class, 'show']);



Route::group(['middleware' => 'auth:api'], function () {
    Route::get('/user', [UserController::class, 'show']);
    Route::patch('/user', [UserController::class, 'update']);
    Route::post('/user/change-password', [UserController::class, 'changePassword']);
    Route::post('/user/profile-image', [UserController::class, 'uploadProfileImage']);

    /* Products List, Store, Update, Delete */
    Route::post('/products', [ProductController::class, 'store']);
    Route::patch('/products/{product}', [ProductController::class, 'update']);
    Route::delete('/products/{product}', [ProductController::class, 'destroy']);

    /* Product Images, Features, Discounts */
    Route::prefix('products/{product}')->group(function () {
        Route::post('images', [ProductImageController::class, 'store']);
        Route::delete('images/{image}', [ProductImageController::class, 'destroy']);
    });

    /* Wishlist */
    Route::get('/wishlist', [WishlistController::class, 'index']);
    Route::post('/wishlist/{productId}', [WishlistController::class, 'store']);
    Route::delete('/wishlist/{productId}', [WishlistController::class, 'destroy']);

    /* Carts */
    Route::get('/cart', [CartController::class, 'index']);
    Route::post('/cart/{productId}', [CartController::class, 'store']);
    Route::patch('/cart/{productId}/decrement', [CartController::class, 'decrement']);
    Route::delete('/cart/clear', [CartController::class, 'clear']); // ✅ Esta debe ir antes
    Route::delete('/cart/{productId}', [CartController::class, 'destroy']);

    Route::post('/logout', [AuthController::class, 'logout']);
});
