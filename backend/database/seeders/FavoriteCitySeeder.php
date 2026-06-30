<?php

namespace Database\Seeders;

use App\Models\FavoriteCity;
use Illuminate\Database\Seeder;

class FavoriteCitySeeder extends Seeder
{
    public function run(): void
    {
        $cities = [
            ['city' => 'São Paulo',     'country' => 'BR'],
            ['city' => 'Curitiba',      'country' => 'BR'],
            ['city' => 'Rio de Janeiro','country' => 'BR'],
            ['city' => 'Campinas',      'country' => 'BR'],
            ['city' => 'Porto Alegre',  'country' => 'BR'],
        ];

        foreach ($cities as $city) {
            FavoriteCity::firstOrCreate($city);
        }
    }
}
