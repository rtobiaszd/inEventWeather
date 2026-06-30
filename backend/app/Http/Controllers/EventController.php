<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Services\WeatherService;
use App\Services\WeatherRiskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventController extends Controller
{
    public function index(): JsonResponse
    {
        return $this->success(Event::orderBy('event_date')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'              => 'required|string|max:255',
            'city'              => 'required|string|max:100',
            'country'           => 'required|string|max:10',
            'event_date'        => 'required|date_format:Y-m-d',
            'event_time'        => 'required',
            'type'              => 'required|in:indoor,outdoor',
            'expected_audience' => 'nullable|integer|min:0',
            'description'       => 'nullable|string',
        ]);

        return $this->success(Event::create($data), 201);
    }

    public function show(int $id): JsonResponse
    {
        $event = Event::find($id);

        if (!$event) {
            return $this->error('Evento não encontrado', 404);
        }

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
            'type'              => 'sometimes|in:indoor,outdoor',
            'expected_audience' => 'nullable|integer|min:0',
            'description'       => 'nullable|string',
        ]);

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
}
