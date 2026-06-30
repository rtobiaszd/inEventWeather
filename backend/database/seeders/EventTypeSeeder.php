<?php

namespace Database\Seeders;

use App\Models\EventType;
use Illuminate\Database\Seeder;

class EventTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['slug' => 'indoor',   'name' => 'Indoor',   'icon' => '🏛', 'description' => 'Evento em local fechado/coberto', 'is_system' => true],
            ['slug' => 'outdoor',  'name' => 'Outdoor',  'icon' => '🌤', 'description' => 'Evento ao ar livre',               'is_system' => true],
        ];

        foreach ($types as $type) {
            EventType::updateOrCreate(['slug' => $type['slug']], $type);
        }
    }
}
