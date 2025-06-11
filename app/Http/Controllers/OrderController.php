<?php

namespace App\Http\Controllers;

use App\Http\Requests\Orders\StoreOrderRequest;
use App\Http\Requests\Orders\UpdateOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\OrderStatusHistory;
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

            // Solo se actualiza el status en la tabla orders
            $order->status = $request->status;
            $order->save();

            // El tracking_url se guarda únicamente en el historial
            OrderStatusHistory::create([
                'order_id'     => $order->id,
                'user_id'      => $user->id,
                'status'       => $request->status,
                'message'      => $request->admin_message,
                'tracking_url' => $request->tracking_url,
            ]);

            DB::commit();

            return $this->sendResponse([], 'Estado actualizado correctamente.');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('Error al actualizar el estado: ' . $e->getMessage(), [], 500);
        }
    }


    public function history($id)
    {
        try {
            $order = Order::with(['statusHistories.admin:id,name,email'])->findOrFail($id);

            return $this->sendResponse([
                'order_id' => $order->id,
                'status_history' => $order->statusHistories->map(function ($history) {
                    return [
                        'status' => $history->status,
                        'message' => $history->message,
                        'tracking_url' => $history->tracking_url,
                        'changed_by' => $history->admin ? $history->admin->name : 'Sistema',
                        'changed_at' => $history->created_at->toDateTimeString(),
                    ];
                }),
            ], 'Historial de estados obtenido exitosamente.');
        } catch (\Exception $e) {
            return $this->sendError('Error al obtener el historial: ' . $e->getMessage(), [], 500);
        }
    }
}
