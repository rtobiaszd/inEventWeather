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
