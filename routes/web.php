<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PropertyTypeController;
use App\Http\Controllers\PropertyController;
use App\Http\Controllers\BookingController;

Route::get('/', function () {
    return view('welcome');
});

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::apiResource('property-types', PropertyTypeController::class);
    Route::apiResource('properties', PropertyController::class);
    Route::apiResource('bookings', BookingController::class);
});

// Web - Laptop B Inventory Hub
Route::prefix('web')->middleware('api.key')->group(function () {
    // Property Types
    Route::get('/property-types', [App\Http\Controllers\Web\PropertyTypeController::class, 'index']);

    // Properties
    Route::get('/properties', [App\Http\Controllers\Web\PropertyController::class, 'index']);
    Route::get('/properties/{id}', [App\Http\Controllers\Web\PropertyController::class, 'show']);
    Route::get('/properties/{id}/availability', [App\Http\Controllers\Web\PropertyController::class, 'availability']);

    // Bookings
    Route::post('/bookings', [App\Http\Controllers\Web\BookingController::class, 'store']);
    Route::put('/bookings/{id}/cancel', [App\Http\Controllers\Web\BookingController::class, 'cancel']);
});
