<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\Products\ProductController;
use App\Http\Controllers\Products\ProductDiscountController;
use App\Http\Controllers\Products\ProductFeatureController;
use App\Http\Controllers\Products\ProductImageController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::group(['middleware' => 'auth:api'], function () {
    Route::get('/user', [UserController::class, 'show']);

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

        Route::post('features', [ProductFeatureController::class, 'store']);
        Route::patch('features/{feature}', [ProductFeatureController::class, 'update']);
        Route::delete('features/{feature}', [ProductFeatureController::class, 'destroy']);

        Route::post('discount', [ProductDiscountController::class, 'store']);
        Route::patch('discount', [ProductDiscountController::class, 'update']);
        Route::delete('discount', [ProductDiscountController::class, 'destroy']);
    });

    Route::post('/logout', [AuthController::class, 'logout']);
});
