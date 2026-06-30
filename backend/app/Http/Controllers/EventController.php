<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\FavoriteCity;
use App\Services\WeatherService;
use App\Services\WeatherRiskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventController extends Controller
{
    public function __construct(
        private WeatherService $weatherService,
    ) {}

    public function index(): JsonResponse
    {
        $events = Event::withCount('registrations')->orderBy('event_date')->get();

        return $this->success($events);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'              => 'required|string|max:255',
            'city'              => 'required|string|max:100',
            'country'           => 'required|string|max:10',
            'event_date'        => 'required|date_format:Y-m-d',
            'event_time'        => 'required',
            'type'              => 'required|string|max:50',
            'expected_audience' => 'nullable|integer|min:0',
            'description'       => 'nullable|string',
            'latitude'          => 'nullable|numeric|between:-90,90|required_with:longitude',
            'longitude'         => 'nullable|numeric|between:-180,180|required_with:latitude',
            'status'            => 'nullable|string|in:planned,confirmed,in_progress,completed,cancelled',
            'budget'            => 'nullable|numeric|min:0',
            'revenue'           => 'nullable|numeric|min:0',
            'ticket_price'      => 'nullable|numeric|min:0',
            'organizer'         => 'nullable|string|max:255',
            'organizer_contact' => 'nullable|string|max:255',
            'venue'             => 'nullable|string|max:255',
            'end_date'          => 'nullable|date_format:Y-m-d',
            'end_time'          => 'nullable',
            'banner_url'        => 'nullable|string|max:500',
            'tags'              => 'nullable|string|max:500',
            'notes'             => 'nullable|string',
        ]);

        $data = $this->resolveCoordinates($data);
        $event = Event::create($data);

        // Auto-adiciona a cidade aos favoritos (silencioso se já existir)
        try {
            FavoriteCity::firstOrCreate(
                ['city' => $data['city'], 'country' => strtoupper($data['country'])],
            );
        } catch (\Throwable) {
            // Não crítico — não falha a criação do evento
        }

        return $this->success($event, 201);
    }

    public function riskAlerts(): JsonResponse
    {
        try {
            $events = Event::upcoming()
                ->where('event_date', '<=', now()->addDays(14)->toDateString())
                ->get(['id', 'name', 'city', 'country', 'event_date', 'event_time', 'type', 'status', 'venue', 'latitude', 'longitude']);

            if ($events->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'data'    => [],
                    'meta'    => ['total' => 0, 'high_risk' => 0, 'medium_risk' => 0],
                ]);
            }

            $riskService    = app(WeatherRiskService::class);
            $weatherService = $this->weatherService;

            $cityWeather = [];
            foreach ($events as $event) {
                $key = mb_strtolower($event->city . '|' . $event->country);
                if (isset($cityWeather[$key])) continue;
                try {
                    $current  = $weatherService->getCurrentWeather($event->city, $event->country);
                    $forecast = $weatherService->getForecast($event->city, $event->country);
                    $airData  = $weatherService->getAirQuality($event->city, $event->country);
                    $aqi      = (int) ($airData['list'][0]['main']['aqi'] ?? 1);
                    $cityWeather[$key] = compact('current', 'forecast', 'aqi');
                } catch (\Throwable) {
                    $cityWeather[$key] = null;
                }
            }

            $alerts     = [];
            $highRisk   = 0;
            $mediumRisk = 0;

            foreach ($events as $event) {
                $key     = mb_strtolower($event->city . '|' . $event->country);
                $weather = $cityWeather[$key] ?? null;

                $alert = [
                    'event_id'      => $event->id,
                    'event_name'    => $event->name,
                    'city'          => $event->city,
                    'country'       => $event->country,
                    'event_date'    => (string) $event->event_date,
                    'event_time'    => $event->event_time,
                    'type'          => $event->type,
                    'status'        => $event->status,
                    'venue'         => $event->venue,
                    'latitude'      => $event->latitude,
                    'longitude'     => $event->longitude,
                ];

                if ($weather) {
                    $risk = $riskService->analyze($weather['current'], $weather['forecast'], $weather['aqi']);
                    $alert['risk']    = $risk;
                    $alert['weather'] = [
                        'temperature'         => round((float) ($weather['current']['main']['temp'] ?? 0), 1),
                        'weather_main'        => $weather['current']['weather'][0]['main'] ?? '',
                        'weather_description' => $weather['current']['weather'][0]['description'] ?? '',
                        'icon'                => $weather['current']['weather'][0]['icon'] ?? '01d',
                    ];

                    $eventDate = (string) $event->event_date;
                    $eventTime = $event->event_time ?? '12:00';
                    try {
                        $alert['event_forecast'] = $weatherService->getEventForecast(
                            $event->city, $event->country, $eventDate, $eventTime
                        );
                    } catch (\Throwable) {
                        $alert['event_forecast'] = null;
                    }

                    if (($risk['status'] ?? '') === 'HIGH_RISK') $highRisk++;
                    if (($risk['status'] ?? '') === 'MEDIUM_RISK') $mediumRisk++;
                } else {
                    $alert['risk']          = null;
                    $alert['weather']       = null;
                    $alert['event_forecast'] = null;
                }

                $alerts[] = $alert;
            }

            return response()->json([
                'success' => true,
                'data'    => $alerts,
                'meta'    => [
                    'total'       => count($alerts),
                    'high_risk'   => $highRisk,
                    'medium_risk' => $mediumRisk,
                ],
            ]);
        } catch (\Throwable $e) {
            return $this->error('Erro ao analisar riscos: ' . $e->getMessage(), 500);
        }
    }

    public function financialInsights(): JsonResponse
    {
        try {
            $events = Event::all();

            if ($events->isEmpty()) {
                return $this->success([
                    'summary'      => [
                        'total_events'       => 0,
                        'total_budget'       => 0,
                        'total_revenue'      => 0,
                        'total_profit'       => 0,
                        'avg_roi'            => 0,
                        'capital_at_risk'    => 0,
                        'high_risk_financial'=> 0,
                        'medium_risk_count'  => 0,
                        'high_risk_count'    => 0,
                    ],
                    'events'       => [],
                    'distribution' => [],
                ]);
            }

            $riskService = app(WeatherRiskService::class);

            $insights = [];
            $totalBudget = 0;
            $totalRevenue = 0;
            $capitalAtRisk = 0;
            $highRiskFinancial = 0;
            $highRiskCount = 0;
            $mediumRiskCount = 0;
            $profitEvents = 0;

            foreach ($events as $event) {
                $budget  = (float) ($event->budget ?? 0);
                $revenue = (float) ($event->revenue ?? 0);
                $profit  = $revenue - $budget;
                $roi     = $budget > 0 ? round(($profit / $budget) * 100, 1) : 0;
                $totalBudget  += $budget;
                $totalRevenue += $revenue;

                $riskStatus = 'unknown';
                $riskScore  = 0;
                try {
                    $current  = $this->weatherService->getCurrentWeather($event->city, $event->country);
                    $forecast = $this->weatherService->getForecast($event->city, $event->country);
                    $airData  = $this->weatherService->getAirQuality($event->city, $event->country);
                    $aqi      = (int) ($airData['list'][0]['main']['aqi'] ?? 1);
                    $risk     = $riskService->analyze($current, $forecast, $aqi);
                    $riskStatus = $risk['status'];
                    $riskScore  = $risk['score'];
                } catch (\Throwable) {
                    // Sem dados de clima — mantém unknown
                }

                $financialExposure = $budget > 0 ? round($budget * ($riskScore / 100), 2) : 0;
                $adjustedRevenue   = $revenue > 0 ? round($revenue * (1 - $riskScore / 200), 2) : 0;

                if ($riskStatus === 'HIGH_RISK') {
                    $highRiskCount++;
                    $capitalAtRisk += $budget;
                    if ($budget > 0) $highRiskFinancial++;
                }
                if ($riskStatus === 'MEDIUM_RISK') $mediumRiskCount++;
                if ($profit > 0) $profitEvents++;

                $insights[] = [
                    'event_id'           => $event->id,
                    'event_name'         => $event->name,
                    'city'               => $event->city,
                    'event_date'         => (string) $event->event_date,
                    'type'               => $event->type,
                    'status'             => $event->status,
                    'budget'             => $budget,
                    'revenue'            => $revenue,
                    'profit'             => round($profit, 2),
                    'roi'                => $roi,
                    'risk_status'        => $riskStatus,
                    'risk_score'         => $riskScore,
                    'financial_exposure' => $financialExposure,
                    'adjusted_revenue'   => $adjustedRevenue,
                ];
            }

            $totalProfit = round($totalRevenue - $totalBudget, 2);
            $avgRoi      = $totalBudget > 0 ? round(($totalProfit / $totalBudget) * 100, 1) : 0;

            // Distribuição financeira por nível de risco
            $riskBuckets = ['LOW_RISK' => 0, 'MEDIUM_RISK' => 0, 'HIGH_RISK' => 0, 'unknown' => 0];
            $budgetByRisk = ['LOW_RISK' => 0, 'MEDIUM_RISK' => 0, 'HIGH_RISK' => 0, 'unknown' => 0];
            foreach ($insights as $i) {
                $status = $i['risk_status'] ?: 'unknown';
                $riskBuckets[$status] = ($riskBuckets[$status] ?? 0) + 1;
                $budgetByRisk[$status] = ($budgetByRisk[$status] ?? 0) + $i['budget'];
            }

            return response()->json([
                'success' => true,
                'data'    => [
                    'summary' => [
                        'total_events'        => count($events),
                        'total_budget'        => round($totalBudget, 2),
                        'total_revenue'       => round($totalRevenue, 2),
                        'total_profit'        => $totalProfit,
                        'avg_roi'             => $avgRoi,
                        'capital_at_risk'     => round($capitalAtRisk, 2),
                        'high_risk_financial' => $highRiskFinancial,
                        'high_risk_count'     => $highRiskCount,
                        'medium_risk_count'   => $mediumRiskCount,
                        'profitable_count'    => $profitEvents,
                    ],
                    'events'       => $insights,
                    'distribution' => [
                        'by_risk'     => $riskBuckets,
                        'budget_at_risk' => $budgetByRisk,
                    ],
                ],
            ]);
        } catch (\Throwable $e) {
            return $this->error('Erro ao calcular insights financeiros: ' . $e->getMessage(), 500);
        }
    }

    public function upcomingWeather(): JsonResponse
    {
        $events = Event::upcoming()
            ->where('event_date', '<=', now()->addDays(14)->toDateString())
            ->get();

        if ($events->isEmpty()) {
            return $this->success([]);
        }

        $cityWeather = [];
        foreach ($events as $event) {
            $key = strtolower($event->city . '|' . $event->country);
            if (isset($cityWeather[$key])) continue;

            try {
                $current  = $this->weatherService->getCurrentWeather($event->city, $event->country);
                $forecast = $this->weatherService->getForecast($event->city, $event->country);
                $airData  = $this->weatherService->getAirQuality($event->city, $event->country);
                $aqi      = (int) ($airData['list'][0]['main']['aqi'] ?? 1);
                $cityWeather[$key] = compact('current', 'forecast', 'aqi');
            } catch (\Throwable) {
                $cityWeather[$key] = null;
            }
        }

        $riskService = app(WeatherRiskService::class);
        $results = [];
        $highRisk = 0;
        $mediumRisk = 0;

        foreach ($events as $event) {
            $key = strtolower($event->city . '|' . $event->country);
            $weather = $cityWeather[$key] ?? null;

            $item = [
                'id'                => $event->id,
                'name'              => $event->name,
                'city'              => $event->city,
                'country'           => $event->country,
                'event_date'        => (string) $event->event_date,
                'event_time'        => $event->event_time,
                'type'              => $event->type,
                'status'            => $event->status,
                'latitude'          => $event->latitude,
                'longitude'         => $event->longitude,
                'expected_audience' => $event->expected_audience,
                'budget'            => $event->budget,
                'revenue'           => $event->revenue,
                'ticket_price'      => $event->ticket_price,
                'venue'             => $event->venue,
                'organizer'         => $event->organizer,
            ];

            if ($weather) {
                $item['weather'] = [
                    'temperature'         => round((float) ($weather['current']['main']['temp'] ?? 0), 1),
                    'weather_main'        => $weather['current']['weather'][0]['main'] ?? '',
                    'weather_description' => $weather['current']['weather'][0]['description'] ?? '',
                    'icon'                => $weather['current']['weather'][0]['icon'] ?? '01d',
                ];

                $item['risk'] = $riskService->analyze(
                    $weather['current'], $weather['forecast'], $weather['aqi']
                );

                $eventDate = is_string($event->event_date)
                    ? $event->event_date
                    : (string) $event->event_date;
                $eventTime = $event->event_time ?? '12:00';

                try {
                    $item['event_forecast'] = $this->weatherService->getEventForecast(
                        $event->city, $event->country, $eventDate, $eventTime
                    );
                } catch (\Throwable) {
                    $item['event_forecast'] = null;
                }

                if (($item['risk']['status'] ?? '') === 'HIGH_RISK') $highRisk++;
                if (($item['risk']['status'] ?? '') === 'MEDIUM_RISK') $mediumRisk++;
            } else {
                $item['weather'] = null;
                $item['risk'] = null;
                $item['event_forecast'] = null;
            }

            $results[] = $item;
        }

        return response()->json([
            'success' => true,
            'data'    => $results,
            'meta'    => [
                'total'          => count($results),
                'cities_checked' => count(array_filter($cityWeather)),
                'high_risk'      => $highRisk,
                'medium_risk'    => $mediumRisk,
            ],
        ]);
    }

    public function duplicate(int $id): JsonResponse
    {
        $event = Event::find($id);

        if (!$event) {
            return $this->error('Evento não encontrado', 404);
        }

        $copy = $event->replicate();
        $copy->name = $event->name . ' (Cópia)';
        $copy->event_date = now()->addWeek()->toDateString();
        $copy->status = 'planned';
        $copy->save();

        // Auto-favoritar a cidade
        try {
            FavoriteCity::firstOrCreate(
                ['city' => $event->city, 'country' => strtoupper($event->country)],
            );
        } catch (\Throwable) {}

        return $this->success($copy->fresh(), 201);
    }

    public function show(int $id): JsonResponse
    {
        $event = Event::find($id);

        if (!$event) {
            return $this->error('Evento não encontrado', 404);
        }

        $event = $this->ensureEventCoordinates($event);
        $eventData = $event->toArray();

        try {
            $weather  = app(WeatherService::class);
            $risk     = app(WeatherRiskService::class);
            $current  = $weather->getCurrentWeather($event->city, $event->country);
            $forecast = $weather->getForecast($event->city, $event->country);
            $airData  = $weather->getAirQuality($event->city, $event->country);
            $aqi      = (int) ($airData['list'][0]['main']['aqi'] ?? 1);

            $eventData['weather'] = [
                'temperature'         => round((float) ($current['main']['temp'] ?? 0), 1),
                'weather_main'        => $current['weather'][0]['main'] ?? '',
                'weather_description' => $current['weather'][0]['description'] ?? '',
                'icon'                => $current['weather'][0]['icon'] ?? '01d',
            ];

            $eventData['risk'] = $risk->analyze($current, $forecast, $aqi);

            // Previsão para o dia/hora do evento (real se ≤5 dias, estimativa se além)
            $eventDate = is_string($event->event_date)
                ? $event->event_date
                : (string) $event->event_date;
            $eventTime = $event->event_time ?? '12:00';

            $eventData['event_forecast'] = $weather->getEventForecast(
                $event->city,
                $event->country,
                $eventDate,
                $eventTime
            );
        } catch (\Throwable) {
            $eventData['weather']         = null;
            $eventData['risk']            = null;
            $eventData['event_forecast']  = null;
        }

        return $this->success($eventData);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $event = Event::find($id);

        if (!$event) {
            return $this->error('Evento não encontrado', 404);
        }

        $data = $request->validate([
            'name'              => 'sometimes|string|max:255',
            'city'              => 'sometimes|string|max:100',
            'country'           => 'sometimes|string|max:10',
            'event_date'        => 'sometimes|date_format:Y-m-d',
            'event_time'        => 'sometimes',
            'type'              => 'sometimes|string|max:50',
            'expected_audience' => 'nullable|integer|min:0',
            'description'       => 'nullable|string',
            'latitude'          => 'nullable|numeric|between:-90,90|required_with:longitude',
            'longitude'         => 'nullable|numeric|between:-180,180|required_with:latitude',
            'status'            => 'nullable|string|in:planned,confirmed,in_progress,completed,cancelled',
            'budget'            => 'nullable|numeric|min:0',
            'revenue'           => 'nullable|numeric|min:0',
            'ticket_price'      => 'nullable|numeric|min:0',
            'organizer'         => 'nullable|string|max:255',
            'organizer_contact' => 'nullable|string|max:255',
            'venue'             => 'nullable|string|max:255',
            'end_date'          => 'nullable|date_format:Y-m-d',
            'end_time'          => 'nullable',
            'banner_url'        => 'nullable|string|max:500',
            'tags'              => 'nullable|string|max:500',
            'notes'             => 'nullable|string',
        ]);

        $data = $this->resolveCoordinates($data, $event);
        $event->update($data);

        return $this->success($event->fresh());
    }

    public function destroy(int $id): JsonResponse
    {
        $event = Event::find($id);

        if (!$event) {
            return $this->error('Evento não encontrado', 404);
        }

        $event->delete();

        return $this->success(['message' => 'Evento removido com sucesso']);
    }

    private function resolveCoordinates(array $data, ?Event $event = null): array
    {
        if (array_key_exists('latitude', $data) && array_key_exists('longitude', $data)) {
            return $data;
        }

        $city = $data['city'] ?? $event?->city;
        $country = strtoupper($data['country'] ?? $event?->country ?? 'BR');

        if (!$city) {
            return $data;
        }

        $cityChanged = array_key_exists('city', $data) || array_key_exists('country', $data);
        if (!$cityChanged && $event?->latitude !== null && $event?->longitude !== null) {
            return $data;
        }

        try {
            $coords = $this->weatherService->geocode($city, $country);
            $data['latitude'] = $coords['lat'];
            $data['longitude'] = $coords['lon'];
        } catch (\Throwable) {
            if ($event?->latitude !== null && $event?->longitude !== null) {
                return $data;
            }
        }

        return $data;
    }

    private function ensureEventCoordinates(Event $event): Event
    {
        if ($event->latitude !== null && $event->longitude !== null) {
            return $event;
        }

        $data = $this->resolveCoordinates([], $event);
        if (!isset($data['latitude'], $data['longitude'])) {
            return $event;
        }

        $event->forceFill([
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
        ])->save();

        return $event->fresh();
    }
}
