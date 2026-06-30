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
        'status', 'budget', 'revenue', 'ticket_price',
        'organizer', 'organizer_contact', 'venue',
        'end_date', 'end_time', 'banner_url', 'tags', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'expected_audience' => 'integer',
            'latitude' => 'float',
            'longitude' => 'float',
            'budget' => 'float',
            'revenue' => 'float',
            'ticket_price' => 'float',
            'event_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
        ];
    }
}
