<?php

namespace App\Http\Controllers;

use App\Models\EventType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class EventTypeController extends Controller
{
    public function index(): JsonResponse
    {
        return $this->success(
            EventType::where('is_active', true)->orderBy('is_system', 'desc')->orderBy('name')->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'        => 'required|string|max:100',
            'icon'        => 'nullable|string|max:10',
            'description' => 'nullable|string',
        ]);

        $data['slug']      = Str::slug($data['name'], '_');
        $data['is_system'] = false;

        if (EventType::where('slug', $data['slug'])->exists()) {
            return $this->error('Já existe um tipo com este nome', 409);
        }

        return $this->success(EventType::create($data), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $type = EventType::find($id);

        if (!$type) {
            return $this->error('Tipo de evento não encontrado', 404);
        }

        $data = $request->validate([
            'name'        => 'sometimes|string|max:100',
            'icon'        => 'nullable|string|max:10',
            'description' => 'nullable|string',
            'is_active'   => 'sometimes|boolean',
        ]);

        if (isset($data['name'])) {
            $newSlug = Str::slug($data['name'], '_');

            if ($newSlug !== $type->slug && EventType::where('slug', $newSlug)->exists()) {
                return $this->error('Já existe um tipo com este nome', 409);
            }

            if (!$type->is_system) {
                $data['slug'] = $newSlug;
            }
        }

        $type->update($data);

        return $this->success($type->fresh());
    }

    public function destroy(int $id): JsonResponse
    {
        $type = EventType::find($id);

        if (!$type) {
            return $this->error('Tipo de evento não encontrado', 404);
        }

        if ($type->is_system) {
            return $this->error('Tipos de sistema não podem ser removidos', 403);
        }

        $type->delete();

        return $this->success(['message' => 'Tipo de evento removido']);
    }
}
