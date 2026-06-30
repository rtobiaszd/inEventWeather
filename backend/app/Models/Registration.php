<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Registration extends Model
{
    protected $fillable = [
        'event_id',
        'name', 'email', 'phone', 'company',
        'status', 'checkin_token', 'checked_in_at',
    ];

    protected function casts(): array
    {
        return [
            'checked_in_at' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    protected static function booted(): void
    {
        static::creating(function (Registration $registration) {
            if (empty($registration->checkin_token)) {
                $registration->checkin_token = Str::random(32);
            }
        });
    }
}
