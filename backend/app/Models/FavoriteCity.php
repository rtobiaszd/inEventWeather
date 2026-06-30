<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FavoriteCity extends Model
{
    protected $table   = 'favorite_cities';
    const UPDATED_AT   = null;

    protected $fillable = ['city', 'country'];
}
