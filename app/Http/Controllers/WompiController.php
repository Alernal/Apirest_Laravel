<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WompiController extends BaseController
{
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

        $user = $order->user;

        if ($status === 'APPROVED') {
            $order->update([
                'payment_status' => 'approved',
                'status' => 'processing',
            ]);

            if ($user) {
                $cart = $user->cart;
                if ($cart) {
                    $cart->products()->detach();
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

    public function generarLinkPago(Request $request)
    {
        $request->validate([
            'subtotal' => 'required|numeric|min:0',
            'iva' => 'required|numeric|min:0',
            'shipping' => 'required|numeric|min:0',
            'total' => 'required|numeric|min:1'
        ]);

        $amountInCents = (int)($request['total'] * 100);
        $ivaInCents = (int)($request['iva'] * 100);

        $descripcion = "Subtotal: $" . number_format($request['subtotal'], 0, ',', '.') .
            ", IVA: $" . number_format($request['iva'], 0, ',', '.') .
            ", Envío: $" . number_format($request['shipping'], 0, ',', '.');

        $payload = [
            'name' => 'Pago en NARUE - Accesorios',
            'description' => $descripcion,
            'single_use' => true,
            'collect_shipping' => false,
            'currency' => 'COP',
            'amount_in_cents' => $amountInCents,
            'expires_at' => now()->addMinutes(10)->toIso8601String(),
            'redirect_url' => env('WOMPI_REDIRECT_URL', 'http://localhost:3000/checkout/confirmacion'),
            'image_url' => null,
            'taxes' => [
                [
                    'type' => 'VAT',
                    "percentage" => 19,
                    'amount_in_cents' => $ivaInCents,
                ]
            ],
            'customer_data' => [
                'customer_references' => [
                    [
                        'label' => 'Correo electrónico',
                        'is_required' => true
                    ],
                    [
                        'label' => 'Documento de identidad',
                        'is_required' => true
                    ]
                ]
            ],
        ];

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . env('WOMPI_PRIVATE_KEY'),
                'Content-Type' => 'application/json',
            ])->post('https://production.wompi.co/v1/payment_links', $payload);

            $body = $response->json();

            if ($response->successful() && isset($body['data']['id'])) {
                $linkId = $body['data']['id'];

                return response()->json([
                    'url' => "https://checkout.wompi.co/l/{$linkId}",
                    'payment_link_id' => $linkId,
                    'expires_at' => $body['data']['expires_at'] ?? null,
                ]);
            } else {
                Log::error('Error al generar link de Wompi', [
                    'payload' => $payload,
                    'status' => $response->status(),
                    'body' => $body,
                ]);

                return response()->json([
                    'message' => 'Error al generar el enlace de pago',
                    'error' => $body,
                ], 500);
            }
        } catch (\Exception $e) {
            Log::error('Excepción al generar link de Wompi', ['exception' => $e]);

            return response()->json([
                'message' => 'Hubo un problema al comunicarse con Wompi',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
