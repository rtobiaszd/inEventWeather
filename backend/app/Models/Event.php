<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('event_date', '>=', now()->toDateString())
            ->orderBy('event_date');
    }

    public function scopePast(Builder $query): Builder
    {
        return $query->where('event_date', '<', now()->toDateString());
    }

    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeByCity(Builder $query, string $city): Builder
    {
        return $query->where('city', $city);
    }

    public function scopeWithoutCoordinates(Builder $query): Builder
    {
        return $query->whereNull('latitude')->orWhereNull('longitude');
    }
}
