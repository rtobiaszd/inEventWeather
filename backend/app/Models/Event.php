<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    protected $fillable = [
        'name', 'city', 'country',
        'event_date', 'event_time',
        'type', 'expected_audience', 'description',
        'latitude', 'longitude',
    ];

    protected function casts(): array
    {
        return [
            'expected_audience' => 'integer',
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }
}
