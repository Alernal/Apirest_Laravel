<?php

namespace App\Console\Commands;

use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CleanupExpiredOrders extends Command
{
    protected $signature = 'orders:cleanup-expired';
    protected $description = 'Elimina órdenes expiradas sin pago ni progreso';

    public function handle()
    {
        $threshold = Carbon::now()->subMinutes(1);

        $orders = Order::where('payment_status', 'pending')
            ->where('status', 'pending')
            ->whereNotNull('reference')
            // ->where('created_at', '<', $threshold)
            ->get();

        $count = 0;

        foreach ($orders as $order) {
            try {
                $order->products()->detach();      // Detach productos
                $order->statusLogs()->delete();    // Eliminar logs
                $order->delete();                  // Eliminar orden
                $count++;
            } catch (\Throwable $e) {
                Log::error("Error al eliminar orden #{$order->id}: " . $e->getMessage());
            }
        }

        $this->info("🧹 Se eliminaron $count órdenes expiradas.");
    }
}
