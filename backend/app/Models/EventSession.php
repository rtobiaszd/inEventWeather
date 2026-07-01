<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class EventSession extends Model
{
    protected $table = 'event_sessions';

    protected $fillable = [
        'event_id',
        'name', 'description',
        'speaker_name', 'speaker_bio',
        'date', 'start_time', 'end_time',
        'room', 'type', 'capacity', 'status',
        'outdoor_suitable', 'weather_optimized_at',
        'actual_start_time', 'actual_end_time',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'capacity' => 'integer',
            'outdoor_suitable' => 'boolean',
            'weather_optimized_at' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function speakers(): BelongsToMany
    {
        return $this->belongsToMany(Speaker::class, 'session_speaker');
    }
}
