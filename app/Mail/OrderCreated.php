<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;

class OrderCreated extends Mailable implements ShouldQueue
{
    use Queueable;

    public int $orderId;

    public function __construct(int $orderId)
    {
        $this->orderId = $orderId;
    }

    public function build()
    {
        $order = Order::with(['user', 'orderItems'])->findOrFail($this->orderId);

        $metodo = $order->payment_method === 'contraentrega'
            ? '🛒 (Contraentrega)'
            : '💳 (Pago en línea)';

        return $this->subject('🎉 ¡Tu orden #'.$order->id.' ha sido recibida! '.$metodo)
            ->view('emails.orders.created', [
                'order' => $order,
            ]);
    }
}
