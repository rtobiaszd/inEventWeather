<?php

namespace App\Http\Controllers;

use App\Models\FavoriteCity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    public function index(): JsonResponse
    {
        return $this->success(FavoriteCity::orderBy('city')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'city'    => 'required|string|max:100',
            'country' => 'required|string|max:10',
        ]);

        $data['country'] = strtoupper($data['country']);

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
