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

    public function handleWompiWebhook(Request $request)
    {
        Log::info('Webhook de Wompi recibido', [
            'headers' => $request->headers->all(),
            'payload' => $request->all()
        ]);

        $event = $request->input('event');
        $transaction = $request->input('data.transaction');

        // Solo nos interesa cuando la transacción es aprobada
        if ($event !== 'transaction.updated' || ($transaction['status'] ?? null) !== 'APPROVED') {
            return response()->json(['message' => 'Evento no procesado'], 200);
        }

        $transactionId = $transaction['id'];
        $customerEmail = $transaction['customer_email'] ?? null;

        if (!$customerEmail) {
            Log::warning("Webhook recibido sin correo: transacción [$transactionId]");
            return response()->json(['message' => 'Falta correo del cliente'], 400);
        }

        $user = User::where('email', $customerEmail)->first();
        if (!$user) {
            Log::warning("Usuario no encontrado para transacción [$transactionId] con email [$customerEmail]");
            return response()->json(['message' => 'Usuario no encontrado'], 404);
        }

        $order = $this->crearOrdenDesdeTransaccion($transaction, $user);

        if (!$order) {
            return response()->json(['message' => 'No se pudo crear la orden (ya existía o hubo error)'], 200);
        }

        return response()->json(['message' => 'Orden creada exitosamente'], 200);
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
        $baseShipping = in_array($department, $caribbeanDepartments) ? 9000 : 15000;
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

        $expiresAt = now('UTC')->addMinutes(5)->format('Y-m-d\TH:i:s');

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
                $expiraEn = now()->addMinutes(5)->toIso8601String();

                Cache::put("link_pago_bloqueado_user_{$user->id}", [
                    'payment_link_id' => $linkId,
                    'expires_at' => $expiraEn
                ], now()->addMinutes(5));

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

    public function crearOrdenDesdeTransaccion(array $data, User $user)
    {
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

        $transactionId = $data['id'];

        // Validar que no se haya procesado antes
        if (Order::where('transaction_id', $transactionId)->exists()) {
            return null; // Orden ya existe
        }

        DB::beginTransaction();

        try {
            $address = $user->addresses()->where('is_default', true)->firstOrFail();
            $cart = $user->cart()->with('products')->first();

            if (!$cart || $cart->products->isEmpty()) {
                throw new \Exception('El carrito está vacío.');
            }

            $cartItems = [];
            $subtotalSinIVA = 0;

            foreach ($cart->products as $product) {
                $priceConIVA = ($product->original_price && $product->original_price > 0 && $product->original_price < $product->price)
                    ? $product->original_price
                    : $product->price;

                $quantity = $product->pivot->quantity;

                if ($product->stock_count !== null && $quantity > $product->stock_count) {
                    throw new \Exception("No hay suficiente stock para '{$product->name}'");
                }

                $precioSinIVA = $priceConIVA / 1.19;
                $subtotalSinIVA += $precioSinIVA * $quantity;

                $cartItems[] = [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'price' => $priceConIVA,
                    'quantity' => $quantity,
                    'total' => $priceConIVA * $quantity,
                ];
            }

            $department = strtolower($address->state ?? '');
            $baseShipping  = in_array($department, $caribbeanDepartments) ? 9000 : 15000;
            $shipping = $subtotalSinIVA >= 126050.42 ? 0 : $baseShipping;
            $tax = $subtotalSinIVA * 0.19;
            $total = $subtotalSinIVA + $tax + $shipping;

            $order = Order::create([
                'user_id' => $user->id,
                'address_id' => $address->id,
                'payment_method' => 'wompi',
                'payment_status' => 'approved',
                'status' => 'processing',
                'shipping_method' => 'standard',
                'shipping_cost' => $shipping,
                'tax' => $tax,
                'subtotal' => $subtotalSinIVA,
                'total' => $total,
                'transaction_id' => $transactionId,
            ]);

            Mail::to($user->email)->queue(new OrderCreated($order));

            $order->statusLogs()->create([
                'user_id' => null,
                'status' => 'processing',
                'message' => 'Orden generada automáticamente tras aprobación del pago.',
                'tracking_url' => null,
            ]);

            foreach ($cartItems as $item) {
                $order->products()->attach($item['product_id'], [
                    'product_name' => $item['product_name'],
                    'price' => $item['price'],
                    'quantity' => $item['quantity'],
                    'total' => $item['total'],
                ]);

                // Descontar stock
                $product = Product::find($item['product_id']);
                if ($product && $product->stock_count !== null) {
                    $product->decrement('stock_count', $item['quantity']);
                }
            }

            // Limpiar y desbloquear carrito
            $cart->products()->detach();
            $cart->locked = false;
            $cart->save();

            DB::commit();

            return $order;
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error("Error al crear orden desde transacción [$transactionId]: " . $e->getMessage());

            // Fallback para trazabilidad
            Order::create([
                'user_id' => $user->id,
                'payment_method' => 'wompi',
                'payment_status' => 'approved',
                'status' => 'error',
                'shipping_method' => null,
                'shipping_cost' => 0,
                'tax' => 0,
                'subtotal' => 0,
                'total' => 0,
                'transaction_id' => $transactionId,
                'note' => 'Error al generar la orden. Revisión manual necesaria.',
            ]);

            return null;
        }
    }
}
