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
        $events = Event::orderBy('event_date')->get();

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
