<?php

namespace App\Http\Controllers;

use App\Models\Address;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class PaymentController extends BaseController
{
    public function pay(Request $request, WompiController $wompi)
    {
        $request->validate([
            'address_id' => 'required|integer|exists:addresses,id',
            'payment_method' => 'required|in:NEQUI,CARD,PSE',
            'phone_number' => 'required|string|min:10',
        ]);

        $user = Auth::user();

        // Obtener carrito con productos y validar que tenga productos
        $cart = $user->cart()->with('products')->first();

        if (!$cart || $cart->products->isEmpty()) {
            return $this->sendError('El carrito está vacío', [], 422);
        }

        // Validar stock disponible antes de continuar
        foreach ($cart->products as $product) {
            $requestedQty = $product->pivot->quantity;
            $availableQty = $product->stock_count;

            if ($availableQty <= 0 || $requestedQty > $availableQty) {
                return $this->sendError(
                    "El producto '{$product->name}' no tiene suficiente stock. Disponible: {$availableQty}, Solicitado: {$requestedQty}",
                    [],
                    422
                );
            }
        }

        // Obtener dirección
        $address = Address::find($request->address_id);

        if (!$address) {
            return $this->sendError('La dirección no fue encontrada', [], 404);
        }

        // Cálculo del costo de envío
        $shipping = strtolower($address->city) === 'Sincelejo' ? 0 : 30000;

        // Calcular subtotal usando original_price si aplica
        $subtotal = $cart->products->sum(function ($product) {
            $price = ($product->original_price > 0 && $product->original_price < $product->price)
            ? $product->original_price
            : $product->price;
            return $price * $product->pivot->quantity;
        });

        $tax = $subtotal * 0.19;
        $total = $subtotal + $tax + $shipping;

        $reference = 'orden-' . now()->timestamp;

        // Crear transacción en Wompi
        try {
            $transaction = $wompi->createTransaction([
                'amount_in_cents' => intval($total * 100),
                'customer_email' => $address->email,
                'reference' => $reference,
                'payment_method' => [
                    'type' => $request->payment_method,
                    'phone_number' => $request->phone_number,
                ],
                'customer_data' => [
                    'full_name' => "{$address->first_name} {$address->last_name}",
                    'phone_number' => $address->phone,
                    'legal_id' => $address->document_number,
                    'legal_id_type' => $address->document_type,
                ],
            ]);
        } catch (\Throwable $e) {
            return $this->sendError('Error al crear la transacción: ' . $e->getMessage(), [], 500);
        }

        // Registrar orden
        DB::beginTransaction();

        try {
            $order = Order::create([
                'user_id' => $user->id,
                'address_id' => $address->id,
                'payment_method' => $request->payment_method,
                'payment_status' => 'pending',
                'status' => 'pending',
                'shipping_cost' => $shipping,
                'tax' => $tax,
                'subtotal' => $subtotal,
                'total' => $total,
                'transaction_id' => $transaction['id'],
            ]);

            foreach ($cart->products as $product) {
                $quantity = $product->pivot->quantity;

                // Determinar el precio a usar
                $price = ($product->original_price > 0 && $product->original_price < $product->price)
                    ? $product->original_price
                    : $product->price;

                // Asociar producto a la orden
                $order->products()->attach($product->id, [
                    'product_name' => $product->name,
                    'price' => $price,
                    'quantity' => $quantity,
                    'total' => $price * $quantity,
                ]);

                // Descontar del stock
                $product->decrement('stock_count', $quantity);
            }


            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->sendError('Error al registrar la orden: ' . $e->getMessage(), [], 500);
        }

        return $this->sendResponse([
            'redirect_url' => $transaction['payment_method']['extra']['async_payment_url'] ?? null,
            'transaction_id' => $transaction['id'],
        ], 'Orden creada y transacción generada', 201);
    }
}
