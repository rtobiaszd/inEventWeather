<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\ProductionTask;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    public function index(int $eventId): JsonResponse
    {
        $event = Event::find($eventId);
        if (!$event) {
            return $this->error('Evento não encontrado', 404);
        }

        $tasks = ProductionTask::where('event_id', $eventId)
            ->orderBy('order')
            ->orderBy('created_at')
            ->get();

        return $this->success($tasks);
    }

    public function store(Request $request, int $eventId): JsonResponse
    {
        $event = Event::find($eventId);
        if (!$event) {
            return $this->error('Evento não encontrado', 404);
        }

        $data = $request->validate([
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'assigned_to' => 'nullable|string|max:255',
            'due_date'    => 'nullable|date_format:Y-m-d',
            'priority'    => 'nullable|string|in:low,medium,high,critical',
            'status'      => 'nullable|string|in:pending,in_progress,completed,cancelled',
            'category'    => 'nullable|string|in:logistics,venue,equipment,team,marketing,finance,other',
        ]);

        $data['event_id'] = $eventId;

        $maxOrder = ProductionTask::where('event_id', $eventId)->max('order');
        $data['order'] = ($maxOrder ?? 0) + 1;

        if (($data['status'] ?? 'pending') === 'completed') {
            $data['completed_at'] = now();
        }

        $task = ProductionTask::create($data);

        return $this->success($task, 201);
    }

    public function update(Request $request, int $eventId, int $id): JsonResponse
    {
        $task = ProductionTask::where('event_id', $eventId)->find($id);
        if (!$task) {
            return $this->error('Tarefa não encontrada', 404);
        }

        $data = $request->validate([
            'title'       => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'assigned_to' => 'nullable|string|max:255',
            'due_date'    => 'nullable|date_format:Y-m-d',
            'priority'    => 'nullable|string|in:low,medium,high,critical',
            'status'      => 'nullable|string|in:pending,in_progress,completed,cancelled',
            'category'    => 'nullable|string|in:logistics,venue,equipment,team,marketing,finance,other',
            'order'       => 'nullable|integer|min:0',
        ]);

        if (isset($data['status'])) {
            $data['completed_at'] = $data['status'] === 'completed' ? now() : null;
        }

        $task->update($data);

        return $this->success($task->fresh());
    }

    public function updateStatus(Request $request, int $eventId, int $id): JsonResponse
    {
        $task = ProductionTask::where('event_id', $eventId)->find($id);
        if (!$task) {
            return $this->error('Tarefa não encontrada', 404);
        }

        $data = $request->validate([
            'status' => 'required|string|in:pending,in_progress,completed,cancelled',
        ]);

        $data['completed_at'] = $data['status'] === 'completed' ? now() : null;

        $task->update($data);

        return $this->success($task->fresh());
    }

    public function reorder(Request $request, int $eventId): JsonResponse
    {
        $event = Event::find($eventId);
        if (!$event) {
            return $this->error('Evento não encontrado', 404);
        }

        $data = $request->validate([
            'tasks' => 'required|array',
            'tasks.*.id'    => 'required|integer|exists:production_tasks,id',
            'tasks.*.order' => 'required|integer|min:0',
        ]);

        foreach ($data['tasks'] as $item) {
            ProductionTask::where('event_id', $eventId)
                ->where('id', $item['id'])
                ->update(['order' => $item['order']]);
        }

        return $this->success(['message' => 'Ordem atualizada']);
    }

    public function destroy(int $eventId, int $id): JsonResponse
    {
        $task = ProductionTask::where('event_id', $eventId)->find($id);
        if (!$task) {
            return $this->error('Tarefa não encontrada', 404);
        }

        $task->delete();

        return $this->success(['message' => 'Tarefa removida com sucesso']);
    }
}
