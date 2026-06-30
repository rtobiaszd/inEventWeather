<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    public function index(): JsonResponse
    {
        $users = User::orderBy('name')
            ->get(['id', 'name', 'username', 'role', 'permissions', 'is_active', 'created_at', 'updated_at']);

        return $this->success($users);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'     => 'required|string|max:100',
            'username' => 'required|string|min:3|max:50|unique:users|regex:/^[a-zA-Z0-9_]+$/',
            'password' => 'required|string|min:6',
            'role'     => 'required|in:admin,editor,viewer',
        ]);

        $data['is_active'] = true;
        $user = User::create($data);

        return $this->success(
            $user->refresh()->only(['id', 'name', 'username', 'role', 'is_active', 'created_at']),
            201
        );
    }

    public function show(int $id): JsonResponse
    {
        $user = User::find($id, ['id', 'name', 'username', 'role', 'permissions', 'is_active', 'created_at', 'updated_at']);

        if (!$user) {
            return $this->error('Usuário não encontrado', 404);
        }

        return $this->success($user);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $user = User::find($id);

        if (!$user) {
            return $this->error('Usuário não encontrado', 404);
        }

        $data = $request->validate([
            'name'      => 'sometimes|string|max:100',
            'username'  => "sometimes|string|min:3|max:50|regex:/^[a-zA-Z0-9_]+$/|unique:users,username,{$id}",
            'password'  => 'sometimes|string|min:6',
            'role'      => 'sometimes|in:admin,editor,viewer',
            'is_active' => 'sometimes|boolean',
        ]);

        $user->update($data);

        return $this->success(
            $user->fresh(['id', 'name', 'username', 'role', 'permissions', 'is_active', 'updated_at'])
        );
    }

    public function updatePermissions(Request $request, int $id): JsonResponse
    {
        $user = User::find($id);

        if (!$user) {
            return $this->error('Usuário não encontrado', 404);
        }

        $request->validate([
            'permissions' => 'nullable|array',
        ]);

        $user->update(['permissions' => $request->input('permissions')]);

        return $this->success(['message' => 'Permissões atualizadas', 'permissions' => $user->permissions]);
    }

    public function destroy(int $id): JsonResponse
    {
        if (Auth::id() === $id) {
            return $this->error('Você não pode excluir seu próprio usuário', 403);
        }

        $user = User::find($id);

        if (!$user) {
            return $this->error('Usuário não encontrado', 404);
        }

        $user->tokens()->delete();
        $user->delete();

        return $this->success(['message' => 'Usuário removido com sucesso']);
    }
}
