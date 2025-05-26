<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use App\Http\Controllers\BaseController as BaseController;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends BaseController
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password)
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return $this->sendResponse(['user' => $user, 'token' => $token], 'Usuario registrado con éxito', 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        if (!Auth::attempt(['email' => $request->email, 'password' => $request->password])) {
            return $this->sendError('Credenciales inválidas', ['El Email o la Contraseña son incorrectos.'], 401);
        }

        $user = Auth::user();
        if ($user->status !== 1) {
            return $this->sendError('Acceso denegado.', ['Su cuenta no está activa. Contacte al administrador.'], 403);
        }
        $token = $user->createToken('auth_token')->plainTextToken;

        $data = [
            'token' => $token,
            'name' => $user->name,
            'email' => $user->email,
        ];

        return $this->sendResponse($data, 'Inicio de sesión exitoso.');
    }

    public function logout(): JsonResponse
    {
        $user = Auth::user();
        $user->currentAccessToken()->delete(); 

        return $this->sendResponse([], 'Sesión cerrada con éxito.');
    }
}
