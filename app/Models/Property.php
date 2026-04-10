<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Property extends Model
{
    protected $fillable = [
        'property_type_id', 'name', 'description', 'capacity', 'status'
    ];

    public function type()
    {
        return $this->belongsTo(PropertyType::class, 'property_type_id');
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }
}
