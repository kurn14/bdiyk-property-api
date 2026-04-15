<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PropertyTypeController;
use App\Http\Controllers\PropertyController;
use App\Http\Controllers\BookingController;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::apiResource('property-types', PropertyTypeController::class);
    Route::apiResource('properties', PropertyController::class);

    // Booking Monitoring & Calendar
    Route::get('/bookings/monitoring', [BookingController::class, 'monitoring']);
    Route::get('/bookings/calendar', [BookingController::class, 'calendar']);
    Route::get('/bookings/calendar/{date}', [BookingController::class, 'calendarDetail']);

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
    Route::put('/bookings/{id}/payment', [App\Http\Controllers\Web\BookingController::class, 'payment']);
    Route::put('/bookings/{code}/cancel', [App\Http\Controllers\Web\BookingController::class, 'cancel']);
});

// Catch-all route for Flutter Web
Route::get('/{any?}', function () {
    return file_get_contents(public_path('index.html'));
})->where('any', '.*');
