<?php

namespace App\Http\Controllers;

use App\Mail\OrderCreated;
use App\Mail\OrderFallbackCreated;
use App\Mail\OrderStatusUpdated;
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

    public function getTransaction(Request $request, $id)
    {
        // Auth opcional por JWT
        try {
            if ($token = JWTAuth::getToken()) {
                $user = JWTAuth::authenticate($token);
                if ($user) {
                    Auth::login($user);
                }
            }
        } catch (\Throwable $e) {
            Log::debug('JWT opcional, continuo como invitado: ' . $e->getMessage());
        }

        $kind = $request->query('kind', 'tx'); // 'tx' | 'order'

        // === Contraentrega / Consulta directa de orden ===
        if ($kind === 'order') {
            try {
                $order = Order::with([
                    'products',
                    'user:id,first_name,email',
                    'address',
                ])->find($id);

                if (!$order) {
                    return $this->sendError('Orden no encontrada.', [], 404);
                }

                // status sintético para UI cuando no hay transacción
                $status = 'PENDING_VALIDATION';

                return $this->sendResponse([
                    'status'      => $status,
                    'order'       => $order,
                    'transaction' => null,
                ], 'Estado de la orden.');
            } catch (\Throwable $e) {
                Log::error("Error getTransaction(order {$id}): " . $e->getMessage());
                return $this->sendError('Error al consultar la orden.', [], 500);
            }
        }

        // === Pago estándar (consulta Wompi) ===
        try {
            $secretKey = env('WOMPI_PRIVATE_KEY');

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $secretKey,
                'Content-Type'  => 'application/json',
            ])->get("https://production.wompi.co/v1/transactions/{$id}");

            if (!$response->successful()) {
                Log::error('Error consultando transacción en Wompi', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                    'transaction_id' => $id,
                ]);
                return $this->sendError('No se pudo consultar la transacción.', [], 500);
            }

            $data   = $response['data'];
            $status = $data['status'] ?? 'UNKNOWN';

            $order = Order::where('transaction_id', $id)
                ->with(['products', 'user:id,first_name,email', 'address'])
                ->first();

            return $this->sendResponse([
                'status'      => $status,
                'order'       => $order,
                'transaction' => $data,
            ], 'Estado de la transacción.');
        } catch (\Throwable $e) {
            Log::error("Error getTransaction(tx {$id}): " . $e->getMessage());
            return $this->sendError('Error al consultar la transacción.', [], 500);
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
                        Mail::to($email)->queue(new PaymentApprovedNoOrder(
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
        $reference     = $data['payment_link_id'] ?? null;
        $transactionId = $data['id'] ?? null;
        $status        = $data['status'] ?? null;

        if (!$reference || !$transactionId || !$status) {
            Log::warning("Datos incompletos en transacción", ['data' => $data]);
            return response()->json(['message' => 'Datos incompletos'], 400);
        }

        $order = Order::where('reference', $reference)->first();

        if (!$order) {
            Log::warning("Orden no encontrada con referencia [$reference]");
            return response()->json(['message' => 'Orden no encontrada'], 404);
        }

        // Actualizar transaction_id siempre que venga
        $order->transaction_id = $transactionId;
        $order->save();

        // Si ya está aprobada, no duplicar acciones
        if ($order->payment_status === 'approved') {
            Log::info("La orden ya está aprobada, no se hará ninguna acción.");
            return response()->json(['message' => 'Pago ya procesado'], 200);
        }

        if ($order->status === 'pending') {
            if ($status === 'PENDING') {
                // mover a processing pero pago sigue pendiente
                $order->status = 'processing';
                $order->payment_status = 'pending';
                $order->save();

                // Notificar cambio a "processing" (pago aún pendiente)
                Mail::to($user->email)->queue(
                    new OrderStatusUpdated(
                        $order,
                        'processing',
                        'Estamos preparando tu pedido mientras confirmamos el pago.'
                    )
                );

                Log::info("Orden [$reference] -> processing (pago pending).");
            } elseif ($status === 'APPROVED') {
                // pago aprobado
                $order->status = 'processing';
                $order->payment_status = 'approved';
                $order->payment_link = null;
                $order->save();

                // Descontar stock / limpiar carrito
                $cart = $user->cart()->with('products')->first();
                if ($cart) {
                    foreach ($order->products as $product) {
                        $orderedQty = $product->pivot->quantity;
                        if (!is_null($product->stock_count)) {
                            $product->decrement('stock_count', $orderedQty);
                        }
                    }
                    $cart->products()->detach();
                    $cart->save();
                }

                // Notificar pago aprobado (estado processing)
                Mail::to($user->email)->queue(
                    new OrderStatusUpdated(
                        $order,
                        'processing',
                        'Tu pago fue aprobado por Wompi. Estamos preparando tu pedido.'
                    )
                );

                $order->statusLogs()->create([
                    'user_id' => null,
                    'status'  => 'processing',
                    'message' => 'Pago aprobado por Wompi. Orden confirmada automáticamente.',
                ]);

                Log::info("Orden [$reference] -> processing (pago approved).");
            } elseif (in_array($status, ['DECLINED', 'VOIDED', 'ERROR'])) {
                $order->status = 'cancelled';
                $order->payment_status = 'failed';
                $order->payment_link = null;
                $order->save();

                // Notificar cancelación
                Mail::to($user->email)->queue(
                    new OrderStatusUpdated(
                        $order,
                        'cancelled',
                        "La transacción fue marcada como {$status}. Si crees que es un error, contáctanos."
                    )
                );

                $order->statusLogs()->create([
                    'user_id' => null,
                    'status'  => 'cancelled',
                    'message' => "Transacción Wompi marcada como {$status}.",
                ]);

                Log::info("Orden [$reference] -> cancelled ({$status}).");
            }
        } elseif ($order->status === 'processing') {
            if ($status === 'PENDING') {
                Log::info("Orden [$reference] ya en processing; pago sigue pendiente, sin cambios.");
                return response()->json(['message' => 'La orden ya está en procesamiento'], 200);
            }

            if ($status === 'APPROVED') {
                $order->payment_status = 'approved';
                $order->payment_link = null;
                $order->save();

                $cart = $user->cart()->with('products')->first();
                if ($cart) {
                    foreach ($order->products as $product) {
                        $orderedQty = $product->pivot->quantity;
                        if (!is_null($product->stock_count)) {
                            $product->decrement('stock_count', $orderedQty);
                        }
                    }
                    $cart->products()->detach();
                    $cart->save();
                }

                // Notificar pago aprobado
                Mail::to($user->email)->queue(
                    new OrderStatusUpdated(
                        $order,
                        'processing',
                        'Tu pago fue aprobado por Wompi. Estamos preparando tu pedido.'
                    )
                );

                Log::info("Orden [$reference] processing + pago approved.");
            } elseif (in_array($status, ['DECLINED', 'VOIDED', 'ERROR'])) {
                $order->status = 'cancelled';
                $order->payment_status = 'failed';
                $order->payment_link = null;
                $order->save();

                // Notificar cancelación
                Mail::to($user->email)->queue(
                    new OrderStatusUpdated(
                        $order,
                        'cancelled',
                        "La transacción fue marcada como {$status}. Si crees que es un error, contáctanos."
                    )
                );

                Log::info("Orden [$reference] -> cancelled ({$status}).");
            }
        }

        return response()->json(['message' => 'Orden actualizada correctamente'], 200);
    }


    /**
     * Normaliza strings: lower, sin tildes, trim
     */
    private function normalize(?string $s): string
    {
        if (!$s) return '';
        $s = trim(mb_strtolower($s, 'UTF-8'));
        $s = \Normalizer::normalize($s, \Normalizer::FORM_D);
        // elimina diacríticos
        $s = preg_replace('/\p{Mn}+/u', '', $s);
        // normaliza espacios
        $s = preg_replace('/\s+/', ' ', $s);
        return $s ?: '';
    }

    /**
     * Obtiene y cachea el JSON de Colombia (24h)
     * Estructura: [ ['departamento' => 'Antioquia', 'ciudades' => ['Medellín', ...]], ... ]
     *
     * @return array<int, array{departamento:string, ciudades:array<int,string>}>
     */
    private function getColombiaJson(): array
    {
        return Cache::remember('colombia_json_v1', now()->addHours(24), function () {
            $res = Http::timeout(10)->get('https://raw.githubusercontent.com/marcovega/colombia-json/master/colombia.min.json');
            if (!$res->ok()) {
                Log::warning('No se pudo cargar colombia.min.json, status: ' . $res->status());
                return [];
            }
            $data = $res->json();
            if (!is_array($data)) return [];
            // Limpieza básica
            foreach ($data as &$d) {
                if (isset($d['departamento']) && isset($d['ciudades']) && is_array($d['ciudades'])) {
                    $d['departamento'] = trim($d['departamento']);
                    $d['ciudades'] = array_values(array_unique(array_map('trim', $d['ciudades'])));
                    sort($d['ciudades'], SORT_NATURAL | SORT_FLAG_CASE);
                }
            }
            return $data;
        });
    }

    /**
     * Valida y devuelve el par (Departamento, Ciudad) en su forma "oficial" del JSON.
     * Si la ciudad no pertenece al dpto → null.
     *
     * @return array{state:string, city:string}|null
     */
    private function canonizeDepartmentCity(string $state, string $city): ?array
    {
        $stateN = $this->normalize($state);
        $cityN  = $this->normalize($city);
        $col    = $this->getColombiaJson();

        foreach ($col as $row) {
            $rowStateN = $this->normalize($row['departamento'] ?? '');
            if ($rowStateN === $stateN) {
                foreach ($row['ciudades'] ?? [] as $c) {
                    if ($this->normalize($c) === $cityN) {
                        return [
                            'state' => $row['departamento'],
                            'city'  => $c,
                        ];
                    }
                }
                // dpto encontrado pero ciudad no coincide
                return null;
            }
        }
        // dpto no encontrado
        return null;
    }

    /**
     * Determina si un departamento pertenece a la región Caribe.
     * Usa la misma lista que en el frontend, pero con normalización robusta.
     */
    private function isCaribbeanDepartment(string $state): bool
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
        $stateN = $this->normalize($state);
        foreach ($caribbeanDepartments as $dep) {
            if ($this->normalize($dep) === $stateN) return true;
        }
        return false;
    }

    /**
     * Calcula el costo de envío siguiendo EXACTAMENTE la regla del frontend:
     * - totalBruto = subtotalSinIVA + tax
     * - totalBruto >= 150000 => 0
     * - Sucre+Sincelejo => 5000
     * - Caribe => 9000 + round(1% de totalBruto)
     * - Resto => 15000 + round(1% de totalBruto)
     */
    private function calculateShipping(?string $state, ?string $city, float $subtotalSinIVA, float $tax): int
    {
        $totalBruto = $subtotalSinIVA + $tax;

        if (!$state || !$city) {
            // Sin address completa: cobrar como "resto" + 1%
            return 15000 + (int) round($totalBruto * 0.01);
        }

        $stateN = $this->normalize($state);
        $cityN  = $this->normalize($city);

        // Sincelejo fijo (sin 1%)
        if ($stateN === $this->normalize('sucre') && $cityN === $this->normalize('sincelejo')) {
            return 5000;
        }

        // Caribe + 1%
        if ($this->isCaribbeanDepartment($state)) {
            return 9000 + (int) round($totalBruto * 0.01);
        }

        // Resto + 1%
        return 15000 + (int) round($totalBruto * 0.01);
    }

    public function generarLinkPago(Request $request)
    {
        // --- Auth por JWT si llega token ---
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
        Log::debug('Usuario autenticado:', ['user' => $user?->only('id', 'email')]);

        // --- Validación base ---
        $request->validate([
            'shipping_type' => 'required|in:standard,contraentrega',
            'subtotal' => 'required|numeric|min:0',
            'iva'      => 'required|numeric|min:0',
            'shipping' => 'required|numeric|min:0',
            'total'    => 'required|numeric|min:1',
            'items'    => 'required|array|min:1',
        ]);

        $shippingType = $request->string('shipping_type')->toString();

        $items = collect($request->items);
        $productIds = $items->pluck('id');
        $products = Product::whereIn('id', $productIds)->get();

        if ($products->count() !== $items->count()) {
            return response()->json(['message' => 'Uno o más productos no existen.'], 422);
        }

        // --- Recalcular subtotalSinIVA y validar stock ---
        $subtotalSinIVA = 0;
        foreach ($items as $item) {
            $product = $products->firstWhere('id', $item['id']);
            if (!$product) continue;

            $price = ($product->original_price && $product->original_price > 0 && $product->original_price < $product->price)
                ? $product->original_price
                : $product->price;

            $quantity = (int) $item['quantity'];
            if ($product->stock_count !== null && $quantity > $product->stock_count) {
                return response()->json(['message' => "Stock insuficiente para el producto: {$product->name}"], 422);
            }

            $precioSinIVA = $price;
            $subtotalSinIVA += $precioSinIVA * $quantity;
        }

        $tax = round($subtotalSinIVA * 0, 2);
        $address = null;
        $shipping = 0;

        // --- Invitado vs logueado ---
        if (!$user) {
            $request->validate([
                // info de compra (ya validadas antes: subtotal/iva/shipping/total/items/shipping_type)
                'guest_info.name'            => 'nullable|string|max:255',
                'guest_info.first_name'      => 'required|string|max:100',
                'guest_info.last_name'       => 'required|string|max:100',
                'guest_info.email'           => 'required|email',
                'guest_info.phone'           => 'required|string|max:30',
                'guest_info.company'         => 'nullable|string|max:255',
                'guest_info.document_type'   => 'required|in:CC,NIT,RUC,RFC',
                'guest_info.document_number' => 'required|string|max:50',
                'guest_info.fiscal_name'     => 'nullable|string|max:255',

                'address.state'              => 'required|string|max:100',
                'address.city'               => 'required|string|max:100',
                'address.address'            => 'required|string|max:255', // calle principal
                'address.apartment'          => 'nullable|string|max:100',
                'address.postal_code'        => 'nullable|string|max:20',
                'address.country'            => 'nullable|string|max:100',
                'address.notes'              => 'nullable|string|max:500',
            ]);

            // Canonizar dpto/ciudad al formato oficial del JSON (y validar pertenencia)
            $canon = $this->canonizeDepartmentCity(
                $request->input('address.state'),
                $request->input('address.city')
            );
            if (!$canon) {
                return response()->json(['message' => 'Departamento y ciudad no coinciden con el listado oficial.'], 422);
            }

            $state = $canon['state'];
            $city  = $canon['city'];
            $shipping = $this->calculateShipping($state, $city, $subtotalSinIVA, $tax);

            $totalBruto = $subtotalSinIVA + $tax;
            $total = ceil($totalBruto + $shipping);

            // Validación de totales enviados vs calculados
            if (
                round($request->subtotal, 2) != round($subtotalSinIVA, 2) ||
                round($request->iva, 2)      != round($tax, 2) ||
                round($request->shipping, 2) != round($shipping, 2) ||
                round($request->total, 2)    != round($total, 2)
            ) {
                return response()->json([
                    'message'   => 'Los valores enviados no coinciden con los calculados.',
                    'calculado' => [
                        'subtotalSinIVA' => $subtotalSinIVA,
                        'tax'            => $tax,
                        'shipping'       => $shipping,
                        'total'          => $total,
                    ],
                ], 422);
            }

            $guestEmail   = $request->input('guest_info.email');
            $firstName    = $request->input('guest_info.first_name');
            $lastName     = $request->input('guest_info.last_name');
            $displayName  = $request->input('guest_info.name') ?: trim($firstName . ' ' . $lastName);
            $phone        = $request->input('guest_info.phone');

            $user = User::firstOrCreate(
                ['email' => $guestEmail],
                [
                    'first_name'        => $firstName,
                    'last_name'         => $lastName,
                    'phone'             => $phone,
                    'password'          => Hash::make(Str::random(10)),
                    'email_verified_at' => now(),
                ]
            );

            // Si se creó, enviar bienvenida con password
            if ($user->wasRecentlyCreated) {
                $password = Str::random(10);
                $user->password = Hash::make($password);
                $user->save();
                try {
                    Mail::to($guestEmail)->queue(new WelcomeGuestAccount($user, $password));
                } catch (\Throwable $e) {
                    Log::error('No se pudo enviar correo de cuenta invitado', ['error' => $e->getMessage()]);
                }
            }

            $streetBase   = $request->input('address.address');    // calle principal
            $apartment    = trim((string)$request->input('address.apartment', ''));
            $streetFull   = trim($streetBase . ($apartment ? (' ' . $apartment) : ''));

            // ¿Primera dirección? -> la marcamos por defecto
            $markDefault = !$user->addresses()->exists();

            $addressData = [
                'name'            => $displayName,
                'first_name'      => $firstName,
                'last_name'       => $lastName,
                'email'           => $guestEmail,
                'phone'           => $phone,
                'company'         => $request->input('guest_info.company'),
                'document_type'   => $request->input('guest_info.document_type'),
                'document_number' => $request->input('guest_info.document_number'),
                'fiscal_name'     => $request->input('guest_info.fiscal_name'),
                'street_address'  => $streetFull,
                'city'            => $city,
                'state'           => $state,
                'postal_code'     => $request->input('address.postal_code', '000000'),
                'country'         => $request->input('address.country', 'CO'),
                'notes'           => $request->input('address.notes'),
                'is_default'      => $markDefault,
            ];

            // Dirección por defecto (o crearla)
            $address = $user->addresses()->firstOrCreate(
                [
                    'street_address' => $streetFull,
                    'city'           => $city,
                    'state'          => $state,
                ],
                $addressData
            );
        } else {
            // Usuario logueado
            $cart = $user->cart()->with('products')->first();
            if (!$cart || $cart->products->isEmpty()) {
                return response()->json(['message' => 'El carrito está vacío.'], 422);
            }

            $address = $user->addresses()->where('is_default', true)->first();
            if (!$address) {
                return response()->json(['message' => 'No tienes una dirección predeterminada configurada.'], 422);
            }

            // Canoniza/valida dpto-ciudad del address guardado
            $canon = $this->canonizeDepartmentCity($address->state, $address->city);
            if (!$canon) {
                // Si no valida, igual intentamos calcular con lo que hay (pero logeamos)
                Log::warning('Address del usuario no coincide con listado oficial', [
                    'state' => $address->state,
                    'city' => $address->city,
                    'user_id' => $user->id
                ]);
                $state = $address->state;
                $city  = $address->city;
            } else {
                $state = $canon['state'];
                $city  = $canon['city'];
            }

            $shipping = $this->calculateShipping($state, $city, $subtotalSinIVA, $tax);
            $totalBruto = $subtotalSinIVA + $tax;
            $total = ceil($totalBruto + $shipping);
        }

        // ---- Si el tipo de envío es CONTRAENTREGA → crear orden directo, sin Wompi ----
        if ($shippingType === 'contraentrega') {

            $reference = 'CE-' . now()->format('Ymd-His') . '-' . Str::upper(Str::random(6));

            $order = Order::create([
                'user_id'        => $user->id,
                'address_id'     => $address->id,
                'payment_method' => 'contraentrega',
                'payment_status' => 'pending',  // pendiente de pago al recibir
                'status'         => 'pending',
                'shipping_method' => 'contraentrega',
                'shipping_cost'  => $shipping,
                'tax'            => $tax,
                'subtotal'       => $subtotalSinIVA,
                'total'          => ceil($subtotalSinIVA + $tax + $shipping),
                'reference'      => $reference,
                'payment_link'   => null,
                'transaction_id' => null,
            ]);

            foreach ($items as $item) {
                $product = $products->firstWhere('id', $item['id']);
                $price = ($product->original_price && $product->original_price > 0 && $product->original_price < $product->price)
                    ? $product->original_price
                    : $product->price;

                $order->products()->attach($product->id, [
                    'product_name' => $product->name,
                    'price'        => $price,
                    'quantity'     => $item['quantity'],
                    'total'        => $price * $item['quantity'],
                ]);
            }

            $order->statusLogs()->create([
                'user_id' => null,
                'status'  => 'pending',
                'message' => 'Orden generada. Pago contraentrega.',
            ]);

            try {
                Mail::to($user->email)->queue((new OrderCreated($order->id))->afterCommit());
            } catch (\Throwable $e) {
                Log::error('No se pudo enviar correo de orden contraentrega', ['error' => $e->getMessage()]);
            }

            return response()->json([
                'message'  => 'Orden creada para pago contraentrega.',
                'order_id' => $order->id,
            ], 201);
        }

        // ---- STANDARD → Generación de link Wompi (como ya lo tenías) ----
        $amountInCents = (int) round(($subtotalSinIVA + $tax + $shipping) * 100);
        $ivaInCents    = (int) round($tax * 100);

        $descripcion = "Subtotal: $" . number_format($request['subtotal'], 0, ',', '.') .
            ", IVA: $" . number_format($request['iva'], 0, ',', '.') .
            ", Envío: $" . number_format($request['shipping'], 0, ',', '.');

        $expiresAt = now('UTC')->addMinutes(5)->format('Y-m-d\TH:i:s');

        $payload = [
            'name'            => 'NURAE',
            'description'     => $descripcion,
            'single_use'      => true,
            'collect_shipping' => false,
            'currency'        => 'COP',
            'amount_in_cents' => $amountInCents,
            'expires_at'      => $expiresAt,
            'image_url'       => null,
            'redirect_url'    => 'https://nurae.com.co/confirmacion-pago',
            'taxes'           => [[
                'type' => 'VAT',
                'percentage' => 19,
                'amount_in_cents' => $ivaInCents,
            ]],
        ];

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . env('WOMPI_PRIVATE_KEY'),
                'Content-Type'  => 'application/json',
            ])->post('https://production.wompi.co/v1/payment_links', $payload);

            $body = $response->json();

            if ($response->successful() && isset($body['data']['id'])) {
                $linkId = $body['data']['id'];

                $order = Order::create([
                    'user_id'        => $user->id,
                    'address_id'     => $address->id,
                    'payment_method' => 'wompi',
                    'payment_status' => 'pending',
                    'status'         => 'pending',
                    'shipping_method' => 'standard',
                    'shipping_cost'  => $shipping,
                    'tax'            => $tax,
                    'subtotal'       => $subtotalSinIVA,
                    'total'          => ceil($subtotalSinIVA + $tax + $shipping),
                    'reference'      => $linkId,
                    'payment_link'   => "https://checkout.wompi.co/l/{$linkId}",
                    'transaction_id' => null,
                ]);

                foreach ($items as $item) {
                    $product = $products->firstWhere('id', $item['id']);
                    $price = ($product->original_price && $product->original_price > 0 && $product->original_price < $product->price)
                        ? $product->original_price
                        : $product->price;

                    $order->products()->attach($product->id, [
                        'product_name' => $product->name,
                        'price'        => $price,
                        'quantity'     => $item['quantity'],
                        'total'        => $price * $item['quantity'],
                    ]);
                }

                $order->statusLogs()->create([
                    'user_id' => null,
                    'status'  => 'pending',
                    'message' => 'Orden generada. Pendiente de pago.',
                ]);

                try {
                    Mail::to($user->email)->queue((new OrderCreated($order->id))->afterCommit());
                } catch (\Throwable $e) {
                    Log::error('No se pudo enviar correo de orden standard', ['error' => $e->getMessage()]);
                }

                return response()->json([
                    'url'             => "https://checkout.wompi.co/l/{$linkId}",
                    'payment_link_id' => $linkId,
                    'expires_at'      => $body['data']['expires_at'] ?? null,
                    'order_id'        => $order->id,
                ]);
            }

            Log::error('Error al generar link de Wompi', [
                'payload' => $payload,
                'status'  => $response->status(),
                'body'    => $body,
            ]);

            return response()->json(['message' => 'Error al generar el enlace de pago'], 500);
        } catch (\Exception $e) {
            Log::error('Excepción al generar link de Wompi', ['exception' => $e]);
            return response()->json(['message' => 'Error interno al generar link'], 500);
        }
    }
}
