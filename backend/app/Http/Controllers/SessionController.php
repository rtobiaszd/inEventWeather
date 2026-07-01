<?php

namespace App\Http\Controllers;

use App\Models\EventSession;
use App\Models\Event;
use App\Services\SessionOptimizerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SessionController extends Controller
{
    public function __construct(
        private SessionOptimizerService $optimizer,
    ) {}

    public function index(int $eventId): JsonResponse
    {
        $event = Event::find($eventId);
        if (!$event) {
            return $this->error('Evento não encontrado', 404);
        }

        $sessions = EventSession::where('event_id', $eventId)
            ->with('speakers')
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();

        return $this->success($sessions);
    }

    public function store(Request $request, int $eventId): JsonResponse
    {
        $event = Event::find($eventId);
        if (!$event) {
            return $this->error('Evento não encontrado', 404);
        }

        $data = $request->validate([
            'name'              => 'required|string|max:255',
            'description'       => 'nullable|string',
            'speaker_name'      => 'nullable|string|max:255',
            'speaker_bio'       => 'nullable|string',
            'date'              => 'required|date_format:Y-m-d',
            'start_time'        => 'required',
            'end_time'          => 'required',
            'room'              => 'nullable|string|max:100',
            'type'              => 'nullable|string|in:talk,workshop,panel,keynote,other',
            'capacity'          => 'nullable|integer|min:0',
            'status'            => 'nullable|string|in:scheduled,in_progress,completed,cancelled',
            'outdoor_suitable'  => 'nullable|boolean',
            'speaker_ids'       => 'nullable|array',
            'speaker_ids.*'     => 'integer|exists:speakers,id',
        ]);

        $speakerIds = $data['speaker_ids'] ?? [];
        unset($data['speaker_ids']);

        $data['event_id'] = $eventId;
        $session = EventSession::create($data);

        if (!empty($speakerIds)) {
            $session->speakers()->sync($speakerIds);
        }

        $session->load('speakers');

        return $this->success($session, 201);
    }

    public function show(int $eventId, int $id): JsonResponse
    {
        $session = EventSession::where('event_id', $eventId)->with('speakers')->find($id);

        if (!$session) {
            return $this->error('Sessão não encontrada', 404);
        }

        return $this->success($session);
    }

    public function update(Request $request, int $eventId, int $id): JsonResponse
    {
        $session = EventSession::where('event_id', $eventId)->find($id);

        if (!$session) {
            return $this->error('Sessão não encontrada', 404);
        }

        $data = $request->validate([
            'name'              => 'sometimes|string|max:255',
            'description'       => 'nullable|string',
            'speaker_name'      => 'nullable|string|max:255',
            'speaker_bio'       => 'nullable|string',
            'date'              => 'sometimes|date_format:Y-m-d',
            'start_time'        => 'sometimes',
            'end_time'          => 'sometimes',
            'room'              => 'nullable|string|max:100',
            'type'              => 'nullable|string|in:talk,workshop,panel,keynote,other',
            'capacity'          => 'nullable|integer|min:0',
            'status'            => 'nullable|string|in:scheduled,in_progress,completed,cancelled',
            'outdoor_suitable'  => 'nullable|boolean',
            'speaker_ids'       => 'nullable|array',
            'speaker_ids.*'     => 'integer|exists:speakers,id',
        ]);

        $speakerIds = $data['speaker_ids'] ?? null;
        unset($data['speaker_ids']);

        $session->update($data);

        if ($speakerIds !== null) {
            $session->speakers()->sync($speakerIds);
        }

        $session->load('speakers');

        return $this->success($session->fresh());
    }

    public function destroy(int $eventId, int $id): JsonResponse
    {
        $session = EventSession::where('event_id', $eventId)->find($id);

        if (!$session) {
            return $this->error('Sessão não encontrada', 404);
        }

        $session->delete();

        return $this->success(['message' => 'Sessão removida com sucesso']);
    }

    public function conflicts(int $eventId): JsonResponse
    {
        $event = Event::find($eventId);

        if (!$event) {
            return $this->error('Evento não encontrado', 404);
        }

        $conflicts = $this->optimizer->detectConflicts($event);

        return $this->success([
            'conflicts' => $conflicts,
            'total' => count($conflicts),
        ]);
    }

    public function optimize(int $eventId): JsonResponse
    {
        $event = Event::find($eventId);

        if (!$event) {
            return $this->error('Evento não encontrado', 404);
        }

        $suggestions = $this->optimizer->suggestOptimalSchedule($event);

        return $this->success([
            'suggestions' => $suggestions,
            'total' => count($suggestions),
        ]);
    }

    public function applyOptimization(int $eventId): JsonResponse
    {
        $event = Event::find($eventId);

        if (!$event) {
            return $this->error('Evento não encontrado', 404);
        }

        $applied = $this->optimizer->applyWeatherOptimization($event);

        return $this->success([
            'applied' => $applied,
            'message' => "Otimização climática aplicada em {$applied} sessões.",
        ]);
    }
}
