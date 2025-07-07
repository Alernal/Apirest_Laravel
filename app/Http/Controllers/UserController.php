<?php

namespace App\Http\Controllers;

use App\Http\Requests\User\UpdateUserRequest;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UserController extends BaseController
{
    public function index()
    {
        $users = User::all();
        $users->load(['addresses', 'cart', 'orders']);

        return $this->sendResponse($users, "Listado de usuarios", 200);
    }

    public function update(UpdateUserRequest $request)
    {
        $user = Auth::user();
        $data = $request->validated();

        $user->update($data);

        return $this->sendResponse($user, 'Información del usuario actualizada correctamente');
    }

    public function show($id)
    {
        $user = User::with([
            'addresses',
            'orders',
            'cart.products' => function ($query) {
                $query->select('products.*'); // campos del producto
            }
        ])->findOrFail($id);

        return $this->sendResponse($user, 'Información del usuario');
    }


    public function changePassword(Request $request)
    {
        $request->validate([
            'current' => ['required', 'string'],
            'new' => ['required', 'string', 'min:8', 'regex:/[A-Z]/', 'regex:/[0-9]/'],
        ]);

        $user = Auth::user();

        // Verificar contraseña actual
        if (!Hash::check($request->input('current'), $user->password)) {
            return $this->sendError('La contraseña actual es incorrecta', [], 422);
        }

        // Cambiar contraseña
        $user->password = bcrypt($request->input('new'));
        $user->save();

        return $this->sendResponse(null, 'Contraseña actualizada correctamente');
    }

    public function uploadProfileImage(Request $request)
    {
        $request->validate([
            'image' => ['required', 'image', 'max:2048'], // 2MB max
        ]);

        $user = Auth::user();

        // Eliminar imagen anterior si existe y no es una por defecto
        if ($user->profile_image_url && Storage::disk('public')->exists(str_replace('/storage/', '', $user->profile_image_url))) {
            Storage::disk('public')->delete(str_replace('/storage/', '', $user->profile_image_url));
        }

        // Guardar nueva imagen
        $file = $request->file('image');
        $filename = 'profile_' . $user->id . '_' . Str::uuid() . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs('profiles', $filename, 'public');

        // Actualizar el usuario
        $user->profile_image_url = '/storage/' . $path;
        $user->save();

        return $this->sendResponse($user, 'Imagen de perfil actualizada correctamente');
    }

    public function verifyEmail(Request $request, $id, $hash)
    {
        $user = User::findOrFail($id);

        if (! hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            return view('auth.verification.message', [
                'title' => 'Verificación Fallida',
                'message' => 'El enlace de verificación no es válido.',
                'status' => 'error',
            ]);
        }

        if ($user->hasVerifiedEmail()) {
            if (! $user->is_verified) {
                $user->is_verified = true;
                $user->save();
            }

            return view('auth.verification.message', [
                'title' => 'Correo ya verificado',
                'message' => 'Este correo electrónico ya fue verificado previamente.',
                'status' => 'info',
            ]);
        }

        $user->markEmailAsVerified();
        $user->is_verified = true;
        $user->save();
        event(new Verified($user));

        return view('auth.verification.message', [
            'title' => 'Correo verificado',
            'message' => 'Tu correo ha sido verificado exitosamente.',
            'status' => 'success',
        ]);
    }

    public function resendVerificationEmail(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        $user = User::where('email', $request->input('email'))->first();

        if ($user->hasVerifiedEmail()) {
            return view('auth.verification.message', [
                'title' => 'Ya verificado',
                'message' => 'Este correo ya fue verificado.',
                'status' => 'info',
            ]);
        }

        $user->sendEmailVerificationNotification();

        return view('auth.verification.message', [
            'title' => 'Correo reenviado',
            'message' => 'Se ha enviado un nuevo enlace de verificación a tu correo.',
            'status' => 'success',
        ]);
    }
}
