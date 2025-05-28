<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use App\Http\Controllers\BaseController as BaseController;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends BaseController
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password)
        ]);

        $token = JWTAuth::fromUser($user);

        return $this->sendResponse(['user' => $user, 'token' => $token], 'Usuario registrado con éxito', 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->only(['email', 'password']);

        $user = User::where('email', $credentials['email'])->first();
        if (!$user) {
            return $this->sendError('Usuario no encontrado', ['El usuario con ese email no existe.'], 404);
        }

        if (!$token = JWTAuth::attempt($credentials)) {
            return $this->sendError('Credenciales inválidas', ['El Email o la Contraseña son incorrectos.'], 401);
        }

        if ($user->status !== 1) {
            return $this->sendError('Acceso denegado.', ['Su cuenta no está activa. Contacte al administrador.'], 403);
        }

        $data = [
            'token' => $token,
            'name' => $user->name,
            'email' => $user->email,
        ];

        return $this->sendResponse($data, 'Inicio de sesión exitoso.');
    }

    public function logout(): JsonResponse
    {
        try {
            JWTAuth::invalidate(JWTAuth::getToken());
            return $this->sendResponse([], 'Sesión cerrada con éxito.');
        } catch (\Exception $e) {
            return $this->sendError('Error al cerrar sesión.', [$e->getMessage()], 500);
        }
    }
}
