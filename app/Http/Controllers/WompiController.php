<?php

namespace App\Http\Controllers;

use App\Mail\OrderCreated;
use App\Mail\OrderFallbackCreated;
use App\Mail\PaymentApprovedNoOrder;
use App\Mail\WelcomeGuestAccount;
use App\Models\Order;
use App\Models\Products\Product;
use App\Models\User;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tymon\JWTAuth\Facades\JWTAuth;

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

        try {
            if (!$user = JWTAuth::parseToken()->authenticate()) {
                Log::debug('Token válido pero no se encontró el usuario.');
            } else {
                Log::debug('Usuario autenticado por JWT:', ['user_id' => $user->id]);
                Auth::login($user);
            }
        } catch (\Exception $e) {
            Log::debug('No se pudo autenticar vía JWT:', ['error' => $e->getMessage()]);
        }

        $user = Auth::user();
        Log::debug('Usuario autenticado:', ['user' => $user]);

        $request->validate([
            'subtotal' => 'required|numeric|min:0',
            'iva' => 'required|numeric|min:0',
            'shipping' => 'required|numeric|min:0',
            'total' => 'required|numeric|min:1',
            'items' => 'required|array|min:1',
        ]);

        $items = collect($request->items);
        $productIds = $items->pluck('id');
        $products = Product::whereIn('id', $productIds)->get();

        if ($products->count() !== $items->count()) {
            return response()->json(['message' => 'Uno o más productos no existen.'], 422);
        }

        $subtotalSinIVA = 0;
        foreach ($items as $item) {
            $product = $products->firstWhere('id', $item['id']);
            if (!$product) continue;

            $price = ($product->original_price && $product->original_price > 0 && $product->original_price < $product->price)
                ? $product->original_price
                : $product->price;

            $quantity = (int)$item['quantity'];
            if ($product->stock_count !== null && $quantity > $product->stock_count) {
                return response()->json(['message' => "Stock insuficiente para el producto: {$product->name}"], 422);
            }

            $precioSinIVA = $price / 1.19;
            $subtotalSinIVA += $precioSinIVA * $quantity;
        }

        $tax = round($subtotalSinIVA * 0.19, 2);

        $caribbeanDepartments = ['atlántico', 'bolívar', 'cesar', 'córdoba', 'la guajira', 'magdalena', 'sucre', 'san andrés y providencia'];
        $shipping = 0;
        $address = null;

        if (!$user) {
            $request->validate([
                'guest_info.name' => 'required|string|max:255',
                'guest_info.email' => 'required|email',
                'address.state' => 'required|string|max:100',
                'address.city' => 'required|string|max:100',
                'address.address' => 'required|string|max:255',
            ]);

            $state = strtolower($request->input('address.state'));
            $city = strtolower($request->input('address.city'));

            $baseShipping = ($state === 'sucre' && $city === 'sincelejo') ? 5000 : (in_array($state, $caribbeanDepartments) ? 9000 : 15000);
            $shipping = $subtotalSinIVA >= 126050.42 ? 0 : $baseShipping;

            $total = $subtotalSinIVA + $tax + $shipping;

            if (
                round($request->subtotal, 2) != round($subtotalSinIVA, 2) ||
                round($request->iva, 2) != round($tax, 2) ||
                round($request->shipping, 2) != round($shipping, 2) ||
                round($request->total, 2) != round($total, 2)
            ) {
                return response()->json([
                    'message' => 'Los valores enviados no coinciden con los calculados.',
                    'calculado' => compact('subtotalSinIVA', 'tax', 'shipping', 'total'),
                ], 422);
            }

            $guestEmail = $request->input('guest_info.email');
            $guestName = $request->input('guest_info.name');

            $user = User::firstOrCreate(
                ['email' => $guestEmail],
                [
                    'first_name' => $guestName,
                    'password' => Hash::make(Str::random(10)),
                    'email_verified_at' => now()
                ]
            );

            if (!$user->wasRecentlyCreated) {
                $password = null;
            } else {
                $password = Str::random(10);
                $user->password = Hash::make($password);
                $user->save();

                try {
                    Mail::to($guestEmail)->send(new WelcomeGuestAccount($user, $password));
                } catch (\Throwable $e) {
                    Log::error('No se pudo enviar correo de cuenta invitado', ['error' => $e->getMessage()]);
                }
            }

            $address = $user->addresses()->firstOrCreate(
                ['is_default' => true],
                [
                    'name' => $guestName,
                    'first_name' => $guestName,
                    'last_name' => 'Cliente',
                    'email' => $guestEmail,
                    'phone' => '0000000000',
                    'street_address' => $request->input('address.address'),
                    'city' => $request->input('address.city'),
                    'state' => $request->input('address.state'),
                    'postal_code' => '000000',
                    'country' => 'CO',
                    'is_default' => true
                ]
            );
        } else {
            $cart = $user->cart()->with('products')->first();
            if (!$cart || $cart->products->isEmpty()) {
                return response()->json(['message' => 'El carrito está vacío.'], 422);
            }

            $address = $user->addresses()->where('is_default', true)->first();
            if (!$address) {
                return response()->json(['message' => 'No tienes una dirección predeterminada configurada.'], 422);
            }

            $department = strtolower($address->state);
            $city = strtolower($address->city);
            $baseShipping = ($department === 'sucre' && $city === 'sincelejo') ? 5000 : (in_array($department, $caribbeanDepartments) ? 9000 : 15000);
            $shipping = $subtotalSinIVA >= 126050.42 ? 0 : $baseShipping;
            $total = $subtotalSinIVA + $tax + $shipping;
        }

        $amountInCents = (int)($total * 100);
        $ivaInCents = (int)($tax * 100);

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
            'image_url' => null,
            'taxes' => [[
                'type' => 'VAT',
                'percentage' => 19,
                'amount_in_cents' => $ivaInCents,
            ]],
        ];

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . env('WOMPI_PRIVATE_KEY'),
                'Content-Type' => 'application/json',
            ])->post('https://production.wompi.co/v1/payment_links', $payload);

            $body = $response->json();

            if ($response->successful() && isset($body['data']['id'])) {
                $linkId = $body['data']['id'];

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

                foreach ($items as $item) {
                    $product = $products->firstWhere('id', $item['id']);
                    $price = ($product->original_price && $product->original_price > 0 && $product->original_price < $product->price)
                        ? $product->original_price
                        : $product->price;

                    $order->products()->attach($product->id, [
                        'product_name' => $product->name,
                        'price' => $price,
                        'quantity' => $item['quantity'],
                        'total' => $price * $item['quantity'],
                    ]);
                }

                $order->statusLogs()->create([
                    'user_id' => null,
                    'status' => 'pending',
                    'message' => 'Orden generada. Pendiente de pago.',
                ]);
                
                Mail::to($user->email)->send(new OrderCreated($order));

                return response()->json([
                    'url' => "https://checkout.wompi.co/l/{$linkId}",
                    'payment_link_id' => $linkId,
                    'expires_at' => $body['data']['expires_at'] ?? null,
                    'order_id' => $order->id,
                ]);
            }

            Log::error('Error al generar link de Wompi', [
                'payload' => $payload,
                'status' => $response->status(),
                'body' => $body,
            ]);

            return response()->json(['message' => 'Error al generar el enlace de pago'], 500);
        } catch (\Exception $e) {
            Log::error('Excepción al generar link de Wompi', ['exception' => $e]);
            return response()->json(['message' => 'Error interno al generar link'], 500);
        }
    }

    public function handleWompiWebhook(Request $request)
    {
        Log::info('Webhook de Wompi recibido');

        $event = $request->input('event');
        $transaction = $request->input('data.transaction');

        if ($event !== 'transaction.updated' || !$transaction) {
            return response()->json(['message' => 'Evento no procesado'], 200);
        }

        $reference = $transaction['payment_link_id'] ?? null;
        if (!$reference) {
            Log::warning("Webhook recibido sin referencia válida", ['transaction' => $transaction]);
            return response()->json(['message' => 'Referencia no encontrada'], 400);
        }

        $order = Order::where('reference', $reference)->first();

        if (!$order) {
            Log::error("Orden no encontrada para pago aprobado", [
                'reference' => $reference,
                'transaction_id' => $transaction['id'] ?? null,
                'status' => $status,
                'email' => $transaction['customer_email'] ?? null,
            ]);

            if ($status === 'APPROVED') {
                $email = $transaction['customer_email'] ?? null;
                $name = $transaction['customer_data']['full_name'] ?? 'Cliente';

                if ($email) {
                    try {
                        Mail::to($email)->send(new PaymentApprovedNoOrder(
                            $name,
                            $reference,
                            $transaction['id']
                        ));
                        Log::info("Correo enviado a cliente por pago sin orden registrada.");
                    } catch (\Throwable $e) {
                        Log::error("Error al enviar correo por pago sin orden registrada", [
                            'email' => $email,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }

            return response()->json(['message' => 'Pago aprobado pero orden no encontrada. Cliente ha sido notificado.'], 200);
        }


        $this->crearOrdenDesdeTransaccion($transaction, $order->user);

        return response()->json(['message' => 'Orden actualizada'], 200);
    }

    public function crearOrdenDesdeTransaccion(array $data, User $user)
    {
        $reference = $data['payment_link_id'] ?? null;
        $transactionId = $data['id'] ?? null;
        $status = $data['status'] ?? null;

        if (!$reference || !$transactionId || !$status) {
            Log::warning("Datos incompletos en transacción", ['data' => $data]);
            return response()->json(['message' => 'Datos incompletos'], 400);
        }

        // Buscar la orden por la referencia
        $order = Order::where('reference', $reference)->first();

        if (!$order) {
            Log::warning("Orden no encontrada con referencia [$reference]");
            return response()->json(['message' => 'Orden no encontrada'], 404);
        }

        // Si la transacción ya está procesada (APPROVED), no hacemos nada
        if ($order->payment_status === 'approved') {
            Log::info("La orden ya está aprobada, no se hará ninguna acción.");
            return response()->json(['message' => 'Pago ya procesado'], 200);
        }

        // Si la orden está pendiente y el pago llega pendiente, cambiar a "processing"
        if ($order->status === 'pending') {
            if ($status === 'PENDING') {
                // Cambiar la orden a 'processing' si está pendiente y el pago sigue pendiente
                $order->status = 'processing';
                $order->payment_status = 'pending';
                Log::info("La orden con referencia [$reference] se ha movido a 'processing' con estado de pago 'pending'.");
            } elseif ($status === 'APPROVED') {
                // Si llega un pago aprobado, procesamos la orden
                $order->status = 'processing';
                $order->payment_status = 'approved';
                $order->payment_link = null;  // Limpiar payment_link_id

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
                    $cart->save();
                }

                // Enviar correo de confirmación de la orden
                Log::info("Enviando correo a: " . $user->email);
                Mail::to($user->email)->send(new OrderCreated($order));

                $order->statusLogs()->create([
                    'user_id' => null,
                    'status' => 'processing',
                    'message' => 'Pago aprobado por Wompi. Orden confirmada automáticamente.',
                ]);
            } elseif ($status === 'DECLINED' || $status === 'VOIDED') {
                $order->status = 'cancelled';
                $order->payment_status = 'failed';
                $order->payment_link = null;

                $order->statusLogs()->create([
                    'user_id' => null,
                    'status' => 'cancelled',
                    'message' => "Transacción Wompi marcada como $status.",
                ]);
            }
        } elseif ($order->status === 'processing') {
            // Si la orden ya está en 'processing', no hacer nada si el estado sigue 'PENDING'
            if ($status === 'PENDING') {
                Log::info("La orden con referencia [$reference] ya está en 'processing', no se hace ningún cambio.");
                return response()->json(['message' => 'La orden ya está en procesamiento'], 200);
            }

            // Si llega un pago aprobado o rechazado, actualizamos el estado
            if ($status === 'APPROVED') {
                $order->payment_status = 'approved';
                $order->payment_link = null;

                $cart = $user->cart()->with('products')->first();
                if ($cart) {
                    foreach ($order->products as $product) {
                        $orderedQty = $product->pivot->quantity;

                        if ($product->stock_count !== null) {
                            $product->decrement('stock_count', $orderedQty);
                        }
                    }

                    $cart->products()->detach();
                    $cart->save();
                }

                // Enviar correo de confirmación de la orden
                Log::info("Enviando correo a: " . $user->email);
                Mail::to($user->email)->send(new OrderCreated($order));

                Log::info("La orden con referencia [$reference] ha sido aprobada.");
            } elseif ($status === 'DECLINED' || $status === 'VOIDED') {
                $order->status = 'cancelled';
                $order->payment_status = 'failed';
                $order->payment_link = null;

                Log::info("La orden con referencia [$reference] ha sido cancelada.");
            }
        }

        // Guardar los cambios
        $order->save();

        return response()->json(['message' => 'Orden actualizada correctamente'], 200);
    }
}
