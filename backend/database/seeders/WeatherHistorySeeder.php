<?php

namespace Database\Seeders;

use App\Models\WeatherHistory;
use Illuminate\Database\Seeder;

class WeatherHistorySeeder extends Seeder
{
    public function run(): void
    {
        // Insere dados de exemplo apenas se a tabela estiver vazia
        if (WeatherHistory::exists()) {
            return;
        }

        WeatherHistory::insert([
            ['city' => 'São Paulo',     'country' => 'BR', 'temperature' => 24.50, 'feels_like' => 25.10, 'humidity' => 72, 'wind_speed' => 3.60, 'weather_main' => 'Clouds', 'weather_description' => 'nublado',              'aqi' => 2],
            ['city' => 'Curitiba',      'country' => 'BR', 'temperature' => 18.30, 'feels_like' => 16.80, 'humidity' => 80, 'wind_speed' => 7.20, 'weather_main' => 'Rain',   'weather_description' => 'chuva leve',           'aqi' => 1],
            ['city' => 'Rio de Janeiro','country' => 'BR', 'temperature' => 31.20, 'feels_like' => 35.50, 'humidity' => 65, 'wind_speed' => 5.40, 'weather_main' => 'Clear',  'weather_description' => 'céu limpo',            'aqi' => 2],
            ['city' => 'Campinas',      'country' => 'BR', 'temperature' => 27.80, 'feels_like' => 29.10, 'humidity' => 68, 'wind_speed' => 4.10, 'weather_main' => 'Clouds', 'weather_description' => 'parcialmente nublado', 'aqi' => 2],
            ['city' => 'Porto Alegre',  'country' => 'BR', 'temperature' => 22.10, 'feels_like' => 21.50, 'humidity' => 75, 'wind_speed' => 9.00, 'weather_main' => 'Clear',  'weather_description' => 'céu limpo',            'aqi' => 1],
        ]);
    }
}
