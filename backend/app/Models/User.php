<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;

    // A tabela usa apenas created_at (sem updated_at)
    public const UPDATED_AT = null;

    protected $fillable = ['username', 'password', 'name'];
    protected $hidden   = ['password'];

    protected function casts(): array
    {
        return ['password' => 'hashed'];
    }
}
