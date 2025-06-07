<?php

namespace App\Http\Controllers;

use App\Http\Requests\Orders\StoreOrderRequest;
use App\Http\Requests\Orders\UpdateOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OrderController extends BaseController
{
    public function index()
    {
        $user = Auth::user();

        if ($user->role === 'admin') {
            $orders = Order::paginate();
        } else {
            $orders = Order::where('user_id', $user->id)->paginate();
        }

        return $this->sendResponse(OrderResource::collection($orders), 'Lista de ordenes obtenida exitosamente.');
    }

    public function show(Order $order)
    {
        // Cargar los productos con los datos de la tabla pivot
        $order->load(['products' => function ($query) {
            $query->withPivot(['product_name', 'price', 'quantity', 'total']);
        }]);

        return $this->sendResponse(OrderResource::make($order), 'Orden obtenida exitosamente.');
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


    /* Store de history status */

    public function store(Request $request, $id)
    {
        $user = Auth::user();

        $request->validate([
            'status' => 'required|string|in:pending,processing,shipped,completed,cancelled',
            'admin_message' => 'nullable|string',
            'tracking_url' => 'nullable|string',
        ]);

        DB::beginTransaction();

        try {
            $order = Order::findOrFail($id);
            $order->status = $request->status;

            if ($request->status === 'shipped' && $request->tracking_url) {
                $order->tracking_url = $request->tracking_url;
            }

            $order->save();

            DB::table('order_status_histories')->insert([
                'order_id' => $order->id,
                'admin_id' => $user->id,
                'status' => $request->status,
                'message' => $request->admin_message,
                'tracking_url' => $request->tracking_url,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::commit();

            return $this->sendResponse([], 'Estado actualizado correctamente.');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('Error al actualizar el estado: ' . $e->getMessage(), [], 500);
        }
    }
}
