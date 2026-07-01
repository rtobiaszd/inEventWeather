<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
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

    public function publicIndex(Request $request): JsonResponse
    {
        $query = Event::withCount('registrations')
            ->whereNull('provider_id')
            ->whereIn('status', ['confirmed', 'planned', 'in_progress']);

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('city', 'like', "%{$search}%")
                  ->orWhere('organizer', 'like', "%{$search}%");
            });
        }

        if ($city = $request->query('city')) {
            $query->where('city', $city);
        }

        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }

        if ($dateFrom = $request->query('date_from')) {
            $query->where('event_date', '>=', $dateFrom);
        }

        if ($dateTo = $request->query('date_to')) {
            $query->where('event_date', '<=', $dateTo);
        }

        $order = $request->query('order', 'upcoming');
        if ($order === 'upcoming') {
            $query->where('event_date', '>=', now()->subDay()->toDateString())
                  ->orderBy('event_date');
        } elseif ($order === 'past') {
            $query->where('event_date', '<', now()->toDateString())
                  ->orderBy('event_date', 'desc');
        } else {
            $query->orderBy('event_date');
        }

        $events = $query->paginate($request->query('per_page', 12));

        return response()->json([
            'success' => true,
            'data' => $events->items(),
            'meta' => [
                'current_page' => $events->currentPage(),
                'last_page' => $events->lastPage(),
                'per_page' => $events->perPage(),
                'total' => $events->total(),
            ],
        ]);
    }

    public function publicEvent(int $id): JsonResponse
    {
        $event = Event::with('speakers', 'sessions')->find($id);

        if (!$event) {
            return $this->error('Evento não encontrado', 404);
        }

        $eventData = $event->toArray();

        $eventData['speakers'] = $event->speakers->map(fn ($s) => [
            'id' => $s->id,
            'name' => $s->name,
            'bio' => $s->bio,
            'avatar_url' => $s->avatar_url,
            'company' => $s->company,
            'role_title' => $s->role_title,
            'expertise' => $s->expertise,
            'pivot' => ['is_featured' => $s->pivot->is_featured],
        ]);

        $eventData['sessions'] = $event->sessions->map(fn ($s) => [
            'id' => $s->id,
            'title' => $s->title,
            'description' => $s->description,
            'start_time' => $s->start_time,
            'end_time' => $s->end_time,
            'room' => $s->room,
            'speaker_name' => $s->speaker_name,
            'outdoor_suitable' => $s->outdoor_suitable,
        ]);

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

        app(CertificateController::class)->generateForRegistration($registration->fresh());

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

        Certificate::where('registration_id', $registration->id)->delete();
        $registration->update(['checked_in_at' => null]);

        return $this->success([
            'registration' => $registration->fresh(),
            'message'      => 'Check-in desfeito.',
        ]);
    }

    public function badge(int $eventId, string $token): JsonResponse
    {
        $registration = Registration::where('event_id', $eventId)
            ->where('checkin_token', $token)
            ->first();

        if (!$registration) {
            return $this->error('Credencial não encontrada', 404);
        }

        $event = $registration->event;

        return $this->success([
            'registration' => $registration->only(['id', 'name', 'email', 'company', 'status', 'checked_in_at']),
            'event' => $event->only(['id', 'name', 'city', 'venue', 'event_date', 'event_time', 'type', 'organizer']),
        ]);
    }

    public function checkInByToken(Request $request, int $eventId): JsonResponse
    {
        $data = $request->validate([
            'token' => 'required|string|size:32',
        ]);

        $registration = Registration::where('event_id', $eventId)
            ->where('checkin_token', $data['token'])
            ->first();

        if (!$registration) {
            return $this->error('Credencial não encontrada', 404);
        }

        if ($registration->checked_in_at) {
            $formatted = $registration->checked_in_at->format('d/m/Y H:i');
            return $this->error("Check-in já realizado em {$formatted}.", 409);
        }

        $registration->update([
            'checked_in_at' => now(),
            'status' => 'confirmed',
        ]);

        app(CertificateController::class)->generateForRegistration($registration->fresh());

        return $this->success([
            'registration' => $registration->fresh()->only(['id', 'name', 'email', 'company', 'status', 'checked_in_at']),
            'message' => 'Check-in realizado com sucesso!',
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
