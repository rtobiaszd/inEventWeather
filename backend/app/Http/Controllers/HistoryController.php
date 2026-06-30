<?php

namespace App\Http\Controllers;

use App\Models\WeatherHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HistoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $limit   = min(200, max(1, (int) $request->input('limit', 50)));
        $history = WeatherHistory::orderByDesc('created_at')->limit($limit)->get();

        return $this->success($history);
    }
}
