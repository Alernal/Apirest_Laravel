<?php

namespace App\Http\Controllers;

use App\Mail\OrderCreated;
use App\Mail\OrderFallbackCreated;
use App\Models\Order;
use App\Models\Products\Product;
use App\Models\User;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

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
        try {
            $user = Auth::user();

            $secretKey = config('services.wompi.private_key');

            $response = Http::withToken($secretKey)
                ->get("https://sandbox.wompi.co/v1/transactions/{$id}");

            if (!$response->successful()) {
                return $this->sendError('No se pudo consultar la transacción.', [], 500);
            }

            $data = $response['data'];
            $status = $data['status'];

            // Buscar si la orden ya existe
            $order = Order::where('transaction_id', $id)->with('products')->first();

            return $this->sendResponse([
                'status' => $status,
                'order' => $order,
                'transaction' => $data,
            ], 'Estado de la transacción.');
        } catch (\Throwable $e) {
            Log::error("Error en getTransaction({$id}): " . $e->getMessage());
            return $this->sendError('Error al consultar la transacción.', [], 500);
        }
    }

    public function generarLinkPago(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'subtotal' => 'required|numeric|min:0',
            'iva' => 'required|numeric|min:0',
            'shipping' => 'required|numeric|min:0',
            'total' => 'required|numeric|min:1'
        ]);

        // VALIDACION
        $cart = $user->cart()->with('products')->first();
        if (!$cart || $cart->products->isEmpty()) {
            return response()->json(['message' => 'El carrito está vacío.'], 422);
        }

        // Validar dirección
        $address = $user->addresses()->where('is_default', true)->first();
        if (!$address) {
            return response()->json(['message' => 'No tienes una dirección predeterminada configurada.'], 422);
        }

        // Calcular valores reales desde el carrito
        $subtotalSinIVA = 0;
        foreach ($cart->products as $product) {
            $price = ($product->original_price && $product->original_price > 0 && $product->original_price < $product->price)
                ? $product->original_price
                : $product->price;

            $quantity = $product->pivot->quantity;

            if ($product->stock_count !== null && $quantity > $product->stock_count) {
                return response()->json(['message' => "Stock insuficiente para el producto: {$product->name}"], 422);
            }

            $precioSinIVA = $price / 1.19;
            $subtotalSinIVA += $precioSinIVA * $quantity;
        }

        $tax = round($subtotalSinIVA * 0.19, 2);
        $caribbeanDepartments = [
            'atlántico',
            'bolívar',
            'cesar',
            'córdoba',
            'la guajira',
            'magdalena',
            'sucre',
            'san andrés y providencia',
        ];

        $department = strtolower($address->state ?? '');
        $city = strtolower($address->city ?? '');

        // Excepción para Sincelejo (Sucre)
        if ($department === 'sucre' && $city === 'sincelejo') {
            $baseShipping = 5000;
        } else {
            $baseShipping = in_array($department, $caribbeanDepartments) ? 9000 : 15000;
        }

        $shipping = $subtotalSinIVA >= 126050.42 ? 0 : $baseShipping;

        $total = $subtotalSinIVA + $tax + $shipping;

        // Comparar con lo recibido en el request
        if (
            round($request->subtotal, 1) != round($subtotalSinIVA, 1) ||
            round($request->iva, 1) != round($tax, 1) ||
            round($request->shipping, 1) != round($shipping, 1) ||
            round($request->total, 1) != round($total, 1)
        ) {
            return response()->json([
                'message' => 'Los valores del pago no coinciden con los calculados en el servidor.',
                'calculado' => compact('subtotalSinIVA', 'tax', 'shipping', 'total'),
                'enviado' => $request->only(['subtotal', 'iva', 'shipping', 'total'])
            ], 422);
        }

        $amountInCents = (int)($request['total'] * 100);
        $ivaInCents = (int)($request['iva'] * 100);

        $descripcion = "Subtotal: $" . number_format($request['subtotal'], 0, ',', '.') .
            ", IVA: $" . number_format($request['iva'], 0, ',', '.') .
            ", Envío: $" . number_format($request['shipping'], 0, ',', '.');

        $expiresAt = now('UTC')->addMinutes(120)->format('Y-m-d\TH:i:s');

        $payload = [
            'name' => 'NURAE',
            'description' => $descripcion,
            'single_use' => true,
            'collect_shipping' => false,
            'currency' => 'COP',
            'amount_in_cents' => $amountInCents,
            'expires_at' => $expiresAt,
            'redirect_url' => env('WOMPI_REDIRECT_URL', 'https://nurae.alernal.com.co/checkout/confirmacion'),
            'image_url' => null,
            'taxes' => [
                [
                    'type' => 'VAT',
                    "percentage" => 19,
                    'amount_in_cents' => $ivaInCents,
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

                // Crear la orden en base al link de pago generado
                $order = Order::create([
                    'user_id' => $user->id,
                    'address_id' => $address->id,
                    'payment_method' => 'wompi',
                    'payment_status' => 'pending',
                    'status' => 'pending',
                    'shipping_method' => 'standard',
                    'shipping_cost' => $shipping,
                    'tax' => $tax,
                    'subtotal' => $subtotalSinIVA,
                    'total' => $total,
                    'reference' => $linkId,
                    'payment_link' => "https://checkout.wompi.co/l/{$linkId}",
                    'transaction_id' => null,
                ]);

                foreach ($cart->products as $product) {
                    $price = ($product->original_price && $product->original_price > 0 && $product->original_price < $product->price)
                        ? $product->original_price
                        : $product->price;

                    $quantity = $product->pivot->quantity;

                    $order->products()->attach($product->id, [
                        'product_name' => $product->name,
                        'price' => $price,
                        'quantity' => $quantity,
                        'total' => $price * $quantity,
                    ]);
                }

                $order->statusLogs()->create([
                    'user_id' => null,
                    'status' => 'pending',
                    'message' => 'Orden generada. Pendiente de pago a través de Wompi.',
                    'tracking_url' => null,
                ]);

                return response()->json([
                    'url' => "https://checkout.wompi.co/l/{$linkId}",
                    'payment_link_id' => $linkId,
                    'expires_at' => $body['data']['expires_at'] ?? null,
                    'order_id' => $order->id,
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

    public function handleWompiWebhook(Request $request)
    {
        Log::info('Webhook de Wompi recibido', [
            'headers' => $request->headers->all(),
            'payload' => $request->all()
        ]);

        $event = $request->input('event');
        $transaction = $request->input('data.transaction');

        if ($event !== 'transaction.updated' || !$transaction) {
            return response()->json(['message' => 'Evento no procesado'], 200);
        }

        $reference = $transaction['reference'] ?? null;
        if (!$reference) {
            Log::warning("Webhook recibido sin referencia válida", ['transaction' => $transaction]);
            return response()->json(['message' => 'Referencia no encontrada'], 400);
        }

        $order = Order::where('reference', $reference)->first();
        if (!$order) {
            Log::warning("Orden no encontrada para referencia [$reference]");
            return response()->json(['message' => 'Orden no encontrada'], 404);
        }

        $this->crearOrdenDesdeTransaccion($transaction, $order->user);

        return response()->json(['message' => 'Orden actualizada'], 200);
    }

    public function crearOrdenDesdeTransaccion(array $data, User $user)
    {
        $reference = $data['reference'] ?? null;
        $transactionId = $data['id'] ?? null;
        $status = $data['status'] ?? null;

        if (!$reference || !$transactionId || !$status) {
            Log::warning("Datos incompletos en transacción", ['data' => $data]);
            return null;
        }

        $order = Order::where('reference', $reference)->first();

        if (!$order) {
            Log::warning("Orden no encontrada con referencia [$reference]");
            return null;
        }

        // Ya se actualizó antes
        if ($order->transaction_id === $transactionId) {
            return $order;
        }

        $order->transaction_id = $transactionId;

        if ($order->status === 'pending') {
            if ($status === 'APPROVED') {
                $order->status = 'processing';
                $order->payment_status = 'approved';

                // Descontar stock y desbloquear carrito
                $cart = $user->cart()->with('products')->first();
                if ($cart) {
                    foreach ($order->products as $product) {
                        $orderedQty = $product->pivot->quantity;

                        if ($product->stock_count !== null) {
                            $product->decrement('stock_count', $orderedQty);
                        }
                    }

                    $cart->products()->detach();
                    $cart->locked = false;
                    $cart->save();
                }

                // Enviar correo y registrar log
                Mail::to($user->email)->queue(new OrderCreated($order));

                $order->statusLogs()->create([
                    'user_id' => null,
                    'status' => 'processing',
                    'message' => 'Pago aprobado por Wompi. Orden confirmada automáticamente.',
                ]);
            } elseif ($status === 'DECLINED' || $status === 'VOIDED') {
                $order->status = 'cancelled';

                $order->statusLogs()->create([
                    'user_id' => null,
                    'status' => 'cancelled',
                    'message' => "Transacción Wompi marcada como $status.",
                ]);
            }
        }

        $order->save();

        return $order;
    }
}
