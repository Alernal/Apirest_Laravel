<?php

use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Verificación de correo electrónico
Route::get('/email/verify/{id}/{hash}', [UserController::class, 'verifyEmail'])->middleware('signed')->name('verification.verify');
Route::post('/email/resend', [UserController::class, 'resendVerificationEmail']);