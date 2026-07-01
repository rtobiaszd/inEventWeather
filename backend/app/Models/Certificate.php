<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Certificate extends Model
{
    protected $fillable = [
        'event_id',
        'registration_id',
        'hash',
        'template_id',
        'metadata',
        'issued_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'issued_at' => 'datetime',
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

    public static function generateHash(int $registrationId, int $eventId): string
    {
        return hash('sha256', sprintf('%d|%d|%s', $registrationId, $eventId, config('app.key')));
    }

    public function getVerifyUrlAttribute(): string
    {
        return url("/certificates/verify/{$this->hash}");
    }
}
