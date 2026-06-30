<?php

namespace App\Http\Controllers;

use App\Models\WeatherHistory;
use App\Services\WeatherService;
use App\Services\WeatherRiskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WeatherController extends Controller
{
    public function __construct(
        private WeatherService     $weatherService,
        private WeatherRiskService $riskService,
    ) {}

    public function search(Request $request): JsonResponse
    {
        $request->validate(['city' => 'required|string']);

        $city    = trim($request->city);
        $country = strtoupper(trim($request->input('country', 'BR')));

        try {
            $weather  = $this->weatherService->getCurrentWeather($city, $country);
            $forecast = $this->weatherService->getForecast($city, $country);
            $airData  = $this->weatherService->getAirQuality($city, $country);
            $aqi      = (int) ($airData['list'][0]['main']['aqi'] ?? 1);
            $risk     = $this->riskService->analyze($weather, $forecast, $aqi);

            WeatherHistory::create([
                'city'                => $city,
                'country'             => $country,
                'temperature'         => $weather['main']['temp'] ?? null,
                'feels_like'          => $weather['main']['feels_like'] ?? null,
                'humidity'            => $weather['main']['humidity'] ?? null,
                'wind_speed'          => $weather['wind']['speed'] ?? null,
                'weather_main'        => $weather['weather'][0]['main'] ?? null,
                'weather_description' => $weather['weather'][0]['description'] ?? null,
                'aqi'                 => $aqi,
            ]);

            return $this->success([
                'city'    => $city,
                'country' => $country,
                'coordinates' => [
                    'latitude' => (float) ($weather['coord']['lat'] ?? 0),
                    'longitude' => (float) ($weather['coord']['lon'] ?? 0),
                ],
                'current' => [
                    'temperature'         => round((float) ($weather['main']['temp'] ?? 0), 1),
                    'feels_like'          => round((float) ($weather['main']['feels_like'] ?? 0), 1),
                    'temp_min'            => round((float) ($weather['main']['temp_min'] ?? 0), 1),
                    'temp_max'            => round((float) ($weather['main']['temp_max'] ?? 0), 1),
                    'humidity'            => (int) ($weather['main']['humidity'] ?? 0),
                    'pressure'            => (int) ($weather['main']['pressure'] ?? 0),
                    'wind_speed'          => round((float) ($weather['wind']['speed'] ?? 0) * 3.6, 1),
                    'wind_direction'      => (int) ($weather['wind']['deg'] ?? 0),
                    'visibility'          => round((float) ($weather['visibility'] ?? 10000) / 1000, 1),
                    'weather_main'        => $weather['weather'][0]['main'] ?? '',
                    'weather_description' => $weather['weather'][0]['description'] ?? '',
                    'icon'                => $weather['weather'][0]['icon'] ?? '01d',
                    'clouds'              => (int) ($weather['clouds']['all'] ?? 0),
                ],
                'aqi'  => $aqi,
                'risk' => $risk,
            ]);
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    public function forecast(Request $request): JsonResponse
    {
        $request->validate(['city' => 'required|string']);

        $city    = trim($request->city);
        $country = strtoupper(trim($request->input('country', 'BR')));

        try {
            $forecast = $this->weatherService->getForecast($city, $country);

            $items = array_map(fn (array $item) => [
                'datetime'            => $item['dt_txt'],
                'timestamp'           => $item['dt'],
                'temperature'         => round((float) ($item['main']['temp'] ?? 0), 1),
                'feels_like'          => round((float) ($item['main']['feels_like'] ?? 0), 1),
                'temp_min'            => round((float) ($item['main']['temp_min'] ?? 0), 1),
                'temp_max'            => round((float) ($item['main']['temp_max'] ?? 0), 1),
                'humidity'            => (int) ($item['main']['humidity'] ?? 0),
                'wind_speed'          => round((float) ($item['wind']['speed'] ?? 0) * 3.6, 1),
                'weather_main'        => $item['weather'][0]['main'] ?? '',
                'weather_description' => $item['weather'][0]['description'] ?? '',
                'icon'                => $item['weather'][0]['icon'] ?? '01d',
                'rain_probability'    => (int) round(($item['pop'] ?? 0) * 100),
                'rain_amount'         => round((float) ($item['rain']['3h'] ?? 0), 1),
            ], $forecast['list'] ?? []);

            return $this->success(['city' => $city, 'country' => $country, 'forecast' => $items]);
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    public function bestDates(Request $request): JsonResponse
    {
        $request->validate([
            'city'    => 'required|string|max:100',
            'country' => 'nullable|string|max:10',
        ]);

        $city    = trim($request->city);
        $country = strtoupper(trim($request->input('country', 'BR')));

        try {
            $forecast = $this->weatherService->getForecast($city, $country);
            $list     = $forecast['list'] ?? [];

            if (empty($list)) {
                return $this->error('Previsão não disponível para esta cidade.', 422);
            }

            // Agrupa previsão por dia
            $days = [];
            foreach ($list as $item) {
                $date = explode(' ', $item['dt_txt'])[0];
                if (!isset($days[$date])) {
                    $days[$date] = [];
                }
                $days[$date][] = $item;
            }

            // Calcula score para cada dia (0 = ideal, 100 = péssimo)
            $results = [];
            $now = now()->startOfDay();
            foreach ($days as $date => $items) {
                $dateObj = \Carbon\Carbon::parse($date);
                if ($dateObj->lessThan($now) || $dateObj->greaterThan($now->copy()->addDays(14))) {
                    continue;
                }

                $temps      = [];
                $rains      = [];
                $winds      = [];
                $humidities = [];

                foreach ($items as $item) {
                    $temps[]      = (float) ($item['main']['temp'] ?? 25);
                    $rains[]      = (float) ($item['pop'] ?? 0) * 100;
                    $winds[]      = round((float) ($item['wind']['speed'] ?? 0) * 3.6, 1);
                    $humidities[] = (int) ($item['main']['humidity'] ?? 50);
                }

                $avgTemp      = array_sum($temps) / count($temps);
                $maxRain      = max($rains);
                $maxWind      = max($winds);
                $avgHumidity  = array_sum($humidities) / count($humidities);

                // Score calculation (0-100, lower is better)
                $score = 0;
                $score += $this->scoreRain($maxRain);
                $score += $this->scoreWind($maxWind);
                $score += $this->scoreTemperature($avgTemp);
                $score += $this->scoreHumidity($avgHumidity);
                $score = min(100, $score);

                $results[] = [
                    'date'            => $date,
                    'weekday'         => $dateObj->locale('pt_BR')->dayName,
                    'score'           => $score,
                    'status'          => $score <= 20 ? 'IDEAL' : ($score <= 50 ? 'FAVORABLE' : ($score <= 70 ? 'CAUTION' : 'AVOID')),
                    'avg_temperature' => round($avgTemp, 1),
                    'max_rain'        => round($maxRain),
                    'max_wind'        => $maxWind,
                    'avg_humidity'    => round($avgHumidity),
                    'conditions'      => $this->summarizeConditions($items),
                ];
            }

            usort($results, fn ($a, $b) => $a['score'] <=> $b['score']);

            return $this->success([
                'city'      => $city,
                'country'   => $country,
                'city_name' => $city,
                'dates'     => $results,
                'best_date' => $results[0] ?? null,
            ]);
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    private function scoreRain(float $maxRain): int
    {
        if ($maxRain >= 80) return 40;
        if ($maxRain >= 60) return 30;
        if ($maxRain >= 40) return 20;
        if ($maxRain >= 20) return 10;
        return 0;
    }

    private function scoreWind(float $maxWind): int
    {
        if ($maxWind > 60) return 30;
        if ($maxWind > 35) return 25;
        if ($maxWind > 20) return 15;
        if ($maxWind > 12) return 8;
        return 0;
    }

    private function scoreTemperature(float $avgTemp): int
    {
        if ($avgTemp > 38 || $avgTemp < 5) return 35;
        if ($avgTemp > 32 || $avgTemp < 10) return 25;
        if ($avgTemp > 28 || $avgTemp < 15) return 15;
        if ($avgTemp > 25) return 5;
        return 0;
    }

    private function scoreHumidity(float $avgHumidity): int
    {
        if ($avgHumidity > 90) return 15;
        if ($avgHumidity > 80) return 10;
        if ($avgHumidity > 70) return 5;
        return 0;
    }

    private function summarizeConditions(array $items): string
    {
        $conditions = [];
        foreach ($items as $item) {
            $main = $item['weather'][0]['main'] ?? '';
            if ($main) {
                $conditions[$main] = ($conditions[$main] ?? 0) + 1;
            }
        }
        arsort($conditions);
        $dominant = array_key_first($conditions);
        $translations = [
            'Clear' => 'predominantemente limpo',
            'Clouds' => 'nublado',
            'Rain' => 'chuvoso',
            'Drizzle' => 'garoa',
            'Thunderstorm' => 'tempestade',
            'Snow' => 'neve',
            'Mist' => 'névoa',
            'Fog' => 'nevoeiro',
            'Haze' => 'neblina',
        ];
        return $translations[$dominant] ?? $dominant ?? 'variado';
    }

    public function airQuality(Request $request): JsonResponse
    {
        $request->validate(['city' => 'required|string']);

        $city    = trim($request->city);
        $country = strtoupper(trim($request->input('country', 'BR')));

        try {
            $data   = $this->weatherService->getAirQuality($city, $country);
            $item   = $data['list'][0] ?? [];
            $aqi    = (int) ($item['main']['aqi'] ?? 1);
            $labels = [1 => 'Boa', 2 => 'Razoável', 3 => 'Moderada', 4 => 'Ruim', 5 => 'Muito Ruim'];

            return $this->success([
                'city'       => $city,
                'country'    => $country,
                'aqi'        => $aqi,
                'aqi_label'  => $labels[$aqi] ?? 'Desconhecida',
                'components' => $item['components'] ?? [],
            ]);
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 422);
        }
    }
}
