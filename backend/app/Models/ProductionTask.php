<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionTask extends Model
{
    protected $fillable = [
        'event_id',
        'title', 'description',
        'assigned_to', 'due_date',
        'priority', 'status', 'category',
        'order', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'due_date'     => 'date:Y-m-d',
            'completed_at' => 'datetime',
            'order'        => 'integer',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
