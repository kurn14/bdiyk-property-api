<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

use App\Models\Booking;

\Illuminate\Support\Facades\Schedule::call(function () {
    Booking::where('status', 'booked')
        ->whereNotNull('payment_time_limit')
        ->where('payment_time_limit', '<', now())
        ->update(['status' => 'cancelled']);
})->everyMinute();
