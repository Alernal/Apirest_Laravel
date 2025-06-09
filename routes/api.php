<?php

use App\Http\Controllers\AddressController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\Products\ProductController;
use App\Http\Controllers\Products\ProductImageController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WishlistController;
use App\Http\Controllers\WompiController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{product}', [ProductController::class, 'show']);



Route::group(['middleware' => 'auth:api'], function () {
    Route::get('users', [UserController::class, 'index']);
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
    Route::delete('/cart/clear', [CartController::class, 'clear']);
    Route::delete('/cart/{productId}', [CartController::class, 'destroy']);

    /* Addresses */
    Route::get('/addresses', [AddressController::class, 'index']);
    Route::post('/addresses', [AddressController::class, 'store']);
    Route::get('/addresses/{address}', [AddressController::class, 'show']);
    Route::patch('/addresses/{address}', [AddressController::class, 'update']);
    Route::delete('/addresses/{address}', [AddressController::class, 'destroy']);

    /* Orders */
    Route::get('/orders', [OrderController::class, 'index']);
    Route::post('/orders/{id}/status', [OrderController::class, 'store']);
    Route::get('/orders/{order}', [OrderController::class, 'show']);
    Route::delete('/orders/{order}', [OrderController::class, 'destroy']);

    /* WOMPI - Pasarela de pago */
    Route::get('/wompi/transaction/{id}', [WompiController::class, 'getTransaction']);
    Route::post('/generate-link', [WompiController::class, 'generarLinkPago']);


    Route::post('/logout', [AuthController::class, 'logout']);
});
