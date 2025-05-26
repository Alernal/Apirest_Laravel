<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class BaseController extends Controller
{
    /**
     * Enviar respuesta de éxito.
     *
     * @param mixed  $data      Datos de la respuesta
     * @param string $message   Mensaje a mostrar
     * @param int    $code      Código de respuesta HTTP (por defecto 200)
     * @return JsonResponse
     */
    public function sendResponse($data, $message = 'Conexión exitosa', $code = 200): JsonResponse
    {
        return response()->json([
            'status'  => 'success',
            'code'    => $code,
            'message' => $message,
            'data'    => $data,
        ], $code);
    }

    /**
     * Enviar respuesta de error.
     *
     * @param string $message        Mensaje de error
     * @param array  $errorMessages  Errores adicionales (opcional)
     * @param int    $code           Código de error (por defecto 400)
     * @return JsonResponse
     */
    public function sendError($message = 'Conexión exitosa', $errorMessages = [], $code = 400): JsonResponse
    {
        return response()->json([
            'status'  => 'error',
            'code'    => $code,
            'message' => is_array($errorMessages) && count($errorMessages)
                ? implode(' ', $errorMessages)
                : $message,
        ], $code);
    }
}
