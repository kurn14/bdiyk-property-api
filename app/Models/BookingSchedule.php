<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookingSchedule extends Model
{
    protected $fillable = [
        'booking_id',
        'start_time',
        'end_time',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    protected static function booted()
    {
        $syncDates = function ($schedule) {
            $booking = $schedule->booking;
            if ($booking) {
                // Update start_date & end_date from min & max schedules
                $booking->withoutEvents(function () use ($booking) {
                    $booking->update([
                        'start_date' => $booking->schedules()->min('start_time'),
                        'end_date' => $booking->schedules()->max('end_time'),
                    ]);
                });
            }
        };

        static::saved($syncDates);
        static::deleted($syncDates);
    }
}
