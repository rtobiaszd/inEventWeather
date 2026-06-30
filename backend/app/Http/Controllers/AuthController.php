<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $user = User::where('username', $request->username)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return $this->error('Usuário ou senha incorretos', 401);
        }

        $user->tokens()->where('expires_at', '<', now())->delete();

        $token = $user->createToken('auth-token', ['*'], now()->addHours(24))->plainTextToken;

        return $this->success([
            'token' => $token,
            'user'  => ['id' => $user->id, 'username' => $user->username, 'name' => $user->name],
        ]);
    }

    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'name'     => 'required|string|max:100',
            'username' => ['required', 'string', 'min:3', 'max:50', 'unique:users', 'regex:/^[a-zA-Z0-9_]+$/'],
            'password' => 'required|string|min:6',
        ]);

        $user = User::create([
            'name'     => $request->name,
            'username' => $request->username,
            'password' => $request->password,
        ]);

        $token = $user->createToken('auth-token', ['*'], now()->addHours(24))->plainTextToken;

        return $this->success([
            'token' => $token,
            'user'  => ['id' => $user->id, 'username' => $user->username, 'name' => $user->name],
        ], 201);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        return $this->success(['message' => 'Logout realizado com sucesso']);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        return $this->success([
            'id'         => $user->id,
            'username'   => $user->username,
            'name'       => $user->name,
            'created_at' => $user->created_at,
        ]);
    }
}
