<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['username' => 'admin'],
            ['password' => 'admin123', 'name' => 'Administrador', 'role' => 'admin', 'is_active' => true]
        );
    }
}
