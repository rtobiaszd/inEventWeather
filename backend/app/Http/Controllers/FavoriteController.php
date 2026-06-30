<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\FavoriteCity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class FavoriteController extends Controller
{
    public function index(): JsonResponse
    {
        $favorites = FavoriteCity::orderBy('city')->get();

        if ($favorites->isEmpty()) {
            return $this->success([]);
        }

        // Busca eventos futuros de todas as cidades favoritas em uma única query
        $cityNames = $favorites->map(fn ($f) => strtolower($f->city))->unique()->values();

        $placeholders = implode(',', array_fill(0, $cityNames->count(), '?'));

        $eventsByCity = Event::whereRaw("LOWER(city) IN ({$placeholders})", $cityNames->toArray())
            ->where('event_date', '>=', Carbon::today()->format('Y-m-d'))
            ->orderBy('event_date')
            ->get(['id', 'name', 'city', 'event_date', 'event_time', 'type'])
            ->groupBy(fn ($e) => strtolower($e->city));

        $favorites->each(function ($fav) use ($eventsByCity) {
            $fav->events = $eventsByCity->get(strtolower($fav->city), collect())->values();
        });

        return $this->success($favorites);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'city'    => 'required|string|max:100',
            'country' => 'nullable|string|max:10',
        ]);

        $data['country'] = strtoupper($data['country'] ?? 'BR');

        $exists = FavoriteCity::where('city', $data['city'])
            ->where('country', $data['country'])
            ->exists();

        if ($exists) {
            return $this->error('Cidade já está nos favoritos', 409);
        }

        return $this->success(FavoriteCity::create($data), 201);
    }

    public function destroy(int $id): JsonResponse
    {
        $favorite = FavoriteCity::find($id);

        if (!$favorite) {
            return $this->error('Favorito não encontrado', 404);
        }

        $favorite->delete();

        return $this->success(['message' => 'Cidade removida dos favoritos']);
    }
}
