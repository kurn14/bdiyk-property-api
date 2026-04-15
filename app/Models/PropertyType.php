<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PropertyType extends Model
{
    protected $fillable = ['name', 'description', 'is_continuous_booking'];

    protected $casts = [
        'is_continuous_booking' => 'boolean',
    ];

    public function properties()
    {
        return $this->hasMany(Property::class);
    }
}
