<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PropertyTypeController;
use App\Http\Controllers\Api\PropertyController;
use App\Http\Controllers\Api\BookingController;

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

// API V1 - Laptop B Inventory Hub
Route::prefix('v1')->middleware('api.key')->group(function () {
    // Property Types
    Route::get('/property-types', [App\Http\Controllers\Api\V1\PropertyTypeController::class, 'index']);

    // Properties
    Route::get('/properties', [App\Http\Controllers\Api\V1\PropertyController::class, 'index']);
    Route::get('/properties/{id}', [App\Http\Controllers\Api\V1\PropertyController::class, 'show']);
    Route::get('/properties/{id}/availability', [App\Http\Controllers\Api\V1\PropertyController::class, 'availability']);

    // Bookings
    Route::post('/bookings', [App\Http\Controllers\Api\V1\BookingController::class, 'store']);
    Route::put('/bookings/{id}/cancel', [App\Http\Controllers\Api\V1\BookingController::class, 'cancel']);
});
