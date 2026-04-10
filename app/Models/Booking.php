<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    protected $fillable = [
        'booking_code', 'property_id', 'property_type_id', 'user_id', 'contact_name', 'contact_email', 'contact_phone', 
        'institution', 'start_date', 'end_date', 'status', 'external_reference'
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($booking) {
            if (empty($booking->booking_code)) {
                $booking->booking_code = self::generateBookingCode($booking);
            }
        });
    }

    public static function generateBookingCode($booking)
    {
        $prefix = 'XX';
        
        $property = $booking->property;
        if ($property) {
            $typeId = $property->property_type_id;
            $prefixes = [
                1 => 'CL',
                2 => 'MR',
                3 => 'VP',
                4 => 'R2',
                5 => 'R3',
            ];
            $prefix = $prefixes[$typeId] ?? 'XX';
        } elseif ($booking->property_type_id) {
            $prefixes = [
                1 => 'CL',
                2 => 'MR',
                3 => 'VP',
                4 => 'R2',
                5 => 'R3',
            ];
            $prefix = $prefixes[$booking->property_type_id] ?? 'XX';
        }

        do {
            $code = $prefix . strtoupper(\Illuminate\Support\Str::random(4));
        } while (self::where('booking_code', $code)->exists());

        return $code;
    }

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function propertyType()
    {
        return $this->belongsTo(PropertyType::class, 'property_type_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function type()
    {
        return $this->hasOneThrough(
            PropertyType::class,
            Property::class,
            'id', // Foreign key on properties table...
            'id', // Foreign key on property_types table...
            'property_id', // Local key on bookings table...
            'property_type_id' // Local key on properties table...
        );
    }
}
