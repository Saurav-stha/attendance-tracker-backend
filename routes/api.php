<?php

use App\Http\Controllers\api\AuthController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// in routes/api.php
Route::get('/test-auth', function () {
    return ['message' => 'You are authenticated!'];
})->middleware('auth:sanctum');

Route::group(['middleware' => ['auth:sanctum'], 'prefix' => 'auth'],  function () {
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('refresh', [AuthController::class, 'refresh']);
    Route::get('me', [AuthController::class, 'me']);
});
// Route::post('update-profile', [AuthController::class, 'updateProfile']);
// Route::post('change-password', [AuthController::class, 'changePassword']);

Route::group(['prefix'=>'auth'], function () {
    Route::post('signup', [AuthController::class, 'signup']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('verify-email', [AuthController::class, 'verifyEmail'])->name('password.email');
    Route::post('forgot-password', [AuthController::class, 'forgot-password'])->name('password.forgot');
    Route::post('reset-password', [AuthController::class, 'reset-password'])->name('password.reset');
});



