<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class WompiController extends BaseController
{
    // public function getAcceptanceToken()
    // {
    //     $publicKey = config('services.wompi.public_key');

    //     $response = Http::get("https://sandbox.wompi.co/v1/merchants/{$publicKey}");

    //     if ($response->successful()) {
    //         return response()->json([
    //             'acceptance_token' => $response['data']['presigned_acceptance']['acceptance_token'],
    //             'accept_personal_auth' => $response['data']['presigned_personal_data_auth']['acceptance_token'],
    //             'public_key' => $publicKey,
    //         ]);
    //     }

    //     return response()->json(['error' => 'No se pudo obtener el token'], 500);
    // }

    // public function generateSignature(Request $request)
    // {
    //     $validated = $request->validate([
    //         'amount_in_cents' => 'required|integer',
    //         'reference' => 'required|string',
    //         'expiration_time' => 'nullable|date_format:Y-m-d\TH:i:sP', // formato ISO 8601
    //     ]);

    //     $reference = $validated['reference'];
    //     $amount = $validated['amount_in_cents'];
    //     $currency = 'COP';
    //     $expiration = $validated['expiration_time'] ?? '';
    //     $integritySecret = config('services.wompi.integrity');

    //     // Concatenar en el orden exigido
    //     $stringToSign = $reference . $amount . $currency . $expiration . $integritySecret;

    //     // SHA256 puro
    //     $signature = hash('sha256', $stringToSign);

    //     return response()->json([
    //         'signature' => $signature,
    //     ]);
    // }

    public function getAcceptanceToken(): ?string
    {
        $publicKey = config('services.wompi.public_key');

        $res = Http::get("https://sandbox.wompi.co/v1/merchants/{$publicKey}");

        if ($res->successful()) {
            return $res['data']['presigned_acceptance']['acceptance_token'];
        }

        return null;
    }

    public function generateSignature(string $reference, int $amountInCents, string $expiration = ''): string
    {
        $string = $reference . $amountInCents . 'COP' . $expiration . config('services.wompi.integrity');
        return hash('sha256', $string);
    }

    public function createTransaction(array $data): array
    {
        $publicKey = config('services.wompi.public_key');
        $token = $this->getAcceptanceToken();

        $signature = $this->generateSignature(
            $data['reference'],
            $data['amount_in_cents'],
            $data['expiration_time'] ?? ''
        );

        $payload = [
            'acceptance_token' => $token,
            'amount_in_cents' => $data['amount_in_cents'],
            'currency' => 'COP',
            'customer_email' => $data['customer_email'],
            'payment_method' => $data['payment_method'],
            'customer_data' => $data['customer_data'],
            'reference' => $data['reference'],
            'signature' => $signature,
        ];

        if (!empty($data['expiration_time'])) {
            $payload['expiration_time'] = $data['expiration_time'];
        }

        $res = Http::withToken($publicKey)
            ->post('https://sandbox.wompi.co/v1/transactions', $payload);

        if (!$res->successful() || empty($res['data']['id'])) {
            throw new \Exception("Error creando transacción en Wompi");
        }

        return $res['data'];
    }

    public function handleWebhook(Request $request)
    {
        $event = $request->input('event');
        $data = $request->input('data');

        if (!$data || empty($data['id'])) {
            return response()->json(['error' => 'Datos de transacción inválidos'], 400);
        }

        // Buscar la orden por el transaction_id
        $order = Order::where('transaction_id', $data['id'])->first();

        if (!$order) {
            return response()->json(['error' => 'Orden no encontrada'], 404);
        }

        // Actualizar estado de la orden según resultado
        $status = $data['status'];

        $order->update([
            'payment_status' => $status,
            'status' => $status === 'APPROVED' ? 'processing' : 'failed',
        ]);

        return response()->json(['received' => true]);
    }

    public function getTransaction($id)
    {
        $secretKey = config('services.wompi.private_key');

        $response = Http::withToken($secretKey)
            ->get("https://sandbox.wompi.co/v1/transactions/{$id}");

        if (!$response->successful()) {
            return $this->sendError('No se pudo consultar la transacción', [], 500);
        }

        $data = $response['data'];
        $status = $data['status'];

        $order = Order::where('transaction_id', $id)->first();

        if (!$order) {
            return $this->sendError('Orden no encontrada', [], 404);
        }

        $user = $order->user; // Asume que tienes la relación definida en el modelo Order

        if ($status === 'APPROVED') {
            $order->update([
                'payment_status' => 'approved',
                'status' => 'processing',
            ]);

            // Limpiar carrito del usuario
            if ($user) {
                $cart = $user->cart;
                if ($cart) {
                    $cart->products()->detach(); // Elimina los productos del carrito
                }
            }

            return $this->sendResponse([
                'status' => $status,
                'message' => 'Pago aprobado y orden actualizada.',
            ]);
        }

        if (in_array($status, ['REJECTED', 'DECLINED', 'ERROR'])) {
            // Eliminar productos de la orden
            $order->products()->detach();

            // Eliminar la orden
            $order->delete();

            // Limpiar carrito también
            if ($user) {
                $cart = $user->cart;
                if ($cart) {
                    $cart->products()->detach(); // Limpiar productos
                }
            }

            return $this->sendResponse([
                'status' => $status,
                'message' => 'Pago rechazado. Orden eliminada.',
            ]);
        }

        return $this->sendResponse([
            'status' => $status,
            'message' => 'Transacción en estado ' . $status,
        ]);
    }
}
