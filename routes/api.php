<?php

use App\Http\Controllers\AuthController;
use App\Http\Middleware\JwtMiddleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::controller(AuthController::class)->group(function () {
    Route::get('verify-email/{id}/{hash}', 'verifyEmail')
        ->name('verification.verify')
        ->middleware('signed');
    Route::post('login', 'login');
    Route::post('register', 'register');
});

Route::middleware([JwtMiddleware::class])->group(function () {
    // Auth
    Route::post('logout', [AuthController::class, 'logout']);
});
