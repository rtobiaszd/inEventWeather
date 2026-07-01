<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventFeedback;
use App\Models\Registration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FeedbackController extends Controller
{
    public function showForm(int $eventId, string $token): JsonResponse
    {
        $registration = Registration::where('event_id', $eventId)
            ->where('checkin_token', $token)
            ->first();

        if (!$registration) {
            return $this->error('Link de feedback inválido.', 404);
        }

        $existing = EventFeedback::where('registration_id', $registration->id)->first();

        $event = $registration->event;

        return $this->success([
            'registration' => $registration->only(['id', 'name', 'email', 'company']),
            'event' => $event->only(['id', 'name', 'city', 'venue', 'event_date']),
            'already_submitted' => $existing !== null,
            'feedback' => $existing ? $existing->only(['nps_score', 'comment', 'submitted_at']) : null,
        ]);
    }

    public function submit(Request $request, int $eventId, string $token): JsonResponse
    {
        $registration = Registration::where('event_id', $eventId)
            ->where('checkin_token', $token)
            ->first();

        if (!$registration) {
            return $this->error('Link de feedback inválido.', 404);
        }

        $existing = EventFeedback::where('registration_id', $registration->id)->first();
        if ($existing) {
            return $this->error('Você já enviou seu feedback para este evento.', 409);
        }

        $data = $request->validate([
            'nps_score' => 'required|integer|min:0|max:10',
            'comment' => 'nullable|string|max:2000',
        ]);

        $feedback = EventFeedback::create([
            'event_id' => $eventId,
            'registration_id' => $registration->id,
            'nps_score' => $data['nps_score'],
            'comment' => $data['comment'] ?? null,
            'submitted_at' => now(),
        ]);

        return $this->success([
            'feedback' => $feedback->fresh()->only(['nps_score', 'comment', 'submitted_at']),
            'message' => 'Feedback enviado com sucesso! Obrigado pela sua opinião.',
        ], 201);
    }

    public function results(int $eventId): JsonResponse
    {
        $event = Event::find($eventId);

        if (!$event) {
            return $this->error('Evento não encontrado', 404);
        }

        $all = EventFeedback::where('event_id', $eventId)
            ->orderBy('submitted_at', 'desc')
            ->get();

        $total = $all->count();
        $avgNps = $total > 0 ? round($all->avg('nps_score'), 1) : null;

        $promoters = $all->where('nps_score', '>=', 9)->count();
        $passives  = $all->where('nps_score', '>=', 7)->where('nps_score', '<=', 8)->count();
        $detractors = $all->where('nps_score', '<=', 6)->count();

        $nps = null;
        if ($total > 0) {
            $nps = round((($promoters - $detractors) / $total) * 100);
        }

        $distribution = [];
        for ($i = 0; $i <= 10; $i++) {
            $distribution[$i] = $all->where('nps_score', $i)->count();
        }

        $comments = $all->whereNotNull('comment')->values()->map(fn ($f) => [
            'nps_score' => $f->nps_score,
            'comment' => $f->comment,
            'submitted_at' => (string) $f->submitted_at,
        ]);

        $totalRegistrations = Registration::where('event_id', $eventId)->count();
        $responseRate = $totalRegistrations > 0
            ? round(($total / $totalRegistrations) * 100, 1)
            : 0;

        return $this->success([
            'summary' => [
                'total_responses' => $total,
                'total_registrations' => $totalRegistrations,
                'response_rate' => $responseRate,
                'average_nps' => $avgNps,
                'nps_score' => $nps,
                'promoters' => $promoters,
                'passives' => $passives,
                'detractors' => $detractors,
            ],
            'distribution' => $distribution,
            'comments' => $comments,
            'latest' => $all->take(5)->map(fn ($f) => [
                'nps_score' => $f->nps_score,
                'comment' => $f->comment,
                'submitted_at' => (string) $f->submitted_at,
                'name' => $f->registration->name,
            ]),
        ]);
    }

    public function index(int $eventId): JsonResponse
    {
        $event = Event::find($eventId);

        if (!$event) {
            return $this->error('Evento não encontrado', 404);
        }

        $feedback = EventFeedback::where('event_id', $eventId)
            ->with('registration')
            ->orderBy('submitted_at', 'desc')
            ->get()
            ->map(fn ($f) => [
                'id' => $f->id,
                'nps_score' => $f->nps_score,
                'comment' => $f->comment,
                'submitted_at' => (string) $f->submitted_at,
                'name' => $f->registration->name,
                'email' => $f->registration->email,
            ]);

        return $this->success($feedback);
    }
}
