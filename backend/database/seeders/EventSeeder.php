<?php

namespace Database\Seeders;

use App\Models\Event;
use Illuminate\Database\Seeder;

class EventSeeder extends Seeder
{
    public function run(): void
    {
        $events = [
            ['name' => 'Festival de Música de Verão',     'city' => 'São Paulo',      'event_date' => '2026-08-15', 'event_time' => '18:00', 'type' => 'outdoor', 'expected_audience' => 5000,  'description' => 'Festival de música ao ar livre com artistas nacionais e internacionais.'],
            ['name' => 'Congresso de Tecnologia 2026',    'city' => 'Campinas',        'event_date' => '2026-09-10', 'event_time' => '09:00', 'type' => 'indoor',  'expected_audience' => 800,   'description' => 'Evento focado em inovação, IA e desenvolvimento de software.'],
            ['name' => 'Maratona de São Paulo',           'city' => 'São Paulo',       'event_date' => '2026-09-20', 'event_time' => '07:00', 'type' => 'outdoor', 'expected_audience' => 15000, 'description' => 'Corrida de 42km pelas principais avenidas da cidade.'],
            ['name' => 'Exposição de Arte Contemporânea', 'city' => 'Curitiba',        'event_date' => '2026-07-22', 'event_time' => '10:00', 'type' => 'indoor',  'expected_audience' => 300,   'description' => 'Mostra de artistas paranaenses e nacionais com foco em arte digital.'],
            ['name' => 'Show de Rock ao Ar Livre',        'city' => 'Rio de Janeiro',  'event_date' => '2026-08-30', 'event_time' => '19:00', 'type' => 'outdoor', 'expected_audience' => 12000, 'description' => 'Show com as melhores bandas de rock do Brasil.'],
            ['name' => 'Feira Gastronômica Internacional','city' => 'Porto Alegre',    'event_date' => '2026-10-05', 'event_time' => '11:00', 'type' => 'outdoor', 'expected_audience' => 3000,  'description' => 'Maior feira de gastronomia do Sul do Brasil.'],
            ['name' => 'Hackathon de Inovação',           'city' => 'Florianópolis',   'event_date' => '2026-07-18', 'event_time' => '08:00', 'type' => 'indoor',  'expected_audience' => 200,   'description' => 'Maratona de programação de 48 horas com foco em cidades inteligentes.'],
        ];

        foreach ($events as $event) {
            Event::firstOrCreate(
                ['name' => $event['name']],
                array_merge($event, ['country' => 'BR'])
            );
        }
    }
}
