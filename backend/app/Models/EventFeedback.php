<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventFeedback extends Model
{
    protected $table = 'event_feedback';

    public $timestamps = false;

    protected $fillable = [
        'event_id',
        'registration_id',
        'nps_score',
        'comment',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'nps_score' => 'integer',
            'submitted_at' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class);
    }
}
