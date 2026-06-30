<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WeatherHistory extends Model
{
    protected $table    = 'weather_history';
    const UPDATED_AT    = null;

    protected $fillable = [
        'city', 'country',
        'temperature', 'feels_like', 'humidity', 'wind_speed',
        'weather_main', 'weather_description', 'aqi',
    ];
}
