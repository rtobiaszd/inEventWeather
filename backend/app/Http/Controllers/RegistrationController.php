<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Registration;
use App\Services\WeatherService;
use App\Services\WeatherRiskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RegistrationController extends Controller
{
    public function __construct(
        private WeatherService $weatherService,
    ) {}

    public function publicEvent(int $id): JsonResponse
    {
        $event = Event::find($id);

        if (!$event) {
            return $this->error('Evento não encontrado', 404);
        }

        $eventData = $event->toArray();

        try {
            $weather  = $this->weatherService->getCurrentWeather($event->city, $event->country);
            $forecast = $this->weatherService->getForecast($event->city, $event->country);
            $airData  = $this->weatherService->getAirQuality($event->city, $event->country);
            $aqi      = (int) ($airData['list'][0]['main']['aqi'] ?? 1);

            $riskService = app(WeatherRiskService::class);
            $risk = $riskService->analyze($weather, $forecast, $aqi);

            $eventData['weather'] = [
                'temperature'         => round((float) ($weather['main']['temp'] ?? 0), 1),
                'weather_main'        => $weather['weather'][0]['main'] ?? '',
                'weather_description' => $weather['weather'][0]['description'] ?? '',
                'icon'                => $weather['weather'][0]['icon'] ?? '01d',
            ];
            $eventData['risk'] = $risk;

            $eventDate = is_string($event->event_date)
                ? $event->event_date
                : (string) $event->event_date;
            $eventTime = $event->event_time ?? '12:00';

            $eventData['event_forecast'] = $this->weatherService->getEventForecast(
                $event->city, $event->country, $eventDate, $eventTime
            );
        } catch (\Throwable) {
            $eventData['weather']        = null;
            $eventData['risk']           = null;
            $eventData['event_forecast'] = null;
        }

        $eventData['registration_count'] = Registration::where('event_id', $id)
            ->where('status', 'confirmed')
            ->count();

        return $this->success($eventData);
    }

    public function register(Request $request, int $eventId): JsonResponse
    {
        $event = Event::find($eventId);

        if (!$event) {
            return $this->error('Evento não encontrado', 404);
        }

        $data = $request->validate([
            'name'    => 'required|string|max:255',
            'email'   => 'required|email|max:255',
            'phone'   => 'nullable|string|max:50',
            'company' => 'nullable|string|max:255',
        ]);

        $existing = Registration::where('event_id', $eventId)
            ->where('email', $data['email'])
            ->first();

        if ($existing) {
            return $this->error('Este email já está inscrito neste evento.', 409);
        }

        $registration = Registration::create([
            'event_id' => $eventId,
            'name'     => $data['name'],
            'email'    => $data['email'],
            'phone'    => $data['phone'] ?? null,
            'company'  => $data['company'] ?? null,
            'status'   => 'confirmed',
        ]);

        return $this->success([
            'registration' => $registration,
            'message' => 'Inscrição confirmada com sucesso!',
        ], 201);
    }

    public function index(int $eventId): JsonResponse
    {
        $event = Event::find($eventId);

        if (!$event) {
            return $this->error('Evento não encontrado', 404);
        }

        $registrations = Registration::where('event_id', $eventId)
            ->orderBy('created_at', 'desc')
            ->get();

        $stats = [
            'total'       => $registrations->count(),
            'confirmed'   => $registrations->where('status', 'confirmed')->count(),
            'waitlist'    => $registrations->where('status', 'waitlist')->count(),
            'cancelled'   => $registrations->where('status', 'cancelled')->count(),
            'checked_in'  => $registrations->whereNotNull('checked_in_at')->count(),
        ];

        return $this->success([
            'registrations' => $registrations,
            'stats'         => $stats,
        ]);
    }

    public function show(int $eventId, int $id): JsonResponse
    {
        $registration = Registration::where('event_id', $eventId)->find($id);

        if (!$registration) {
            return $this->error('Inscrição não encontrada', 404);
        }

        return $this->success($registration);
    }

    public function update(Request $request, int $eventId, int $id): JsonResponse
    {
        $registration = Registration::where('event_id', $eventId)->find($id);

        if (!$registration) {
            return $this->error('Inscrição não encontrada', 404);
        }

        $data = $request->validate([
            'name'    => 'sometimes|string|max:255',
            'email'   => 'sometimes|email|max:255',
            'phone'   => 'nullable|string|max:50',
            'company' => 'nullable|string|max:255',
            'status'  => 'sometimes|in:registered,confirmed,waitlist,cancelled',
        ]);

        if (isset($data['email']) && $data['email'] !== $registration->email) {
            $existing = Registration::where('event_id', $eventId)
                ->where('email', $data['email'])
                ->where('id', '!=', $id)
                ->first();
            if ($existing) {
                return $this->error('Este email já está cadastrado neste evento.', 409);
            }
        }

        $registration->update($data);

        return $this->success($registration->fresh());
    }

    public function checkIn(int $eventId, int $id): JsonResponse
    {
        $registration = Registration::where('event_id', $eventId)->find($id);

        if (!$registration) {
            return $this->error('Inscrição não encontrada', 404);
        }

        if ($registration->checked_in_at) {
            return $this->error('Esta pessoa já realizou check-in.', 409);
        }

        $registration->update([
            'checked_in_at' => now(),
            'status'        => 'confirmed',
        ]);

        return $this->success([
            'registration' => $registration->fresh(),
            'message'      => 'Check-in realizado com sucesso!',
        ]);
    }

    public function undoCheckIn(int $eventId, int $id): JsonResponse
    {
        $registration = Registration::where('event_id', $eventId)->find($id);

        if (!$registration) {
            return $this->error('Inscrição não encontrada', 404);
        }

        if (!$registration->checked_in_at) {
            return $this->error('Esta pessoa ainda não fez check-in.', 409);
        }

        $registration->update(['checked_in_at' => null]);

        return $this->success([
            'registration' => $registration->fresh(),
            'message'      => 'Check-in desfeito.',
        ]);
    }

    public function destroy(int $eventId, int $id): JsonResponse
    {
        $registration = Registration::where('event_id', $eventId)->find($id);

        if (!$registration) {
            return $this->error('Inscrição não encontrada', 404);
        }

        $registration->delete();

        return $this->success(['message' => 'Inscrição removida com sucesso']);
    }
}
