<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    protected $fillable = [
        'booking_code', 'user_id', 'contact_name', 'contact_email', 'contact_phone',
        'institution', 'status', 'payment_time_limit', 'start_date', 'end_date'
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($booking) {
            if (empty($booking->booking_code)) {
                $booking->booking_code = self::generateBookingCode();
            }
        });
    }

    public static function generateBookingCode()
    {
        do {
            $code = strtoupper(\Illuminate\Support\Str::random(6));
        } while (self::where('booking_code', $code)->exists());

        return $code;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(BookingItem::class);
    }

    /**
     * Sync start_date and end_date from all schedules across all items.
     */
    public function syncDates()
    {
        $minStart = BookingSchedule::whereHas('item', function ($q) {
            $q->where('booking_id', $this->id);
        })->min('start_time');

        $maxEnd = BookingSchedule::whereHas('item', function ($q) {
            $q->where('booking_id', $this->id);
        })->max('end_time');

        $this->withoutEvents(function () use ($minStart, $maxEnd) {
            $this->update([
                'start_date' => $minStart,
                'end_date'   => $maxEnd,
            ]);
        });
    }
}
