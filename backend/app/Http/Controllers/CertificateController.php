<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use App\Models\Event;
use App\Models\Registration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CertificateController extends Controller
{
    public function show(int $eventId, string $token): JsonResponse
    {
        $registration = Registration::where('event_id', $eventId)
            ->where('checkin_token', $token)
            ->first();

        if (!$registration) {
            return $this->error('Participante não encontrado.', 404);
        }

        $certificate = Certificate::where('registration_id', $registration->id)->first();

        if (!$certificate) {
            if ($registration->checked_in_at) {
                $certificate = $this->generateForRegistration($registration);
            } else {
                return $this->error('Certificado disponível apenas após o check-in.', 403);
            }
        }

        $event = Event::find($eventId);

        return $this->success([
            'certificate' => [
                'id' => $certificate->id,
                'hash' => $certificate->hash,
                'issued_at' => $certificate->issued_at,
                'verify_url' => $certificate->verify_url,
                'template_id' => $certificate->template_id,
            ],
            'event' => [
                'id' => $event->id,
                'name' => $event->name,
                'city' => $event->city,
                'event_date' => $event->event_date,
                'venue' => $event->venue,
                'organizer' => $event->organizer,
            ],
            'registration' => [
                'id' => $registration->id,
                'name' => $registration->name,
                'email' => $registration->email,
                'checked_in_at' => $registration->checked_in_at,
            ],
        ]);
    }

    public function verify(string $hash): JsonResponse
    {
        $certificate = Certificate::with(['event', 'registration'])->where('hash', $hash)->first();

        if (!$certificate) {
            return $this->error('Certificado não encontrado ou inválido.', 404);
        }

        $event = $certificate->event;
        $registration = $certificate->registration;

        return $this->success([
            'valid' => true,
            'hash' => $certificate->hash,
            'issued_at' => $certificate->issued_at,
            'participant' => [
                'name' => $registration->name,
            ],
            'event' => [
                'name' => $event->name,
                'city' => $event->city,
                'event_date' => $event->event_date,
                'hours' => intval($event->end_time
                    ? (strtotime($event->end_time) - strtotime($event->event_time)) / 3600
                    : 4),
            ],
        ]);
    }

    public function index(int $eventId): JsonResponse
    {
        $event = Event::find($eventId);

        if (!$event) {
            return $this->error('Evento não encontrado', 404);
        }

        $certificates = Certificate::with('registration')
            ->where('event_id', $eventId)
            ->orderBy('created_at', 'desc')
            ->get();

        $totalRegistrations = Registration::where('event_id', $eventId)->count();
        $checkedIn = Registration::where('event_id', $eventId)->whereNotNull('checked_in_at')->count();
        $issued = $certificates->count();

        return $this->success([
            'certificates' => $certificates->map(fn ($c) => [
                'id' => $c->id,
                'hash' => $c->hash,
                'issued_at' => $c->issued_at,
                'participant_name' => $c->registration->name,
                'participant_email' => $c->registration->email,
            ]),
            'stats' => [
                'total_registrations' => $totalRegistrations,
                'checked_in' => $checkedIn,
                'issued' => $issued,
                'pending' => $checkedIn - $issued,
                'issuance_rate' => $checkedIn > 0 ? round(($issued / $checkedIn) * 100, 1) : 0,
            ],
        ]);
    }

    public function reissue(int $eventId, int $registrationId): JsonResponse
    {
        $registration = Registration::where('event_id', $eventId)->find($registrationId);

        if (!$registration) {
            return $this->error('Inscrição não encontrada.', 404);
        }

        Certificate::where('registration_id', $registration->id)->delete();

        $certificate = $this->generateForRegistration($registration);

        return $this->success([
            'certificate' => $certificate,
            'message' => 'Certificado reemitido com sucesso!',
        ]);
    }

    public function generateForRegistration(Registration $registration): Certificate
    {
        $hash = Certificate::generateHash($registration->id, $registration->event_id);

        return Certificate::create([
            'event_id' => $registration->event_id,
            'registration_id' => $registration->id,
            'hash' => $hash,
            'issued_at' => now(),
        ]);
    }
}
