<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;

    protected $fillable = ['username', 'password', 'name', 'role', 'permissions', 'is_active'];
    protected $hidden   = ['password'];

    protected function casts(): array
    {
        return [
            'password'    => 'hashed',
            'permissions' => 'array',
            'is_active'   => 'boolean',
        ];
    }
}
