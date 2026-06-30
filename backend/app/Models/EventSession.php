<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventSession extends Model
{
    protected $table = 'event_sessions';

    protected $fillable = [
        'event_id',
        'name', 'description',
        'speaker_name', 'speaker_bio',
        'date', 'start_time', 'end_time',
        'room', 'type', 'capacity', 'status',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'capacity' => 'integer',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
