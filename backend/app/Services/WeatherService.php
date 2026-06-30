<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class WeatherService
{
    private string $apiKey;
    private string $baseUrl = 'https://api.openweathermap.org';

    // TTLs em segundos
    private const TTL_GEO      = 86400; // coordenadas mudam nunca → 24h
    private const TTL_CURRENT  = 600;   // clima atual → 10 min
    private const TTL_FORECAST = 1800;  // previsão 5d → 30 min
    private const TTL_AIR      = 1800;  // qualidade do ar → 30 min

    public function __construct()
    {
        $this->apiKey = config('services.openweather.key', env('OPENWEATHER_API_KEY', ''));

        if (empty($this->apiKey)) {
            throw new \RuntimeException('OPENWEATHER_API_KEY não configurada.');
        }
    }

    private function request(string $url): array
    {
        $context  = stream_context_create(['http' => ['method' => 'GET', 'timeout' => 10, 'ignore_errors' => true, 'header' => "User-Agent: EventWeatherDashboard/2.0\r\n"]]);
        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            throw new \RuntimeException('Não foi possível conectar à API do OpenWeather.');
        }

        $data = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Resposta inválida da API do OpenWeather.');
        }

        if (is_array($data) && isset($data['cod'])) {
            $cod = (string) $data['cod'];
            if ($cod !== '200' && $cod !== '0') {
                throw new \RuntimeException($data['message'] ?? "Erro {$cod} na API do OpenWeather");
            }
        }

        return $data;
    }

    private function buildUrl(string $endpoint, array $params): string
    {
        return $this->baseUrl . $endpoint . '?' . http_build_query($params);
    }

    private function cacheKey(string $prefix, string $city, string $country): string
    {
        return "ew:{$prefix}:" . strtolower("{$city}:{$country}");
    }

    public function geocode(string $city, string $country = 'BR'): array
    {
        return Cache::remember($this->cacheKey('geo', $city, $country), self::TTL_GEO, function () use ($city, $country) {
            $data = $this->request($this->buildUrl('/geo/1.0/direct', [
                'q'     => $city . ',' . strtoupper($country),
                'limit' => 1,
                'appid' => $this->apiKey,
            ]));

            if (empty($data)) {
                throw new \RuntimeException("Cidade não encontrada: {$city}, {$country}");
            }

            return ['lat' => (float) $data[0]['lat'], 'lon' => (float) $data[0]['lon'], 'name' => $data[0]['name'] ?? $city];
        });
    }

    public function getCurrentWeather(string $city, string $country = 'BR'): array
    {
        return Cache::remember($this->cacheKey('current', $city, $country), self::TTL_CURRENT, function () use ($city, $country) {
            $coords = $this->geocode($city, $country);
            return $this->request($this->buildUrl('/data/2.5/weather', [
                'lat' => $coords['lat'], 'lon' => $coords['lon'],
                'appid' => $this->apiKey, 'units' => 'metric', 'lang' => 'pt_br',
            ]));
        });
    }

    public function getForecast(string $city, string $country = 'BR'): array
    {
        return Cache::remember($this->cacheKey('forecast', $city, $country), self::TTL_FORECAST, function () use ($city, $country) {
            $coords = $this->geocode($city, $country);
            return $this->request($this->buildUrl('/data/2.5/forecast', [
                'lat' => $coords['lat'], 'lon' => $coords['lon'],
                'appid' => $this->apiKey, 'units' => 'metric', 'lang' => 'pt_br', 'cnt' => 40,
            ]));
        });
    }

    public function getAirQuality(string $city, string $country = 'BR'): array
    {
        return Cache::remember($this->cacheKey('air', $city, $country), self::TTL_AIR, function () use ($city, $country) {
            $coords = $this->geocode($city, $country);
            return $this->request($this->buildUrl('/data/2.5/air_pollution', [
                'lat' => $coords['lat'], 'lon' => $coords['lon'], 'appid' => $this->apiKey,
            ]));
        });
    }

    /**
     * Retorna previsão climática para o dia/hora do evento.
     * — Evento nos próximos 5 dias: entrada mais próxima do forecast real.
     * — Evento além de 5 dias ou no passado: média calculada dos 5 dias disponíveis.
     */
    public function getEventForecast(string $city, string $country, string $eventDate, string $eventTime): array
    {
        $forecast = $this->getForecast($city, $country); // já cacheado
        $list     = $forecast['list'] ?? [];

        if (empty($list)) {
            throw new \RuntimeException('Previsão não disponível para a cidade do evento.');
        }

        $eventTs  = strtotime("{$eventDate} {$eventTime}");
        $nowTs    = time();
        $diffDays = ($eventTs - $nowTs) / 86400;

        if ($eventTs < $nowTs || $diffDays > 5) {
            return $this->calculateAverageForecast($list, $eventTs < $nowTs);
        }

        $closest = null;
        $minDiff = PHP_INT_MAX;
        foreach ($list as $entry) {
            $diff = abs(($entry['dt'] ?? 0) - $eventTs);
            if ($diff < $minDiff) {
                $minDiff = $diff;
                $closest = $entry;
            }
        }

        return [
            'available'           => true,
            'type'                => 'forecast',
            'note'                => 'Previsão baseada em dados reais para o horário do evento',
            'temperature'         => round((float) ($closest['main']['temp'] ?? 0), 1),
            'feels_like'          => round((float) ($closest['main']['feels_like'] ?? 0), 1),
            'humidity'            => (int) ($closest['main']['humidity'] ?? 0),
            'wind_speed'          => round((float) ($closest['wind']['speed'] ?? 0), 1),
            'rain_probability'    => (int) round(((float) ($closest['pop'] ?? 0)) * 100),
            'weather_main'        => $closest['weather'][0]['main'] ?? '',
            'weather_description' => $closest['weather'][0]['description'] ?? '',
            'icon'                => $closest['weather'][0]['icon'] ?? '01d',
            'forecast_time'       => $closest['dt_txt'] ?? null,
        ];
    }

    private function calculateAverageForecast(array $list, bool $isPast = false): array
    {
        $temps      = [];
        $feels      = [];
        $humidities = [];
        $winds      = [];
        $rains      = [];
        $conditions = [];

        foreach ($list as $entry) {
            $temps[]      = (float) ($entry['main']['temp'] ?? 20);
            $feels[]      = (float) ($entry['main']['feels_like'] ?? 20);
            $humidities[] = (int)   ($entry['main']['humidity'] ?? 50);
            $winds[]      = (float) ($entry['wind']['speed'] ?? 0);
            $rains[]      = ((float) ($entry['pop'] ?? 0)) * 100;

            $main              = $entry['weather'][0]['main'] ?? 'Clear';
            $conditions[$main] = ($conditions[$main] ?? 0) + 1;
        }

        arsort($conditions);
        $dominant = (string) array_key_first($conditions);
        $count    = count($list);

        $note = $isPast
            ? 'Evento no passado — estimativa baseada no clima atual da cidade'
            : 'Evento além de 5 dias — estimativa baseada na média climática recente da cidade';

        return [
            'available'           => true,
            'type'                => 'estimate',
            'note'                => $note,
            'temperature'         => round(array_sum($temps) / $count, 1),
            'feels_like'          => round(array_sum($feels) / $count, 1),
            'humidity'            => (int) round(array_sum($humidities) / $count),
            'wind_speed'          => round(array_sum($winds) / $count, 1),
            'rain_probability'    => (int) round(array_sum($rains) / $count),
            'weather_main'        => $dominant,
            'weather_description' => 'estimativa baseada em média histórica recente',
            'icon'                => '01d',
            'forecast_time'       => null,
        ];
    }
}
