<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Speaker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SpeakerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Speaker::query();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('company', 'like', "%{$search}%")
                  ->orWhere('expertise', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($eventId = $request->input('event_id')) {
            $query->whereHas('events', fn ($q) => $q->where('event_id', $eventId));
        }

        if ($expertise = $request->input('expertise')) {
            $query->where('expertise', 'like', "%{$expertise}%");
        }

        $speakers = $query->orderBy('name')->get();

        return $this->success($speakers);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'            => 'required|string|max:255',
            'email'           => 'nullable|email|max:255|unique:speakers,email',
            'bio'             => 'nullable|string',
            'avatar_url'      => 'nullable|string|max:500',
            'company'         => 'nullable|string|max:255',
            'role_title'      => 'nullable|string|max:255',
            'expertise'       => 'nullable|string|max:500',
            'social_linkedin' => 'nullable|string|max:500',
            'social_twitter'  => 'nullable|string|max:500',
            'website'         => 'nullable|string|max:500',
        ]);

        $speaker = Speaker::create($data);

        return $this->success($speaker, 201);
    }

    public function show(int $id): JsonResponse
    {
        $speaker = Speaker::with(['events', 'sessions'])->find($id);

        if (!$speaker) {
            return $this->error('Palestrante não encontrado', 404);
        }

        return $this->success($speaker);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $speaker = Speaker::find($id);

        if (!$speaker) {
            return $this->error('Palestrante não encontrado', 404);
        }

        $data = $request->validate([
            'name'            => 'sometimes|string|max:255',
            'email'           => 'nullable|email|max:255|unique:speakers,email,' . $id,
            'bio'             => 'nullable|string',
            'avatar_url'      => 'nullable|string|max:500',
            'company'         => 'nullable|string|max:255',
            'role_title'      => 'nullable|string|max:255',
            'expertise'       => 'nullable|string|max:500',
            'social_linkedin' => 'nullable|string|max:500',
            'social_twitter'  => 'nullable|string|max:500',
            'website'         => 'nullable|string|max:500',
        ]);

        $speaker->update($data);

        return $this->success($speaker->fresh());
    }

    public function destroy(int $id): JsonResponse
    {
        $speaker = Speaker::find($id);

        if (!$speaker) {
            return $this->error('Palestrante não encontrado', 404);
        }

        $speaker->delete();

        return $this->success(['message' => 'Palestrante removido com sucesso']);
    }

    public function linkToEvent(Request $request, int $speakerId): JsonResponse
    {
        $speaker = Speaker::find($speakerId);

        if (!$speaker) {
            return $this->error('Palestrante não encontrado', 404);
        }

        $data = $request->validate([
            'event_id'    => 'required|integer|exists:events,id',
            'is_featured' => 'nullable|boolean',
            'sort_order'  => 'nullable|integer|min:0',
        ]);

        $speaker->events()->syncWithoutDetaching([
            $data['event_id'] => [
                'is_featured' => $data['is_featured'] ?? false,
                'sort_order'  => $data['sort_order'] ?? 0,
            ],
        ]);

        return $this->success(['message' => 'Palestrante vinculado ao evento']);
    }

    public function unlinkFromEvent(int $speakerId, int $eventId): JsonResponse
    {
        $speaker = Speaker::find($speakerId);

        if (!$speaker) {
            return $this->error('Palestrante não encontrado', 404);
        }

        $speaker->events()->detach($eventId);

        return $this->success(['message' => 'Palestrante desvinculado do evento']);
    }

    public function linkToSession(Request $request, int $speakerId): JsonResponse
    {
        $speaker = Speaker::find($speakerId);

        if (!$speaker) {
            return $this->error('Palestrante não encontrado', 404);
        }

        $data = $request->validate([
            'session_id' => 'required|integer|exists:event_sessions,id',
        ]);

        $speaker->sessions()->syncWithoutDetaching([$data['session_id']]);

        return $this->success(['message' => 'Palestrante vinculado à sessão']);
    }

    public function unlinkFromSession(int $speakerId, int $sessionId): JsonResponse
    {
        $speaker = Speaker::find($speakerId);

        if (!$speaker) {
            return $this->error('Palestrante não encontrado', 404);
        }

        $speaker->sessions()->detach($sessionId);

        return $this->success(['message' => 'Palestrante desvinculado da sessão']);
    }
}
