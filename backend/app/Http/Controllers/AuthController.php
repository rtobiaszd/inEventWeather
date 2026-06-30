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

        if (isset($user->is_active) && !$user->is_active) {
            return $this->error('Usuário inativo. Contate o administrador.', 403);
        }

        $user->tokens()->where('expires_at', '<', now())->delete();

        $token = $user->createToken('auth-token', ['*'], now()->addHours(24))->plainTextToken;

        return $this->success([
            'token' => $token,
            'user'  => $this->userPayload($user),
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
            'role'     => 'viewer',
        ]);

        $token = $user->createToken('auth-token', ['*'], now()->addHours(24))->plainTextToken;

        return $this->success([
            'token' => $token,
            'user'  => $this->userPayload($user),
        ], 201);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        return $this->success(['message' => 'Logout realizado com sucesso']);
    }

    public function me(Request $request): JsonResponse
    {
        return $this->success($this->userPayload($request->user()));
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name'     => 'sometimes|string|max:100',
            'password' => 'sometimes|string|min:6',
        ]);

        $user->update($data);

        return $this->success($this->userPayload($user->fresh()));
    }

    private function userPayload(User $user): array
    {
        return [
            'id'          => $user->id,
            'username'    => $user->username,
            'name'        => $user->name,
            'role'        => $user->role ?? 'viewer',
            'permissions' => $user->permissions,
            'is_active'   => $user->is_active ?? true,
            'created_at'  => $user->created_at,
        ];
    }
}
