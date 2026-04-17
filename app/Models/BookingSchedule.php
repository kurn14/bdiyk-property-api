<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookingSchedule extends Model
{
    protected $fillable = [
        'booking_item_id',
        'start_time',
        'end_time',
    ];

    public function item()
    {
        return $this->belongsTo(BookingItem::class, 'booking_item_id');
    }

    protected static function booted()
    {
        $syncDates = function ($schedule) {
            $item = $schedule->item;
            if ($item && $item->booking) {
                $item->booking->syncDates();
            }
        };

        static::saved($syncDates);
        static::deleted($syncDates);
    }
}
