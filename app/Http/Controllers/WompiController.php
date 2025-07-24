<?php

namespace App\Http\Controllers;

use App\Mail\OrderCreated;
use App\Mail\OrderFallbackCreated;
use App\Models\Order;
use App\Models\Products\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

        $user = Auth::user();

        $secretKey = config('services.wompi.private_key');

        $response = Http::withToken($secretKey)
            ->get("https://sandbox.wompi.co/v1/transactions/{$id}");

        if (!$response->successful()) {
            return $this->sendError('No se pudo consultar la transacción.', [], 500);
        }

        $data = $response['data'];
        $status = $data['status'];

        if ($status !== 'APPROVED') {
            return $this->sendResponse(['status' => $status], "Transacción no aprobada");
        }

        // Validar que no se haya procesado antes
        $existingOrder = Order::where('transaction_id', $id)->first();
        if ($existingOrder) {
            return $this->sendResponse([
                'status' => $status,
                'message' => 'La orden ya fue creada previamente.',
                'order' => $existingOrder->load('products'),
            ], 'Orden ya existe');
        }

        try {
            DB::beginTransaction();

            // Dirección predeterminada
            $address = $user->addresses()->where('is_default', true)->firstOrFail();

            // Obtener carrito con productos
            $cart = $user->cart()->with('products')->first();

            if (!$cart || $cart->products->isEmpty()) {
                throw new \Exception('El carrito está vacío.');
            }

            // Preparar productos y cálculos
            $cartItems = [];
            $subtotalSinIVA = 0;

            foreach ($cart->products as $product) {
                $priceConIVA = ($product->original_price && $product->original_price > 0 && $product->original_price < $product->price)
                    ? $product->original_price
                    : $product->price;

                $quantity = $product->pivot->quantity;

                // Validar stock antes de crear la orden
                if ($product->stock_count !== null && $quantity > $product->stock_count) {
                    throw new \Exception("No hay suficiente stock para '{$product->name}'");
                }

                $precioSinIVA = $priceConIVA / 1.19;
                $totalItemSinIVA = $precioSinIVA * $quantity;

                $subtotalSinIVA += $totalItemSinIVA;

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

            $tax = $subtotalSinIVA * 0.19;
            $shipping = $subtotalSinIVA >= 126050.42 ? 0 : $baseShipping;
            $total = $subtotalSinIVA + $tax + $shipping;

            // Crear orden
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
                'transaction_id' => $id,
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

            // Limpiar carrito
            $cart->products()->detach();

            DB::commit();

            return $this->sendResponse([
                'status' => 'APPROVED',
                'order' => $order->load('products'),
                'transaction' => $data,
            ], 'Orden creada exitosamente.');
        } catch (\Throwable $e) {
            DB::rollBack();

            try {
                // Crear orden mínima para trazabilidad
                $fallbackOrder = Order::create([
                    'user_id' => $user->id,
                    'payment_method' => 'wompi',
                    'payment_status' => 'approved',
                    'status' => 'error',
                    'shipping_method' => null,
                    'shipping_cost' => 0,
                    'tax' => 0,
                    'subtotal' => 0,
                    'total' => 0,
                    'transaction_id' => $id,
                    'note' => 'Orden generada automáticamente tras fallo en el procesamiento. Se requiere revisión manual.',
                ]);

                Mail::to($user->email)->queue(new OrderFallbackCreated($fallbackOrder));

                $fallbackOrder->statusLogs()->create([
                    'user_id' => null,
                    'status' => 'error',
                    'message' => 'Orden generada con error. Revisar detalles manualmente.',
                    'tracking_url' => null,
                ]);
            } catch (\Throwable $e2) {
                // Si incluso la orden mínima falla, se loguea como fallo crítico
                Log::critical("Fallo crítico: no se pudo crear orden fallback para transacción [{$id}]: " . $e2->getMessage());
            }

            // Log para desarrolladores
            Log::error("Error al crear orden tras transacción aprobada [{$id}]: " . $e->getMessage());

            return $this->sendError(
                'Tu pago fue aprobado, pero hubo un error al generar tu orden. Hemos registrado el incidente. Por favor guarda este ID de transacción para cualquier reclamo: ' . $id,
                ['transaction_id' => $id, 'error' => $e->getMessage()],
                500
            );
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
        $shipping = $subtotalSinIVA >= 126050.42 ? 0 : 15000;
        $total = $subtotalSinIVA + $tax + $shipping;

        // Comparar con lo recibido en el request
        if (
            round($request->subtotal, 2) != round($subtotalSinIVA, 2) ||
            round($request->iva, 2) != round($tax, 2) ||
            round($request->shipping, 2) != round($shipping, 2) ||
            round($request->total, 2) != round($total, 2)
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
