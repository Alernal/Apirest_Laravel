<?php

namespace App\Http\Controllers;

use App\Http\Requests\Orders\StoreOrderRequest;
use App\Http\Requests\Orders\UpdateOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends BaseController
{
    public function index()
    {
        $orders = Order::paginate();

        return $this->sendResponse(OrderResource::collection($orders), 'Lista de ordenes obtenida exitosamente.');
    }

    public function store(StoreOrderRequest $request)
    {
        DB::beginTransaction();

        try {
            $products = $request->get('products');
            $orderData = $request->except('products');

            $order = Order::create($orderData);

            foreach ($products as $product) {
                $order->products()->attach($product['product_id'], [
                    'product_name' => $product['product_name'],
                    'price' => $product['price'],
                    'quantity' => $product['quantity'],
                    'total' => $product['total'],
                ]);
            }

            DB::commit();

            return $this->sendResponse(['product' => OrderResource::make($order)], 'Orden creada exitosamente.', 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->sendError('Error al crear la orden: ' . $e->getMessage(), [],  500);
        }
    }

    public function show(Order $order)
    {
        return $this->sendResponse(OrderResource::make($order), 'Orden obtenida exitosamente.');
    }

    public function update(UpdateOrderRequest $request, Order $order)
    {
        DB::beginTransaction();

        try {
            $order->update($request->all());
            $order->save();

            DB::commit();
            return $this->sendResponse(['product' => OrderResource::make($order)], '', 0);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('Error al editar la orden' . $e->getMessage(), [], 500);
        }
    }

    public function destroy(Order $order)
    {
        DB::beginTransaction();

        try {
            $order->delete();

            DB::commit();

            return $this->sendResponse([], 'Orden eliminado exitosamente.');
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->sendError('Error al eliminar la orden: ' . $e->getMessage(), [], 500);
        }
    }
}
