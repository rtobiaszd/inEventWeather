<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Speaker extends Model
{
    protected $fillable = [
        'name', 'email', 'bio', 'avatar_url',
        'company', 'role_title', 'expertise',
        'social_linkedin', 'social_twitter', 'website',
    ];

    protected function casts(): array
    {
        return [];
    }

    public function events(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'event_speaker')
            ->withPivot('is_featured', 'sort_order')
            ->withTimestamps();
    }

    public function sessions(): BelongsToMany
    {
        return $this->belongsToMany(EventSession::class, 'session_speaker');
    }
}
