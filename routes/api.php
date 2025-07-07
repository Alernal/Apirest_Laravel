<?php

use App\Http\Controllers\AddressController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\Products\ProductController;
use App\Http\Controllers\Products\ProductImageController;
use App\Http\Controllers\Products\ProductReviewController;
use App\Http\Controllers\SubscriberController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WishlistController;
use App\Http\Controllers\WompiController;
use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Illuminate\Auth\Events\Verified;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{product}', [ProductController::class, 'show']);
Route::get('/reviews', [ProductReviewController::class, 'index']);
Route::post('/subscribers', [SubscriberController::class, 'store']);

Route::group(['middleware' => 'auth:api'], function () {
    /* RUTAS SOLO PARA ADMINISTRADOR */
    Route::group(['middleware' => 'is.admin'], function () {
        /* Products List, Store, Update, Delete */
        Route::post('/products', [ProductController::class, 'store']);
        Route::patch('/products/{product}', [ProductController::class, 'update']);
        Route::delete('/products/{product}', [ProductController::class, 'destroy']);

        /* Product Images, Features, Discounts */
        Route::prefix('products/{product}')->group(function () {
            Route::post('images', [ProductImageController::class, 'store']);
            Route::delete('images/{image}', [ProductImageController::class, 'destroy']);
        });

        Route::get('/user/{id}', [UserController::class, 'show']);

        Route::delete('/orders/{order}', [OrderController::class, 'destroy']);

        /* WOMPI - Pasarela de pago */
        Route::get('/wompi/transaction/{id}', [WompiController::class, 'getTransaction']);
        Route::post('/generate-link', [WompiController::class, 'generarLinkPago']);
    });

    Route::get('users', [UserController::class, 'index']);
    Route::patch('/user', [UserController::class, 'update']);
    Route::post('/user/change-password', [UserController::class, 'changePassword']);
    Route::post('/user/profile-image', [UserController::class, 'uploadProfileImage']);



    // Product Reviews
    Route::post('/reviews', [ProductReviewController::class, 'store']);
    Route::patch('/reviews/{review}', [ProductReviewController::class, 'update']);
    Route::put('/reviews/{review}', [ProductReviewController::class, 'update']);
    Route::delete('/reviews/{review}', [ProductReviewController::class, 'destroy']);

    /* Wishlist */
    Route::get('/wishlist', [WishlistController::class, 'index']);
    Route::post('/wishlist/{productId}', [WishlistController::class, 'store']);
    Route::delete('/wishlist/{productId}', [WishlistController::class, 'destroy']);

    /* Carts */
    Route::get('/cart', [CartController::class, 'index']);
    Route::post('/cart/{productId}', [CartController::class, 'store']);
    Route::patch('/cart/{productId}/decrement', [CartController::class, 'decrement']);
    Route::delete('/cart/clear', [CartController::class, 'clear']);
    Route::delete('/cart/{productId}', [CartController::class, 'destroy']);

    /* Addresses */
    Route::get('/addresses', [AddressController::class, 'index']);
    Route::post('/addresses', [AddressController::class, 'store']);
    Route::get('/addresses/{address}', [AddressController::class, 'show']);
    Route::patch('/addresses/{address}', [AddressController::class, 'update']);
    Route::delete('/addresses/{address}', [AddressController::class, 'destroy']);
    Route::patch('/addresses/{id}/default', [AddressController::class, 'setDefaultAddress']);

    /* Orders */
    Route::get('/orders', [OrderController::class, 'index']);
    Route::post('/orders/{id}/status', [OrderController::class, 'store']);
    Route::get('/orders/{order}', [OrderController::class, 'show']);
    Route::get('/orders/{id}/history', [OrderController::class, 'history']);

    Route::post('/logout', [AuthController::class, 'logout']);
});
