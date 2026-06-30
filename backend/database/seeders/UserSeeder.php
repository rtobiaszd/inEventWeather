<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // updateOrCreate usa o cast 'hashed' do modelo — não precisa de Hash::make()
        User::updateOrCreate(
            ['username' => 'admin'],
            ['password' => 'admin123', 'name' => 'Administrador']
        );
    }
}
