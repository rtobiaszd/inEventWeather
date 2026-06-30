<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

abstract class Controller
{
    protected function success(mixed $data, int $status = 200): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $data], $status);
    }

    protected function error(string $message, int $status = 400): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message], $status);
    }
}
